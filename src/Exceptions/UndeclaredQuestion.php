<?php

namespace RobertoGallea\Judgment\Exceptions;

use InvalidArgumentException;
use RobertoGallea\Judgment\Judgment;

final class UndeclaredQuestion extends InvalidArgumentException
{
    /** @param  list<string>  $declared */
    public static function for(Judgment $judgment, string $key, array $declared): self
    {
        return new self(sprintf(
            '%s declares no Question "%s". Declared: %s.',
            $judgment::class,
            $key,
            implode(', ', $declared),
        ));
    }
}
