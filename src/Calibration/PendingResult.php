<?php

namespace RobertoGallea\Judgment\Calibration;

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\Questions\LikelihoodSet;
use RobertoGallea\Judgment\Questions\Rating;

/**
 * The results so far of the cases sharing one Calibration Identity: question set, model version,
 * Decision version and Evidence language, which are never mixed (ADR-0008).
 *
 * @internal
 */
final class PendingResult
{
    public readonly Tally $tally;

    /** @var array<string, array<int, Tally>> per Question, then per tenth of its measure (0 for 0.0–0.1), the cases whose answer falls there */
    public array $bands = [];

    /** @param  class-string<Decision>  $decision */
    public function __construct(
        public readonly string $questions,
        public readonly string $model,
        public readonly string $decision,
        public readonly ?string $version,
        public readonly ?string $language,
    ) {
        $this->tally = new Tally;
    }

    public function decided(Assessment $assessment, string $expected, Outcome $outcome): void
    {
        $this->tally->decided($expected, $outcome);

        foreach (self::measures($assessment) as $question => $measure) {
            $band = (int) min(9, floor(round($measure * 10, 9)));
            ($this->bands[$question][$band] ??= new Tally)->decided($expected, $outcome);
            ksort($this->bands[$question]);
        }
    }

    /**
     * What each answer is banded by: a Likelihood's probability, each probability of a
     * Likelihood Set (under "set.label"), and the Confidence of a Classification or Rating.
     *
     * @return array<string, float>
     */
    private static function measures(Assessment $assessment): array
    {
        $measures = [];
        foreach ($assessment->judgment->questions() as $key => $question) {
            $key = (string) $key;
            match (true) {
                $question instanceof Likelihood => $measures[$key] = $assessment->likelihood($key)->probability(),
                $question instanceof Classification => $measures[$key] = $assessment->classification($key)->confidence(),
                $question instanceof Rating => $measures[$key] = $assessment->rating($key)->confidence(),
                $question instanceof LikelihoodSet => array_map(function (string $label) use ($assessment, $key, &$measures) {
                    $measures["$key.$label"] = $assessment->likelihoodSet($key)->of($label)->probability();
                }, array_map(strval(...), array_keys($question->likelihoods()))),
                default => null,
            };
        }

        return $measures;
    }

    public function unassessed(): void
    {
        $this->tally->unassessed++;
    }

    /** The results so far, as a value that no later case changes. */
    public function result(): CalibrationResult
    {
        $bands = [];
        foreach ($this->bands as $question => $tallies) {
            foreach ($tallies as $band => $tally) {
                $bands[] = new CalibrationBand((string) $question, $band / 10, ($band + 1) / 10, $tally->cases, $tally->expected, $tally->sentToReview, $tally->automatic, $tally->correct);
            }
        }

        return new CalibrationResult(
            new CalibrationIdentity($this->questions, $this->model, $this->decision, $this->version, $this->language),
            $this->tally->cases + $this->tally->unassessed,
            $this->tally->unassessed,
            $this->tally->sentToReview,
            $this->tally->automatic,
            $this->tally->correct,
            $bands,
        );
    }
}
