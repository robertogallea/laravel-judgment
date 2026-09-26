<?php

namespace RobertoGallea\Judgment\Calibration;

use Illuminate\Contracts\Container\Container;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\EngineManager;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Exceptions\InvalidCalibration;
use RobertoGallea\Judgment\Exceptions\InvalidDecision;
use RobertoGallea\Judgment\Exceptions\NoDefaultDecision;
use RobertoGallea\Judgment\Judge;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Support\AssessmentRecorder;
use WeakMap;

/**
 * Asks an Engine about every labelled case and applies each Decision to its answers, grouping
 * the results by Calibration Identity. Nothing is cached, recorded or announced.
 *
 * @internal
 */
final class Runner
{
    /** @var array<string, PendingResult> */
    private array $results = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  list<LabelledCase>  $cases
     * @param  list<string|null>  $connections  the Engine connections to ask; null asks each Judgment's own
     * @param  list<class-string<Decision>|null>  $decisions  null applies each Judgment's default Decision
     * @return list<PendingResult>
     */
    public function run(array $cases, array $connections, array $decisions): array
    {
        $this->results = [];
        foreach ($connections as $connection) {
            $asked = [];
            $reported = new WeakMap;
            foreach ($cases as $case) {
                $engine = $this->engine($case->judgment, $connection);
                try {
                    $assessment = $this->container->make(Judge::class)->ask($case->judgment, $engine, $case->evidence);
                    $reported[$engine] ??= $assessment->provenance->model;
                } catch (EngineFailed) {
                    $assessment = null;
                }
                $asked[] = [$case, $engine, $assessment];
            }

            // A failed case has no Provenance: it counts under the model version its Engine reported for the others.
            foreach ($asked as [$case, $engine, $assessment]) {
                $this->calibrate($case, $assessment, $assessment?->provenance->model ?? $reported[$engine] ?? $engine->model(), $decisions);
            }
        }

        return array_values($this->results);
    }

    /** @param  list<class-string<Decision>|null>  $decisions */
    private function calibrate(LabelledCase $case, ?Assessment $assessment, string $model, array $decisions): void
    {
        $judgment = $case->judgment;
        $questions = AssessmentRecorder::fingerprint($judgment->questions());

        foreach ($decisions as $class) {
            $decision = $this->container->make($class ?? $judgment->decision() ?? throw NoDefaultDecision::for($judgment));
            if (! is_callable($decision)) {
                throw InvalidDecision::notInvokable($decision, $judgment);
            }

            $result = $this->result($questions, $model, $decision, $case->language);
            if ($assessment === null) {
                $result->unassessed();

                continue;
            }

            $outcome = $decision($assessment, $judgment);
            $expected = $this->expected($case, $decision, $outcome);
            $label = (string) $expected->value;
            if ($expected->requiresReview()) {
                throw InvalidCalibration::requiresReview($label);
            }
            $result->decided($assessment, $label, $outcome);
        }
    }

    /** The case's expected Outcome, which must be one of the Decision's Outcome enum. */
    private function expected(LabelledCase $case, Decision $decision, Outcome $outcome): Outcome
    {
        $expected = $case->expected instanceof Outcome
            ? ($case->expected instanceof $outcome ? $case->expected : null)
            : $outcome::tryFrom($case->expected);

        return $expected ?? throw InvalidCalibration::notAnOutcome($case->expected, $decision, $outcome::class);
    }

    private function engine(Judgment $judgment, ?string $connection): Engine
    {
        $connection ??= $judgment->engine();

        return $connection === null
            ? $this->container->make(Engine::class)
            : $this->container->make(EngineManager::class)->engine($connection);
    }

    private function result(string $questions, string $model, Decision $decision, ?string $language): PendingResult
    {
        $version = AssessmentRecorder::decisionVersion($decision);
        $key = json_encode([$questions, $model, $decision::class, $version, $language], JSON_THROW_ON_ERROR);

        return $this->results[$key] ??= new PendingResult($questions, $model, $decision::class, $version, $language);
    }
}
