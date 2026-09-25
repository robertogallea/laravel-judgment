<?php

namespace RobertoGallea\Judgment;

use RobertoGallea\Judgment\Answers\Answer;

final class EngineResponse
{
    /** @param  array<string, Answer>  $answers */
    public function __construct(
        public readonly array $answers,
        public readonly Provenance $provenance,
    ) {}
}
