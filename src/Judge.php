<?php

namespace RobertoGallea\Judgment;

use Illuminate\Contracts\Container\Container;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Judge as JudgeContract;
use RobertoGallea\Judgment\Exceptions\MalformedEngineResponse;

class Judge implements JudgeContract
{
    public function __construct(private readonly Container $container) {}

    public function assess(Judgment $judgment): Assessment
    {
        $request = new EngineRequest($judgment->questions(), $judgment->evidence());

        foreach ($request->questions as $key => $question) {
            $question->ensureAnswerable($key);
        }

        $response = $this->container->make(Engine::class)->answer($request);

        $this->ensureEveryQuestionIsAnswered($judgment, $request, $response);

        return new Assessment($judgment, $request->questions, $response->answers, $response->provenance);
    }

    private function ensureEveryQuestionIsAnswered(Judgment $judgment, EngineRequest $request, EngineResponse $response): void
    {
        foreach (array_keys($request->questions) as $key) {
            if (! isset($response->answers[$key])) {
                throw MalformedEngineResponse::unanswered($judgment, $key);
            }
        }

        foreach (array_keys($response->answers) as $key) {
            if (! isset($request->questions[$key])) {
                throw MalformedEngineResponse::undeclared($judgment, $key);
            }
        }
    }
}
