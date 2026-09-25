<?php

use PHPUnit\Framework\AssertionFailedError;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Exceptions\ExhaustedSequence;
use RobertoGallea\Judgment\Exceptions\ImpureDecision;
use RobertoGallea\Judgment\Exceptions\RealEngineCallPrevented;
use RobertoGallea\Judgment\Exceptions\UnscriptedJudgment;
use RobertoGallea\Judgment\Exceptions\UnscriptedQuestion;
use RobertoGallea\Judgment\Facades\Judge;
use RobertoGallea\Judgment\Tests\Fixtures\Department;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\ImpureRefundDecision;
use RobertoGallea\Judgment\Tests\Fixtures\PostModeration;
use RobertoGallea\Judgment\Tests\Fixtures\ProductReview;
use RobertoGallea\Judgment\Tests\Fixtures\Refund;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\SupportTicket;
use RobertoGallea\Judgment\Tests\Fixtures\Ticket;
use RobertoGallea\Judgment\Unassessed;

it('answers each Judgment from a static script of its Questions', function () {
    Judge::fake([
        RefundAbuse::class => ['abusive' => .80],
        SupportTicket::class => ['language' => 'italian', 'department' => Department::Billing, 'severity' => 2],
        PostModeration::class => ['flags' => ['spam' => .90], 'topics' => []],
    ]);

    $ticket = (new SupportTicket(new Ticket('Doppio addebito', 'Mi avete addebitato due volte.')))->assess();
    $post = (new PostModeration('Buy followers now!'))->assess();

    expect(refundAbuse()->assess()->outcome())->toBe(RefundOutcome::Reject)
        ->and($ticket->classification('language')->label())->toBe('italian')
        ->and($ticket->classification('department')->label())->toBe(Department::Billing)
        ->and($ticket->rating('severity')->level())->toBe(2)
        ->and($post->likelihoodSet('flags')->of('spam')->probability())->toBe(.90);
});

it('answers a Judgment from a closure script, given the Judgment', function () {
    Judge::fake([
        RefundAbuse::class => fn (RefundAbuse $judgment) => ['abusive' => $judgment->refund->amountEur > 100 ? .80 : .05],
        ProductReview::class => fn (ProductReview $judgment) => Assessment::fake($judgment)->likelihood('spam', .9),
    ]);

    $cheap = new RefundAbuse(new Refund('Cable', 10, 'Never arrived.'));

    expect(refundAbuse()->assess()->outcome())->toBe(RefundOutcome::Reject)
        ->and($cheap->assess()->outcome())->toBe(RefundOutcome::Approve)
        ->and((new ProductReview('Buy watches'))->assess()->likelihood('spam')->probability())->toBe(.9);
});

it('fails the Judgment as the Judge would when a closure script throws an Engine failure', function () {
    config(['judgment.failure' => 'unassessed']);
    Judge::fake([
        RefundAbuse::class => fn (RefundAbuse $judgment) => throw EngineFailed::for($judgment, new RuntimeException('Timed out')),
    ]);

    expect(refundAbuse()->assess())->toBeInstanceOf(Unassessed::class);
});

it('answers successive assessments of a Judgment from a sequence of scripts', function () {
    Judge::fake([
        RefundAbuse::class => Judge::sequence(
            ['abusive' => .80],
            fn (RefundAbuse $judgment) => ['abusive' => .40],
        ),
    ]);

    expect(refundAbuse()->assess()->outcome())->toBe(RefundOutcome::Reject)
        ->and(refundAbuse()->assess()->outcome())->toBe(RefundOutcome::Escalate);
});

it('fails when a sequence runs out of scripts', function () {
    Judge::fake([RefundAbuse::class => Judge::sequence(['abusive' => .80])]);

    refundAbuse()->assess();
    refundAbuse()->assess();
})->throws(ExhaustedSequence::class, 'The fake sequence for '.RefundAbuse::class.' has no script left for assessment #2.');

it('fails when a Judgment without a script is assessed', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .80]]);

    (new ProductReview('Buy watches'))->assess();
})->throws(UnscriptedJudgment::class, ProductReview::class.' has no script in Judge::fake(). Scripted: '.RefundAbuse::class.'.');

it('fails when a Decision reads a Question the script left out', function () {
    Judge::fake([RefundAbuse::class => []]);

    refundAbuse()->assess()->outcome();
})->throws(UnscriptedQuestion::class, 'Question "abusive" on '.RefundAbuse::class.' was not scripted');

it('prevents real Engine calls while the Judge is faked', function () {
    config(['judgment.engine' => FakeEngine::class]);
    Judge::fake();

    app(Engine::class)->answer(new EngineRequest([], []));
})->throws(RealEngineCallPrevented::class, 'The Judge is faked, so real Engine calls are prevented.');

it('checks every Decision for purity on the Assessments it fakes', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .5]]);

    refundAbuse()->assess()->decide(new ImpureRefundDecision);
})->throws(ImpureDecision::class);

it('asserts a Judgment was assessed, optionally matching a callback over the Judgment', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .80]]);

    refundAbuse()->assess();

    Judge::assertAssessed(RefundAbuse::class);
    Judge::assertAssessed(RefundAbuse::class, fn (RefundAbuse $judgment) => $judgment->refund->item === 'Headphones');
});

it('fails assertAssessed when no matching Judgment was assessed', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .80]]);
    refundAbuse()->assess();

    expect(fn () => Judge::assertAssessed(ProductReview::class))
        ->toThrow(AssertionFailedError::class, 'Expected '.ProductReview::class.' to be assessed, but it was not.')
        ->and(fn () => Judge::assertAssessed(RefundAbuse::class, fn (RefundAbuse $judgment) => $judgment->refund->item === 'Cable'))
        ->toThrow(AssertionFailedError::class, 'Expected '.RefundAbuse::class.' to be assessed matching the callback, but none of the 1 assessed matched.');
});

it('asserts a Judgment was not assessed, optionally matching a callback over the Judgment', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .80]]);
    refundAbuse()->assess();

    Judge::assertNotAssessed(ProductReview::class);
    Judge::assertNotAssessed(RefundAbuse::class, fn (RefundAbuse $judgment) => $judgment->refund->item === 'Cable');

    expect(fn () => Judge::assertNotAssessed(RefundAbuse::class))->toThrow(AssertionFailedError::class, 'Expected '.RefundAbuse::class.' not to be assessed, but it was, 1 time.');
});

it('asserts nothing was assessed', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .80]]);

    Judge::assertNothingAssessed();
    refundAbuse()->assess();

    expect(fn () => Judge::assertNothingAssessed())->toThrow(AssertionFailedError::class, 'Expected nothing to be assessed, but 1 Judgment was: '.RefundAbuse::class.'.');
});
