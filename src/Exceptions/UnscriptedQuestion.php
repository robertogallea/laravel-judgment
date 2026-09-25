<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Judgment;

final class UnscriptedQuestion extends LogicException
{
    public static function for(Judgment $judgment, string $key): self
    {
        return new self(sprintf('Question "%s" on %s was not scripted in this fake Assessment, but was read.', $key, $judgment::class));
    }
}
