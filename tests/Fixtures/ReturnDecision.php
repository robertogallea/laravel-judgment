<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;

final class ReturnDecision implements Decision
{
    public function __invoke(Assessment $assessment, ReturnAbuse $judgment): RefundOutcome
    {
        return $assessment->likelihood('abusive')->above(.65) ? RefundOutcome::Reject : RefundOutcome::Approve;
    }

    public function version(): string
    {
        return '2';
    }
}
