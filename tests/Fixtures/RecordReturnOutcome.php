<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use RobertoGallea\Judgment\Events\AssessmentCompleted;

/** A queued listener: it receives a serialised copy of the event, so it decides through the record. */
final class RecordReturnOutcome implements ShouldQueue
{
    public function handle(AssessmentCompleted $event): void
    {
        $event->record?->outcome();
    }
}
