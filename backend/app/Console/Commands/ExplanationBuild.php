<?php

namespace App\Console\Commands;

use App\Models\Concept;
use App\Services\ConceptExplainer;
use App\Services\ExplanationBuilder;
use Illuminate\Console\Command;

class ExplanationBuild extends Command
{
    protected $signature = 'explanation:build {concept : Concept id} {style=simple : simple|example|analogy|steps}';

    protected $description = 'Generate, verify and save an explanation (runs synchronously)';

    public function handle(ExplanationBuilder $builder): int
    {
        $concept = Concept::find($this->argument('concept'));

        if (! $concept) {
            $this->error('Concept not found.');

            return self::FAILURE;
        }

        $style = $this->argument('style');

        if (! array_key_exists($style, ConceptExplainer::STYLES)) {
            $this->error('Unknown style. Use: ' . implode(', ', array_keys(ConceptExplainer::STYLES)));

            return self::FAILURE;
        }

        $record = $concept->explanations()->updateOrCreate(
            ['style' => $style],
            ['status' => 'processing', 'attempts' => 0, 'explanation' => null, 'example' => null],
        );
        $record->load('concept');

        $this->info("المفهوم: {$concept->title}  |  النمط: {$style}");
        $this->line("اقتباس: {$concept->source_quote}");
        $this->newLine();

        $start = microtime(true);
        $log = $builder->build($record);
        $record->refresh();

        foreach ($log as $line) {
            $this->line($line);
        }

        $this->newLine();
        $this->info("الحالة: {$record->status} | المحاولات: {$record->attempts}");

        if ($record->explanation !== null) {
            $this->newLine();
            $this->line($record->explanation);
        }

        if ($record->example !== null) {
            $this->newLine();
            $this->warn('[ليس من المحاضرة] ' . $record->example);
        }

        $this->newLine();
        $this->info(sprintf('%.0f seconds', microtime(true) - $start));

        return self::SUCCESS;
    }
}