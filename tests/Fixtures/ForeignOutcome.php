<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Contracts\Outcome;

/** Outcomes of another Judgment, sharing a value with RefundOutcome. */
enum ForeignOutcome: string implements Outcome
{
    case Approve = 'approve';
    case Hold = 'hold';

    public function requiresReview(): bool
    {
        return false;
    }
}
