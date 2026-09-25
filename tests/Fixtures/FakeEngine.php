<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\EngineResponse;
use RobertoGallea\Judgment\Provenance;

/** Test double at the Engine contract seam: scripted probabilities in, recorded requests out. */
final class FakeEngine implements Engine
{
    /** @var list<EngineRequest> */
    public array $requests = [];

    /** @param array<string, float> $probabilities */
    public function __construct(private readonly array $probabilities) {}

    public function answer(EngineRequest $request): EngineResponse
    {
        $this->requests[] = $request;

        return new EngineResponse(
            array_map(fn (float $p) => new LikelihoodAnswer($p), $this->probabilities),
            new Provenance(engine: 'fake', model: 'fake-1.0.0', requestId: 'req-'.count($this->requests)),
        );
    }
}
