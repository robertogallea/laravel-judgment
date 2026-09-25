<?php

namespace RobertoGallea\Judgment\Tests\Fixtures\Readme;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;

final class RefundDecision implements Decision
{
    public function __invoke(Assessment $assessment, RefundAbuse $judgment): RefundOutcome
    {
        $abusive = $assessment->likelihood('abusive');
        $doubtful = $assessment->rating('credibility')->below(2.0);
        $frequent = $judgment->refund->customer->refundsThisYear() >= 3;

        return match (true) {
            $abusive->above(.65) => RefundOutcome::Reject,
            $abusive->above(.30) => RefundOutcome::Escalate,
            $doubtful => RefundOutcome::Escalate,
            $frequent => RefundOutcome::Escalate,
            default => RefundOutcome::Approve,
        };
    }
}
