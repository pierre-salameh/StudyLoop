<?php

namespace App\Services;

use App\Models\Concept;
use InvalidArgumentException;
use RuntimeException;

class ConceptExplainer
{
         private const BASE_PROMPT = 'أنت مدرّس صبور. اشرح المفهوم للطالب بالعربية الواضحة اعتماداً على فقرات المحاضرة المعطاة فقط. '
        . 'قواعد صارمة: لا تضف أي معلومة علمية أو تقنية غير موجودة في تلك الفقرات، ولا تغيّر معنى أي عبارة فيها، '
        . 'وحافظ على المصطلحات كما وردت. '
        . 'أجب بحقلين: '
        . '"explanation": الشرح، ويعتمد على فقرات المحاضرة فقط، ويركّز على المقطع الذي يخص المفهوم. اجعله قصيراً بقدر ما يغطيه النص '
        . '(من جملتين إلى خمس جمل، ولا يزيد على 100 كلمة)، ولا تُطِل لتملأ المساحة. ابدأ مباشرة بشرح المفهوم دون جملة تقديمية عامة، '
        . 'ولا تصفه بأنه مهم أو قوي أو مفيد، ولا تذكر فوائده أو أسبابه إلا إذا ذكرتها الفقرات. '
        . 'ولا تضع أمثلة داخل هذا الحقل. '
        . '"example": مثال أو تشبيه توضيحي من عندك لتقريب الفكرة، وهو الجزء الوحيد الذي يجوز أن يتجاوز النص، '
        . 'ويجب ألا يناقض النص. ولا تصف في المثال سلوك أي دالة (متى تُنفَّذ أو كم مرة) إلا بنفس عبارات النص حرفياً، '
        . 'واجعل المثال مجرد سيناريو يوضح الاستخدام. '
        . 'وإن لم يطلب منك النمط مثالاً أو تشبيهاً فاترك هذا الحقل نصاً فارغاً. ';

    public const STYLES = [
        'simple' => 'اكتب في "explanation" شرحاً بأبسط أسلوب ممكن لطالب لم يسمع بالموضوع من قبل، مستنداً إلى الفقرات فقط. أبقِ المصطلح كما ورد في المحاضرة عند أول ذكر، ثم وضّحه بكلمات بسيطة دون إضافة معلومة جديدة. وضع في "example" تصوّراً أو مثالاً قصيراً لتقريب الفكرة، ولا تضع أي تصوّر أو "تخيل..." داخل "explanation".',
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
    public function explain(Concept $concept, string $style, string $context = '', ?string $feedback = null): array
    {
        if (! array_key_exists($style, self::STYLES)) {
            throw new InvalidArgumentException("Unknown style: {$style}");
        }

        $context = $context !== '' ? $context : $concept->source_quote;

        $system = self::BASE_PROMPT . self::STYLES[$style];

        $user = "المفهوم: {$concept->title}\n\n"
            . "المقطع الذي يخص هذا المفهوم:\n{$concept->source_quote}\n\n"
            . "فقرات المحاضرة المتاحة (يمكنك الاعتماد على كل ما فيها):\n{$context}";

        if ($feedback !== null && $feedback !== '') {
            $user .= "\n\nملاحظة مهمة: كتبتَ محاولة سابقة فرفضها المراجع. أعد الكتابة وتجنّب ما يلي:\n{$feedback}";
        }

                $raw = $this->llm->chat($system, $user, self::SCHEMA, config('llm.explainer_model'));
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