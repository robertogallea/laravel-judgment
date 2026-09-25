<?php

namespace RobertoGallea\Judgment\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/**
 * A reviewer recorded a Resolution on a record awaiting Review. Perform the
 * Action for $resolution, the Outcome the person decided, from here.
 */
final class AssessmentResolved
{
    use SerializesModels;

    /**
     * @param  Outcome  $outcome  the automatic Outcome the Decision yielded
     * @param  Outcome  $resolution  the reviewer's, which may overturn it
     */
    public function __construct(
        public readonly AssessmentRecord $record,
        public readonly Outcome $outcome,
        public readonly Outcome $resolution,
        public readonly Authenticatable&Model $reviewer,
    ) {}
}
