<?php

namespace RobertoGallea\Judgment\Answers;

/** The probability that a Likelihood's statement is true. It has no Confidence: the probability is the measure (ADR-0005). */
final class LikelihoodAnswer implements Answer
{
    public function __construct(private readonly float $probability) {}

    public function probability(): float
    {
        return $this->probability;
    }

    /** At or over the threshold. */
    public function above(float $threshold): bool
    {
        return $this->probability >= $threshold;
    }

    /** Strictly under the threshold. */
    public function below(float $threshold): bool
    {
        return $this->probability < $threshold;
    }

    /** In the half-open band [$from, $to). */
    public function between(float $from, float $to): bool
    {
        return $this->above($from) && $this->below($to);
    }
}
