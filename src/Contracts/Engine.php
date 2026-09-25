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
    /**
     * The exact model version this Engine answers with, e.g. "jev-1.13.0": part of
     * what identifies an Assessment for caching and Calibration (ADR-0008).
     */
    public function model(): string;

    public function answer(EngineRequest $request): EngineResponse;
}
