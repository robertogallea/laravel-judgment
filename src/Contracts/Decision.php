<?php

namespace RobertoGallea\Judgment\Contracts;

/**
 * The deterministic, application-owned mapping from an Assessment to an Outcome.
 *
 * Marker only: implement `__invoke(Assessment $assessment, YourJudgment $judgment): YourOutcome`.
 * The method is deliberately not declared here, because PHP would then forbid
 * narrowing the Judgment parameter, losing IDE help on the Subject and the
 * TypeError that stops a Decision being applied to another Judgment (ADR-0004).
 */
interface Decision {}
