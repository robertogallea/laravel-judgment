<?php

namespace RobertoGallea\Judgment\Calibration;

use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Judgment;

/** One case to calibrate against: a Judgment, the Evidence to ask over and the Outcome a person says is right. */
final class LabelledCase
{
    /**
     * @internal build a case with of()
     *
     * @param  array<string, mixed>  $evidence
     * @param  Outcome|string  $expected  the Outcome, or its value when read from a dataset or a Resolution
     */
    public function __construct(
        public readonly Judgment $judgment,
        public readonly array $evidence,
        public readonly Outcome|string $expected,
        public readonly ?string $language,
    ) {}

    /** A case asked over the Judgment's current Evidence, labelled with the Outcome a person says is right. */
    public static function of(Judgment $judgment, Outcome $expected): self
    {
        return self::labelled($judgment, $expected);
    }

    /** @internal */
    public static function labelled(Judgment $judgment, Outcome|string $expected): self
    {
        return new self($judgment, $judgment->evidence(), $expected, $judgment->language());
    }
}
