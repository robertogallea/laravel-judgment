<?php

namespace RobertoGallea\Judgment\Events;

use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/** The Engine failed to assess a Judgment; fired whether the failure is thrown or ends Unassessed. */
final class AssessmentFailed
{
    /** @param  AssessmentRecord|null  $record  the failed attempt as recorded; null when persistence is off or recording it failed */
    public function __construct(
        public readonly Judgment $judgment,
        public readonly EngineFailed $exception,
        public readonly ?AssessmentRecord $record = null,
    ) {}
}
