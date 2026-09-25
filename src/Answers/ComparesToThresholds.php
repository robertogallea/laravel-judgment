<?php

namespace RobertoGallea\Judgment\Answers;

/** Threshold helpers over an answer's single measure, for readable Decisions. */
trait ComparesToThresholds
{
    /** The value thresholds are compared against. */
    abstract protected function measure(): float;

    /** At or over the threshold. */
    public function above(float $threshold): bool
    {
        return $this->measure() >= $threshold;
    }

    /** Strictly under the threshold. */
    public function below(float $threshold): bool
    {
        return $this->measure() < $threshold;
    }

    /** In the half-open band [$from, $to). */
    public function between(float $from, float $to): bool
    {
        return $this->above($from) && $this->below($to);
    }
}
