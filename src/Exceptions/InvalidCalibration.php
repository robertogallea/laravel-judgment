<?php

namespace RobertoGallea\Judgment\Exceptions;

use InvalidArgumentException;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;

/** A Calibration run that cannot go on: an unknown class, a dataset it cannot read, or an expected Outcome no Decision can settle on. */
final class InvalidCalibration extends InvalidArgumentException
{
    public static function notA(string $kind, string $name): self
    {
        return new self(sprintf('%s is not a %s.', $name, $kind));
    }

    /** @param  class-string<Outcome>  $outcome */
    public static function notAnOutcome(string $expected, Decision $decision, string $outcome): self
    {
        $values = array_map(fn (Outcome $case) => (string) $case->value, $outcome::cases());

        return new self(sprintf(
            '"%s" is not an Outcome of %s: expected %s.',
            $expected,
            class_basename($decision),
            count($values) > 1 ? implode(', ', array_slice($values, 0, -1)).' or '.end($values) : implode('', $values),
        ));
    }

    public static function requiresReview(string $expected): self
    {
        return new self(sprintf('"%s" requires Review, so it cannot be the right Outcome of a case.', $expected));
    }

    public static function noCases(string $judgment): self
    {
        return new self(sprintf('No labelled cases to calibrate %s against.', $judgment));
    }

    public static function notEloquentSubject(string $judgment): self
    {
        return new self(sprintf('A dataset builds Eloquent Subjects, but %s is not constructed with an Eloquent model.', $judgment));
    }

    public static function unreadable(string $path): self
    {
        return new self(sprintf('The dataset %s is not a readable JSON list of labelled cases.', $path));
    }

    public static function invalidCase(int $index, string $reason): self
    {
        return new self(sprintf('Case %d of the dataset %s.', $index, $reason));
    }
}
