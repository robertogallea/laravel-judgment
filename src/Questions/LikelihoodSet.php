<?php

namespace RobertoGallea\Judgment\Questions;

use BackedEnum;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Answers\LikelihoodSetAnswer;
use RobertoGallea\Judgment\Exceptions\InvalidQuestion;

/**
 * A group of independent Likelihoods over a closed set of labels, each with its
 * own complete question: no templating, so every question reads as written.
 */
final class LikelihoodSet
{
    /**
     * @param  non-empty-array<string, Likelihood>  $likelihoods  label => Likelihood
     * @param  class-string<BackedEnum>|null  $enum  the enum the labels come from, if any
     */
    private function __construct(
        private readonly array $likelihoods,
        private readonly ?string $enum,
    ) {}

    /**
     * The labels are a label => question map, or a backed enum whose values are
     * the labels, each case supplying its complete question from a question() method.
     *
     * @param  array<string, string>|list<string>|class-string<BackedEnum>  $labels  a list is refused: every label needs its question
     */
    public static function of(array|string $labels): self
    {
        if (is_array($labels) && $labels !== [] && array_is_list($labels)) {
            throw InvalidQuestion::unquestionedLabels();
        }

        $questions = is_string($labels) ? self::caseQuestions($labels) : $labels;

        $likelihoods = [];
        foreach ($questions as $label => $question) {
            $likelihoods[(string) $label] = Likelihood::that($question);
        }

        if ($likelihoods === []) {
            throw InvalidQuestion::noSetLabels();
        }

        return new self($likelihoods, is_string($labels) ? $labels : null);
    }

    /** @return non-empty-array<string, Likelihood> label => Likelihood, each asked independently */
    public function likelihoods(): array
    {
        return $this->likelihoods;
    }

    /** @param  non-empty-array<string, LikelihoodAnswer>  $answers  label => answer, as the Engine answered each Likelihood */
    public function answer(array $answers): LikelihoodSetAnswer
    {
        return new LikelihoodSetAnswer($answers, $this->enum);
    }

    /** @return array<string, string> label => question */
    private static function caseQuestions(string $enum): array
    {
        if (! is_subclass_of($enum, BackedEnum::class) || ! method_exists($enum, 'question')) {
            throw InvalidQuestion::unquestionedLabels();
        }

        $questions = [];
        foreach ($enum::cases() as $case) {
            $questions[(string) $case->value] = $case->question();
        }

        return $questions;
    }
}
