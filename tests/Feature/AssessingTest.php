<?php

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Facades\Judge;
use RobertoGallea\Judgment\Provenance;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\Refund;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;

function refundAbuse(): RefundAbuse
{
    return new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.'));
}

function useEngine(float $abusive): FakeEngine
{
    $engine = new FakeEngine(['abusive' => $abusive]);
    app()->instance(Engine::class, $engine);

    return $engine;
}

it('assesses a Judgment through the Judge facade', function () {
    useEngine(abusive: .42);

    $assessment = Judge::assess(refundAbuse());

    expect($assessment)->toBeInstanceOf(Assessment::class)
        ->and($assessment->likelihood('abusive')->probability())->toBe(.42);
});

it('assesses a Judgment through the injected Judge contract', function () {
    useEngine(abusive: .42);

    $assessment = app(RobertoGallea\Judgment\Contracts\Judge::class)->assess(refundAbuse());

    expect($assessment->likelihood('abusive')->probability())->toBe(.42);
});

it('assesses a Judgment through its own assess() method', function () {
    useEngine(abusive: .42);

    expect(refundAbuse()->assess()->likelihood('abusive')->probability())->toBe(.42);
});

it('asks all Questions over the Evidence in a single Engine round', function () {
    $engine = useEngine(abusive: .42);

    refundAbuse()->assess();

    expect($engine->requests)->toHaveCount(1)
        ->and($engine->requests[0]->evidence)->toBe([
            'order' => ['item' => 'Headphones', 'amount_eur' => 120],
            'request' => ['explanation' => 'Arrived damaged.'],
        ])
        ->and(array_keys($engine->requests[0]->questions))->toBe(['abusive']);
});

it('carries the Provenance the Engine reported', function () {
    useEngine(abusive: .42);

    $provenance = refundAbuse()->assess()->provenance;

    expect($provenance->engine)->toBe('fake')
        ->and($provenance->model)->toBe('fake-1.0.0')
        ->and($provenance->requestId)->toBe('req-1');
});

it('returns an Assessment that cannot be altered', function () {
    useEngine(abusive: .42);
    $assessment = refundAbuse()->assess();

    expect(fn () => $assessment->provenance = new Provenance('other', 'other-1'))
        ->toThrow(Error::class, 'readonly');
});
