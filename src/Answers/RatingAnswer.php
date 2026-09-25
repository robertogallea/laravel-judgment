<?php

namespace RobertoGallea\Judgment\Answers;

/** The probability of each level of a Rating, lowest first. Thresholds compare against expected(). */
final class RatingAnswer implements Answer
{
    use ComparesToThresholds;

    /** @param  non-empty-list<float>  $probabilities */
    public function __construct(private readonly array $probabilities) {}

    /** The most probable level, counting from zero. */
    public function level(): int
    {
        return (int) array_search(max($this->probabilities), $this->probabilities, true);
    }

    /** The probability-weighted mean level: unlike level(), it shows a split between neighbouring levels. */
    public function expected(): float
    {
        $expected = 0.0;
        foreach ($this->probabilities as $level => $probability) {
            $expected += $level * $probability;
        }

        return $expected;
    }

    /** @return list<float> a probability per level, lowest first */
    public function probabilities(): array
    {
        return $this->probabilities;
    }

    /** How decisively the top level beats the runner-up: their probability margin (ADR-0005). */
    public function confidence(): float
    {
        return Confidence::of($this->probabilities);
    }

    protected function measure(): float
    {
        return $this->expected();
    }
}
