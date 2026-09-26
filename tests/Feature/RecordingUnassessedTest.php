<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineRequest;
use RobertoGallea\Judgment\EngineResponse;
use RobertoGallea\Judgment\Events\AssessmentFailed;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Exceptions\MalformedEngineResponse;
use RobertoGallea\Judgment\Exceptions\UnassessedNotRecorded;
use RobertoGallea\Judgment\Exceptions\UnrebuildableAssessment;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\CachedListingTone;
use RobertoGallea\Judgment\Tests\Fixtures\FailingEngine;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnRequest;
use RobertoGallea\Judgment\Unassessed;

it('records a failed attempt before throwing, with why the Engine failed and no answers', function () {
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    expect(fn () => $judgment->assess())->toThrow(EngineFailed::class);

    expect(AssessmentRecord::sole())
        ->judgment->toBe(ReturnAbuse::class)
        ->subject->toBeInstanceOf(ReturnRequest::class)
        ->failure_type->toBe(EngineFailed::class)
        ->failure_message->toBe('The Engine failed to assess '.ReturnAbuse::class.': Engine unreachable.')
        ->answers->toBeNull()
        ->engine->toBeNull()
        ->model->toBeNull()
        ->request_id->toBeNull()
        ->outcome->toBeNull();
});

it('ends Unassessed holding the record of the failed attempt', function () {
    config(['judgment.throw_on_failure' => false]);
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    $result = $judgment->assess();

    expect($result)->toBeInstanceOf(Unassessed::class)
        ->and($result->record?->is(AssessmentRecord::sole()))->toBeTrue()
        ->and($result->record?->failure_type)->toBe(EngineFailed::class);
});

it('announces the failure with the record of the failed attempt', function () {
    Event::fake([AssessmentFailed::class]);
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    expect(fn () => $judgment->assess())->toThrow(EngineFailed::class);

    Event::assertDispatched(AssessmentFailed::class, fn (AssessmentFailed $event) => $event->record?->is(AssessmentRecord::sole()) === true);
});

it('ends Unassessed without a record when persistence is off', function () {
    config(['judgment.throw_on_failure' => false, 'judgment.persistence.enabled' => false]);
    Event::fake([AssessmentFailed::class]);
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    expect($judgment->assess()->record)->toBeNull()
        ->and(AssessmentRecord::count())->toBe(0);
    Event::assertDispatched(AssessmentFailed::class, fn (AssessmentFailed $event) => $event->record === null);
});

it('records the Provenance of an unusable Engine response, without its answers', function () {
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FakeEngine(['abusive' => .42], details: ['usage' => ['tokens' => 12]]));

    expect(fn () => $judgment->assess())->toThrow(MalformedEngineResponse::class);

    expect(AssessmentRecord::sole())
        ->failure_type->toBe(MalformedEngineResponse::class)
        ->failure_message->toBe('The Engine did not answer Question "department" on '.ReturnAbuse::class.'.')
        ->engine->toBe('fake')
        ->model->toBe('fake-1.0.0')
        ->request_id->toBe('req-1')
        ->provenance_details->toBe(['usage' => ['tokens' => 12]])
        ->answers->toBeNull();
});

it('still throws the Engine failure when its attempt cannot be recorded, however recording is required, reporting the gap', function () {
    config(['judgment.persistence.required' => true]);
    Exceptions::fake();
    Log::spy();
    $judgment = returnAbuse();
    $failure = new EngineFailed('Engine down.');
    app()->instance(Engine::class, new class($failure) implements Engine
    {
        public function __construct(private readonly EngineFailed $failure) {}

        public function model(): string
        {
            return 'failing-1';
        }

        public function answer(EngineRequest $request): EngineResponse
        {
            throw $this->failure;
        }
    });
    Schema::drop('judgment_assessments');

    expect(fn () => $judgment->assess())->toThrow(fn (EngineFailed $e) => expect($e)->toBe($failure));

    Exceptions::assertReported(fn (UnassessedNotRecorded $e) => $e->judgment === $judgment
        && $e->failure === $failure
        && $e->getPrevious() instanceof QueryException);
    Log::shouldHaveReceived('warning')->with('Judgment not recorded.', Mockery::on(
        fn (array $context) => $context['judgment'] === ReturnAbuse::class && $context['exception'] instanceof UnassessedNotRecorded,
    ));
});

it('still ends Unassessed, without a record, when its attempt cannot be recorded', function () {
    config(['judgment.throw_on_failure' => false, 'judgment.persistence.required' => true]);
    Exceptions::fake();
    Event::fake([AssessmentFailed::class]);
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);
    Schema::drop('judgment_assessments');

    $result = $judgment->assess();

    expect($result)->toBeInstanceOf(Unassessed::class)
        ->and($result->record)->toBeNull()
        ->and($result->exception->getMessage())->toBe('The Engine failed to assess '.ReturnAbuse::class.': Engine unreachable.');
    Exceptions::assertReported(UnassessedNotRecorded::class);
    Event::assertDispatched(AssessmentFailed::class, fn (AssessmentFailed $event) => $event->record === null);
});

it('records a failed attempt of a dispatched Judgment, whether the job fails or completes Unassessed', function (bool $throwOnFailure) {
    config(['queue.default' => 'sync', 'judgment.throw_on_failure' => $throwOnFailure]);
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    try {
        $judgment->dispatch();
    } catch (EngineFailed) {
        expect($throwOnFailure)->toBeTrue();
    }

    expect(AssessmentRecord::sole())
        ->failure_type->toBe(EngineFailed::class)
        ->answers->toBeNull();
})->with(['throwing on failure' => true, 'ending Unassessed' => false]);

it('records each failed attempt of a Subject', function () {
    config(['judgment.throw_on_failure' => false]);
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    $judgment->assess();
    $judgment->assess();

    expect($judgment->request->assessments()->count())->toBe(2);
});

/** A ReturnAbuse assessed once, then failed once: returns the Judgment. */
function answeredThenUnassessed(): ReturnAbuse
{
    config(['judgment.throw_on_failure' => false]);
    $judgment = returnAbuse();
    $judgment->assess();
    app()->instance(Engine::class, new FailingEngine);
    $judgment->assess();

    return $judgment;
}

it('tells answered records from Unassessed attempts', function () {
    answeredThenUnassessed();

    expect(AssessmentRecord::answered()->sole())
        ->answers->toBe(['abusive' => .42, 'department' => ['billing' => .70, 'technical' => .20, 'other' => .10]])
        ->failure_type->toBeNull()
        ->and(AssessmentRecord::unassessed()->sole())
        ->answers->toBeNull()
        ->failure_type->toBe(EngineFailed::class);
});

it('finds the latest attempt of a Subject, even an Unassessed one', function () {
    $judgment = answeredThenUnassessed();

    expect($judgment->request->latestAssessment(ReturnAbuse::class)?->failure_type)->toBe(EngineFailed::class)
        ->and($judgment->request->assessments()->answered()->latest('id')->first()?->answers)->not->toBeNull();
});

it('refuses to rebuild, decide or take the Outcome of an Unassessed attempt', function () {
    answeredThenUnassessed();
    $record = AssessmentRecord::unassessed()->sole();

    expect(fn () => $record->assessment())->toThrow(UnrebuildableAssessment::class, 'Assessment record '.$record->id.' is an Unassessed attempt of '.ReturnAbuse::class.': it has no answers to rebuild.')
        ->and(fn () => $record->decide(new ReturnDecision))->toThrow(UnrebuildableAssessment::class)
        ->and(fn () => $record->outcome())->toThrow(UnrebuildableAssessment::class)
        ->and($record->fresh()->outcome)->toBeNull();
});

it('never serves a cache hit from an Unassessed attempt', function () {
    config(['judgment.throw_on_failure' => false]);
    app()->instance(Engine::class, new FailingEngine);
    (new CachedListingTone('Best jacket ever!!!'))->assess();
    $engine = new FakeEngine(['hyped' => .8]);
    app()->instance(Engine::class, $engine);

    (new CachedListingTone('Best jacket ever!!!'))->assess();
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    $answered = AssessmentRecord::answered()->orderBy('id')->get();
    expect($engine->requests)->toHaveCount(1)
        ->and($answered)->toHaveCount(2)
        ->and($answered[0]->cached_from_id)->toBeNull()
        ->and($answered[1]->cached_from_id)->toBe($answered[0]->id);
});

it('still throws the Engine failure when recording its attempt raises an error', function () {
    Exceptions::fake();
    AssessmentRecord::creating(fn () => throw new TypeError('A bug in an observer.'));
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    expect(fn () => $judgment->assess())->toThrow(EngineFailed::class);
    Exceptions::assertReported(fn (UnassessedNotRecorded $e) => $e->getPrevious() instanceof TypeError);
});
