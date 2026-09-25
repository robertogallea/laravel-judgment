<?php

namespace RobertoGallea\Judgment\Exceptions;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Outcome;
use RuntimeException;
use Throwable;

/**
 * An Assessment, or the Outcome decided from it, could not be recorded. Thrown
 * while judgment.persistence.required is on, so it is not acted on (ADR-0013),
 * and reported otherwise. The Assessment is kept so its paid-for answers are not lost.
 */
final class AssessmentNotRecorded extends RuntimeException
{
    private function __construct(string $message, public readonly Assessment $assessment, Throwable $previous)
    {
        parent::__construct($message, previous: $previous);
    }

    public static function for(Assessment $assessment, Throwable $previous): self
    {
        return new self(sprintf(
            'The Assessment of %s could not be recorded: %s',
            $assessment->judgment::class,
            $previous->getMessage(),
        ), $assessment, $previous);
    }

    public static function forOutcome(Assessment $assessment, Outcome $outcome, Throwable $previous): self
    {
        return new self(sprintf(
            'The Outcome %s::%s of %s could not be recorded: %s',
            $outcome::class,
            $outcome->name,
            $assessment->judgment::class,
            $previous->getMessage(),
        ), $assessment, $previous);
    }
}
