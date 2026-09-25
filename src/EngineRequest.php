<?php

namespace RobertoGallea\Judgment;

use RobertoGallea\Judgment\Questions\Question;

/** What an Engine is asked: independent Questions over Evidence, nothing else (ADR-0001). */
final class EngineRequest
{
    /**
     * @param  array<string, Question>  $questions
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public readonly array $questions,
        public readonly array $evidence,
    ) {}
}
