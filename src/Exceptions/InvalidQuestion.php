<?php

namespace RobertoGallea\Judgment\Exceptions;

use InvalidArgumentException;
use RobertoGallea\Judgment\Judgment;

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

    public static function unquestionedLabels(): self
    {
        return new self('Each label of a Likelihood Set supplies its complete question: give a label => question map or a backed enum with a question() method.');
    }

    public static function noSetLabels(): self
    {
        return new self('A Likelihood Set needs at least 1 label; 0 given.');
    }

    public static function dottedSetLabel(string $label): self
    {
        return new self(sprintf('A Likelihood Set label cannot contain a dot; "%s" given.', $label));
    }

    public static function collidingSetKey(Judgment $judgment, string $key): self
    {
        return new self(sprintf('%s declares Question "%s" twice: once directly and once as a label of a Likelihood Set.', $judgment::class, $key));
    }
}
