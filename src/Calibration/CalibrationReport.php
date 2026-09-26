<?php

namespace RobertoGallea\Judgment\Calibration;

/** What a Calibration run found: one result per Calibration Identity, and the Resolutions it had to skip. */
final class CalibrationReport
{
    /**
     * @param  list<CalibrationResult>  $results
     * @param  int  $skipped  Resolutions whose Subject no longer exists
     */
    public function __construct(
        public readonly array $results,
        public readonly int $skipped,
    ) {}
}
