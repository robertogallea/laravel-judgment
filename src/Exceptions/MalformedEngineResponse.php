<?php

namespace RobertoGallea\Judgment\Exceptions;

use RobertoGallea\Judgment\Judgment;
use RuntimeException;

final class MalformedEngineResponse extends RuntimeException
{
    public static function unanswered(Judgment $judgment, string $key): self
    {
        return new self(sprintf('The Engine did not answer Question "%s" on %s.', $key, $judgment::class));
    }

    public static function undeclared(Judgment $judgment, string $key): self
    {
        return new self(sprintf('The Engine answered Question "%s", which %s does not declare.', $key, $judgment::class));
    }
}
