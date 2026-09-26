<?php

namespace RobertoGallea\Judgment\Exceptions;

use RobertoGallea\Judgment\Judgment;
use RuntimeException;
use Throwable;

/**
 * An Unassessed attempt could not be recorded. Only ever reported, never thrown: an Unassessed
 * result cannot be acted on, so refusing it protects nothing (ADR-0014). The Engine's failure is kept.
 */
final class UnassessedNotRecorded extends RuntimeException
{
    private function __construct(string $message, public readonly Judgment $judgment, public readonly EngineFailed $failure, Throwable $previous)
    {
        parent::__construct($message, previous: $previous);
    }

    public static function for(Judgment $judgment, EngineFailed $failure, Throwable $previous): self
    {
        return new self(sprintf(
            'The Unassessed attempt of %s could not be recorded: %s',
            $judgment::class,
            $previous->getMessage(),
        ), $judgment, $failure, $previous);
    }
}
