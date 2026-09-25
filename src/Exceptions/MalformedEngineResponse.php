<?php

namespace RobertoGallea\Judgment\Exceptions;

use RobertoGallea\Judgment\Judgment;

final class MalformedEngineResponse extends EngineFailed
{
    public static function unanswered(Judgment $judgment, string $key): self
    {
        return new self(sprintf('The Engine did not answer Question "%s" on %s.', $key, $judgment::class));
    }

    public static function undeclared(Judgment $judgment, string $key): self
    {
        return new self(sprintf('The Engine answered Question "%s", which %s does not declare.', $key, $judgment::class));
    }

    public static function unreadable(?string $requestId): self
    {
        return new self(sprintf('The Engine response is unreadable%s: it has no answers or no model.', self::request($requestId)));
    }

    public static function unasked(string $key, ?string $requestId): self
    {
        return new self(sprintf('The Engine answered Question "%s", which was not asked%s.', $key, self::request($requestId)));
    }

    public static function unreadableAnswer(string $key, string $expected, ?string $requestId): self
    {
        return new self(sprintf('The Engine answered Question "%s" unreadably%s: expected a %s answer.', $key, self::request($requestId), $expected));
    }

    private static function request(?string $requestId): string
    {
        return $requestId === null ? '' : " (request $requestId)";
    }
}
