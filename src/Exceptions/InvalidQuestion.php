<?php

namespace RobertoGallea\Judgment\Exceptions;

use InvalidArgumentException;

/** A Question declared in a way no Engine could answer, caught where it is written. */
final class InvalidQuestion extends InvalidArgumentException
{
    public static function noInstructions(): self
    {
        return new self('A Question needs instructions: ask it as a complete question about the Evidence.');
    }

    public static function notLabels(string $given): self
    {
        return new self(sprintf('Classification labels must be a list, a label => description map or a backed enum class; %s given.', $given));
    }

    public static function tooFewLabels(int $given): self
    {
        return new self(sprintf('A Classification needs at least 2 labels; %d given.', $given));
    }

    public static function tooManyLabels(int $given): self
    {
        return new self(sprintf('A Classification accepts at most 255 labels; %d given.', $given));
    }

    public static function repeatedLabel(string $label): self
    {
        return new self(sprintf('A Classification\'s labels must be distinct; "%s" is repeated.', $label));
    }

    public static function levelsOutOfRange(int $given): self
    {
        return new self(sprintf('A Rating needs 2 to 10 levels; %d given.', $given));
    }

    public static function noLabels(string $key): self
    {
        return new self(sprintf('Classification "%s" declares no labels; call labels() on it.', $key));
    }

    public static function noLevels(string $key): self
    {
        return new self(sprintf('Rating "%s" declares no levels; call levels() on it.', $key));
    }
}
