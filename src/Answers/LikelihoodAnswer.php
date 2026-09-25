<?php

namespace RobertoGallea\Judgment\Answers;

/** The probability that a Likelihood's statement is true. It has no Confidence: the probability is the measure (ADR-0005). */
final class LikelihoodAnswer implements Answer
{
    use ComparesToThresholds;

    public function __construct(private readonly float $probability) {}

    public function probability(): float
    {
        return $this->probability;
    }

    protected function measure(): float
    {
        return $this->probability;
    }
}
