<?php

namespace App\Jobs;

use App\Models\Lecture;
use App\Services\ConceptExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExtractLectureConcepts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $lectureId)
    {
    }

    public function handle(ConceptExtractor $extractor): void
    {
        $lecture = Lecture::find($this->lectureId);

        // قد تُحذف المحاضرة قبل أن يبدأ العامل بمعالجتها
        if (! $lecture) {
            return;
        }

        $lecture->status = 'processing';
        $lecture->save();

        $extractor->extract($lecture);
    }

    public function failed(?Throwable $exception): void
    {
        Lecture::whereKey($this->lectureId)->update(['status' => 'failed']);
    }
}