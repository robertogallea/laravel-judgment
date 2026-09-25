<?php

use Illuminate\Support\Facades\Event;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Events\AssessmentCompleted;
use RobertoGallea\Judgment\Events\AssessmentFailed;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Tests\Fixtures\FailingEngine;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;

beforeEach(fn () => Event::fake());

it('announces a completed Assessment with its Judgment', function () {
    app()->instance(Engine::class, new FakeEngine(['abusive' => .80]));
    $judgment = refundAbuse();

    $assessment = $judgment->assess();

    Event::assertDispatchedTimes(AssessmentCompleted::class, 1);
    Event::assertDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->judgment === $judgment && $event->assessment === $assessment);
    Event::assertNotDispatched(AssessmentFailed::class);
});

it('announces a failed Assessment with its Judgment and the failure', function () {
    app()->instance(Engine::class, new FailingEngine);
    $judgment = refundAbuse();

    expect(fn () => $judgment->assess())->toThrow(EngineFailed::class);

    Event::assertDispatched(AssessmentFailed::class, fn (AssessmentFailed $event) => $event->judgment === $judgment
        && $event->exception instanceof EngineFailed
        && $event->exception->getPrevious()?->getMessage() === 'Engine unreachable.');
    Event::assertNotDispatched(AssessmentCompleted::class);
});

it('announces the failure when the Judgment ends Unassessed', function () {
    config(['judgment.failure' => 'unassessed']);
    app()->instance(Engine::class, new FailingEngine);

    $result = refundAbuse()->assess();

    Event::assertDispatched(AssessmentFailed::class, fn (AssessmentFailed $event) => $event->exception === $result->exception);
});
