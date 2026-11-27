<?php

namespace App\Services;

use App\Models\Explanation;
use Throwable;

class ExplanationBuilder
{
    private const MAX_ATTEMPTS = 3;

    private const MIN_EXPLANATION_LENGTH = 60;

    private const MIN_EXAMPLE_LENGTH = 20;

    private const MIN_SENTENCES_AFTER_PRUNING = 2;

    public function __construct(
        private ConceptExplainer $explainer,
        private GroundingChecker $checker,
        private PassageLocator $locator,
    ) {
    }

    /**
     * يولّد الشرح ويدقّقه ويحفظ النتيجة. يعيد سجلاً بكل محاولة.
     *
     * @return list<string>
     */
    public function build(Explanation $record): array
    {
        $concept = $record->concept;
        $source = $this->locator->locate($concept);
        $log = [
            'طول المصدر المستخدم: ' . mb_strlen($source) . ' حرفاً (الاقتباس: ' . mb_strlen($concept->source_quote) . ')',
        ];
        $fallback = null;
        $feedback = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $record->attempts = $attempt;
            $record->save();

            try {
                $t0 = microtime(true);
                $result = $this->explainer->explain($concept, $record->style, $source, $feedback);
                $generationTime = microtime(true) - $t0;

                // حارس الاكتمال: نرفض الشرح المبتور قبل أن نصرف وقتاً في تدقيقه
                if (! $this->isComplete($result['explanation'], self::MIN_EXPLANATION_LENGTH)) {
                    $log[] = sprintf('المحاولة %d: رُفض الشرح لأنه مبتور أو قصير جداً [توليد %.0fs]', $attempt, $generationTime);
                    $log[] = '    الشرح المرفوض: ' . $result['explanation'];
                    $feedback = 'الشرح الذي كتبته كان ناقصاً أو مبتوراً في منتصف الجملة. '
                        . 'اكتب شرحاً كاملاً بجمل تامة، تنتهي كل جملة بنقطة.';

                    continue;
                }

                if ($result['example'] !== '' && ! $this->isComplete($result['example'], self::MIN_EXAMPLE_LENGTH)) {
                    $log[] = '    أُهمل المثال لأنه مبتور';
                    $result['example'] = '';
                }

                $t1 = microtime(true);
                $claims = $this->checker->check($source, $result['explanation']);
                $verdict = GroundingChecker::verdict($claims);
                $checkTime = microtime(true) - $t1;

                $timing = sprintf('توليد %.0fs، تدقيق الشرح %.0fs', $generationTime, $checkTime);

                if ($verdict !== 'supported') {
                    $pruned = $this->prune($result['explanation'], $claims);

                    if ($pruned === null) {
                        $log[] = "المحاولة {$attempt}: رُفض الشرح ({$verdict}) [{$timing}]";
                        $log[] = '    الشرح المرفوض: ' . $result['explanation'];

                        $rejected = [];

                        foreach ($claims as $claim) {
                            if ($claim['status'] !== 'supported') {
                                $log[] = "    [{$claim['status']}] " . mb_substr($claim['claim'], 0, 200);
                                $rejected[] = '- ' . mb_substr($claim['claim'], 0, 200);
                            }
                        }

                        $feedback = "هذه الجمل من شرحك غير موجودة في فقرات المحاضرة، فاحذفها ولا تكررها، "
                            . "ولا تضع أي تصوّر أو مثال داخل الشرح:\n" . implode("\n", array_slice($rejected, 0, 3));

                        continue;
                    }

                    $log[] = "المحاولة {$attempt}: حُذفت جمل غير مدعومة وبقي الجزء المدقَّق [{$timing}]";
                    $log[] = '    قبل الحذف: ' . $result['explanation'];
                    $result['explanation'] = $pruned;
                }

                if ($result['example'] !== '') {
                    $t2 = microtime(true);
                    $conflicts = $this->checker->conflicts($source, $result['example']);
                    $timing .= sprintf('، تدقيق المثال %.0fs', microtime(true) - $t2);

                    if ($conflicts !== []) {
                        $log[] = "المحاولة {$attempt}: الشرح سليم لكن المثال يتناقض مع النص [{$timing}]";

                        $issues = [];

                        foreach ($conflicts as $conflict) {
                            $log[] = '    ' . mb_substr($conflict['statement'], 0, 200)
                                . '  <- ' . mb_substr($conflict['reason'], 0, 200);
                            $issues[] = '- ' . mb_substr($conflict['statement'], 0, 200)
                                . ' (' . mb_substr($conflict['reason'], 0, 200) . ')';
                        }

                        $feedback = "المثال الذي كتبته يناقض المحاضرة:\n" . implode("\n", array_slice($issues, 0, 2))
                            . "\nاكتب مثالاً مختلفاً لا يصف سلوك أي دالة.";

                        $fallback ??= $result['explanation'];

                        continue;
                    }
                }
            } catch (Throwable $e) {
                $log[] = "المحاولة {$attempt}: خطأ (" . mb_substr($e->getMessage(), 0, 120) . ')';

                continue;
            }

            $log[] = "المحاولة {$attempt}: مقبول [{$timing}]";
            $this->finish($record, 'ready', $result['explanation'], $result['example'], $log);

            return $log;
        }

        if ($fallback !== null) {
            $log[] = 'نُشر الشرح دون المثال';
            $this->finish($record, 'ready', $fallback, null, $log);
        } else {
            $this->finish($record, 'failed', null, null, $log);
        }

        return $log;
    }

    /**
     * يحذف الجمل التي حدّدها المدقق كغير مدعومة، ويعيد null إن لم يكن الحذف آمناً
     *
     * @param  list<array{claim:string, status:string}>  $claims
     */
    private function prune(string $text, array $claims): ?string
    {
        $flagged = [];

        foreach ($claims as $claim) {
            if ($claim['status'] === 'contradicted') {
                return null;
            }

            if ($claim['status'] === 'unsupported') {
                $flagged[] = $this->plain($claim['claim']);
            }
        }

        if ($flagged === []) {
            return null;
        }

        $sentences = preg_split('/(?<=[.!؟?])\s+|\R+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $matched = array_fill(0, count($flagged), false);
        $kept = [];

        foreach ($sentences as $sentence) {
            $plain = $this->plain($sentence);
            $drop = false;

            foreach ($flagged as $i => $claim) {
                if (mb_strlen($plain) >= 10 && mb_strlen($claim) >= 10
                    && (str_contains($plain, $claim) || str_contains($claim, $plain))) {
                    $matched[$i] = true;
                    $drop = true;
                }
            }

            if (! $drop) {
                $kept[] = trim($sentence);
            }
        }

        // إن لم نستطع ربط كل ادعاء مرفوض بجملة محددة فلا نخمّن
        if (in_array(false, $matched, true) || count($kept) < self::MIN_SENTENCES_AFTER_PRUNING) {
            return null;
        }

        $result = implode(str_contains($text, "\n") ? "\n" : ' ', $kept);

        return $this->isComplete($result, self::MIN_EXPLANATION_LENGTH) ? $result : null;
    }

    private function isComplete(string $text, int $minLength): bool
    {
        $text = trim($text);

        return mb_strlen($text) >= $minLength
            && preg_match('/[.!؟?…)\]`*]$/u', $text) === 1;
    }

    private function plain(string $text): string
    {
        $text = str_replace(['*', '`', '"', '«', '»'], '', $text);
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim(preg_replace('/[\s.!؟?،,:]+$/u', '', trim($text)));
    }

    private function finish(Explanation $record, string $status, ?string $explanation, ?string $example, array $log): void
    {
        $record->status = $status;
        $record->explanation = $explanation;
        $record->example = ($example === null || $example === '') ? null : $example;
        $record->log = implode("\n", $log);
        $record->save();
    }
}