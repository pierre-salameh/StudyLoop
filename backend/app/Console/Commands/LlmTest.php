<?php

namespace App\Console\Commands;

use App\Services\LlmClient;
use Illuminate\Console\Command;

class LlmTest extends Command
{
    protected $signature = 'llm:test';

    protected $description = 'Send a test prompt to the configured LLM';

    public function handle(LlmClient $llm): int
    {
        $this->info('Model: ' . config('llm.model'));

        $start = microtime(true);

        $answer = $llm->chat(
            'أنت مساعد تعليمي. أجب بالعربية الفصحى المبسطة وبإيجاز.',
            'ما الفرق بين الخلية النباتية والخلية الحيوانية؟ أجب في ثلاث نقاط.',
        );

        $this->line($answer);
        $this->info(sprintf('Done in %.1f seconds', microtime(true) - $start));

        return self::SUCCESS;
    }
}