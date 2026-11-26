<?php

namespace App\Console\Commands;

use App\Services\GroundingChecker;
use Illuminate\Console\Command;

class GroundingTest extends Command
{
    protected $signature = 'grounding:test';

    protected $description = 'Evaluate the grounding checker on a labelled set (tuning + held-out)';

    private const SOURCE = 'تُستخدم الدالة useEffect لتنفيذ ما يُسمّى الآثار الجانبية (Side Effects)، '
        . 'مثل جلب البيانات من الخادم أو الاشتراك في حدث أو تعديل عنوان الصفحة. '
        . 'إذا مررنا مصفوفة فارغة فإن التأثير يُنفَّذ مرة واحدة فقط بعد أول رسم للمكوّن.';

    public function handle(GroundingChecker $checker): int
    {
        $cases = [
            [
                'id' => 'E1', 'group' => 'tuning', 'type' => 'explanation', 'expect' => 'supported',
                'text' => 'تُستخدم الدالة useEffect لتنفيذ الآثار الجانبية، مثل جلب البيانات من الخادم أو تعديل عنوان الصفحة. '
                    . 'وإذا مررنا لها مصفوفة فارغة فإنها تُنفَّذ مرة واحدة فقط بعد أول رسم للمكوّن.',
            ],
            [
                'id' => 'E2', 'group' => 'tuning', 'type' => 'explanation', 'expect' => 'not supported',
                'text' => 'تُنفَّذ الدالة useEffect أيضاً بعد كل عملية رسم للمكوّن إذا لم نمرر لها أي مصفوفة اعتماديات.',
            ],
            [
                'id' => 'E3', 'group' => 'tuning', 'type' => 'explanation', 'expect' => 'not supported',
                'text' => 'إذا مررنا مصفوفة فارغة إلى useEffect فإن التأثير يُنفَّذ بعد كل عملية رسم للمكوّن.',
            ],
            [
                'id' => 'X1', 'group' => 'tuning', 'type' => 'example', 'expect' => 'no conflict',
                'text' => 'تخيل صفحة تعرض اسم المستخدم. نستخدم useEffect مع مصفوفة فارغة لجلب بيانات المستخدم من الخادم '
                    . 'مرة واحدة فقط بعد أول رسم للصفحة.',
            ],
            [
                'id' => 'X2', 'group' => 'tuning', 'type' => 'example', 'expect' => 'conflict',
                'text' => 'تخيل أنك تطور تطبيقًا يعرض قائمة مهام. يمكنك استخدام useEffect لتحديث القائمة. '
                    . 'عند تمرير مصفوفة فارغة، سيتم تحديث القائمة عند تحميل المكون، ثم يتكرر التحديث فقط عند إضافة مهام جديدة.',
            ],
            [
                'id' => 'X3', 'group' => 'tuning', 'type' => 'example', 'expect' => 'conflict',
                'text' => 'نستخدم useEffect لتعديل عنوان الصفحة. ومع تمرير مصفوفة فارغة سيتم تعديل العنوان '
                    . 'في كل مرة يُعاد فيها رسم المكوّن.',
            ],
            [
                'id' => 'X4', 'group' => 'tuning', 'type' => 'example', 'expect' => 'no conflict',
                'text' => 'تخيل تطبيق طقس. عند فتح التطبيق نستخدم useEffect بمصفوفة فارغة لجلب حالة الطقس من الخادم مرة واحدة.',
            ],
            // الحالات المحجوبة: كُتبت قبل تعديل التعليمات، ولا نضبط التعليمات بناءً عليها
            [
                'id' => 'H1', 'group' => 'holdout', 'type' => 'example', 'expect' => 'conflict',
                'text' => 'نريد جلب بيانات المنتجات من الخادم. نضع الطلب داخل useEffect بمصفوفة فارغة، '
                    . 'فيُرسل الطلب عند أول عرض للصفحة ثم يُرسل من جديد في كل مرة يتغير فيها شيء في الصفحة.',
            ],
            [
                'id' => 'H2', 'group' => 'holdout', 'type' => 'example', 'expect' => 'no conflict',
                'text' => 'تخيل صفحة تعرض عنواناً يحمل اسم المستخدم. نستخدم useEffect لتعديل عنوان الصفحة بعد أول عرض للمكوّن، '
                    . 'ويحدث ذلك مرة واحدة فقط لأننا مررنا مصفوفة فارغة.',
            ],
            [
                'id' => 'H3', 'group' => 'holdout', 'type' => 'example', 'expect' => 'no conflict',
                'text' => 'تخيل موقع أخبار. عند فتح الصفحة نشترك في حدث مع الخادم عبر useEffect بمصفوفة فارغة، '
                    . 'ويتم الاشتراك مرة واحدة بعد أول رسم.',
            ],
            [
                'id' => 'H4', 'group' => 'holdout', 'type' => 'example', 'expect' => 'conflict',
                'text' => 'ننفّذ useEffect بمصفوفة فارغة، وهكذا يُنفَّذ التأثير قبل أول رسم للمكوّن.',
            ],
        ];

        $this->info('النموذج المدقق: ' . config('llm.verifier_model'));

        $score = ['tuning' => [0, 0], 'holdout' => [0, 0]];

        foreach ($cases as $case) {
            $start = microtime(true);
            $details = [];

            if ($case['type'] === 'explanation') {
                $claims = $checker->check(self::SOURCE, $case['text']);
                $actual = GroundingChecker::verdict($claims) === 'supported' ? 'supported' : 'not supported';

                foreach ($claims as $claim) {
                    $details[] = "[{$claim['status']}] {$claim['claim']}";
                }
            } else {
                $conflicts = $checker->conflicts(self::SOURCE, $case['text']);
                $actual = $conflicts === [] ? 'no conflict' : 'conflict';

                foreach ($conflicts as $conflict) {
                    $details[] = "{$conflict['statement']}  <- {$conflict['reason']}";
                }
            }

            $ok = $actual === $case['expect'];
            $score[$case['group']][0] += $ok ? 1 : 0;
            $score[$case['group']][1]++;

            $this->newLine();
            $line = sprintf(
                '%s [%s] المتوقع: %s | الفعلي: %s (%.0fs)',
                $case['id'],
                $ok ? 'PASS' : 'FAIL',
                $case['expect'],
                $actual,
                microtime(true) - $start,
            );
            $ok ? $this->info($line) : $this->error($line);

            foreach ($details as $detail) {
                $this->line('   ' . $detail);
            }
        }

        $this->newLine();
        $this->info("مجموعة الضبط: {$score['tuning'][0]}/{$score['tuning'][1]}");
        $this->info("المجموعة المحجوبة: {$score['holdout'][0]}/{$score['holdout'][1]}");

        return self::SUCCESS;
    }
}