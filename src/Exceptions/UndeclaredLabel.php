<?php

namespace RobertoGallea\Judgment\Exceptions;

use InvalidArgumentException;

final class UndeclaredLabel extends InvalidArgumentException
{
    /** @param  list<string>  $declared */
    public static function for(string $label, array $declared): self
    {
        return new self(sprintf('The Classification declares no label "%s". Declared: %s.', $label, implode(', ', $declared)));
    }
}
