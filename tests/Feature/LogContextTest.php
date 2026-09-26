<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Events\AssessmentDecided;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\CachedListingTone;
use RobertoGallea\Judgment\Tests\Fixtures\FailingEngine;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundDecision;
use RobertoGallea\Judgment\Tests\Fixtures\StrictRefundDecision;
use RobertoGallea\Judgment\Tests\Fixtures\StrictReturnDecision;

beforeEach(fn () => Log::spy());

it('logs a completed Assessment with its Judgment, model and engine request id', function () {
    app()->instance(Engine::class, new FakeEngine(['abusive' => .80]));

    refundAbuse()->assess();

    Log::shouldHaveReceived('info')->once()->with('Judgment assessed.', [
        'judgment' => RefundAbuse::class,
        'engine' => 'fake',
        'model' => 'fake-1.0.0',
        'request_id' => 'req-1',
    ]);
});

it('logs a cache hit apart from an Engine answer, pointing at the original record', function () {
    app()->instance(Engine::class, new FakeEngine(['hyped' => .80]));

    (new CachedListingTone('Best jacket ever!!!'))->assess();
    (new CachedListingTone('Best jacket ever!!!'))->assess();

    Log::shouldHaveReceived('info')->with('Judgment assessed.', Mockery::any())->once();
    Log::shouldHaveReceived('info')->with('Judgment assessed from cache.', [
        'judgment' => CachedListingTone::class,
        'engine' => 'fake',
        'model' => 'fake-1.0.0',
        'request_id' => 'req-1',
        'cached_from' => AssessmentRecord::orderBy('id')->value('id'),
    ])->once();
});

it('logs a failed Assessment with its Judgment and the failure', function () {
    config(['judgment.throw_on_failure' => false]);
    app()->instance(Engine::class, new FailingEngine);

    $result = refundAbuse()->assess();

    Log::shouldHaveReceived('warning')->once()->with('Judgment unassessed.', [
        'judgment' => RefundAbuse::class,
        'exception' => $result->exception,
    ]);
});

it('logs the model and engine request id of a malformed Engine response', function () {
    config(['judgment.throw_on_failure' => false]);
    app()->instance(Engine::class, new FakeEngine([]));

    $result = refundAbuse()->assess();

    Log::shouldHaveReceived('warning')->once()->with('Judgment unassessed.', [
        'judgment' => RefundAbuse::class,
        'engine' => 'fake',
        'model' => 'fake-1.0.0',
        'request_id' => 'req-1',
        'exception' => $result->exception,
    ]);
});

it('logs the Outcome with the Decision that produced it', function () {
    app()->instance(Engine::class, new FakeEngine(['abusive' => .80]));

    refundAbuse()->assess()->outcome();

    Log::shouldHaveReceived('info')->with('Judgment decided.', [
        'judgment' => RefundAbuse::class,
        'engine' => 'fake',
        'model' => 'fake-1.0.0',
        'request_id' => 'req-1',
        'decision' => RefundDecision::class,
        'outcome' => 'reject',
    ])->once();
});

it('logs the Outcome of a Decision given explicitly', function () {
    app()->instance(Engine::class, new FakeEngine(['abusive' => .60]));

    refundAbuse()->assess()->decide(new StrictRefundDecision);

    Log::shouldHaveReceived('info')->with('Judgment decided.', Mockery::subset([
        'decision' => StrictRefundDecision::class,
        'outcome' => 'reject',
    ]));
});

it('logs to the configured channel', function () {
    config(['judgment.log_channel' => 'judgment']);
    $channel = Mockery::spy(LoggerInterface::class);
    Log::shouldReceive('channel')->with('judgment')->andReturn($channel);
    app()->instance(Engine::class, new FakeEngine(['abusive' => .80]));

    refundAbuse()->assess()->outcome();

    $channel->shouldHaveReceived('info')->with('Judgment assessed.', Mockery::any())->once();
    $channel->shouldHaveReceived('info')->with('Judgment decided.', Mockery::any())->once();
});

it('writes no log entries when logging is turned off', function () {
    config(['judgment.log' => false]);
    app()->instance(Engine::class, new FakeEngine(['abusive' => .80]));

    refundAbuse()->assess()->outcome();

    Log::shouldNotHaveReceived('info');
});

it('logs no decision for a Replay', function () {
    returnAbuse()->assess();

    AssessmentRecord::sole()->assessment()->decide(new StrictReturnDecision);

    Log::shouldNotHaveReceived('info', ['Judgment decided.', Mockery::any()]);
});

it('logs the Outcome of an Assessment it did not record', function () {
    config(['judgment.persistence.enabled' => false]);
    app()->instance(Engine::class, new FakeEngine(['abusive' => .80]));

    refundAbuse()->assess()->outcome();

    Log::shouldHaveReceived('info')->with('Judgment decided.', Mockery::subset(['outcome' => 'reject']))->once();
});

it('logs the Outcome decided through the record', function () {
    returnAbuse()->assess();
    $record = AssessmentRecord::sole();

    $record->outcome();
    $record->decide(new StrictReturnDecision);

    Log::shouldHaveReceived('info')->with('Judgment decided.', Mockery::subset(['outcome' => 'approve']))->once();
    Log::shouldHaveReceived('info')->with('Judgment decided.', Mockery::subset(['decision' => StrictReturnDecision::class, 'outcome' => 'reject']))->once();
});

it('logs the Outcome even when a listener of the decision throws', function () {
    Event::listen(AssessmentDecided::class, fn () => throw new RuntimeException('Action failed.'));
    $assessment = returnAbuse()->assess();

    expect(fn () => $assessment->outcome())->toThrow(RuntimeException::class, 'Action failed.');
    Log::shouldHaveReceived('info')->with('Judgment decided.', Mockery::subset(['outcome' => 'approve']))->once();
});
