<?php

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\AssertionFailedError;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Events\AssessmentAwaitingReview;
use RobertoGallea\Judgment\Facades\Judge;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Tests\Fixtures\ProductReview;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;

it('asserts a Judgment awaits Review, optionally matching a callback over the Judgment and Outcome', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .40]]);

    refundAbuse()->assess()->outcome();

    Judge::assertAwaitingReview(RefundAbuse::class);
    Judge::assertAwaitingReview(RefundAbuse::class, fn (RefundAbuse $judgment, RefundOutcome $outcome) => $outcome === RefundOutcome::Escalate
        && $judgment->refund->item === 'Headphones');
});

it('fails assertAwaitingReview when the Outcome did not require Review', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .80]]);

    refundAbuse()->assess()->outcome();

    Judge::assertAwaitingReview(RefundAbuse::class);
})->throws(AssertionFailedError::class, 'Expected '.RefundAbuse::class.' to await Review, but it did not.');

it('fails assertAwaitingReview when no Judgment awaiting Review matches the callback', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .40]]);

    refundAbuse()->assess()->outcome();

    Judge::assertAwaitingReview(RefundAbuse::class, fn (Judgment $judgment, RefundOutcome $outcome) => $outcome === RefundOutcome::Reject);
})->throws(AssertionFailedError::class, 'Expected '.RefundAbuse::class.' to await Review matching the callback, but none of the 1 awaiting Review matched.');

it('fails assertAwaitingReview for another Judgment', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .40]]);

    refundAbuse()->assess()->outcome();

    Judge::assertAwaitingReview(ProductReview::class);
})->throws(AssertionFailedError::class, 'Expected '.ProductReview::class.' to await Review, but it did not.');

it('announces Review from a faked assessment, without a record', function () {
    Event::fake([AssessmentAwaitingReview::class]);
    Judge::fake([RefundAbuse::class => ['abusive' => .40]]);

    refundAbuse()->assess()->outcome();

    Event::assertDispatched(AssessmentAwaitingReview::class, fn (AssessmentAwaitingReview $event) => $event->record === null
        && $event->outcome === RefundOutcome::Escalate);
});

it('keeps Decision unit tests out of Review', function () {
    Event::fake([AssessmentAwaitingReview::class]);

    Assessment::fake(refundAbuse())->likelihood('abusive', .40)->make()->outcome();

    Event::assertNotDispatched(AssessmentAwaitingReview::class);
});
