<?php

use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Exceptions\MalformedEngineResponse;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\Refund;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundDecision;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;

it('never sends an Outcome or a Decision to the Engine', function () {
    $engine = new FakeEngine(['abusive' => .80]);
    app()->instance(Engine::class, $engine);

    $assessment = (new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.')))->assess();
    $assessment->outcome();

    $sent = strtolower(serialize($engine->requests));

    foreach (RefundOutcome::cases() as $outcome) {
        expect($sent)->not->toContain(strtolower($outcome->value))
            ->not->toContain(strtolower($outcome->name));
    }
    expect($sent)->not->toContain(strtolower(RefundOutcome::class))
        ->not->toContain(strtolower(RefundDecision::class));
});

it('rejects an Engine response that leaves a declared Question unanswered', function () {
    app()->instance(Engine::class, new FakeEngine([]));

    (new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.')))->assess();
})->throws(MalformedEngineResponse::class, 'The Engine did not answer Question "abusive" on '.RefundAbuse::class.'.');

it('rejects an Engine response that answers an undeclared Question', function () {
    app()->instance(Engine::class, new FakeEngine(['abusive' => .10, 'fraud' => .20]));

    (new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.')))->assess();
})->throws(MalformedEngineResponse::class, 'The Engine answered Question "fraud", which '.RefundAbuse::class.' does not declare.');
