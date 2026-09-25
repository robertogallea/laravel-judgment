<?php

namespace RobertoGallea\Judgment\Answers;

/**
 * The package's own Confidence measure, independent of any Engine (ADR-0005):
 * how decisively the top option beats the runner-up.
 *
 * @internal
 */
final class Confidence
{
    /** @param  non-empty-array<float>  $probabilities */
    public static function of(array $probabilities): float
    {
        rsort($probabilities);

        return $probabilities[0] - ($probabilities[1] ?? 0.0);
    }
}
