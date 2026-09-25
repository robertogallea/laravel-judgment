<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use RobertoGallea\Judgment\Events\AssessmentCompleted;

/** A queued listener deciding the serialised copy of the Assessment, which is linked to no record. */
final class DecideReturnCopy implements ShouldQueue
{
    public function handle(AssessmentCompleted $event): void
    {
        $event->assessment->outcome();
    }
}
