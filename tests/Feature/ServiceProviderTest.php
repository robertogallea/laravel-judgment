<?php

use Illuminate\Support\Facades\File;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Judge;
use RobertoGallea\Judgment\Exceptions\EngineNotConfigured;
use RobertoGallea\Judgment\Tests\Fixtures\ConstantEngine;
use RobertoGallea\Judgment\Tests\Fixtures\Refund;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;

it('registers the Judge contract as a single shared instance', function () {
    expect(app(Judge::class))->toBeInstanceOf(Judge::class)
        ->toBe(app(Judge::class));
});

it('backs the Judge facade with the Judge contract', function () {
    expect(RobertoGallea\Judgment\Facades\Judge::getFacadeRoot())->toBe(app(Judge::class));
});

it('uses the Engine class named in config', function () {
    config(['judgment.engine' => ConstantEngine::class]);

    $assessment = (new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.')))->assess();

    expect(app(Engine::class))->toBeInstanceOf(ConstantEngine::class)
        ->and($assessment->likelihood('abusive')->probability())->toBe(.5);
});

it('explains how to configure an Engine when none is set', function () {
    config(['judgment.engine' => null]);

    (new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.')))->assess();
})->throws(EngineNotConfigured::class, 'No Judgment Engine is configured. Set JUDGMENT_ENGINE or judgment.engine to a class implementing '.Engine::class.'.');

it('publishes its config file', function () {
    File::delete(config_path('judgment.php'));

    $this->artisan('vendor:publish', ['--tag' => 'judgment-config'])->assertSuccessful();

    expect(config_path('judgment.php'))->toBeFile()
        ->and((require config_path('judgment.php')))->toHaveKey('engine');

    File::delete(config_path('judgment.php'));
});
