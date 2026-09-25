<?php

namespace RobertoGallea\Judgment\Exceptions;

/** The Engine stayed overloaded after every retry. */
final class EngineOverloaded extends EngineFailed
{
    public static function respond(int $status, ?string $requestId, string $reason): self
    {
        return new self(self::describe('The Engine is overloaded', $status, $requestId, $reason));
    }
}
