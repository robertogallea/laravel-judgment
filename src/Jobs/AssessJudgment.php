<?php

namespace RobertoGallea\Judgment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RobertoGallea\Judgment\Contracts\Judge;
use RobertoGallea\Judgment\Judgment;

/** Assesses a Judgment off the request cycle, firing the same lifecycle events as assess(). */
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

    public function handle(Judge $judge): void
    {
        $judge->assess($this->judgment);
    }
}
