<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\EngineResponse;
use RuntimeException;

/** An Engine that never produces an answer, as when the backend is down. */
final class FailingEngine implements Engine
{
    public function model(): string
    {
        return 'failing-1';
    }

    public function answer(EngineRequest $request): EngineResponse
    {
        throw new RuntimeException('Engine unreachable.');
    }
}
