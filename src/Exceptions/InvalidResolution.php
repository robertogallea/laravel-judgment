<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/** A Resolution the Review lifecycle does not allow: not-required → awaiting Review → resolved. */
final class InvalidResolution extends LogicException
{
    public static function notAwaitingReview(AssessmentRecord $record): self
    {
        return new self(sprintf('Assessment record %d is not awaiting Review.', $record->id));
    }

    public static function alreadyResolved(AssessmentRecord $record): self
    {
        return new self(sprintf('Assessment record %d is already resolved.', $record->id));
    }

    public static function otherOutcome(AssessmentRecord $record, Outcome $resolution): self
    {
        return new self(sprintf('Resolve assessment record %d with a case of %s, not %s.', $record->id, $record->outcome_type, $resolution::class));
    }

    public static function requiresReview(AssessmentRecord $record, Outcome $resolution): self
    {
        return new self(sprintf('%s::%s requires Review, so it cannot resolve assessment record %d.', $resolution::class, $resolution->name, $record->id));
    }
}
