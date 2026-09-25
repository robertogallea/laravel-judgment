<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;

/** Depends on state outside its Assessment: each call flips the Outcome. */
final class ImpureRefundDecision implements Decision
{
    private int $calls = 0;

    public function __invoke(Assessment $assessment, RefundAbuse $judgment): RefundOutcome
    {
        return $this->calls++ % 2 === 0 ? RefundOutcome::Approve : RefundOutcome::Reject;
    }
}
