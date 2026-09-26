<?php

use Illuminate\Support\Facades\Event;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Events\AssessmentAwaitingReview;
use RobertoGallea\Judgment\Events\AssessmentDecided;
use RobertoGallea\Judgment\Facades\Judge;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\ReviewedReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\StrictReturnDecision;

beforeEach(fn () => Event::fake([AssessmentDecided::class]));

it('announces a decided Assessment with its Judgment, Decision, Outcome and record', function () {
    $judgment = returnAbuse();
    $assessment = $judgment->assess();

    $assessment->outcome();

    Event::assertDispatchedTimes(AssessmentDecided::class, 1);
    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->judgment === $judgment
        && $event->assessment === $assessment
        && $event->decision instanceof ReturnDecision
        && $event->outcome === RefundOutcome::Approve
        && $event->record?->is(AssessmentRecord::sole()) === true);
});

it('announces each decision made through the record', function () {
    returnAbuse()->assess();
    $record = AssessmentRecord::sole();

    $record->outcome();
    $record->decide(new StrictReturnDecision);

    Event::assertDispatchedTimes(AssessmentDecided::class, 2);
    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->judgment instanceof ReturnAbuse
        && $event->decision instanceof ReturnDecision
        && $event->outcome === RefundOutcome::Approve
        && $event->record?->is($record) === true);
    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->decision instanceof StrictReturnDecision
        && $event->outcome === RefundOutcome::Reject
        && $event->record?->is($record) === true);
});

it('announces no decision for a Replay', function () {
    returnAbuse()->assess();

    AssessmentRecord::sole()->assessment()->decide(new StrictReturnDecision);

    Event::assertNotDispatched(AssessmentDecided::class);
});

it('announces no decision for an Assessment::fake()', function () {
    Assessment::fake(refundAbuse())->likelihood('abusive', .80)->make()->outcome();

    Event::assertNotDispatched(AssessmentDecided::class);
});

it('announces a decision without a record under Judge::fake()', function () {
    Judge::fake([RefundAbuse::class => ['abusive' => .80]]);

    refundAbuse()->assess()->outcome();

    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->outcome === RefundOutcome::Reject
        && $event->record === null);
});

it('announces a decision without a record when persistence is off', function () {
    config(['judgment.persistence.enabled' => false]);
    $assessment = returnAbuse()->assess();

    $assessment->outcome();

    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->assessment === $assessment
        && $event->outcome === RefundOutcome::Approve
        && $event->record === null);
});

it('announces a decision that starts Review alongside AssessmentAwaitingReview', function () {
    Event::fake([AssessmentDecided::class, AssessmentAwaitingReview::class]);

    returnAbuse()->assess()->decide(new ReviewedReturnDecision);

    Event::assertDispatchedTimes(AssessmentAwaitingReview::class, 1);
    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->outcome === RefundOutcome::Escalate
        && $event->record?->isAwaitingReview() === true);
});

it('announces a later decision of a record in Review, which keeps the Outcome that sent it there', function () {
    returnAbuse()->assess()->decide(new ReviewedReturnDecision);
    $record = AssessmentRecord::sole();

    $record->outcome();

    Event::assertDispatchedTimes(AssessmentDecided::class, 2);
    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->outcome === RefundOutcome::Approve
        && $event->record?->isAwaitingReview() === true
        && $event->record->outcome === 'escalate');
});

it('serialises for a queued listener, restoring its record fresh', function () {
    returnAbuse()->assess()->outcome();
    $event = Event::dispatched(AssessmentDecided::class)->sole()[0];
    AssessmentRecord::sole()->update(['outcome' => 'reject']);

    $restored = unserialize(serialize($event));

    expect($restored)->toBeInstanceOf(AssessmentDecided::class)
        ->and($restored->judgment)->toBeInstanceOf(ReturnAbuse::class)
        ->and($restored->assessment->likelihood('abusive')->probability())->toBe(.42)
        ->and($restored->decision)->toBeInstanceOf(ReturnDecision::class)
        ->and($restored->outcome)->toBe(RefundOutcome::Approve)
        ->and($restored->record?->outcome)->toBe('reject');
});
