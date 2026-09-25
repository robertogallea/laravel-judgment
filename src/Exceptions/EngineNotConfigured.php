<?php

namespace RobertoGallea\Judgment\Exceptions;

use LogicException;
use RobertoGallea\Judgment\Contracts\Engine;

final class EngineNotConfigured extends LogicException
{
    public static function noDefault(): self
    {
        return new self('No default Judgment Engine connection is configured. Set JUDGMENT_ENGINE or judgment.engine to a connection in judgment.engines.');
    }

    public static function connection(string $connection): self
    {
        return new self(sprintf('The Judgment Engine connection "%s" is not configured in judgment.engines.', $connection));
    }

    public static function missingKey(string $connection): self
    {
        return new self(sprintf('The Judgment Engine connection "%1$s" has no API key. Set TYPESAFE_API_KEY or judgment.engines.%1$s.key.', $connection));
    }

    public static function notAnEngine(string $connection, string $driver): self
    {
        return new self(sprintf('The driver "%s" of Judgment Engine connection "%s" is neither a registered driver nor a class implementing %s.', $driver, $connection, Engine::class));
    }
}
