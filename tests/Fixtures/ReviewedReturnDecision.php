<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;

/** Rejects a clearly abusive return, sends an ambiguous one to Review, approves the rest. */
final class ReviewedReturnDecision implements Decision
{
    public function __invoke(Assessment $assessment, ReturnAbuse $judgment): RefundOutcome
    {
        $abusive = $assessment->likelihood('abusive');

        return match (true) {
            $abusive->above(.65) => RefundOutcome::Reject,
            $abusive->above(.30) => RefundOutcome::Escalate,
            default => RefundOutcome::Approve,
        };
    }
}
