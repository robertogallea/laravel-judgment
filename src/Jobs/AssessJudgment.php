<?php

namespace RobertoGallea\Judgment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Judge;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Support\AssessmentRecorder;

/**
 * Assesses a Judgment off the request cycle, firing the same lifecycle events as assess(), then
 * decides it with its default Decision, if it declares one, in a chained DecideAssessment (ADR-0015).
 */
final class AssessJudgment implements ShouldQueue
{
    use Queueable;

    /** From judgment.queue.tries, and never below 1: Laravel retries a job with 0 tries without limit. */
    public int $tries;

    public function __construct(public readonly Judgment $judgment)
    {
        $this->tries = max(1, (int) config('judgment.queue.tries', 1));
        $this->onConnection(config('judgment.queue.connection'));
        $this->onQueue(config('judgment.queue.queue'));
    }

    public function handle(Judge $judge, AssessmentRecorder $recorder): void
    {
        $assessment = $judge->assess($this->judgment);
        if (! $assessment instanceof Assessment || $this->judgment->decision() === null) {
            return;
        }

        $record = $recorder->recordOf($assessment);
        if ($record === null) {
            // With nothing recorded there is nothing to chain on, so the Decision runs here.
            $assessment->outcome();

            return;
        }

        $this->prependToChain((new DecideAssessment($record, $this->judgment))
            ->onConnection($this->connection)
            ->onQueue($this->queue));
    }
}
