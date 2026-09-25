<?php

namespace RobertoGallea\Judgment\Events;

use Illuminate\Queue\SerializesModels;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/**
 * The Engine answered every Question of a Judgment. A queued listener records
 * the Outcome through $record->outcome() or $record->decide(), since a
 * serialised Assessment is no longer linked to its record.
 */
final class AssessmentCompleted
{
    use SerializesModels;

    /** @param  AssessmentRecord|null  $record  null when persistence is off, or under Judge::fake() */
    public function __construct(
        public readonly Judgment $judgment,
        public readonly Assessment $assessment,
        public readonly ?AssessmentRecord $record = null,
    ) {}
}
