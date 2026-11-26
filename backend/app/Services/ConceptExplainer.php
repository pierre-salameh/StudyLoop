<?php

namespace App\Services;

use App\Models\Concept;
use InvalidArgumentException;
use RuntimeException;

class ConceptExplainer
{
    private const BASE_PROMPT = 'أنت مدرّس صبور. اشرح المفهوم للطالب بالعربية الواضحة اعتماداً على نص المحاضرة المعطى فقط. '
        . 'قواعد صارمة: لا تضف أي معلومة علمية أو تقنية غير موجودة في النص، ولا تغيّر معنى أي عبارة فيه، '
        . 'وحافظ على المصطلحات كما وردت (مثلاً إذا ذكر النص "عنوان الصفحة" فلا تحوّلها إلى عبارة أخرى). '
        . 'أجب بحقلين: '
        . '"explanation": الشرح، ويعتمد على النص فقط، بين 60 و120 كلمة، دون عناوين أو تنسيق معقد. '
        . '"example": مثال أو تشبيه توضيحي من عندك لتقريب الفكرة، وهو الجزء الوحيد الذي يجوز أن يتجاوز النص، '
        . 'ويجب ألا يناقض النص. وإن لم يطلب منك النمط مثالاً أو تشبيهاً فاترك هذا الحقل نصاً فارغاً. ';

    public const STYLES = [
        'simple' => 'اكتب في "explanation" شرحاً بأبسط أسلوب ممكن لطالب لم يسمع بالموضوع من قبل، وفسّر أي مصطلح صعب فور ذكره. اترك "example" فارغاً.',
        'example' => 'اكتب في "explanation" الفكرة باختصار، وضع في "example" مثالاً عملياً واحداً واضحاً.',
        'analogy' => 'اكتب في "explanation" الفكرة باختصار، وضع في "example" تشبيهاً واحداً من الحياة اليومية يتطابق فعلاً مع الفكرة.',
        'steps' => 'اكتب "explanation" في خطوات مرقمة قصيرة (من 3 إلى 5 خطوات) مستندة إلى النص فقط. اترك "example" فارغاً.',
    ];

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'explanation' => ['type' => 'string'],
            'example' => ['type' => 'string'],
        ],
        'required' => ['explanation', 'example'],
    ];

    public function __construct(private LlmClient $llm)
    {
    }

    /**
     * @return array{explanation:string, example:string}
     */
    public function explain(Concept $concept, string $style): array
    {
        if (! array_key_exists($style, self::STYLES)) {
            throw new InvalidArgumentException("Unknown style: {$style}");
        }

        $system = self::BASE_PROMPT . self::STYLES[$style];

        $user = "المفهوم: {$concept->title}\n\nنص المحاضرة:\n{$concept->source_quote}";

        $raw = $this->llm->chat($system, $user, self::SCHEMA);
        $data = json_decode($raw, true);

        if (! is_array($data)
            || ! is_string($data['explanation'] ?? null)
            || trim($data['explanation']) === '') {
            throw new RuntimeException('The model returned an invalid explanation.');
        }

        return [
            'explanation' => trim($data['explanation']),
            'example' => trim(is_string($data['example'] ?? null) ? $data['example'] : ''),
        ];
    }
}