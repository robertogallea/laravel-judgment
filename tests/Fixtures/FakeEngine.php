<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use Closure;
use LogicException;
use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\EngineResponse;
use RobertoGallea\Judgment\Provenance;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Rating;

/** Test double at the Engine contract seam: scripted probabilities in, recorded requests out. */
final class FakeEngine implements Engine
{
    /** @var list<EngineRequest> */
    public array $requests = [];

    /**
     * The answers script a probability per Likelihood, a label => probability map per Classification
     * and a probability per level per Rating; or a closure given the Evidence returns them per request.
     *
     * @param  array<string, float|non-empty-array<string, float>|non-empty-list<float>>|Closure(array<string, mixed>): array<string, mixed>  $answers
     * @param  array<string, mixed>  $details  the Provenance details to report
     * @param  string|null  $reports  the model version answers report, when not the configured one (as for an alias)
     */
    public function __construct(
        private readonly array|Closure $answers,
        private readonly string $model = 'fake-1.0.0',
        private readonly array $details = [],
        private readonly ?string $reports = null,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    public function answer(EngineRequest $request): EngineResponse
    {
        $this->requests[] = $request;

        $answers = [];
        $scripts = $this->answers instanceof Closure ? ($this->answers)($request->evidence) : $this->answers;
        foreach ($scripts as $key => $scripted) {
            $answers[$key] = $this->scriptedAnswer($request->questions[$key] ?? null, $scripted);
        }

        return new EngineResponse(
            $answers,
            new Provenance(engine: 'fake', model: $this->reports ?? $this->model, requestId: 'req-'.count($this->requests), details: $this->details),
        );
    }

    /** @param  float|non-empty-array<string, float>|non-empty-list<float>  $scripted */
    private function scriptedAnswer(mixed $question, float|array $scripted): Answer
    {
        if (is_float($scripted)) {
            return new LikelihoodAnswer($scripted);
        }

        if ($question instanceof Rating && array_is_list($scripted)) {
            return $question->answer($scripted);
        }

        if ($question instanceof Classification && ! array_is_list($scripted)) {
            /** @var non-empty-array<string, float> $scripted */
            return $question->answer($scripted);
        }

        throw new LogicException('Script a probability for a Likelihood, a label map for a Classification or a level list for a Rating.');
    }
}
