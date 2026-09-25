<?php

use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\EngineResponse;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Exceptions\MalformedEngineResponse;
use RobertoGallea\Judgment\Tests\Fixtures\FailingEngine;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Unassessed;

it('throws a package exception when the Engine fails', function () {
    app()->instance(Engine::class, new FailingEngine);

    expect(fn () => refundAbuse()->assess())->toThrow(function (EngineFailed $e) {
        expect($e->getMessage())->toBe('The Engine failed to assess '.RefundAbuse::class.': Engine unreachable.')
            ->and($e->getPrevious())->toBeInstanceOf(RuntimeException::class);
    });
});

it('ends Unassessed when configured to, holding the Judgment and the failure', function () {
    config(['judgment.failure' => 'unassessed']);
    app()->instance(Engine::class, new FailingEngine);
    $judgment = refundAbuse();

    $result = $judgment->assess();

    expect($result)->toBeInstanceOf(Unassessed::class)
        ->and($result->judgment)->toBe($judgment)
        ->and($result->exception)->toBeInstanceOf(EngineFailed::class)
        ->and($result->exception->getMessage())->toBe('The Engine failed to assess '.RefundAbuse::class.': Engine unreachable.');
});

it('offers no way to reach an Outcome from an Unassessed Judgment', function () {
    expect(Unassessed::class)->not->toHaveMethods(['outcome', 'decide', 'likelihood', 'classification', 'rating', 'likelihoodSet']);
});

it('treats a malformed Engine response as a failure', function () {
    config(['judgment.failure' => 'unassessed']);
    app()->instance(Engine::class, new FakeEngine([]));

    $result = refundAbuse()->assess();

    expect($result)->toBeInstanceOf(Unassessed::class)
        ->and($result->exception)->toBeInstanceOf(MalformedEngineResponse::class);
});

it('lets programming errors through instead of ending Unassessed', function () {
    config(['judgment.failure' => 'unassessed']);
    app()->instance(Engine::class, new class implements Engine
    {
        public function answer(EngineRequest $request): EngineResponse
        {
            throw new TypeError('A bug in the Engine.');
        }
    });

    refundAbuse()->assess();
})->throws(TypeError::class, 'A bug in the Engine.');
