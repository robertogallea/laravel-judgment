<?php

namespace RobertoGallea\Judgment\Exceptions;

/** The Engine refused the configured credentials. */
final class EngineUnauthorized extends EngineFailed
{
    public static function respond(int $status, ?string $requestId, string $reason): self
    {
        return new self(self::describe('The Engine refused the credentials', $status, $requestId, $reason));
    }
}
