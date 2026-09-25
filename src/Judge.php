<?php

namespace RobertoGallea\Judgment;

use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Bus\PendingDispatch;
use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Judge as JudgeContract;
use RobertoGallea\Judgment\Events\AssessmentCompleted;
use RobertoGallea\Judgment\Events\AssessmentFailed;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Exceptions\InvalidQuestion;
use RobertoGallea\Judgment\Exceptions\MalformedEngineResponse;
use RobertoGallea\Judgment\Jobs\AssessJudgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Questions\LikelihoodSet;
use RobertoGallea\Judgment\Questions\Question;
use RobertoGallea\Judgment\Support\AssessmentCache;
use RobertoGallea\Judgment\Support\AssessmentRecorder;
use RobertoGallea\Judgment\Support\JudgmentLog;

class Judge implements JudgeContract
{
    public function __construct(private readonly Container $container) {}

    public function assess(Judgment $judgment): Assessment|Unassessed
    {
        $questions = $judgment->questions();
        $request = new EngineRequest($this->expand($judgment, $questions), $judgment->evidence());

        // The default Engine goes through its container binding, which an application may replace.
        $engines = $this->container->make(EngineManager::class);
        $named = $judgment->engine();
        $engine = $named === null ? $this->container->make(Engine::class) : $engines->engine($named);
        $connection = $named ?? $engines->defaultConnection();
        $cache = $this->container->make(AssessmentCache::class);
        if ($cached = $cache->get($judgment, $questions, $request->evidence, $engine, $connection)) {
            [$answers, $provenance, $originalId] = $cached;
            $assessment = new Assessment($judgment, $questions, $answers, $provenance);
            $this->complete($assessment, $questions, $answers, $request->evidence, $originalId);
            $this->container->make(JudgmentLog::class)->assessedFromCache($assessment, $originalId);

            return $assessment;
        }

        $response = null;
        try {
            $response = $engine->answer($request);
            $this->ensureEveryQuestionIsAnswered($judgment, $request, $response);
        } catch (EngineFailed $e) {
            return $this->fail($judgment, $e, $response);
        } catch (Exception $e) {
            return $this->fail($judgment, EngineFailed::for($judgment, $e), $response);
        }

        $answers = $this->regroup($questions, $response->answers);
        $assessment = new Assessment($judgment, $questions, $answers, $response->provenance);
        $record = $this->complete($assessment, $questions, $answers, $request->evidence);
        $this->container->make(JudgmentLog::class)->assessed($assessment);
        $cache->put($assessment, $questions, $answers, $request->evidence, $engine, $connection, $record?->id);

        return $assessment;
    }

    /**
     * Ask the Engine the Judgment's Questions over the Evidence given, without caching, recording,
     * logging or announcing the Assessment, for Calibration.
     *
     * @internal
     *
     * @param  array<string, mixed>  $evidence
     *
     * @throws EngineFailed when the Engine fails
     */
    public function ask(Judgment $judgment, Engine $engine, array $evidence): Assessment
    {
        $questions = $judgment->questions();
        $request = new EngineRequest($this->expand($judgment, $questions), $evidence);

        try {
            $response = $engine->answer($request);
            $this->ensureEveryQuestionIsAnswered($judgment, $request, $response);
        } catch (EngineFailed $e) {
            throw $e;
        } catch (Exception $e) {
            throw EngineFailed::for($judgment, $e);
        }

        return new Assessment($judgment, $questions, $this->regroup($questions, $response->answers), $response->provenance);
    }

    /**
     * Record the Assessment and announce it.
     *
     * @param  array<string, Question|LikelihoodSet>  $questions
     * @param  array<string, Answer>  $answers
     * @param  array<string, mixed>  $evidence  as the Engine was asked
     * @param  int|null  $cachedFrom  for a cache hit, the id of the original record
     */
    private function complete(Assessment $assessment, array $questions, array $answers, array $evidence, ?int $cachedFrom = null): ?AssessmentRecord
    {
        $record = $this->container->make(AssessmentRecorder::class)->record($assessment, $questions, $answers, $evidence, $cachedFrom);

        $this->container->make(Dispatcher::class)->dispatch(new AssessmentCompleted($assessment->judgment, $assessment, $record));

        return $record;
    }

    public function dispatch(Judgment $judgment): PendingDispatch
    {
        return AssessJudgment::dispatch($judgment);
    }

    /** Announce and log the failure, then throw it or end Unassessed, as configured. */
    private function fail(Judgment $judgment, EngineFailed $exception, ?EngineResponse $response): Unassessed
    {
        $this->container->make(Dispatcher::class)->dispatch(new AssessmentFailed($judgment, $exception));
        $this->container->make(JudgmentLog::class)->unassessed($judgment, $exception, $response?->provenance);

        if ($this->container->make('config')->get('judgment.throw_on_failure')) {
            throw $exception;
        }

        return new Unassessed($judgment, $exception);
    }

    /**
     * Gather each Likelihood Set's answers back into one answer under the set's key.
     *
     * @param  array<string, Question|LikelihoodSet>  $questions
     * @param  array<string, Answer>  $answers  keyed as the expanded request
     * @return array<string, Answer>
     */
    private function regroup(array $questions, array $answers): array
    {
        $regrouped = [];
        foreach ($questions as $key => $question) {
            if (! $question instanceof LikelihoodSet) {
                $regrouped[$key] = $answers[$key];

                continue;
            }

            $likelihoods = [];
            foreach (array_keys($question->likelihoods()) as $label) {
                $answer = $answers["$key.$label"];
                assert($answer instanceof LikelihoodAnswer);
                $likelihoods[$label] = $answer;
            }
            $regrouped[$key] = $question->answer($likelihoods);
        }

        return $regrouped;
    }

    /**
     * Expand each Likelihood Set into its independent Likelihoods under dotted keys (`flags.hate`),
     * so Engines only ever answer single Questions.
     *
     * @param  array<string, Question|LikelihoodSet>  $questions
     * @return array<string, Question>
     */
    private function expand(Judgment $judgment, array $questions): array
    {
        $expanded = [];
        foreach ($questions as $key => $question) {
            $asked = $question instanceof LikelihoodSet
                ? array_combine(array_map(fn (string $label) => "$key.$label", array_keys($question->likelihoods())), $question->likelihoods())
                : [$key => $question];

            foreach ($asked as $askedKey => $single) {
                if (isset($expanded[$askedKey])) {
                    throw InvalidQuestion::collidingSetKey($judgment, $askedKey);
                }
                $expanded[$askedKey] = $single;
            }
        }

        return $expanded;
    }

    private function ensureEveryQuestionIsAnswered(Judgment $judgment, EngineRequest $request, EngineResponse $response): void
    {
        foreach (array_keys($request->questions) as $key) {
            if (! isset($response->answers[$key])) {
                throw MalformedEngineResponse::unanswered($judgment, $key);
            }
        }

        foreach (array_keys($response->answers) as $key) {
            if (! isset($request->questions[$key])) {
                throw MalformedEngineResponse::undeclared($judgment, $key);
            }
        }
    }
}
