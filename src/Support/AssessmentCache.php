<?php

namespace RobertoGallea\Judgment\Support;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Provenance;
use RobertoGallea\Judgment\Questions\LikelihoodSet;
use RobertoGallea\Judgment\Questions\Question;

/**
 * Keeps the Assessments of Judgments that opt in through cacheFor(), keyed on the
 * Judgment, its question fingerprint, the Evidence fingerprint and untrusted paths,
 * and the Engine connection and its pinned model (ADR-0008), so the same Questions
 * over the same Evidence are not paid for twice.
 *
 * @internal
 */
final class AssessmentCache
{
    public function __construct(
        private readonly Cache $cache,
        private readonly AssessmentRecorder $recorder,
        private readonly Config $config,
    ) {}

    /**
     * The cached answers and Provenance of the same Judgment, Questions, Evidence and model, if any, with the id of
     * the record they were first stored as. The Provenance names the Engine request but has no details: a hit spends
     * nothing, and the original record keeps the usage and raw response.
     *
     * @param  array<string, Question|LikelihoodSet>  $questions  as declared
     * @param  array<string, mixed>  $evidence  as the Engine is asked
     * @param  string|null  $connection  the Engine connection asked, since two can serve the same model name
     * @return array{array<string, Answer>, Provenance, ?int}|null
     */
    public function get(Judgment $judgment, array $questions, array $evidence, Engine $engine, ?string $connection): ?array
    {
        if ($judgment->cacheFor() === null) {
            return null;
        }

        $cached = $this->cache->get($this->key($judgment, $questions, $evidence, $engine, $connection));

        // Cached while persistence was off: a hit recorded now would have no original to point at.
        if (! is_array($cached) || ($cached['record_id'] === null && $this->config->get('judgment.persistence.enabled'))) {
            return null;
        }

        return [
            $this->recorder->decode($questions, $cached['answers']),
            new Provenance($cached['engine'], $cached['model'], $cached['request_id']),
            $cached['record_id'],
        ];
    }

    /**
     * Keep an Assessment the Engine produced, for as long as its Judgment asks.
     *
     * @param  array<string, Question|LikelihoodSet>  $questions  as declared
     * @param  array<string, Answer>  $answers
     * @param  array<string, mixed>  $evidence  as the Engine was asked
     * @param  string|null  $connection  the Engine connection asked
     * @param  int|null  $recordId  the id of the record it was stored as, null when persistence is off
     */
    public function put(Assessment $assessment, array $questions, array $answers, array $evidence, Engine $engine, ?string $connection, ?int $recordId): void
    {
        $ttl = $assessment->judgment->cacheFor();
        if ($ttl === null) {
            return;
        }

        $this->cache->put($this->key($assessment->judgment, $questions, $evidence, $engine, $connection), [
            'answers' => $this->recorder->encode($answers),
            'engine' => $assessment->provenance->engine,
            'model' => $assessment->provenance->model,
            'request_id' => $assessment->provenance->requestId,
            'record_id' => $recordId,
        ], $ttl);
    }

    /**
     * @param  array<string, Question|LikelihoodSet>  $questions
     * @param  array<string, mixed>  $evidence
     */
    private function key(Judgment $judgment, array $questions, array $evidence, Engine $engine, ?string $connection): string
    {
        return 'judgment:assessment:'.hash('sha256', json_encode([
            $judgment::class,
            AssessmentRecorder::fingerprint($questions),
            // Untrusted text serialises as plain text, but an Engine is asked it differently.
            AssessmentRecorder::evidenceFingerprint($evidence),
            AssessmentRecorder::untrustedPaths($evidence),
            $engine::class,
            $connection,
            $engine->model(),
        ], JSON_THROW_ON_ERROR));
    }
}
