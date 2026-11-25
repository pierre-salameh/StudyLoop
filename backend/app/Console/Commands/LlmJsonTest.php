<?php

namespace App\Console\Commands;

use App\Services\LlmClient;
use Illuminate\Console\Command;

class LlmJsonTest extends Command
{
    protected $signature = 'llm:json-test';

    protected $description = 'Test structured concept extraction with quote checking';

    public function handle(LlmClient $llm): int
    {
        $text = 'الخلية هي الوحدة الأساسية للحياة. '
            . 'تتكون الخلية النباتية من جدار خلوي سليلوزي يحيط بالغشاء الخلوي ويمنحها شكلاً ثابتاً. '
            . 'تحتوي الخلية النباتية على بلاستيدات خضراء تقوم بعملية البناء الضوئي. '
            . 'أما الميتوكوندريا فهي موجودة في الخلايا النباتية والحيوانية معاً، وتنتج الطاقة عبر التنفس الخلوي. '
            . 'الخلية الحيوانية لا تملك جداراً خلوياً ولا بلاستيدات خضراء، لكنها تحتوي على سنتريولات تشارك في انقسام الخلية.';

        $schema = [
            'type' => 'object',
            'properties' => [
                'concepts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'summary' => ['type' => 'string'],
                            'source_quote' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'summary', 'source_quote'],
                    ],
                ],
            ],
            'required' => ['concepts'],
        ];

        $system = 'أنت مساعد تعليمي. استخرج المفاهيم الرئيسية من النص المعطى فقط. '
            . 'لكل مفهوم أعطِ: عنواناً قصيراً، وملخصاً من جملة واحدة، '
            . 'واقتباساً حرفياً منسوخاً من النص كما هو دون أي تغيير. '
            . 'لا تضف أي معلومة غير موجودة في النص.';

        $this->info('Model: ' . config('llm.model'));
        $start = microtime(true);

        $raw = $llm->chat($system, "النص:\n" . $text, $schema);

        $data = json_decode($raw, true);

        if (! is_array($data) || ! isset($data['concepts'])) {
            $this->error('The model did not return the expected JSON:');
            $this->line($raw);

            return self::FAILURE;
        }

        foreach ($data['concepts'] as $i => $concept) {
            $quote = $concept['source_quote'] ?? '';
            $found = $quote !== '' && str_contains($text, $quote);

            $this->newLine();
            $this->line(($i + 1) . '. ' . ($concept['title'] ?? '?'));
            $this->line('   ' . ($concept['summary'] ?? ''));
            $this->line('   اقتباس: ' . $quote);
            $this->line($found ? '   [OK] الاقتباس موجود في النص' : '   [X] الاقتباس غير موجود في النص');
        }

        $this->newLine();
        $this->info(sprintf('Done in %.1f seconds', microtime(true) - $start));

        return self::SUCCESS;
    }
}