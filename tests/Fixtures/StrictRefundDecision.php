<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;

/** Same Questions, stricter thresholds — the high-value variant. */
final class StrictRefundDecision implements Decision
{
    public function __invoke(Assessment $assessment, RefundAbuse $judgment): RefundOutcome
    {
        return match (true) {
            $assessment->likelihood('abusive')->above(.50) => RefundOutcome::Reject,
            $assessment->likelihood('abusive')->above(.15) => RefundOutcome::Escalate,
            default => RefundOutcome::Approve,
        };
    }
}
