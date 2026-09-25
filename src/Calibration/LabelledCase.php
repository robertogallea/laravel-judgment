<?php

namespace RobertoGallea\Judgment\Calibration;

use RobertoGallea\Judgment\Judgment;

/**
 * One case to calibrate against: a Judgment, the Evidence to ask over and the Outcome a person says is right.
 *
 * @internal
 */
final class LabelledCase
{
    /** @param  array<string, mixed>  $evidence */
    public function __construct(
        public readonly Judgment $judgment,
        public readonly array $evidence,
        public readonly string $expected,
        public readonly ?string $language,
    ) {}

    public static function of(Judgment $judgment, string $expected): self
    {
        return new self($judgment, $judgment->evidence(), $expected, $judgment->language());
    }
}
