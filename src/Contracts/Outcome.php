<?php

namespace RobertoGallea\Judgment\Contracts;

/**
 * A member of an application-defined, closed set of Outcomes. Implement it
 * on a backed enum (ADR-0011): persistence, Resolution and Calibration all
 * need a stable value and a closed list to choose from. Extending
 * BackedEnum means only a backed enum can implement it.
 */
interface Outcome extends \BackedEnum
{
    /** Whether a person must decide before the Outcome's Action is taken. */
    public function requiresReview(): bool;
}
