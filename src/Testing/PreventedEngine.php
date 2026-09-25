<?php

namespace RobertoGallea\Judgment\Testing;

use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\EngineResponse;
use RobertoGallea\Judgment\Exceptions\RealEngineCallPrevented;

/** Bound while the Judge is faked, so no test can reach a real Engine by accident. */
final class PreventedEngine implements Engine
{
    public function model(): string
    {
        throw RealEngineCallPrevented::make();
    }

    public function answer(EngineRequest $request): EngineResponse
    {
        throw RealEngineCallPrevented::make();
    }
}
