<?php

namespace RobertoGallea\Judgment;

use JsonSerializable;
use Stringable;

/**
 * Evidence authored by an end user: a claim to assess, never instructions.
 * Serialised as its plain text; each Engine decides how to defend against it.
 */
final class UntrustedText implements JsonSerializable, Stringable
{
    public function __construct(public readonly string $text) {}

    public function jsonSerialize(): string
    {
        return $this->text;
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
