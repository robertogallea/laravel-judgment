<?php

namespace RobertoGallea\Judgment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/**
 * Applies a queued Judgment's default Decision to its recorded Assessment, chained after the
 * assessing job, so retrying the decision never asks the Engine again (ADR-0015).
 */
final class DecideAssessment implements ShouldQueue
{
    use Queueable;

    /** From judgment.queue.decide_tries, and never below 1: Laravel retries a job with 0 tries without limit. */
    public int $tries;

    public function __construct(public readonly AssessmentRecord $record, public readonly Judgment $judgment)
    {
        $this->tries = max(1, (int) (config('judgment.queue.decide_tries') ?? 3));
    }

    public function handle(): void
    {
        $this->record->outcome($this->judgment);
    }
}
