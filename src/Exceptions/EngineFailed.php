<?php

namespace RobertoGallea\Judgment\Exceptions;

use RobertoGallea\Judgment\Judgment;
use RuntimeException;
use Throwable;

/** The Engine did not produce an Assessment; never to be mapped to a default Outcome. */
class EngineFailed extends RuntimeException
{
    public static function for(Judgment $judgment, Throwable $previous): self
    {
        return new self(sprintf('The Engine failed to assess %s: %s', $judgment::class, $previous->getMessage()), previous: $previous);
    }
}
