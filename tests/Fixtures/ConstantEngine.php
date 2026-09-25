<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\EngineResponse;
use RobertoGallea\Judgment\Provenance;

/** An Engine configurable by class name: answers every Likelihood with an even chance. */
final class ConstantEngine implements Engine
{
    public function answer(EngineRequest $request): EngineResponse
    {
        return new EngineResponse(
            array_map(fn () => new LikelihoodAnswer(.5), $request->questions),
            new Provenance(engine: 'constant', model: 'constant-1'),
        );
    }
}
