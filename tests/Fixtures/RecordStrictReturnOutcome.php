<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use RobertoGallea\Judgment\Events\AssessmentCompleted;

/** A queued listener recording the Outcome of another Decision through the record. */
final class RecordStrictReturnOutcome implements ShouldQueue
{
    public function handle(AssessmentCompleted $event): void
    {
        $event->record?->decide(new StrictReturnDecision);
    }
}
