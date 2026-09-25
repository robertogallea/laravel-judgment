<?php

namespace RobertoGallea\Judgment\Exceptions;

use InvalidArgumentException;

final class InvalidProbability extends InvalidArgumentException
{
    public static function for(string $what, float $given): self
    {
        return new self(sprintf('The %s must be between 0 and 1, %s given.', $what, $given));
    }
}
