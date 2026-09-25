<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Events\AssessmentAwaitingReview;
use RobertoGallea\Judgment\Events\AssessmentCompleted;
use RobertoGallea\Judgment\Exceptions\AssessmentNotRecorded;
use RobertoGallea\Judgment\Tests\Fixtures\CachedListingTone;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReviewedReturnDecision;

/** Make recording fail as when the package's migration has not been run. */
function breakRecording(): void
{
    Schema::drop('judgment_assessments');
}

it('refuses an Assessment it cannot record, keeping its answers on the exception', function () {
    $judgment = returnAbuse();
    breakRecording();

    try {
        $judgment->assess();
        $this->fail('The unrecorded Assessment was returned.');
    } catch (AssessmentNotRecorded $e) {
        expect($e->assessment)->toBeInstanceOf(Assessment::class)
            ->and($e->assessment->likelihood('abusive')->probability())->toBe(.42)
            ->and($e->getPrevious())->toBeInstanceOf(QueryException::class);
    }
});

it('refuses an unrecorded Assessment even when failures end Unassessed', function () {
    config(['judgment.throw_on_failure' => false]);
    $judgment = returnAbuse();
    breakRecording();

    expect(fn () => $judgment->assess())->toThrow(AssessmentNotRecorded::class);
});

it('neither announces nor caches an Assessment it cannot record', function () {
    Event::fake([AssessmentCompleted::class]);
    $engine = new FakeEngine(['hyped' => .8]);
    app()->instance(Engine::class, $engine);
    breakRecording();

    expect(fn () => (new CachedListingTone('Best jacket ever!!!'))->assess())->toThrow(AssessmentNotRecorded::class)
        ->and(fn () => (new CachedListingTone('Best jacket ever!!!'))->assess())->toThrow(AssessmentNotRecorded::class)
        ->and($engine->requests)->toHaveCount(2);
    Event::assertNotDispatched(AssessmentCompleted::class);
});

it('returns an Assessment it cannot record when recording is best-effort, reporting the failure', function () {
    config(['judgment.persistence.required' => false]);
    Exceptions::fake();
    Log::spy();
    $judgment = returnAbuse();
    breakRecording();

    $assessment = $judgment->assess();

    expect($assessment->likelihood('abusive')->probability())->toBe(.42);
    Exceptions::assertReported(fn (AssessmentNotRecorded $e) => $e->assessment === $assessment);
    Log::shouldHaveReceived('warning')->once()->with('Judgment not recorded.', Mockery::on(
        fn (array $context) => $context['judgment'] === ReturnAbuse::class && $context['request_id'] === 'req-1' && $context['exception'] instanceof AssessmentNotRecorded,
    ));
});

it('announces a best-effort Assessment it could not record without a record', function () {
    config(['judgment.persistence.required' => false]);
    Event::fake([AssessmentCompleted::class]);
    $judgment = returnAbuse();
    breakRecording();

    $assessment = $judgment->assess();

    Event::assertDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->assessment === $assessment && $event->record === null);
});

it('refuses an Outcome it cannot record', function () {
    $assessment = returnAbuse()->assess();
    breakRecording();

    expect(fn () => $assessment->outcome())->toThrow(AssessmentNotRecorded::class);
});

it('neither announces nor logs as decided an Outcome it cannot record', function () {
    Event::fake([AssessmentAwaitingReview::class]);
    Log::spy();
    $assessment = returnAbuse()->assess();
    breakRecording();

    expect(fn () => $assessment->decide(new ReviewedReturnDecision))->toThrow(AssessmentNotRecorded::class);
    Event::assertNotDispatched(AssessmentAwaitingReview::class);
    Log::shouldNotHaveReceived('info', ['Judgment decided.', Mockery::any()]);
});

it('returns an Outcome it cannot record when recording is best-effort, still announcing Review', function () {
    config(['judgment.persistence.required' => false]);
    Exceptions::fake();
    Event::fake([AssessmentAwaitingReview::class]);
    $assessment = returnAbuse()->assess();
    breakRecording();

    expect($assessment->decide(new ReviewedReturnDecision))->toBe(RefundOutcome::Escalate);
    Exceptions::assertReported(AssessmentNotRecorded::class);
    Event::assertDispatched(AssessmentAwaitingReview::class, fn (AssessmentAwaitingReview $event) => $event->assessment === $assessment
        && $event->outcome === RefundOutcome::Escalate
        && $event->record === null);
});

it('refuses to decide the Assessment it could not record', function () {
    Event::fake([AssessmentAwaitingReview::class]);
    $judgment = returnAbuse();
    breakRecording();

    try {
        $judgment->assess();
        $this->fail('The unrecorded Assessment was returned.');
    } catch (AssessmentNotRecorded $e) {
        expect(fn () => $e->assessment->decide(new ReviewedReturnDecision))->toThrow(AssessmentNotRecorded::class);
        Event::assertNotDispatched(AssessmentAwaitingReview::class);
    }
});

it('asks the Engine again after a best-effort Assessment it could not record, having no record to point a cache hit at', function () {
    config(['judgment.persistence.required' => false]);
    Exceptions::fake();
    $engine = new FakeEngine(['hyped' => .8]);
    app()->instance(Engine::class, $engine);
    breakRecording();

    (new CachedListingTone('Best jacket ever!!!'))->assess();
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    expect($engine->requests)->toHaveCount(2);
});
