<?php

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Events\AssessmentAwaitingReview;
use RobertoGallea\Judgment\Events\AssessmentDecided;
use RobertoGallea\Judgment\Jobs\DecideAssessment;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\FailingEngine;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\ListingTone;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\ReviewedReturnDecision;

beforeEach(fn () => config(['queue.default' => 'sync']));

it('decides a dispatched Judgment with its default Decision, recording the Outcome', function () {
    Event::fake([AssessmentDecided::class]);

    returnAbuse()->dispatch();

    expect(AssessmentRecord::sole()->only('decision', 'decision_version', 'outcome'))
        ->toBe(['decision' => ReturnDecision::class, 'decision_version' => '2', 'outcome' => 'approve']);
    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->outcome === RefundOutcome::Approve
        && $event->record?->is(AssessmentRecord::sole()) === true);
});

it('starts Review for a dispatched Judgment whose Outcome requires it', function () {
    Event::fake([AssessmentAwaitingReview::class]);
    app()->instance(ReturnDecision::class, new ReviewedReturnDecision);

    returnAbuse()->dispatch();

    expect(AssessmentRecord::sole()->isAwaitingReview())->toBeTrue();
    Event::assertDispatchedTimes(AssessmentAwaitingReview::class, 1);
    Event::assertDispatched(AssessmentAwaitingReview::class, fn (AssessmentAwaitingReview $event) => $event->outcome === RefundOutcome::Escalate
        && $event->record?->is(AssessmentRecord::sole()) === true);
});

it('decides a dispatched Judgment in the assessing job with persistence off', function () {
    config(['judgment.persistence.enabled' => false]);
    Event::fake([AssessmentDecided::class]);

    returnAbuse()->dispatch();

    Event::assertDispatched(AssessmentDecided::class, fn (AssessmentDecided $event) => $event->outcome === RefundOutcome::Approve && $event->record === null);
});

it('only assesses a dispatched Judgment with no default Decision', function () {
    Event::fake([AssessmentDecided::class]);
    app()->instance(Engine::class, new FakeEngine(['hyped' => .30]));

    (new ListingTone('Best jacket ever!!!', 'Is the listing title exaggerated?'))->dispatch();

    expect(AssessmentRecord::sole()->answers)->not->toBeNull()
        ->and(AssessmentRecord::sole()->outcome)->toBeNull();
    Event::assertNotDispatched(AssessmentDecided::class);
});

it('never decides an Unassessed attempt', function () {
    config(['judgment.throw_on_failure' => false]);
    Event::fake([AssessmentDecided::class]);
    $judgment = returnAbuse();
    app()->instance(Engine::class, new FailingEngine);

    $judgment->dispatch();

    expect(AssessmentRecord::sole()->isUnassessed())->toBeTrue()
        ->and(AssessmentRecord::sole()->outcome)->toBeNull();
    Event::assertNotDispatched(AssessmentDecided::class);
});

it('fails only the decision when the Decision throws, and retries it without asking the Engine again', function () {
    $judgment = returnAbuse();
    $engine = app(Engine::class);
    app()->instance(ReturnDecision::class, new class implements Decision
    {
        public function __invoke(Assessment $assessment, ReturnAbuse $judgment): RefundOutcome
        {
            throw new RuntimeException('The Decision broke.');
        }
    });
    $failed = [];
    Event::listen(JobFailed::class, function (JobFailed $event) use (&$failed) {
        $failed[] = $event->job;
    });

    expect(fn () => $judgment->dispatch())->toThrow(RuntimeException::class, 'The Decision broke.')
        ->and(AssessmentRecord::sole()->answers)->not->toBeNull()
        ->and(AssessmentRecord::sole()->outcome)->toBeNull();

    $decide = collect($failed)->sole(fn ($job) => $job->resolveName() === DecideAssessment::class);
    app()->forgetInstance(ReturnDecision::class);
    $decide->fire();

    expect(AssessmentRecord::sole()->outcome)->toBe('approve')
        ->and($engine->requests)->toHaveCount(1);
});

it('decides on the assessing job\'s connection and queue, tried as configured', function (?string $queue, mixed $tries, int $expected) {
    config(['judgment.queue.connection' => 'sync', 'judgment.queue.queue' => 'judgments', 'judgment.queue.decide_tries' => $tries]);
    $processed = [];
    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$processed) {
        $job = unserialize($event->job->payload()['data']['command']);
        $processed[$job::class] = [$job->connection, $job->queue, $job->tries];
    });

    $dispatched = returnAbuse()->dispatch();
    if ($queue !== null) {
        $dispatched->onQueue($queue);
    }
    unset($dispatched);

    expect($processed[DecideAssessment::class])->toBe(['sync', $queue ?? 'judgments', $expected]);
})->with([
    'configured queue, default tries' => [null, null, 3],
    'queue chosen at dispatch' => ['urgent', null, 3],
    'configured tries' => [null, 5, 5],
    'zero tries' => [null, 0, 1],
]);
