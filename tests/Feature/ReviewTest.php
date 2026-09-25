<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use RobertoGallea\Judgment\Events\AssessmentAwaitingReview;
use RobertoGallea\Judgment\Events\AssessmentResolved;
use RobertoGallea\Judgment\Exceptions\InvalidResolution;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\AssessmentRecordPolicy;
use RobertoGallea\Judgment\Tests\Fixtures\ForeignOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\ReviewedReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\Reviewer;

/** A record of a ReturnAbuse, sent to Review by ReviewedReturnDecision. */
function awaitingReview(): AssessmentRecord
{
    returnAbuse()->assess()->decide(new ReviewedReturnDecision);

    return AssessmentRecord::sole();
}

function reviewer(): Reviewer
{
    return Reviewer::create(['name' => 'Ada', 'can_resolve' => true]);
}

it('puts a record in pending Review when its Outcome requires Review', function () {
    $this->travelTo('2026-09-25 10:00:00');

    returnAbuse()->assess()->decide(new ReviewedReturnDecision);

    $record = AssessmentRecord::sole();

    expect($record->isAwaitingReview())->toBeTrue()
        ->and($record->review_requested_at?->toDateTimeString())->toBe('2026-09-25 10:00:00')
        ->and(AssessmentRecord::awaitingReview()->pluck('id')->all())->toBe([$record->id]);
});

it('does not put a record in Review when its Outcome does not require it', function () {
    returnAbuse()->assess()->outcome();

    expect(AssessmentRecord::sole()->isAwaitingReview())->toBeFalse()
        ->and(AssessmentRecord::awaitingReview()->count())->toBe(0);
});

it('announces a record awaiting Review with its Judgment, Assessment, Outcome and when Review was requested', function () {
    Event::fake([AssessmentAwaitingReview::class]);
    $this->travelTo('2026-09-25 10:00:00');

    $judgment = returnAbuse();
    $assessment = $judgment->assess();
    $assessment->decide(new ReviewedReturnDecision);

    Event::assertDispatchedTimes(AssessmentAwaitingReview::class, 1);
    Event::assertDispatched(AssessmentAwaitingReview::class, fn (AssessmentAwaitingReview $event) => $event->judgment === $judgment
        && $event->assessment === $assessment
        && $event->outcome === RefundOutcome::Escalate
        && $event->record?->is(AssessmentRecord::sole()) === true
        && $event->requestedAt->toDateTimeString() === '2026-09-25 10:00:00');
});

it('announces Review once, however often the record is decided again', function () {
    Event::fake([AssessmentAwaitingReview::class]);

    $assessment = returnAbuse()->assess();
    $assessment->decide(new ReviewedReturnDecision);
    $assessment->decide(new ReviewedReturnDecision);
    AssessmentRecord::sole()->decide(new ReviewedReturnDecision);

    Event::assertDispatchedTimes(AssessmentAwaitingReview::class, 1);
});

it('announces no Review for an Outcome that does not require it, or for a what-if', function () {
    Event::fake([AssessmentAwaitingReview::class]);

    returnAbuse()->assess()->outcome();
    AssessmentRecord::sole()->assessment()->decide(new ReviewedReturnDecision);

    Event::assertNotDispatched(AssessmentAwaitingReview::class);
    expect(AssessmentRecord::sole()->isAwaitingReview())->toBeFalse();
});

it('announces Review without a record when persistence is off', function () {
    Event::fake([AssessmentAwaitingReview::class]);
    config(['judgment.persistence.enabled' => false]);

    returnAbuse()->assess()->decide(new ReviewedReturnDecision);

    Event::assertDispatched(AssessmentAwaitingReview::class, fn (AssessmentAwaitingReview $event) => $event->record === null
        && $event->judgment instanceof ReturnAbuse);
});

it('records the Resolution and the reviewer, alongside the automatic Outcome', function () {
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    $record = awaitingReview();
    $reviewer = reviewer();
    $this->travelTo('2026-09-25 11:30:00');

    $record->resolve(RefundOutcome::Approve, $reviewer);

    $record = $record->fresh();
    expect($record->outcome)->toBe('escalate')
        ->and($record->resolution)->toBe('approve')
        ->and($record->resolver?->is($reviewer))->toBeTrue()
        ->and($record->resolved_at?->toDateTimeString())->toBe('2026-09-25 11:30:00')
        ->and($record->isAwaitingReview())->toBeFalse()
        ->and(AssessmentRecord::awaitingReview()->count())->toBe(0);
});

it('refuses a reviewer the application\'s Policy does not permit to resolve', function () {
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    $record = awaitingReview();

    expect(fn () => $record->resolve(RefundOutcome::Approve, Reviewer::create(['name' => 'Bob', 'can_resolve' => false])))
        ->toThrow(AuthorizationException::class);

    expect($record->fresh()->resolution)->toBeNull()
        ->and($record->fresh()->isAwaitingReview())->toBeTrue();
});

it('refuses every reviewer until the application defines who may resolve', function () {
    expect(fn () => awaitingReview()->resolve(RefundOutcome::Approve, reviewer()))
        ->toThrow(AuthorizationException::class);
});

it('refuses to resolve a record that is not awaiting Review', function () {
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    returnAbuse()->assess()->outcome();
    $record = AssessmentRecord::sole();

    expect(fn () => $record->resolve(RefundOutcome::Reject, reviewer()))
        ->toThrow(InvalidResolution::class, "Assessment record {$record->id} is not awaiting Review.");
});

it('refuses to resolve a record already resolved', function () {
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    $record = awaitingReview();
    $record->resolve(RefundOutcome::Approve, reviewer());

    expect(fn () => AssessmentRecord::sole()->resolve(RefundOutcome::Reject, reviewer()))
        ->toThrow(InvalidResolution::class, "Assessment record {$record->id} is already resolved.");
    expect(AssessmentRecord::sole()->resolution)->toBe('approve');
});

it('refuses a stale copy of a record resolved elsewhere in the meantime', function () {
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    $record = awaitingReview();
    AssessmentRecord::sole()->resolve(RefundOutcome::Approve, reviewer());

    expect(fn () => $record->resolve(RefundOutcome::Reject, reviewer()))
        ->toThrow(InvalidResolution::class, "Assessment record {$record->id} is already resolved.");
    expect(AssessmentRecord::sole()->resolution)->toBe('approve');
});

it('refuses a Resolution that is not a case of the automatic Outcome\'s enum', function () {
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    $record = awaitingReview();

    expect(fn () => $record->resolve(ForeignOutcome::Approve, reviewer()))
        ->toThrow(InvalidResolution::class, sprintf('Resolve assessment record %d with a case of %s, not %s.', $record->id, RefundOutcome::class, ForeignOutcome::class));
    expect(AssessmentRecord::sole()->isAwaitingReview())->toBeTrue();
});

it('announces the Resolution with the automatic Outcome it may overturn and the reviewer', function () {
    Event::fake([AssessmentResolved::class]);
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    $record = awaitingReview();
    $reviewer = reviewer();

    $record->resolve(RefundOutcome::Reject, $reviewer);

    Event::assertDispatchedTimes(AssessmentResolved::class, 1);
    Event::assertDispatched(AssessmentResolved::class, fn (AssessmentResolved $event) => $event->record->is($record)
        && $event->outcome === RefundOutcome::Escalate
        && $event->resolution === RefundOutcome::Reject
        && $event->reviewer->is($reviewer));
});

it('announces no Resolution that was refused', function () {
    Event::fake([AssessmentResolved::class]);
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    $record = awaitingReview();

    rescue(fn () => $record->resolve(ForeignOutcome::Approve, reviewer()), report: false);
    rescue(fn () => $record->resolve(RefundOutcome::Approve, Reviewer::create(['name' => 'Bob', 'can_resolve' => false])), report: false);

    Event::assertNotDispatched(AssessmentResolved::class);
});

it('keeps the Outcome that required Review when the record is decided again', function () {
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    $record = awaitingReview();

    $record->decide(new ReturnDecision);
    expect($record->fresh()->only('decision', 'outcome'))->toBe(['decision' => ReviewedReturnDecision::class, 'outcome' => 'escalate']);

    Event::fake([AssessmentResolved::class]);
    $record->fresh()->resolve(RefundOutcome::Approve, reviewer());
    AssessmentRecord::sole()->decide(new ReturnDecision);

    Event::assertDispatched(AssessmentResolved::class, fn (AssessmentResolved $event) => $event->outcome === RefundOutcome::Escalate);
    expect(AssessmentRecord::sole()->outcome)->toBe('escalate');
});

it('announces Review once when stale copies of a record are decided', function () {
    Event::fake([AssessmentAwaitingReview::class]);
    returnAbuse()->assess();
    [$one, $other] = [AssessmentRecord::sole(), AssessmentRecord::sole()];

    $one->decide(new ReviewedReturnDecision);
    $other->decide(new ReviewedReturnDecision);

    Event::assertDispatchedTimes(AssessmentAwaitingReview::class, 1);
});

it('refuses a Resolution that would itself require Review', function () {
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    $record = awaitingReview();

    expect(fn () => $record->resolve(RefundOutcome::Escalate, reviewer()))
        ->toThrow(InvalidResolution::class, sprintf('%s requires Review, so it cannot resolve assessment record %d.', RefundOutcome::class.'::Escalate', $record->id));
    expect(AssessmentRecord::sole()->isAwaitingReview())->toBeTrue();
});

it('refuses a stale copy of a record that never entered Review', function () {
    Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
    returnAbuse()->assess()->outcome();
    $record = AssessmentRecord::sole();
    $record->review_requested_at = now()->toImmutable(); // as if another process saw it enter Review

    expect(fn () => $record->resolve(RefundOutcome::Approve, reviewer()))
        ->toThrow(InvalidResolution::class, "Assessment record {$record->id} is not awaiting Review.");
    expect(AssessmentRecord::sole()->resolution)->toBeNull();
});
