<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Events\AssessmentCompleted;
use RobertoGallea\Judgment\Events\AssessmentFailed;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Facades\Judge;
use RobertoGallea\Judgment\Jobs\AssessJudgment;
use RobertoGallea\Judgment\Tests\Fixtures\FailingEngine;
use RobertoGallea\Judgment\Tests\Fixtures\ListingTone;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;

beforeEach(fn () => config(['queue.default' => 'sync']));

it('assesses a dispatched Judgment on the queue', function () {
    Event::fake([AssessmentCompleted::class]);

    Judge::dispatch(returnAbuse());

    Event::assertDispatched(AssessmentCompleted::class, fn (AssessmentCompleted $event) => $event->judgment instanceof ReturnAbuse
        && $event->assessment->likelihood('abusive')->probability() === .42);
});

it('fires AssessmentFailed when the Engine fails a dispatched Judgment, and fails the job', function () {
    Event::fake([AssessmentFailed::class]);
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    expect(fn () => $judgment->dispatch())->toThrow(EngineFailed::class);

    Event::assertDispatched(AssessmentFailed::class, fn (AssessmentFailed $event) => $event->judgment instanceof ReturnAbuse);
});

it('completes the job Unassessed when configured to', function () {
    config(['judgment.failure' => 'unassessed']);
    Event::fake([AssessmentFailed::class]);
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    $judgment->dispatch();

    Event::assertDispatchedTimes(AssessmentFailed::class, 1);
});

it('queues the Judgment with its Eloquent Subject by reference, restored fresh', function () {
    Queue::fake();
    $judgment = returnAbuse('The zip broke.');

    Judge::dispatch($judgment);

    Queue::assertPushed(AssessJudgment::class, fn (AssessJudgment $job) => $job->judgment === $judgment);

    $payload = serialize(Queue::pushed(AssessJudgment::class)->sole());
    $judgment->request->update(['reason' => 'The zip broke, and the lining tore.']);
    $restored = unserialize($payload);

    expect($payload)->not->toContain('The zip broke.')
        ->and($restored->judgment)->toBeInstanceOf(ReturnAbuse::class)
        ->and($restored->judgment->request->is($judgment->request))->toBeTrue()
        ->and($restored->judgment->evidence()['customer']['reason']->text)->toBe('The zip broke, and the lining tore.');
});

it('restores a queued Judgment with its private state', function () {
    Queue::fake();

    (new ListingTone('Best jacket ever!!!', 'Is the listing title exaggerated?'))->dispatch();

    $restored = unserialize(serialize(Queue::pushed(AssessJudgment::class)->sole()));

    expect($restored->judgment->questions()['hyped']->instructions)->toBe('Is the listing title exaggerated?');
});

it('tries a queued assessment once, on the configured connection and queue', function () {
    Queue::fake();
    config(['judgment.queue.connection' => 'redis', 'judgment.queue.queue' => 'judgments']);

    returnAbuse()->dispatch();
    returnAbuse()->dispatch()->onQueue('urgent');

    [$configured, $chained] = Queue::pushed(AssessJudgment::class)->all();

    expect([$configured->tries, $configured->connection, $configured->queue])->toBe([1, 'redis', 'judgments'])
        ->and($chained->queue)->toBe('urgent');
});

it('never retries a queued assessment without limit', function (mixed $tries) {
    Queue::fake();
    config(['judgment.queue.tries' => $tries]);

    returnAbuse()->dispatch();

    expect(Queue::pushed(AssessJudgment::class)->sole()->tries)->toBe(1);
})->with(['null' => [null], 'empty env' => [''], 'zero' => [0]]);

it('fails the job when the Subject was deleted before it ran', function () {
    Queue::fake();
    $judgment = returnAbuse();
    $judgment->dispatch();
    $payload = serialize(Queue::pushed(AssessJudgment::class)->sole());

    $judgment->request->delete();

    expect(fn () => unserialize($payload))->toThrow(ModelNotFoundException::class)
        ->and(Queue::pushed(AssessJudgment::class)->sole()->deleteWhenMissingModels ?? false)->toBeFalse();
});
