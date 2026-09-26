<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineManager;
use RobertoGallea\Judgment\Tests\Fixtures\FakeEngine;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnRequest;
use RobertoGallea\Judgment\Tests\Fixtures\ReviewedReturnDecision;
use RobertoGallea\Judgment\UntrustedText;

afterEach(fn () => File::delete(storage_path('calibration.json')));

/** @param  array<string, mixed>  $options */
function calibrate(array $options): string
{
    $status = Artisan::call('judgment:eval', $options);
    $output = Artisan::output();
    expect($status)->toBe(0, $output);

    return $output;
}

/** A pattern matching a table row holding the cells given, in order. */
function row(string ...$cells): string
{
    return '/\|\s*'.implode('\s*\|\s*', array_map(fn (string $cell) => preg_quote($cell, '/'), $cells)).'\s*\|/';
}

it('calibrates the default Decision against a labelled dataset of Subjects', function () {
    app()->instance(Engine::class, abuseEngine(['Zip broke' => .10, 'Wore it to a wedding' => .90, 'Changed my mind' => .70]));

    $report = calibrate(['judgment' => ReturnAbuse::class, '--dataset' => labelledCases([
        ['subject' => ['item' => 'Jacket', 'reason' => 'Zip broke'], 'expected' => 'approve'],
        ['subject' => ['item' => 'Dress', 'reason' => 'Wore it to a wedding'], 'expected' => 'reject'],
        ['subject' => ['item' => 'Shoes', 'reason' => 'Changed my mind'], 'expected' => 'approve'],
    ])]);

    expect($report)->toMatch(row('fake-1.0.0', 'ReturnDecision v2', 'en', '3', '0', '0.0%', '66.7% (2/3)'));
});

it('reports the Review rate of each Decision, measuring accuracy over the Outcomes decided without Review', function () {
    $report = calibrate([
        'judgment' => ReturnAbuse::class,
        '--dataset' => fourReturns(),
        '--decision' => [ReturnDecision::class, ReviewedReturnDecision::class],
    ]);

    expect($report)
        ->toMatch(row('ReturnDecision v2', 'en', '4', '0', '0.0%', '75.0% (3/4)'))
        ->toMatch(row('ReviewedReturnDecision', 'en', '4', '0', '50.0%', '100.0% (2/2)'));
});

it('reports per band of each answer how many cases fall in it, what they should be, and how the Decision does', function () {
    $report = calibrate([
        'judgment' => ReturnAbuse::class,
        '--dataset' => fourReturns(),
        '--decision' => [ReviewedReturnDecision::class],
    ]);

    expect($report)
        ->toMatch(row('abusive', '0.1–0.2', '1', 'approve 1', '100.0%', '0.0%'))
        ->toMatch(row('abusive', '0.4–0.5', '2', 'approve 1, reject 1', '—', '100.0%'))
        ->toMatch(row('abusive', '0.9–1.0', '1', 'reject 1', '100.0%', '0.0%'))
        ->toMatch(row('department', '0.5–0.6', '4', 'approve 2, reject 2', '100.0%', '50.0%'))
        ->not->toMatch(row('abusive', '0.0–0.1'));
});

it('finds a dataset Subject by its key', function () {
    app()->instance(Engine::class, abuseEngine(['Wore it to a wedding' => .90]));
    $stored = ReturnRequest::create(['item' => 'Dress', 'reason' => 'Wore it to a wedding']);

    $report = calibrate(['judgment' => ReturnAbuse::class, '--dataset' => labelledCases([
        ['subject' => $stored->id, 'expected' => 'reject'],
    ])]);

    expect($report)->toMatch(row('ReturnDecision v2', 'en', '1', '0', '0.0%', '100.0% (1/1)'));
});

it('uses past Resolutions as labels when no dataset is given, asking over the Evidence as it was recorded', function () {
    reviewedReturn('Wore it to a wedding', RefundOutcome::Reject);
    reviewedReturn('Zip broke', RefundOutcome::Approve)->update(['reason' => 'Edited since']);
    reviewedReturn('Still waiting', null);
    app()->instance(Engine::class, $engine = abuseEngine(['Wore it to a wedding' => .90, 'Zip broke' => .10]));

    $report = calibrate(['judgment' => ReturnAbuse::class]);

    expect($report)->toMatch(row('ReturnDecision v2', 'en', '2', '0', '0.0%', '100.0% (2/2)'))
        ->and(array_map(fn ($request) => $request->evidence['customer']['reason'], $engine->requests))
        ->toContainEqual(new UntrustedText('Wore it to a wedding'), new UntrustedText('Zip broke'));
});

it('compares two pinned model versions on the same cases, never mixing their results', function () {
    $probabilities = [
        'fake-1.0.0' => ['Zip broke' => .10, 'Too small' => .42, 'Changed my mind' => .45, 'Wore it to a wedding' => .90],
        'fake-2.0.0' => ['Zip broke' => .05, 'Too small' => .20, 'Changed my mind' => .80, 'Wore it to a wedding' => .95],
    ];
    app(EngineManager::class)->extend('fake', fn ($app, array $config) => abuseEngine($probabilities[$config['model']], $config['model']));
    config()->set('judgment.engines.current', ['driver' => 'fake', 'model' => 'fake-1.0.0']);
    config()->set('judgment.engines.candidate', ['driver' => 'fake', 'model' => 'fake-2.0.0']);

    $report = calibrate(['judgment' => ReturnAbuse::class, '--dataset' => fourReturns(), '--engine' => ['current', 'candidate']]);

    expect($report)
        ->toMatch(row('fake-1.0.0', 'ReturnDecision v2', 'en', '4', '0', '0.0%', '75.0% (3/4)'))
        ->toMatch(row('fake-2.0.0', 'ReturnDecision v2', 'en', '4', '0', '0.0%', '100.0% (4/4)'));
});

it('reports each Evidence language apart', function () {
    app()->instance(Engine::class, abuseEngine(['Zip broke' => .10, 'Cerniera rotta' => .70]));

    $report = calibrate(['judgment' => ReturnAbuse::class, '--dataset' => labelledCases([
        ['subject' => ['item' => 'Jacket', 'reason' => 'Zip broke'], 'expected' => 'approve'],
        ['subject' => ['item' => 'Giacca', 'reason' => 'Cerniera rotta', 'language' => 'it'], 'expected' => 'approve'],
    ])]);

    expect($report)
        ->toMatch(row('ReturnDecision v2', 'en', '1', '0', '0.0%', '100.0% (1/1)'))
        ->toMatch(row('ReturnDecision v2', 'it', '1', '0', '0.0%', '0.0% (0/1)'));
});

it('counts a case the Engine fails on as unassessed and carries on', function () {
    app()->instance(Engine::class, new FakeEngine(fn (array $evidence) => (string) $evidence['customer']['reason'] === 'Zip broke'
        ? ['abusive' => .10, 'department' => ['billing' => .70, 'technical' => .20, 'other' => .10]]
        : throw new RuntimeException('Engine timed out.')));

    $report = calibrate(['judgment' => ReturnAbuse::class, '--dataset' => labelledCases([
        ['subject' => ['item' => 'Jacket', 'reason' => 'Zip broke'], 'expected' => 'approve'],
        ['subject' => ['item' => 'Dress', 'reason' => 'Wore it to a wedding'], 'expected' => 'reject'],
    ])]);

    expect($report)->toMatch(row('ReturnDecision v2', 'en', '2', '1', '0.0%', '100.0% (1/1)'));
});

it('refuses a label that is not an Outcome of the Decision', function () {
    app()->instance(Engine::class, abuseEngine(['Zip broke' => .10]));

    $status = Artisan::call('judgment:eval', ['judgment' => ReturnAbuse::class, '--dataset' => labelledCases([
        ['subject' => ['item' => 'Jacket', 'reason' => 'Zip broke'], 'expected' => 'aprove'],
    ])]);

    expect($status)->toBe(1)
        ->and(Artisan::output())->toContain('"aprove" is not an Outcome of ReturnDecision: expected approve, escalate or reject.');
});

it('skips a Resolution whose Subject no longer exists, and says so', function () {
    reviewedReturn('Wore it to a wedding', RefundOutcome::Reject);
    reviewedReturn('Zip broke', RefundOutcome::Approve)->delete();
    app()->instance(Engine::class, abuseEngine(['Wore it to a wedding' => .90]));

    $report = calibrate(['judgment' => ReturnAbuse::class]);

    expect($report)->toMatch(row('ReturnDecision v2', 'en', '1', '0', '0.0%', '100.0% (1/1)'))
        ->toContain('Skipped 1 Resolution whose Subject no longer exists.');
});

it('fails when there are no labelled cases', function () {
    expect(Artisan::call('judgment:eval', ['judgment' => ReturnAbuse::class]))->toBe(1)
        ->and(Artisan::output())->toContain('No labelled cases to calibrate '.ReturnAbuse::class.' against.');
});

it('counts one case per Evidence, labelled with its latest Resolution', function () {
    reviewedReturn('Zip broke', RefundOutcome::Reject);
    reviewedReturn('Zip broke', RefundOutcome::Approve);
    app()->instance(Engine::class, abuseEngine(['Zip broke' => .10]));

    $report = calibrate(['judgment' => ReturnAbuse::class]);

    expect($report)->toMatch(row('ReturnDecision v2', 'en', '1', '0', '0.0%', '100.0% (1/1)'));
});

it('refuses a label that itself requires Review, as a Resolution cannot be one', function () {
    app()->instance(Engine::class, abuseEngine(['Zip broke' => .10]));

    $status = Artisan::call('judgment:eval', ['judgment' => ReturnAbuse::class, '--dataset' => labelledCases([
        ['subject' => ['item' => 'Jacket', 'reason' => 'Zip broke'], 'expected' => 'escalate'],
    ])]);

    expect($status)->toBe(1)
        ->and(Artisan::output())->toContain('"escalate" requires Review, so it cannot be the right Outcome of a case.');
});

it('counts a failed case under the model version its Engine reports', function () {
    app()->instance(Engine::class, new FakeEngine(fn (array $evidence) => (string) $evidence['customer']['reason'] === 'Zip broke'
        ? ['abusive' => .10, 'department' => ['billing' => .70, 'technical' => .20, 'other' => .10]]
        : throw new RuntimeException('Engine timed out.'), 'fake-latest', reports: 'fake-1.2.0'));

    $report = calibrate(['judgment' => ReturnAbuse::class, '--dataset' => labelledCases([
        ['subject' => ['item' => 'Dress', 'reason' => 'Wore it to a wedding'], 'expected' => 'reject'],
        ['subject' => ['item' => 'Jacket', 'reason' => 'Zip broke'], 'expected' => 'approve'],
    ])]);

    expect($report)->toMatch(row('fake-1.2.0', 'ReturnDecision v2', 'en', '2', '1', '0.0%', '100.0% (1/1)'))
        ->not->toContain('fake-latest');
});

it('prints the whole report as JSON for tools and CI', function () {
    $status = Artisan::call('judgment:eval', [
        'judgment' => ReturnAbuse::class,
        '--dataset' => fourReturns(),
        '--decision' => [ReviewedReturnDecision::class],
        '--json' => true,
    ]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($report['skipped'])->toBe(0)
        ->and($report['results'])->toHaveCount(1)
        ->and($report['results'][0]['identity'])->toMatchArray([
            'model' => 'fake-1.0.0',
            'decision' => ReviewedReturnDecision::class,
            'decisionVersion' => null,
            'language' => 'en',
        ])
        ->and($report['results'][0]['identity']['questions'])->toMatch('/^[0-9a-f]{16,}$/')
        ->and($report['results'][0])->toMatchArray([
            'cases' => 4,
            'unassessed' => 0,
            'sentToReview' => 2,
            'automatic' => 2,
            'correct' => 2,
            'reviewRate' => 0.5,
            'accuracy' => 1.0,
        ])
        ->and($report['results'][0]['bands'])->toBe([
            ['question' => 'abusive', 'from' => 0.1, 'to' => 0.2, 'cases' => 1, 'expected' => ['approve' => 1], 'sentToReview' => 0, 'automatic' => 1, 'correct' => 1, 'reviewRate' => 0.0, 'accuracy' => 1.0],
            ['question' => 'abusive', 'from' => 0.4, 'to' => 0.5, 'cases' => 2, 'expected' => ['approve' => 1, 'reject' => 1], 'sentToReview' => 2, 'automatic' => 0, 'correct' => 0, 'reviewRate' => 1.0, 'accuracy' => null],
            ['question' => 'abusive', 'from' => 0.9, 'to' => 1.0, 'cases' => 1, 'expected' => ['reject' => 1], 'sentToReview' => 0, 'automatic' => 1, 'correct' => 1, 'reviewRate' => 0.0, 'accuracy' => 1.0],
            ['question' => 'department', 'from' => 0.5, 'to' => 0.6, 'cases' => 4, 'expected' => ['approve' => 2, 'reject' => 2], 'sentToReview' => 2, 'automatic' => 2, 'correct' => 2, 'reviewRate' => 0.5, 'accuracy' => 1.0],
        ]);
});

it('counts skipped Resolutions in the JSON report, printing nothing else', function () {
    reviewedReturn('Wore it to a wedding', RefundOutcome::Reject);
    reviewedReturn('Zip broke', RefundOutcome::Approve)->delete();
    app()->instance(Engine::class, abuseEngine(['Wore it to a wedding' => .90]));

    Artisan::call('judgment:eval', ['judgment' => ReturnAbuse::class, '--json' => true]);

    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['skipped'])->toBe(1)
        ->and($report['results'])->toHaveCount(1)
        ->and($report['results'][0]['cases'])->toBe(1);
});

it('prints an error as JSON and fails', function () {
    app()->instance(Engine::class, abuseEngine(['Zip broke' => .10]));

    $status = Artisan::call('judgment:eval', ['judgment' => ReturnAbuse::class, '--json' => true, '--dataset' => labelledCases([
        ['subject' => ['item' => 'Jacket', 'reason' => 'Zip broke'], 'expected' => 'aprove'],
    ])]);

    expect($status)->toBe(1)
        ->and(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['error' => '"aprove" is not an Outcome of ReturnDecision: expected approve, escalate or reject.']);
});

it('prints any error as JSON, such as an Engine connection that is not configured', function () {
    $status = Artisan::call('judgment:eval', ['judgment' => ReturnAbuse::class, '--dataset' => fourReturns(), '--engine' => ['nope'], '--json' => true]);

    expect($status)->toBe(1)
        ->and(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['error'])->toContain('"nope" is not configured');
});
