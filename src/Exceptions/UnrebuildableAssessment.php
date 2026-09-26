<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/** A record whose Assessment cannot be rebuilt as it was assessed. */
final class UnrebuildableAssessment extends LogicException
{
    public static function otherJudgment(AssessmentRecord $record, Judgment $judgment): self
    {
        return new self(sprintf('Assessment record %d was recorded for %s, not %s.', $record->id, $record->judgment, $judgment::class));
    }

    public static function changedQuestions(AssessmentRecord $record): self
    {
        return new self(sprintf(
            'The Questions of %s have changed since assessment record %d: its answers would be read against wording they were not given for.',
            $record->judgment,
            $record->id,
        ));
    }

    public static function noSubject(AssessmentRecord $record): self
    {
        return new self(sprintf('Assessment record %d has no Subject to construct %s with: pass the Judgment to assessment().', $record->id, $record->judgment));
    }

    public static function unassessed(AssessmentRecord $record): self
    {
        return new self(sprintf('Assessment record %d is an Unassessed attempt of %s: it has no answers to rebuild.', $record->id, $record->judgment));
    }
}
