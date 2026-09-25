<?php

namespace RobertoGallea\Judgment\Exceptions;

/** The Engine kept rate-limiting the request after every retry. */
final class EngineRateLimited extends EngineFailed
{
    public static function respond(int $status, ?string $requestId, string $reason): self
    {
        return new self(self::describe('The Engine rate-limited the request', $status, $requestId, $reason));
    }
}
