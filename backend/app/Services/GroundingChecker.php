<?php

namespace App\Services;

use RuntimeException;

class GroundingChecker
{
    private const STATUSES = ['supported', 'unsupported', 'contradicted'];

    private const CLAIMS_PROMPT = 'أنت مدقق صارم. ستُعطى "النص المصدري" و"النص المرشح". '
        . 'استخرج كل ادعاء معلوماتي في النص المرشح (كل جملة أو فكرة مستقلة)، وصنّف كل ادعاء كما يلي: '
        . 'supported إذا كان مذكوراً صراحة في النص المصدري أو نتيجة مباشرة له. '
        . 'unsupported إذا لم يُذكر في النص المصدري، حتى لو كان صحيحاً في الواقع. '
        . 'contradicted إذا كان يخالف ما يقوله النص المصدري. '
        . 'احكم بالنص المصدري وحده ولا تستعمل معلوماتك الخارجية.';

    private const CLAIMS_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'claims' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'claim' => ['type' => 'string'],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['supported', 'unsupported', 'contradicted'],
                        ],
                    ],
                    'required' => ['claim', 'status'],
                ],
            ],
        ],
        'required' => ['claims'],
    ];

    private const CONFLICTS_PROMPT = 'أنت مدقق صارم. ستُعطى "النص المصدري" و"مثال" كتبه شخص آخر لتقريب الفكرة. '
        . 'أولاً قسّم المثال إلى ادعاءات بسيطة، كل ادعاء يصف معلومة أو سلوكاً واحداً. '
        . 'الجملة التي تجمع أكثر من معلومة (مثل: "يحدث كذا ثم يحدث كذا") يجب فصلها إلى ادعاءات منفصلة، '
        . 'لأن جزءاً منها قد يوافق النص وجزءاً آخر يخالفه. '
        . 'لكل ادعاء اكتب أولاً "reason": مقارنة قصيرة بينه وبين النص المصدري، '
        . 'ثم حدّد "conflicts_with_source": هل يصف سلوكاً أو حقيقة بعكس ما ذكره النص المصدري؟ '
        . 'المثال يحق له أن يضيف تفاصيل غير موجودة في النص (قصة، أسماء، سياق)، ولا يُعد ذلك تناقضاً. '
        . 'التناقض هو فقط أن يصف الادعاء عكس ما يقوله النص. أما إذا كان يطبّق ما في النص أو يتفق معه فالقيمة false.';

     private const CONFLICTS_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'statements' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'statement' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                        'conflicts_with_source' => ['type' => 'boolean'],
                    ],
                    'required' => ['statement', 'reason', 'conflicts_with_source'],
                ],
            ],
        ],
        'required' => ['statements'],
    ];

    public function __construct(private LlmClient $llm)
    {
    }

    /**
     * @return list<array{claim:string, status:string}>
     */
    public function check(string $source, string $candidate): array
    {
        $raw = $this->llm->chat(
            self::CLAIMS_PROMPT,
            "النص المصدري:\n{$source}\n\nالنص المرشح:\n{$candidate}",
            self::CLAIMS_SCHEMA,
            config('llm.verifier_model'),
        );

        $data = json_decode($raw, true);

        if (! is_array($data) || ! is_array($data['claims'] ?? null)) {
            throw new RuntimeException('The model returned an invalid verification result.');
        }

        $claims = [];

        foreach ($data['claims'] as $item) {
            if (is_array($item)
                && is_string($item['claim'] ?? null)
                && in_array($item['status'] ?? null, self::STATUSES, true)) {
                $claims[] = ['claim' => $item['claim'], 'status' => $item['status']];
            }
        }

        return $claims;
    }

    /**
     * قائمة فارغة تعني: لا يوجد تناقض مع النص المصدري
     *
     * @return list<array{statement:string, reason:string}>
     */
    public function conflicts(string $source, string $example): array
    {
        $raw = $this->llm->chat(
            self::CONFLICTS_PROMPT,
            "النص المصدري:\n{$source}\n\nالمثال:\n{$example}",
            self::CONFLICTS_SCHEMA,
            config('llm.verifier_model'),
            0.0,
        );

        $data = json_decode($raw, true);

        if (! is_array($data) || ! is_array($data['statements'] ?? null)) {
            throw new RuntimeException('The model returned an invalid conflict-check result.');
        }

        $conflicts = [];

        foreach ($data['statements'] as $item) {
            if (is_array($item)
                && is_string($item['statement'] ?? null)
                && is_string($item['reason'] ?? null)
                && ($item['conflicts_with_source'] ?? false) === true) {
                $conflicts[] = ['statement' => $item['statement'], 'reason' => $item['reason']];
            }
        }

        return $conflicts;
    }

    /**
     * أسوأ حالة بين كل الادعاءات تحدد الحكم النهائي
     *
     * @param  list<array{claim:string, status:string}>  $claims
     */
    public static function verdict(array $claims): string
    {
        if ($claims === []) {
            return 'unknown';
        }

        $statuses = array_column($claims, 'status');

        if (in_array('contradicted', $statuses, true)) {
            return 'contradicted';
        }

        if (in_array('unsupported', $statuses, true)) {
            return 'unsupported';
        }

        return 'supported';
    }
}