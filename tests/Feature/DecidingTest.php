<?php

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Exceptions\InvalidDecision;
use RobertoGallea\Judgment\Exceptions\NoDefaultDecision;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\ImpureRefundDecision;
use RobertoGallea\Judgment\Tests\Fixtures\ProductReview;
use RobertoGallea\Judgment\Tests\Fixtures\Refund;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\StrictRefundDecision;
use RobertoGallea\Judgment\Tests\Fixtures\UninvokableDecision;

function assessRefund(float $abusive): Assessment
{
    app()->instance(Engine::class, new FakeEngine(['abusive' => $abusive]));

    return (new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.')))->assess();
}

it('applies the Judgment\'s default Decision with outcome()', function (float $abusive, RefundOutcome $expected) {
    expect(assessRefund($abusive)->outcome())->toBe($expected);
})->with([
    'clearly abusive' => [.80, RefundOutcome::Reject],
    'ambiguous' => [.40, RefundOutcome::Escalate],
    'good faith' => [.05, RefundOutcome::Approve],
]);

it('applies another Decision to the same Assessment with decide()', function () {
    $assessment = assessRefund(.20);

    expect($assessment->outcome())->toBe(RefundOutcome::Approve)
        ->and($assessment->decide(new StrictRefundDecision))->toBe(RefundOutcome::Escalate);
});

it('refuses to apply a Decision to another Judgment\'s Assessment', function () {
    app()->instance(Engine::class, new FakeEngine(['spam' => .90]));
    $assessment = (new ProductReview('Buy cheap watches at example.com'))->assess();

    $assessment->decide(new StrictRefundDecision);
})->throws(TypeError::class, RefundAbuse::class);

it('explains that outcome() needs a default Decision when the Judgment names none', function () {
    app()->instance(Engine::class, new FakeEngine(['spam' => .90]));
    $assessment = (new ProductReview('Buy cheap watches at example.com'))->assess();

    $assessment->outcome();
})->throws(NoDefaultDecision::class, ProductReview::class.' names no default Decision; pass one to decide() instead.');

it('explains how to write a Decision that has no __invoke method', function () {
    assessRefund(.20)->decide(new UninvokableDecision);
})->throws(InvalidDecision::class, UninvokableDecision::class.' must define __invoke(Assessment $assessment, '.RefundAbuse::class.' $judgment): Outcome.');

it('runs a Decision once on an Assessment from the Engine, leaving the purity check to fakes', function () {
    expect(assessRefund(.20)->decide(new ImpureRefundDecision))->toBe(RefundOutcome::Approve);
});
