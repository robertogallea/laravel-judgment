<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Judgment;

final class NoDefaultDecision extends LogicException
{
    public static function for(Judgment $judgment): self
    {
        return new self(sprintf('%s names no default Decision; pass one to decide() instead.', $judgment::class));
    }
}
