<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Answers\ClassificationAnswer;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\EngineResponse;
use RobertoGallea\Judgment\Provenance;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Question;
use RobertoGallea\Judgment\Questions\Rating;

/**
 * An Engine configurable by class name: answers every Likelihood with an even
 * chance, and every Classification and Rating with certainty in its first option.
 */
final class ConstantEngine implements Engine
{
    public function answer(EngineRequest $request): EngineResponse
    {
        return new EngineResponse(
            array_map($this->even(...), $request->questions),
            new Provenance(engine: 'constant', model: 'constant-1'),
        );
    }

    /** @param  list<string>  $labels */
    private function first(array $labels): Answer
    {
        return new ClassificationAnswer([array_shift($labels) ?? '' => 1.0, ...array_fill_keys($labels, 0.0)]);
    }

    private function even(Question $question): Answer
    {
        return match (true) {
            $question instanceof Classification => $this->first(array_keys($question->criteria())),
            $question instanceof Rating => $question->answer([1.0, ...array_fill(0, count($question->criteria()) - 1, 0.0)]),
            default => new LikelihoodAnswer(.5),
        };
    }
}
