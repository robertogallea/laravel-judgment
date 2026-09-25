<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\AssertionFailedError;
use RobertoGallea\Judgment\Events\AssessmentCompleted;
use RobertoGallea\Judgment\Facades\Judge;
use RobertoGallea\Judgment\Tests\Fixtures\ProductListing;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;

it('records dispatched Judgments without assessing them while the queue is faked', function () {
    Queue::fake();
    Judge::fake([RefundAbuse::class => ['abusive' => .80]]);

    Judge::dispatch(refundAbuse());

    Judge::assertDispatched(RefundAbuse::class);
    Judge::assertDispatched(RefundAbuse::class, fn (RefundAbuse $judgment) => $judgment->refund->item === 'Headphones');
    Judge::assertNotDispatched(ProductListing::class);
    Judge::assertNothingAssessed();
});

it('fails assertDispatched for a Judgment that was not dispatched', function () {
    Judge::fake();

    Judge::assertDispatched(RefundAbuse::class);
})->throws(AssertionFailedError::class, 'Expected '.RefundAbuse::class.' to be dispatched, but it was not.');

it('answers a dispatched Judgment from its script when the queue runs it', function () {
    config(['queue.default' => 'sync']);
    Event::fake([AssessmentCompleted::class]);
    Judge::fake([RefundAbuse::class => ['abusive' => .80]]);

    refundAbuse()->dispatch();

    Judge::assertDispatched(RefundAbuse::class);
    Judge::assertAssessed(RefundAbuse::class);
    Event::assertDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->assessment->likelihood('abusive')->probability() === .80);
});
