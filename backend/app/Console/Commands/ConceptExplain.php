<?php

namespace App\Console\Commands;

use App\Models\Concept;
use App\Services\ConceptExplainer;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

class ConceptExplain extends Command
{
    protected $signature = 'concept:explain {id? : Concept id (omit to list concepts)} {style=simple : simple|example|analogy|steps}';

    protected $description = 'Generate an explanation for a concept in a given style';

    public function handle(ConceptExplainer $explainer): int
    {
        $id = $this->argument('id');

        if ($id === null) {
            $this->table(
                ['id', 'lecture', 'title'],
                Concept::all()->map(fn (Concept $c) => [$c->id, $c->lecture_id, $c->title])->all(),
            );

            return self::SUCCESS;
        }

        $concept = Concept::find($id);

        if (! $concept) {
            $this->error('Concept not found.');

            return self::FAILURE;
        }

        $style = $this->argument('style');

        $this->info("المفهوم: {$concept->title}  |  النمط: {$style}");
        $this->line("اقتباس: {$concept->source_quote}");
        $this->newLine();

        $start = microtime(true);

        try {
            $result = $explainer->explain($concept, $style);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($result['explanation']);

        if ($result['example'] !== '') {
            $this->newLine();
            $this->warn('[ليس من المحاضرة] ' . $result['example']);
        }

        $this->newLine();
        $this->info(sprintf('%.1f seconds', microtime(true) - $start));

        return self::SUCCESS;
    }
}