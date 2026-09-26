<?php

namespace RobertoGallea\Judgment\Calibration;

use RobertoGallea\Judgment\Contracts\Decision;

/**
 * What a Calibration result belongs to: the Questions fingerprint, model version, Decision and its
 * version, and Evidence language. Results of different identities are never mixed (ADR-0008).
 */
final class CalibrationIdentity
{
    /** @param  class-string<Decision>  $decision */
    public function __construct(
        public readonly string $questions,
        public readonly string $model,
        public readonly string $decision,
        public readonly ?string $decisionVersion,
        public readonly ?string $language,
    ) {}
}
