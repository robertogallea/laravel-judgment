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
        return new self(sprintf('The Judgment Engine connection "%1$s" has no API key. Set judgment.engines.%1$s.key (TYPESAFE_API_KEY on the shipped jev connection), or set require_key to false for a server that needs none.', $connection));
    }

    public static function invalidMaxLabels(string $connection): self
    {
        return new self(sprintf('The Judgment Engine connection "%1$s" has an invalid max_labels. Set judgment.engines.%1$s.max_labels to 2 or more, or to null for the package limit.', $connection));
    }

    public static function missingModel(string $connection): self
    {
        return new self(sprintf('The Judgment Engine connection "%1$s" has no model. Set the model it answers with, pinned to an exact version where the Engine has them, in judgment.engines.%1$s.model.', $connection));
    }

    public static function notAnEngine(string $connection, string $driver): self
    {
        return new self(sprintf('The driver "%s" of Judgment Engine connection "%s" is neither a registered driver nor a class implementing %s.', $driver, $connection, Engine::class));
    }
}
