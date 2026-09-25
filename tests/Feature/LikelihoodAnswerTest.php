<?php

use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\Refund;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;

function abusiveAnswer(float $p): RobertoGallea\Judgment\Answers\LikelihoodAnswer
{
    app()->instance(Engine::class, new FakeEngine(['abusive' => $p]));

    return (new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.')))->assess()->likelihood('abusive');
}

it('is above a threshold it reaches or exceeds', function () {
    expect(abusiveAnswer(.65)->above(.65))->toBeTrue()
        ->and(abusiveAnswer(.70)->above(.65))->toBeTrue()
        ->and(abusiveAnswer(.64)->above(.65))->toBeFalse();
});

it('is below a threshold it does not reach', function () {
    expect(abusiveAnswer(.29)->below(.30))->toBeTrue()
        ->and(abusiveAnswer(.30)->below(.30))->toBeFalse();
});

it('is between a lower bound it reaches and an upper bound it does not', function () {
    expect(abusiveAnswer(.30)->between(.30, .65))->toBeTrue()
        ->and(abusiveAnswer(.50)->between(.30, .65))->toBeTrue()
        ->and(abusiveAnswer(.65)->between(.30, .65))->toBeFalse()
        ->and(abusiveAnswer(.29)->between(.30, .65))->toBeFalse();
});
