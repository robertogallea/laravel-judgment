<?php

use Illuminate\Support\Facades\Event;
use RobertoGallea\Judgment\Events\AssessmentCompleted;
use RobertoGallea\Judgment\Facades\Judge;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\DecideReturnCopy;
use RobertoGallea\Judgment\Tests\Fixtures\RecordReturnOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\RecordStrictReturnOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\StrictReturnDecision;

it('carries the record in AssessmentCompleted', function () {
    Event::fake([AssessmentCompleted::class]);

    returnAbuse()->assess();

    Event::assertDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->record?->is(AssessmentRecord::sole()) === true);
});

it('carries no record when nothing was recorded', function () {
    Event::fake([AssessmentCompleted::class]);
    config(['judgment.persistence.enabled' => false]);
    returnAbuse()->assess();

    Judge::fake([ReturnAbuse::class => ['abusive' => .10]]);
    returnAbuse()->assess();

    Event::assertDispatchedTimes(AssessmentCompleted::class, 2);
    Event::assertNotDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->record !== null);
});

it('records the Outcome from a queued listener through the record', function (string $listener, string $decision, ?string $version, string $outcome) {
    config(['queue.default' => 'sync']);
    Event::listen(AssessmentCompleted::class, $listener);

    returnAbuse()->assess();

    expect(AssessmentRecord::sole()->only('decision', 'decision_version', 'outcome'))
        ->toBe(['decision' => $decision, 'decision_version' => $version, 'outcome' => $outcome]);
})->with([
    'default Decision' => [RecordReturnOutcome::class, ReturnDecision::class, '2', 'approve'],
    'another Decision' => [RecordStrictReturnOutcome::class, StrictReturnDecision::class, null, 'reject'],
]);

it('records nothing when a queued listener decides its copy of the Assessment', function () {
    config(['queue.default' => 'sync']);
    Event::listen(AssessmentCompleted::class, DecideReturnCopy::class);

    returnAbuse()->assess();

    expect(AssessmentRecord::sole()->outcome)->toBeNull();
});
