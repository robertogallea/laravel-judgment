<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Contracts\Outcome;

enum RefundOutcome: string implements Outcome
{
    case Approve = 'approve';
    case Escalate = 'escalate';
    case Reject = 'reject';

    public function requiresReview(): bool
    {
        return $this === self::Escalate;
    }
}
