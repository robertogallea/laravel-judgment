<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Contracts\Engine;

final class EngineNotConfigured extends LogicException
{
    public static function make(): self
    {
        return new self(sprintf('No Judgment Engine is configured. Set JUDGMENT_ENGINE or judgment.engine to a class implementing %s.', Engine::class));
    }
}
