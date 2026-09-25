<?php

namespace RobertoGallea\Judgment\Contracts;

use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\EngineResponse;

/**
 * The execution backend that answers Questions over Evidence (ADR-0002).
 *
 * Expressed only in package terms: a trained classifier served over HTTP
 * could implement it. It never sees the Judgment, its Decision or Outcomes.
 */
interface Engine
{
    public function answer(EngineRequest $request): EngineResponse;
}
