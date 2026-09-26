<?php

namespace RobertoGallea\Judgment;

use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/**
 * A Judgment whose Engine failed to produce an Assessment. It deliberately has
 * no answers and no Outcome, so a failure can never become a default Outcome.
 */
final class Unassessed
{
    /** @param  AssessmentRecord|null  $record  the failed attempt as recorded; null when persistence is off or recording it failed */
    public function __construct(
        public readonly Judgment $judgment,
        public readonly EngineFailed $exception,
        public readonly ?AssessmentRecord $record = null,
    ) {}
}
