<?php

namespace App\Services;

use App\Models\Explanation;
use Throwable;

class ExplanationBuilder
{
    private const MAX_ATTEMPTS = 3;

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

                $t1 = microtime(true);
                $claims = $this->checker->check($source, $result['explanation']);
                $verdict = GroundingChecker::verdict($claims);
                $checkTime = microtime(true) - $t1;

                $timing = sprintf('توليد %.0fs، تدقيق الشرح %.0fs', $generationTime, $checkTime);

                if ($verdict !== 'supported') {
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

    private function finish(Explanation $record, string $status, ?string $explanation, ?string $example, array $log): void
    {
        $record->status = $status;
        $record->explanation = $explanation;
        $record->example = ($example === null || $example === '') ? null : $example;
        $record->log = implode("\n", $log);
        $record->save();
    }
}