<?php

namespace App\Jobs;

use App\Models\Explanation;
use App\Services\ExplanationBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateExplanation implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $explanationId)
    {
    }

    public function handle(ExplanationBuilder $builder): void
    {
        $record = Explanation::with('concept')->find($this->explanationId);

        // قد يُحذف المفهوم (وشروحه) قبل أن يبدأ العامل
        if (! $record) {
            return;
        }

        $record->status = 'processing';
        $record->save();

        $builder->build($record);
    }

    public function failed(?Throwable $exception): void
    {
        Explanation::whereKey($this->explanationId)->update(['status' => 'failed']);
    }
}