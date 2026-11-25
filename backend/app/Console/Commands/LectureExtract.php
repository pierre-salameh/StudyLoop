<?php

namespace App\Console\Commands;

use App\Models\Lecture;
use App\Services\ConceptExtractor;
use Illuminate\Console\Command;

class LectureExtract extends Command
{
    protected $signature = 'lecture:extract {id? : Lecture id (omit to list lectures)} {--show : Only print the saved concepts, without extracting}';

    protected $description = 'Extract and save concepts for a lecture';

    public function handle(ConceptExtractor $extractor): int
    {
        $id = $this->argument('id');

        if ($id === null) {
            $this->table(
                ['id', 'title', 'chars', 'status'],
                Lecture::all()->map(fn (Lecture $l) => [
                    $l->id, $l->title, mb_strlen($l->content), $l->status,
                ])->all(),
            );

            return self::SUCCESS;
        }

        $lecture = Lecture::find($id);

        if (! $lecture) {
            $this->error('Lecture not found.');

            return self::FAILURE;
        }


                if ($this->option('show')) {
            foreach ($lecture->concepts as $concept) {
                $this->newLine();
                $this->line("{$concept->position}. {$concept->title}");
                $this->line("   الملخص: {$concept->summary}");
                $this->line("   الاقتباس: {$concept->source_quote}");
            }

            return self::SUCCESS;
        }

        $this->info("Lecture: {$lecture->title} (" . mb_strlen($lecture->content) . ' chars)');
        $start = microtime(true);

        $result = $extractor->extract($lecture);

        $this->info(sprintf(
            'Chunks: %d | Saved: %d | Rejected: %d | Failed chunks: %d | %.1fs',
            $result['chunks'],
            $result['saved'],
            $result['rejected'],
            $result['failed_chunks'],
            microtime(true) - $start,
        ));
        
                foreach ($result['rejected_items'] as $item) {
            $this->newLine();
            $this->warn("مرفوض: {$item['title']} ({$item['reason']})");
            $this->line("   الاقتباس: {$item['quote']}");
        }

        $this->newLine();
        foreach ($lecture->fresh()->concepts as $concept) {
            $this->line("{$concept->position}. {$concept->title}");
        }

        return self::SUCCESS;
    }
}