<?php

namespace RobertoGallea\Judgment\Exceptions;

use InvalidArgumentException;

final class UndeclaredLabel extends InvalidArgumentException
{
    /** @param  list<string>  $declared */
    public static function for(string $label, array $declared, string $kind = 'Classification'): self
    {
        return new self(sprintf('The %s declares no label "%s". Declared: %s.', $kind, $label, implode(', ', $declared)));
    }
}
