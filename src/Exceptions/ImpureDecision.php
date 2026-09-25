<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;

final class ImpureDecision extends LogicException
{
    public static function for(Decision $decision, Outcome $first, Outcome $second): self
    {
        return new self(sprintf(
            '%s returned %s, then %s, for the same Assessment. A Decision must depend only on its Assessment and Judgment.',
            $decision::class,
            $first->name,
            $second->name,
        ));
    }
}
