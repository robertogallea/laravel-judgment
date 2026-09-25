<?php

use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Tests\Fixtures\Readme\Customer;
use RobertoGallea\Judgment\Tests\Fixtures\Readme\Refund;
use RobertoGallea\Judgment\Tests\Fixtures\Readme\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;

/** The README's headline example: a Decision combining two Questions with a deterministic fact about the Subject. */
function headlineRefundAbuse(int $refundsThisYear = 0): RefundAbuse
{
    return new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.', new Customer($refundsThisYear)));
}

it('rejects a claim the Engine finds clearly abusive', function () {
    $assessment = Assessment::fake(headlineRefundAbuse())
        ->likelihood('abusive', .80)
        ->rating('credibility', 1)
        ->make();

    expect($assessment->outcome())->toBe(RefundOutcome::Reject);
});

it('approves a credible, good-faith claim', function () {
    $assessment = Assessment::fake(headlineRefundAbuse(refundsThisYear: 1))
        ->likelihood('abusive', .05)
        ->rating('credibility', [0, 0, .3, .7])
        ->make();

    expect($assessment->outcome())->toBe(RefundOutcome::Approve);
});

it('sends an ambiguous claim to Review', function () {
    $assessment = Assessment::fake(headlineRefundAbuse())
        ->likelihood('abusive', .40)
        ->rating('credibility', [0, 0, .3, .7])
        ->make();

    expect($assessment->outcome())->toBe(RefundOutcome::Escalate);
});

it('sends a claim with a doubtful explanation to Review, however unlikely the abuse', function () {
    $assessment = Assessment::fake(headlineRefundAbuse())
        ->likelihood('abusive', .05)
        ->rating('credibility', [0, .3, .5, .2])   // expected level 1.9: between Doubtful and Plausible
        ->make();

    expect($assessment->outcome())->toBe(RefundOutcome::Escalate);
});

it('sends a frequent claimant to Review, whatever the Engine thinks of the claim', function () {
    $assessment = Assessment::fake(headlineRefundAbuse(refundsThisYear: 3))
        ->likelihood('abusive', .05)
        ->rating('credibility', [0, 0, .3, .7])
        ->make();

    expect($assessment->outcome())->toBe(RefundOutcome::Escalate);
});
