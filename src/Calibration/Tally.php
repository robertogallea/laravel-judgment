<?php

namespace RobertoGallea\Judgment\Calibration;

use RobertoGallea\Judgment\Contracts\Outcome;

/**
 * How a Decision's Outcomes compare with the expected ones over some cases. Accuracy is measured
 * over the Outcomes decided automatically, as an Outcome requiring Review leaves the case to a person.
 *
 * @internal
 */
final class Tally
{
    public int $cases = 0;

    public int $unassessed = 0;

    public int $sentToReview = 0;

    public int $automatic = 0;

    public int $correct = 0;

    /** @var array<string, int> expected Outcome value => cases, by value */
    public array $expected = [];

    public function decided(string $expected, Outcome $outcome): void
    {
        $this->cases++;
        $this->expected[$expected] = ($this->expected[$expected] ?? 0) + 1;
        ksort($this->expected);

        if ($outcome->requiresReview()) {
            $this->sentToReview++;

            return;
        }

        $this->automatic++;
        if ((string) $outcome->value === $expected) {
            $this->correct++;
        }
    }

    /** The share of assessed cases sent to Review, null when none was assessed. */
    public function reviewRate(): ?float
    {
        return $this->cases === 0 ? null : $this->sentToReview / $this->cases;
    }

    /** The share of automatic Outcomes that match the expected ones, null when none was automatic. */
    public function accuracy(): ?float
    {
        return $this->automatic === 0 ? null : $this->correct / $this->automatic;
    }
}
