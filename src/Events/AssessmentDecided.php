<?php

namespace RobertoGallea\Judgment\Events;

use Illuminate\Queue\SerializesModels;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/**
 * A Decision yielded an Outcome for an Assessment a Judge produced, directly or through its record.
 * Fired for every such decision, sync or queued, never for a Replay; perform the Action from here.
 * An Outcome that requires Review also fires AssessmentAwaitingReview.
 */
final class AssessmentDecided
{
    use SerializesModels;

    /** @param  AssessmentRecord|null  $record  null when persistence is off, recording is best-effort and failed, or under Judge::fake() */
    public function __construct(
        public readonly Judgment $judgment,
        public readonly Assessment $assessment,
        public readonly Decision $decision,
        public readonly Outcome $outcome,
        public readonly ?AssessmentRecord $record,
    ) {}
}
