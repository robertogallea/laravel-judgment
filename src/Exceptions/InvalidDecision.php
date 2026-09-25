<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Judgment;

final class InvalidDecision extends LogicException
{
    public static function notInvokable(Decision $decision, Judgment $judgment): self
    {
        return new self(sprintf(
            '%s must define __invoke(Assessment $assessment, %s $judgment): Outcome.',
            $decision::class,
            $judgment::class,
        ));
    }
}
