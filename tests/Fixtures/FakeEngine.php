<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

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
     * @param  array<string, float|non-empty-array<string, float>|non-empty-list<float>>  $answers  a probability per Likelihood,
     *                                                                                   a label => probability map per Classification,
     *                                                                                   a probability per level per Rating
     */
    public function __construct(private readonly array $answers) {}

    public function answer(EngineRequest $request): EngineResponse
    {
        $this->requests[] = $request;

        $answers = [];
        foreach ($this->answers as $key => $scripted) {
            $answers[$key] = $this->scriptedAnswer($request->questions[$key] ?? null, $scripted);
        }

        return new EngineResponse(
            $answers,
            new Provenance(engine: 'fake', model: 'fake-1.0.0', requestId: 'req-'.count($this->requests)),
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
