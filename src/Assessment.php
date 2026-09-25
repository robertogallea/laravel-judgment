<?php

namespace RobertoGallea\Judgment;

use BackedEnum;
use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Answers\ClassificationAnswer;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Answers\RatingAnswer;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Exceptions\InvalidDecision;
use RobertoGallea\Judgment\Exceptions\NoDefaultDecision;
use RobertoGallea\Judgment\Exceptions\UndeclaredQuestion;
use RobertoGallea\Judgment\Exceptions\WrongQuestionKind;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\Questions\Question;
use RobertoGallea\Judgment\Questions\Rating;

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

    public function likelihood(string|BackedEnum $key): LikelihoodAnswer
    {
        $answer = $this->answer($key, Likelihood::class);
        assert($answer instanceof LikelihoodAnswer);

        return $answer;
    }

    public function classification(string|BackedEnum $key): ClassificationAnswer
    {
        $answer = $this->answer($key, Classification::class);
        assert($answer instanceof ClassificationAnswer);

        return $answer;
    }

    public function rating(string|BackedEnum $key): RatingAnswer
    {
        $answer = $this->answer($key, Rating::class);
        assert($answer instanceof RatingAnswer);

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
    private function answer(string|BackedEnum $key, string $kind): Answer
    {
        $key = $key instanceof BackedEnum ? (string) $key->value : $key;
        $question = $this->questions[$key] ?? throw UndeclaredQuestion::for($this->judgment, $key, array_keys($this->questions));

        if (! $question instanceof $kind) {
            throw WrongQuestionKind::for($this->judgment, $key, $question, $kind);
        }

        return $this->answers[$key];
    }
}
