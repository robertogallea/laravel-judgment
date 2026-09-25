<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use RobertoGallea\Judgment\Events\AssessmentCompleted;

/** A queued listener: it receives a serialised copy of the event, so it decides through the record. */
final class RecordReturnOutcome implements ShouldQueue
{
    public static bool $strict = false;

    public function handle(AssessmentCompleted $event): void
    {
        self::$strict ? $event->record?->decide(new StrictReturnDecision) : $event->record?->outcome();
    }
}
