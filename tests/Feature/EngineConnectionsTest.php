<?php

use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineManager;
use RobertoGallea\Judgment\Exceptions\EngineNotConfigured;
use RobertoGallea\Judgment\Tests\Fixtures\ConstantEngine;
use RobertoGallea\Judgment\Tests\Fixtures\SpamCheck;

beforeEach(function () {
    config([
        'judgment.engine' => 'constant',
        'judgment.engines.constant' => ['driver' => ConstantEngine::class],
    ]);
});

it('resolves the default connection as the Engine', function () {
    expect(app(Engine::class))->toBeInstanceOf(ConstantEngine::class)
        ->and(refundAbuse()->assess()->provenance->engine)->toBe('constant');
});

it('resolves a named connection', function () {
    config(['judgment.engine' => null]);

    expect(app(EngineManager::class)->engine('constant'))->toBeInstanceOf(ConstantEngine::class);
});

it('refuses an undeclared connection', function () {
    app(EngineManager::class)->engine('missing');
})->throws(EngineNotConfigured::class, 'The Judgment Engine connection "missing" is not configured in judgment.engines.');

it('refuses a missing default connection', function () {
    config(['judgment.engine' => null]);

    app(Engine::class);
})->throws(EngineNotConfigured::class, 'No default Judgment Engine connection is configured. Set JUDGMENT_ENGINE or judgment.engine to a connection in judgment.engines.');

it('builds a connection with a driver registered through extend()', function () {
    config(['judgment.engines.custom' => ['driver' => 'custom', 'answer' => .7]]);
    app(EngineManager::class)->extend('custom', fn ($app, array $config) => new ConstantEngine);

    expect(app(EngineManager::class)->engine('custom'))->toBeInstanceOf(ConstantEngine::class);
});

it('asks a Judgment on the connection it chooses', function () {
    config(['judgment.engine' => null]);

    $judgment = new SpamCheck('Buy now!', connection: 'constant');

    expect($judgment->assess()->provenance->engine)->toBe('constant');
});
