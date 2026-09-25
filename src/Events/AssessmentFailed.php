<?php

namespace RobertoGallea\Judgment\Events;

use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Judgment;

/** The Engine failed to assess a Judgment; fired whether the failure is thrown or ends Unassessed. */
final class AssessmentFailed
{
    public function __construct(
        public readonly Judgment $judgment,
        public readonly EngineFailed $exception,
    ) {}
}
