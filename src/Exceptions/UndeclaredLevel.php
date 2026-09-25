<?php

namespace RobertoGallea\Judgment\Exceptions;

use InvalidArgumentException;
use RobertoGallea\Judgment\Judgment;

final class UndeclaredLevel extends InvalidArgumentException
{
    public static function for(Judgment $judgment, string $key, int $levels): self
    {
        return new self(sprintf('The Rating "%s" on %s has %d levels, 0 to %d.', $key, $judgment::class, $levels, $levels - 1));
    }
}
