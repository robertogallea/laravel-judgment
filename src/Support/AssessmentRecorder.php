<?php

namespace RobertoGallea\Judgment\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use LogicException;
use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Answers\ClassificationAnswer;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Answers\LikelihoodSetAnswer;
use RobertoGallea\Judgment\Answers\RatingAnswer;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Events\AssessmentAwaitingReview;
use RobertoGallea\Judgment\Exceptions\UnrebuildableAssessment;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Provenance;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\Questions\LikelihoodSet;
use RobertoGallea\Judgment\Questions\Question;
use RobertoGallea\Judgment\Questions\Rating;
use RobertoGallea\Judgment\UntrustedText;
use WeakMap;

/**
 * Stores each Assessment the Judge produces as an AssessmentRecord.
 *
 * @internal
 */
final class AssessmentRecorder
{
    /**
     * Each Assessment a Judge produced, with its record, or false when it was not recorded;
     * held weakly so both can still be freed. Deciding one of these records its Outcome and may start Review.
     *
     * @var WeakMap<Assessment, AssessmentRecord|false>
     */
    private WeakMap $records;

    /** @var WeakMap<Assessment, Outcome> each of the Judge's Assessments that entered Review, with the Outcome that required it */
    private WeakMap $awaiting;

    public function __construct(private readonly Config $config, private readonly Dispatcher $events)
    {
        $this->records = new WeakMap;
        $this->awaiting = new WeakMap;
    }

    /**
     * @param  array<string, Question|LikelihoodSet>  $questions  as declared
     * @param  array<string, Answer>  $answers
     * @param  array<string, mixed>  $evidence  as the Engine was asked
     * @return AssessmentRecord|null null when judgment.persistence.enabled is off
     */
    public function record(Assessment $assessment, array $questions, array $answers, array $evidence): ?AssessmentRecord
    {
        if (! $this->config->get('judgment.persistence.enabled')) {
            $this->link($assessment, null);

            return null;
        }

        $json = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);

        $record = new AssessmentRecord([
            'judgment' => $assessment->judgment::class,
            'evidence_fingerprint' => hash('sha256', $json),
            'evidence' => $this->config->get('judgment.persistence.evidence') ? json_decode($json, true, flags: JSON_THROW_ON_ERROR) : null,
            'untrusted_paths' => $this->untrustedPaths($evidence),
            'language' => $assessment->judgment->language(),
            'questions_fingerprint' => self::fingerprint($questions),
            'answers' => array_map($this->serialise(...), $answers),
            'engine' => $assessment->provenance->engine,
            'model' => $assessment->provenance->model,
            'request_id' => $assessment->provenance->requestId,
            'provenance_details' => $assessment->provenance->details,
        ]);

        // An unsaved Subject has no key to link it by.
        $subject = $assessment->judgment->subject();
        if ($subject?->exists) {
            $record->subject()->associate($subject);
        }
        $record->save();

        return $this->records[$assessment] = $record;
    }

    /**
     * Link an Assessment rebuilt from its record, so deciding it records the Outcome there,
     * or one a Judge produced without recording it, so deciding it can still announce Review.
     */
    public function link(Assessment $assessment, ?AssessmentRecord $record): Assessment
    {
        $this->records[$assessment] = $record ?? false;

        return $assessment;
    }

    /**
     * Store the Decision last applied to a Judge's Assessment, its version if it declares one,
     * and its Outcome; an Outcome requiring Review puts the record in Review, announced once.
     * From then on the record keeps that Outcome, for the reviewer to resolve.
     */
    public function decided(Assessment $assessment, Decision $decision, Outcome $outcome): void
    {
        if (! isset($this->records[$assessment])) {
            return;
        }

        $record = $this->records[$assessment] ?: null;
        $entersReview = $outcome->requiresReview() && ! isset($this->awaiting[$assessment]);
        if ($record !== null) {
            $entersReview = $this->write($record, $decision, $outcome, $entersReview);
        }
        if (! $entersReview) {
            return;
        }

        $this->awaiting[$assessment] = $outcome;
        $this->events->dispatch(new AssessmentAwaitingReview(
            $assessment->judgment, $assessment, $outcome, $record, $record->review_requested_at ?? now()->toImmutable(),
        ));
    }

    /** The Outcome that put one of the Judge's Assessments in Review, if a Decision applied here did. */
    public function awaitingReview(Assessment $assessment): ?Outcome
    {
        return $this->awaiting[$assessment] ?? null;
    }

    /**
     * Write the Decision to a record not in Review, in one conditional update so that of two
     * copies of a record decided at once only one puts it in Review.
     *
     * @return bool whether the record entered Review
     */
    private function write(AssessmentRecord $record, Decision $decision, Outcome $outcome, bool $review): bool
    {
        $written = AssessmentRecord::query()->whereKey($record->getKey())->whereNull('review_requested_at')->update([
            'decision' => $decision::class,
            'decision_version' => method_exists($decision, 'version') ? (string) $decision->version() : null,
            'outcome_type' => $outcome::class,
            'outcome' => (string) $outcome->value,
            ...$review ? ['review_requested_at' => now()->toImmutable()] : [],
        ]);
        $record->refresh();

        return $review && $written === 1;
    }

    /**
     * The Assessment a record stores, over the Judgment given. It is not linked
     * to the record, so re-deciding it never overwrites the recorded Outcome.
     */
    public function rebuild(AssessmentRecord $record, Judgment $judgment): Assessment
    {
        $questions = $judgment->questions();

        match (true) {
            $judgment::class !== $record->judgment => throw UnrebuildableAssessment::otherJudgment($record, $judgment),
            self::fingerprint($questions) !== $record->questions_fingerprint => throw UnrebuildableAssessment::changedQuestions($record),
            default => null,
        };

        $answers = [];
        foreach ($questions as $key => $question) {
            $answers[$key] = $this->deserialise($question, $record->answers[$key]);
        }

        return new Assessment(
            $judgment,
            $questions,
            $answers,
            new Provenance($record->engine, $record->model, $record->request_id, $record->provenance_details),
        );
    }

    /**
     * Identifies the question set by what the Engine reads: each key, kind,
     * instructions and criteria (labels, levels, meanings), so thresholds
     * calibrated against one wording are never mixed with another (ADR-0008).
     *
     * @param  array<string, Question|LikelihoodSet>  $questions
     */
    public static function fingerprint(array $questions): string
    {
        $described = array_map(fn (Question|LikelihoodSet $question) => match (true) {
            $question instanceof LikelihoodSet => ['likelihood_set', array_map(self::describe(...), $question->likelihoods())],
            default => self::describe($question),
        }, $questions);
        ksort($described);

        return hash('sha256', json_encode($described, JSON_THROW_ON_ERROR));
    }

    /** @return array{string, string, mixed} */
    private static function describe(Question $question): array
    {
        return [
            match (true) {
                $question instanceof Likelihood => 'likelihood',
                $question instanceof Classification => 'classification',
                $question instanceof Rating => 'rating',
                default => $question::class,
            },
            $question->instructions,
            method_exists($question, 'criteria') ? $question->criteria() : null,
        ];
    }

    /**
     * The dotted path of every untrusted text in the Evidence.
     *
     * @param  array<array-key, mixed>  $evidence
     * @return list<string>
     */
    private function untrustedPaths(array $evidence, string $prefix = ''): array
    {
        $paths = [];
        foreach ($evidence as $key => $value) {
            $paths = [...$paths, ...match (true) {
                $value instanceof UntrustedText => [$prefix.$key],
                is_array($value) => $this->untrustedPaths($value, "$prefix$key."),
                default => [],
            }];
        }

        return $paths;
    }

    private function deserialise(Question|LikelihoodSet $question, mixed $answer): Answer
    {
        return match (true) {
            $question instanceof Likelihood => new LikelihoodAnswer((float) $answer),
            $question instanceof Classification => $question->answer($this->probabilities($answer)),
            $question instanceof Rating => $question->answer(array_values($this->probabilities($answer))),
            $question instanceof LikelihoodSet => $this->likelihoodSet($question, $answer),
            default => throw new LogicException(sprintf('A Question of kind %s cannot be rebuilt.', $question::class)),
        };
    }

    /**
     * The recorded probabilities as floats: JSON stores a whole probability such as 1.0 as 1.
     *
     * @return non-empty-array<array-key, float>
     */
    private function probabilities(mixed $recorded): array
    {
        if (! is_array($recorded) || $recorded === []) {
            throw new LogicException('A recorded Classification or Rating answer holds no probabilities.');
        }

        return array_map(floatval(...), $recorded);
    }

    /** @param  array<string, float>  $probabilities  label => probability, as recorded */
    private function likelihoodSet(LikelihoodSet $set, array $probabilities): LikelihoodSetAnswer
    {
        $likelihoods = [];
        foreach (array_keys($set->likelihoods()) as $label) {
            $likelihoods[$label] = new LikelihoodAnswer((float) $probabilities[$label]);
        }

        return $set->answer($likelihoods);
    }

    /** @return float|array<string|int, float> */
    private function serialise(Answer $answer): float|array
    {
        return match (true) {
            $answer instanceof LikelihoodAnswer => $answer->probability(),
            $answer instanceof ClassificationAnswer, $answer instanceof RatingAnswer => $answer->probabilities(),
            $answer instanceof LikelihoodSetAnswer => array_map(fn (LikelihoodAnswer $likelihood) => $likelihood->probability(), $answer->likelihoods()),
            default => throw new LogicException(sprintf('An answer of kind %s cannot be recorded.', $answer::class)),
        };
    }
}
