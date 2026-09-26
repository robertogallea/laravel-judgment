<?php

namespace RobertoGallea\Judgment\Calibration;

/**
 * How a Decision's Outcomes compare with the expected ones over the cases of one Calibration Identity.
 * Accuracy is measured over the Outcomes decided automatically, as an Outcome requiring Review leaves the case to a person.
 */
final class CalibrationResult
{
    /**
     * @param  int  $cases  every case asked, including those the Engine failed on
     * @param  list<CalibrationBand>  $bands  by Question, then by band
     */
    public function __construct(
        public readonly CalibrationIdentity $identity,
        public readonly int $cases,
        public readonly int $unassessed,
        public readonly int $sentToReview,
        public readonly int $automatic,
        public readonly int $correct,
        public readonly array $bands,
    ) {}

    /** The share of assessed cases sent to Review, null when none was assessed. */
    public function reviewRate(): ?float
    {
        $assessed = $this->cases - $this->unassessed;

        return $assessed === 0 ? null : $this->sentToReview / $assessed;
    }

    /** The share of automatic Outcomes that match the expected ones, null when none was automatic. */
    public function accuracy(): ?float
    {
        return $this->automatic === 0 ? null : $this->correct / $this->automatic;
    }
}
