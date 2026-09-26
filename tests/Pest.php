<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\AssessmentRecordPolicy;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\Refund;
use RobertoGallea\Judgment\Tests\Fixtures\RefundAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnRequest;
use RobertoGallea\Judgment\Tests\Fixtures\ReviewedReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\Reviewer;
use RobertoGallea\Judgment\Tests\Fixtures\SupportTicket;
use RobertoGallea\Judgment\Tests\Fixtures\Ticket;
use RobertoGallea\Judgment\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * Assess a SupportTicket, overriding the scripted answers given.
 *
 * @param  array<string, mixed>  $answers
 */
function assessTicket(array $answers = []): Assessment
{
    app()->instance(Engine::class, new FakeEngine([
        'language' => ['english' => .15, 'italian' => .80, 'other' => .05],
        'department' => ['billing' => .70, 'technical' => .20, 'other' => .10],
        'severity' => [.10, .20, .60, .10],
        ...$answers,
    ]));

    return (new SupportTicket(new Ticket('Doppio addebito', 'Mi avete addebitato due volte.')))->assess();
}

/** A ReturnAbuse over a newly stored ReturnRequest, answered by a FakeEngine (abusive: .42, department: billing). */
function returnAbuse(string $reason = 'The zip broke on the first day.'): ReturnAbuse
{
    app()->instance(Engine::class, new FakeEngine([
        'abusive' => .42,
        'department' => ['billing' => .70, 'technical' => .20, 'other' => .10],
    ]));

    return new ReturnAbuse(ReturnRequest::create(['item' => 'Jacket', 'reason' => $reason]));
}

function refundAbuse(): RefundAbuse
{
    return new RefundAbuse(new Refund('Headphones', 120, 'Arrived damaged.'));
}

/**
 * Lint and load a generated class, returning its reflection.
 *
 * @param  class-string  $class
 * @return ReflectionClass<object>
 */
function generatedClass(string $path, string $class): ReflectionClass
{
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $status);
    expect($status)->toBe(0, implode("\n", $output));

    require_once $path;

    return new ReflectionClass($class);
}

/**
 * Write labelled cases to a dataset file.
 *
 * @param  list<array<string, mixed>>  $cases
 */
function labelledCases(array $cases): string
{
    File::put($path = storage_path('calibration.json'), json_encode($cases, JSON_THROW_ON_ERROR));

    return $path;
}

/**
 * A FakeEngine answering whether a return is abusive with the probability given for its reason.
 *
 * @param  array<string, float>  $abusive  reason => probability
 */
function abuseEngine(array $abusive, string $model = 'fake-1.0.0'): FakeEngine
{
    return new FakeEngine(fn (array $evidence) => [
        'abusive' => $abusive[(string) $evidence['customer']['reason']],
        'department' => ['billing' => .70, 'technical' => .20, 'other' => .10],
    ], $model);
}

/** Four labelled returns: abusive .10 (approve), .42 (approve), .45 (reject) and .90 (reject). */
function fourReturns(): string
{
    app()->instance(Engine::class, abuseEngine(['Zip broke' => .10, 'Too small' => .42, 'Changed my mind' => .45, 'Wore it to a wedding' => .90]));

    return labelledCases([
        ['subject' => ['item' => 'Jacket', 'reason' => 'Zip broke'], 'expected' => 'approve'],
        ['subject' => ['item' => 'Boots', 'reason' => 'Too small'], 'expected' => 'approve'],
        ['subject' => ['item' => 'Shoes', 'reason' => 'Changed my mind'], 'expected' => 'reject'],
        ['subject' => ['item' => 'Dress', 'reason' => 'Wore it to a wedding'], 'expected' => 'reject'],
    ]);
}

/** Assess a return, send it to Review and, when a Resolution is given, resolve it so. */
function reviewedReturn(string $reason, ?RefundOutcome $resolution): ReturnRequest
{
    app()->instance(Engine::class, abuseEngine([$reason => .42]));
    $request = ReturnRequest::create(['item' => 'Jacket', 'reason' => $reason]);
    (new ReturnAbuse($request))->assess()->decide(new ReviewedReturnDecision);

    if ($resolution !== null) {
        Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class);
        AssessmentRecord::query()->latest('id')->firstOrFail()->resolve($resolution, Reviewer::create(['name' => 'Ada', 'can_resolve' => true]));
    }

    return $request;
}
