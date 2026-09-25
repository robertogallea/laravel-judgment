<?php

namespace RobertoGallea\Judgment\Events;

use Carbon\CarbonImmutable;
use Illuminate\Queue\SerializesModels;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/**
 * A Decision yielded an Outcome that requires Review: a person must decide
 * before its Action is taken. Fired once per record; notify reviewers from here.
 */
final class AssessmentAwaitingReview
{
    use SerializesModels;

    /** @param  AssessmentRecord|null  $record  null when persistence is off, or under Judge::fake(), so it cannot be resolved */
    public function __construct(
        public readonly Judgment $judgment,
        public readonly Assessment $assessment,
        public readonly Outcome $outcome,
        public readonly ?AssessmentRecord $record,
        public readonly CarbonImmutable $requestedAt,
    ) {}
}
