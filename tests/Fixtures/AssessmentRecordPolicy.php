<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Models\AssessmentRecord;

/** An application's Policy for who may resolve a record: here, reviewers flagged as such. */
final class AssessmentRecordPolicy
{
    public function resolve(Reviewer $reviewer, AssessmentRecord $record): bool
    {
        return $reviewer->can_resolve;
    }
}
