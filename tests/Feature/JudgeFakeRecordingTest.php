<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Judge as JudgeContract;
use RobertoGallea\Judgment\Events\AssessmentCompleted;
use RobertoGallea\Judgment\Events\AssessmentDecided;
use RobertoGallea\Judgment\Events\AssessmentFailed;
use RobertoGallea\Judgment\Exceptions\AssessmentNotRecorded;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Facades\Judge;
use RobertoGallea\Judgment\Judge as RealJudge;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\CachedListingTone;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnRequest;
use RobertoGallea\Judgment\Unassessed;

it('records a scripted Assessment as the Judge would, with the fake Provenance', function () {
    Event::fake([AssessmentCompleted::class]);
    $request = ReturnRequest::create(['item' => 'Jacket', 'reason' => 'The zip broke on the first day.']);

    // The Judge's record of the same answers, from an Engine, is the one to match.
    returnAbuse()->assess();
    $judged = AssessmentRecord::sole();
    $judged->delete();

    Judge::fake([ReturnAbuse::class => ['abusive' => .42, 'department' => ['billing' => .70, 'technical' => .20, 'other' => .10]]]);
    (new ReturnAbuse($request))->assess();

    $faked = AssessmentRecord::sole();
    $same = ['judgment', 'evidence_fingerprint', 'evidence', 'untrusted_paths', 'language', 'questions_fingerprint', 'answers', 'subject_type'];
    expect($faked->only($same))->toBe($judged->only($same))
        ->and($faked->subject->is($request))->toBeTrue()
        ->and($faked->engine)->toBe('fake')
        ->and($faked->model)->toBe('fake')
        ->and($faked->request_id)->toBeNull();
    Event::assertDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->record?->is($faked) === true);
});

it('writes the Outcome of a scripted Assessment to its record and announces it', function () {
    Event::fake([AssessmentDecided::class]);
    Judge::fake([ReturnAbuse::class => ['abusive' => .80]]);

    expect(returnAbuse()->assess()->outcome())->toBe(RefundOutcome::Reject);

    $record = AssessmentRecord::sole();
    expect($record->decision)->toBe(ReturnDecision::class)
        ->and($record->decision_version)->toBe('2')
        ->and($record->outcome)->toBe('reject');
    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->outcome === RefundOutcome::Reject
        && $event->record?->is($record) === true);
});

it('records a scripted Engine failure as an Unassessed attempt', function () {
    config(['judgment.throw_on_failure' => false]);
    Event::fake([AssessmentFailed::class]);
    Judge::fake([ReturnAbuse::class => fn (ReturnAbuse $judgment) => throw EngineFailed::for($judgment, new RuntimeException('Timed out'))]);

    $unassessed = returnAbuse()->assess();

    $record = AssessmentRecord::sole();
    expect($unassessed)->toBeInstanceOf(Unassessed::class)
        ->and($unassessed->record?->is($record))->toBeTrue()
        ->and($record->isUnassessed())->toBeTrue()
        ->and($record->failure_type)->toBe(EngineFailed::class)
        ->and($record->answers)->toBeNull()
        ->and($record->engine)->toBeNull();
    Event::assertDispatched(AssessmentFailed::class, fn (AssessmentFailed $event) => $event->record?->is($record) === true);
});

it('decides a dispatched Judgment from its record in the chained job', function () {
    config(['queue.default' => 'sync']);
    Event::fake([AssessmentDecided::class]);
    Judge::fake([ReturnAbuse::class => ['abusive' => .80]]);

    returnAbuse()->dispatch();

    $record = AssessmentRecord::sole();
    expect($record->outcome)->toBe('reject');
    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->outcome === RefundOutcome::Reject
        && $event->record?->is($record) === true);
    Judge::assertAssessed(ReturnAbuse::class);
});

it('neither reads nor writes the cache of a cached Judgment', function () {
    app()->instance(Engine::class, new FakeEngine(['hyped' => .8]));
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    Judge::fake([CachedListingTone::class => ['hyped' => .1]]);
    $faked = (new CachedListingTone('Other jacket!!!'))->assess();
    $again = (new CachedListingTone('Best jacket ever!!!'))->assess();

    // Back on the Judge, the Evidence only the fake assessed is still asked of the Engine.
    Judge::swap(new RealJudge(app()));
    app()->instance(JudgeContract::class, Judge::getFacadeRoot());
    app()->instance(Engine::class, $engine = new FakeEngine(['hyped' => .3]));
    $judged = (new CachedListingTone('Other jacket!!!'))->assess();

    expect($again->likelihood('hyped')->probability())->toBe(.1)
        ->and($faked->likelihood('hyped')->probability())->toBe(.1)
        ->and($judged->likelihood('hyped')->probability())->toBe(.3)
        ->and($engine->requests)->toHaveCount(1)
        ->and(AssessmentRecord::whereNotNull('cached_from_id')->exists())->toBeFalse();
});

it('records a scripted Engine failure before throwing it', function () {
    config(['judgment.throw_on_failure' => true]);
    Judge::fake([ReturnAbuse::class => fn (ReturnAbuse $judgment) => throw EngineFailed::for($judgment, new RuntimeException('Timed out'))]);

    expect(fn () => returnAbuse()->assess())->toThrow(EngineFailed::class)
        ->and(AssessmentRecord::sole()->isUnassessed())->toBeTrue();
});

it('still answers a scripted Assessment it cannot record while recording is best-effort', function () {
    config(['judgment.persistence.required' => false]);
    Schema::drop('judgment_assessments');
    Event::fake([AssessmentCompleted::class]);
    Judge::fake([ReturnAbuse::class => ['abusive' => .80]]);

    expect(returnAbuse()->assess()->outcome())->toBe(RefundOutcome::Reject);

    Event::assertDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->record === null);
});

it('refuses a scripted Assessment it cannot record while recording is required', function () {
    Schema::drop('judgment_assessments');
    Judge::fake([ReturnAbuse::class => ['abusive' => .80]]);

    returnAbuse()->assess();
})->throws(AssessmentNotRecorded::class);

it('records nothing while persistence is off, and still decides and starts Review', function () {
    config(['judgment.persistence.enabled' => false]);
    Schema::drop('judgment_assessments');
    Event::fake([AssessmentCompleted::class, AssessmentDecided::class]);
    Judge::fake([ReturnAbuse::class => ['abusive' => .80]]);

    expect(returnAbuse()->assess()->outcome())->toBe(RefundOutcome::Reject);

    Event::assertDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->record === null);
    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->record === null);
});

it('asserts a dispatched Judgment awaits Review once the chained job decides it', function () {
    config(['queue.default' => 'sync']);
    Judge::fake([RefundAbuse::class => ['abusive' => .40]]);

    refundAbuse()->dispatch();

    expect(AssessmentRecord::sole()->isAwaitingReview())->toBeTrue();
    Judge::assertAwaitingReview(RefundAbuse::class, fn (RefundAbuse $judgment, RefundOutcome $outcome) => $outcome === RefundOutcome::Escalate);
});
