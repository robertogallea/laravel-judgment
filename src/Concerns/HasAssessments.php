<?php

namespace RobertoGallea\Judgment\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/** For an Eloquent Subject: the Assessments recorded against it. */
trait HasAssessments
{
    /** @return MorphMany<AssessmentRecord, $this> */
    public function assessments(): MorphMany
    {
        return $this->morphMany(AssessmentRecord::class, 'subject');
    }

    /** @param  class-string<Judgment>  $judgment */
    public function latestAssessment(string $judgment): ?AssessmentRecord
    {
        return $this->assessments()->where('judgment', $judgment)->latest('id')->first();
    }
}
