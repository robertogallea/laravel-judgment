<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;

final class RealEngineCallPrevented extends LogicException
{
    public static function make(): self
    {
        return new self('The Judge is faked, so real Engine calls are prevented. Script the Judgment in Judge::fake() instead.');
    }
}
