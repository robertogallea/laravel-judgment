<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Contracts\Decision;

/** A Decision that forgot its __invoke method. */
final class UninvokableDecision implements Decision
{
    public function decide(): RefundOutcome
    {
        return RefundOutcome::Approve;
    }
}
