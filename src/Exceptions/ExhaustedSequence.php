<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Judgment;

final class ExhaustedSequence extends LogicException
{
    public static function for(Judgment $judgment, int $assessment): self
    {
        return new self(sprintf('The fake sequence for %s has no script left for assessment #%d.', $judgment::class, $assessment));
    }
}
