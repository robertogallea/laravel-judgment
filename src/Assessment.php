<?php

namespace RobertoGallea\Judgment;

use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Exceptions\InvalidDecision;
use RobertoGallea\Judgment\Exceptions\NoDefaultDecision;
use RobertoGallea\Judgment\Exceptions\UndeclaredQuestion;
use RobertoGallea\Judgment\Exceptions\WrongQuestionKind;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\Questions\Question;

/** The recorded answers to a Judgment's Questions: probabilistic, immutable, free of consequence. */
final class Assessment
{
    /**
     * @param  array<string, Question>  $questions  as asked, so later changes to the Judgment cannot alter what was assessed
     * @param  array<string, Answer>  $answers
     */
    public function __construct(
        public readonly Judgment $judgment,
        private readonly array $questions,
        private readonly array $answers,
        public readonly Provenance $provenance,
    ) {}

    public function likelihood(string $key): LikelihoodAnswer
    {
        $this->declared($key, Likelihood::class);
        $answer = $this->answers[$key];
        assert($answer instanceof LikelihoodAnswer);

        return $answer;
    }

    /** Apply the Judgment's default Decision. */
    public function outcome(): Outcome
    {
        $decision = $this->judgment->decision() ?? throw NoDefaultDecision::for($this->judgment);

        return $this->decide(app($decision));
    }

    /** Apply the given Decision, e.g. stricter thresholds over the same Questions. */
    public function decide(Decision $decision): Outcome
    {
        if (! is_callable($decision)) {
            throw InvalidDecision::notInvokable($decision, $this->judgment);
        }

        return $decision($this, $this->judgment);
    }

    /** @param  class-string<Question>  $kind */
    private function declared(string $key, string $kind): void
    {
        $question = $this->questions[$key] ?? throw UndeclaredQuestion::for($this->judgment, $key, array_keys($this->questions));

        if (! $question instanceof $kind) {
            throw WrongQuestionKind::for($this->judgment, $key, $question, $kind);
        }
    }
}
