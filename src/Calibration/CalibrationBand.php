<?php

namespace RobertoGallea\Judgment\Calibration;

/**
 * The assessed cases whose answer to one Question falls in a tenth of its measure: a Likelihood's
 * probability, each probability of a Likelihood Set (under "set.label"), or the Confidence of a
 * Classification or Rating. A band includes its lower bound, and the last one also 1.0.
 */
final class CalibrationBand
{
    /** @param  array<string, int>  $expected  expected Outcome value => cases, by value */
    public function __construct(
        public readonly string $question,
        public readonly float $from,
        public readonly float $to,
        public readonly int $cases,
        public readonly array $expected,
        public readonly int $sentToReview,
        public readonly int $automatic,
        public readonly int $correct,
    ) {}

    /** The share of the band's cases sent to Review, null when it has none. */
    public function reviewRate(): ?float
    {
        return $this->cases === 0 ? null : $this->sentToReview / $this->cases;
    }

    /** The share of the band's automatic Outcomes that match the expected ones, null when none was automatic. */
    public function accuracy(): ?float
    {
        return $this->automatic === 0 ? null : $this->correct / $this->automatic;
    }
}
