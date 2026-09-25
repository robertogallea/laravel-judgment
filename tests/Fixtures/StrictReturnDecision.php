<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;

final class StrictReturnDecision implements Decision
{
    public function __invoke(Assessment $assessment, ReturnAbuse $judgment): RefundOutcome
    {
        return $assessment->likelihood('abusive')->above(.30) ? RefundOutcome::Reject : RefundOutcome::Approve;
    }
}
