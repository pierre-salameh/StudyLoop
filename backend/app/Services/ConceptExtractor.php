<?php

namespace App\Services;

use App\Models\Lecture;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConceptExtractor
{
    private const CHUNK_SIZE = 1800;

    private const MIN_QUOTE_LENGTH = 15;

         private const SYSTEM_PROMPT = 'أنت مساعد تعليمي. استخرج المفاهيم الرئيسية من النص المعطى فقط. '
        . 'لكل مفهوم أعطِ بهذا الترتيب: '
        . '(1) عنواناً قصيراً. '
        . '(2) اقتباساً حرفياً منسوخاً من النص: جملة كاملة أو أكثر (حتى ثلاث جمل)، ولا تبدأ من منتصف جملة. '
        . 'إذا احتاج المفهوم إلى جملتين غير متجاورتين فافصل بينهما بثلاث نقاط (...). '
        . 'انسخ كل جزء حرفياً دون حذف أو تغيير أي كلمة أو رمز، بما في ذلك الأقواس والكلمات الإنجليزية. '
        . '(3) ملخصاً من جملة أو جملتين، وكل معلومة فيه يجب أن تكون مذكورة في الاقتباس نفسه، ولا تضف أي معلومة ليست في الاقتباس. '
        . 'لا تكرر المفهوم نفسه، ولا تُنشئ مفهوماً عاماً يجمع مفاهيم أخرى مذكورة في النص. كل مفهوم يغطي فكرة مختلفة.';

      private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'concepts' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'source_quote' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                    ],
                    'required' => ['title', 'source_quote', 'summary'],
                ],
            ],
        ],
        'required' => ['concepts'],
    ];

    public function __construct(private LlmClient $llm)
    {
    }

       /**
     * @return array{chunks:int, saved:int, rejected:int, failed_chunks:int, rejected_items:list<array{title:string, quote:string, reason:string}>}
     */
    public function extract(Lecture $lecture): array
    {
        $chunks = $this->chunk($lecture->content);
        $valid = [];
        $rejected = [];
        $failedChunks = 0;

        foreach ($chunks as $chunk) {
            try {
                $raw = $this->llm->chat(self::SYSTEM_PROMPT, "النص:\n" . $chunk, self::SCHEMA);
                $data = json_decode($raw, true);
            } catch (Throwable) {
                $failedChunks++;

                continue;
            }

            if (! is_array($data) || ! isset($data['concepts']) || ! is_array($data['concepts'])) {
                $failedChunks++;

                continue;
            }

            foreach ($data['concepts'] as $concept) {
                $reason = $this->rejectionReason($concept, $chunk);

                if ($reason !== null) {
                    $rejected[] = [
                        'title' => is_array($concept) && is_string($concept['title'] ?? null) ? $concept['title'] : '?',
                        'quote' => is_array($concept) && is_string($concept['source_quote'] ?? null) ? $concept['source_quote'] : '',
                        'reason' => $reason,
                    ];

                    continue;
                }

                $key = $this->normalize($concept['source_quote']);

                // نتجاهل التكرار إذا استخرج النموذج الاقتباس نفسه مرتين
                $valid[$key] ??= [
                    'title' => trim($concept['title']),
                    'summary' => trim($concept['summary']),
                    'source_quote' => trim($concept['source_quote']),
                ];
            }
        }

        // نكتب في قاعدة البيانات بعد انتهاء كل استدعاءات النموذج، وفي معاملة واحدة
        DB::transaction(function () use ($lecture, $valid) {
            $lecture->concepts()->delete();

            $position = 1;
            foreach ($valid as $concept) {
                $lecture->concepts()->create($concept + ['position' => $position++]);
            }

            $lecture->status = count($valid) > 0 ? 'ready' : 'failed';
            $lecture->save();
        });

        return [
            'chunks' => count($chunks),
            'saved' => count($valid),
            'rejected' => count($rejected),
            'failed_chunks' => $failedChunks,
            'rejected_items' => $rejected,
        ];
    }

    private function rejectionReason(mixed $concept, string $chunk): ?string
    {
        if (! is_array($concept)) {
            return 'not an object';
        }

        foreach (['title', 'summary', 'source_quote'] as $field) {
            if (! isset($concept[$field]) || ! is_string($concept[$field]) || trim($concept[$field]) === '') {
                return "missing field: {$field}";
            }
        }

        if (mb_strlen($concept['title']) > 255) {
            return 'title too long';
        }

        $fragments = $this->fragments($concept['source_quote']);

        if ($fragments === []) {
            return 'quote empty';
        }

        foreach ($fragments as $fragment) {
            // جزء قصير جداً (كلمة أو كلمتان) لا يثبت شيئاً
            if (mb_strlen($fragment) < self::MIN_QUOTE_LENGTH) {
                return 'quote fragment too short';
            }
        }

        $haystack = $this->normalize($chunk);
        $offset = 0;

        // كل جزء يجب أن يكون موجوداً حرفياً، وبنفس ترتيبه في النص
        foreach ($fragments as $fragment) {
            $position = mb_strpos($haystack, $fragment, $offset);

            if ($position === false) {
                return 'quote not found in source chunk (or fragments out of order)';
            }

            $offset = $position + mb_strlen($fragment);
        }

        return null;
    }







    /**
     * يقسّم الاقتباس عند علامات الحذف (... أو …) إلى أجزاء منظَّفة
     *
     * @return list<string>
     */
    private function fragments(string $quote): array
    {
        $parts = preg_split('/\s*(?:\[\.{3}\]|\[…\]|\.{3}|…)\s*/u', $quote);
        $fragments = [];

        foreach ($parts as $part) {
            $part = $this->normalize($part);

            if ($part !== '') {
                $fragments[] = $part;
            }
        }

        return $fragments;
    }






    // نتجاهل التشكيل والتطويل واختلاف المسافات عند المقارنة فقط
    private function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * @return list<string>
     */
    private function chunk(string $text): array
    {
        $chunks = [];
        $current = '';

        foreach (preg_split('/\R+/u', trim($text)) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            foreach (mb_str_split($line, self::CHUNK_SIZE) as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > self::CHUNK_SIZE) {
                    $chunks[] = $current;
                    $current = '';
                }

                $current .= ($current === '' ? '' : "\n") . $piece;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}