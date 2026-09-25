<?php

namespace RobertoGallea\Judgment;

use BackedEnum;
use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Answers\ClassificationAnswer;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Answers\LikelihoodSetAnswer;
use RobertoGallea\Judgment\Answers\RatingAnswer;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Exceptions\ImpureDecision;
use RobertoGallea\Judgment\Exceptions\InvalidDecision;
use RobertoGallea\Judgment\Exceptions\NoDefaultDecision;
use RobertoGallea\Judgment\Exceptions\UndeclaredQuestion;
use RobertoGallea\Judgment\Exceptions\UnscriptedQuestion;
use RobertoGallea\Judgment\Exceptions\WrongQuestionKind;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\Questions\LikelihoodSet;
use RobertoGallea\Judgment\Questions\Question;
use RobertoGallea\Judgment\Questions\Rating;
use RobertoGallea\Judgment\Support\AssessmentRecorder;
use RobertoGallea\Judgment\Support\JudgmentLog;
use RobertoGallea\Judgment\Testing\FakeAssessment;

/** The recorded answers to a Judgment's Questions: probabilistic, immutable, free of consequence. */
final class Assessment
{
    /**
     * @param  array<string, Question|LikelihoodSet>  $questions  as declared, so later changes to the Judgment cannot alter what was assessed
     * @param  array<string, Answer>  $answers
     */
    public function __construct(
        public readonly Judgment $judgment,
        private readonly array $questions,
        private readonly array $answers,
        public readonly Provenance $provenance,
    ) {}

    /** Script an Assessment of the Judgment for testing its Decisions, without an Engine. */
    public static function fake(Judgment $judgment): FakeAssessment
    {
        return new FakeAssessment($judgment);
    }

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

    public function likelihoodSet(string|BackedEnum $key): LikelihoodSetAnswer
    {
        $answer = $this->answer($key, LikelihoodSet::class);
        assert($answer instanceof LikelihoodSetAnswer);

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

        $outcome = $decision($this, $this->judgment);

        // A scripted Assessment runs the Decision twice, so a test catches an impure one.
        if (FakeAssessment::scripted($this) && ($again = $decision($this, $this->judgment)) !== $outcome) {
            throw ImpureDecision::for($decision, $outcome, $again);
        }

        // Recorded first: an Outcome that must be recorded and cannot be is never logged as decided.
        app(AssessmentRecorder::class)->decided($this, $decision, $outcome);
        app(JudgmentLog::class)->decided($this, $decision, $outcome);

        return $outcome;
    }

    /**
     * What identifies this Assessment in logs: the Judgment and its Provenance.
     *
     * @return array{judgment: class-string<Judgment>, engine: string, model: string, request_id: ?string}
     */
    public function logContext(): array
    {
        return ['judgment' => $this->judgment::class, ...$this->provenance->logContext()];
    }

    /** @param  class-string<Question|LikelihoodSet>  $kind */
    private function answer(string|BackedEnum $key, string $kind): Answer
    {
        $key = $key instanceof BackedEnum ? (string) $key->value : $key;
        $question = $this->questions[$key] ?? throw UndeclaredQuestion::for($this->judgment, $key, array_keys($this->questions));

        if (! $question instanceof $kind) {
            throw WrongQuestionKind::for($this->judgment, $key, $question, $kind);
        }

        // Only a fake Assessment can leave a declared Question unanswered: Judge refuses such a response.
        return $this->answers[$key] ?? throw UnscriptedQuestion::for($this->judgment, $key);
    }
}
