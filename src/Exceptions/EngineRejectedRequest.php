<?php

namespace RobertoGallea\Judgment\Exceptions;

/** The Engine refused the request as invalid, such as Evidence or Questions beyond its limits; retrying will not help. */
final class EngineRejectedRequest extends EngineFailed
{
    public static function respond(int $status, ?string $requestId, string $reason): self
    {
        return new self(self::describe('The Engine rejected the request as invalid', $status, $requestId, $reason));
    }

    public static function tooManyLabels(string $connection, string $key, int $labels, int $max): self
    {
        return new self(sprintf('The Judgment Engine connection "%s" answers a Classification over at most %d labels, but "%s" has %d. Narrow the labels or ask another connection.', $connection, $max, $key, $labels));
    }
}
