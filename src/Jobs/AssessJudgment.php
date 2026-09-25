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

    /** Each attempt is a paid Engine round, and the Jev driver already retries rate limits. */
    public int $tries;

    public function __construct(public readonly Judgment $judgment)
    {
        $this->tries = (int) config('judgment.queue.tries', 1);
        $this->onConnection(config('judgment.queue.connection'));
        $this->onQueue(config('judgment.queue.queue'));
    }

    public function handle(Judge $judge): void
    {
        $judge->assess($this->judgment);
    }
}
