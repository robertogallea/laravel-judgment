<?php

use Illuminate\Support\Facades\File;
use RobertoGallea\Judgment\Calibration\Calibration;
use RobertoGallea\Judgment\Calibration\CalibrationBand;
use RobertoGallea\Judgment\Calibration\CalibrationIdentity;
use RobertoGallea\Judgment\Calibration\CalibrationResult;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\EngineManager;
use RobertoGallea\Judgment\Exceptions\InvalidCalibration;
use RobertoGallea\Judgment\Support\AssessmentRecorder;
use RobertoGallea\Judgment\Tests\Fixtures\RefundOutcome;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnDecision;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnRequest;
use RobertoGallea\Judgment\Tests\Fixtures\ReviewedReturnDecision;

afterEach(fn () => File::delete(storage_path('calibration.json')));

it('calibrates the default Decision against a dataset, reporting each result with its identity and figures', function () {
    app()->instance(Engine::class, abuseEngine(['Zip broke' => .10, 'Wore it to a wedding' => .90, 'Changed my mind' => .70]));

    $report = Calibration::for(ReturnAbuse::class)->fromDataset(labelledCases([
        ['subject' => ['item' => 'Jacket', 'reason' => 'Zip broke'], 'expected' => 'approve'],
        ['subject' => ['item' => 'Dress', 'reason' => 'Wore it to a wedding'], 'expected' => 'reject'],
        ['subject' => ['item' => 'Shoes', 'reason' => 'Changed my mind'], 'expected' => 'approve'],
    ]))->run();

    expect($report->skipped)->toBe(0)
        ->and($report->results)->toHaveCount(1);

    $result = $report->results[0];
    expect($result->identity)->toEqual(new CalibrationIdentity(
        AssessmentRecorder::fingerprint((new ReturnAbuse(new ReturnRequest))->questions()),
        'fake-1.0.0',
        ReturnDecision::class,
        '2',
        'en',
    ))
        ->and([$result->cases, $result->unassessed, $result->sentToReview, $result->automatic, $result->correct])->toBe([3, 0, 0, 3, 2])
        ->and($result->reviewRate())->toBe(0.0)
        ->and($result->accuracy())->toBe(2 / 3);
});

it('reports the bands of each answer, in order of Question and band', function () {
    $report = Calibration::for(ReturnAbuse::class)->fromDataset(fourReturns())->decisions(ReviewedReturnDecision::class)->run();

    expect(array_map(fn (CalibrationBand $band) => [
        $band->question, $band->from, $band->to, $band->cases, $band->expected, $band->accuracy(), $band->reviewRate(),
    ], $report->results[0]->bands))->toBe([
        ['abusive', .1, .2, 1, ['approve' => 1], 1.0, 0.0],
        ['abusive', .4, .5, 2, ['approve' => 1, 'reject' => 1], null, 1.0],
        ['abusive', .9, 1.0, 1, ['reject' => 1], 1.0, 0.0],
        ['department', .5, .6, 4, ['approve' => 2, 'reject' => 2], 1.0, .5],
    ]);
});

it('compares Engine connections and Decisions on the same cases, one result per identity', function () {
    $probabilities = [
        'fake-1.0.0' => ['Zip broke' => .10, 'Too small' => .42, 'Changed my mind' => .45, 'Wore it to a wedding' => .90],
        'fake-2.0.0' => ['Zip broke' => .05, 'Too small' => .20, 'Changed my mind' => .80, 'Wore it to a wedding' => .95],
    ];
    app(EngineManager::class)->extend('fake', fn ($app, array $config) => abuseEngine($probabilities[$config['model']], $config['model']));
    config()->set('judgment.engines.current', ['driver' => 'fake', 'model' => 'fake-1.0.0']);
    config()->set('judgment.engines.candidate', ['driver' => 'fake', 'model' => 'fake-2.0.0']);

    $report = Calibration::for(ReturnAbuse::class)->fromDataset(fourReturns())
        ->engines('current', 'candidate')
        ->decisions(ReturnDecision::class, ReviewedReturnDecision::class)
        ->run();

    expect(array_map(fn (CalibrationResult $result) => [
        $result->identity->model, $result->identity->decision, $result->cases, $result->sentToReview, $result->correct, $result->automatic,
    ], $report->results))->toBe([
        ['fake-1.0.0', ReturnDecision::class, 4, 0, 3, 4],
        ['fake-1.0.0', ReviewedReturnDecision::class, 4, 2, 2, 2],
        ['fake-2.0.0', ReturnDecision::class, 4, 0, 4, 4],
        ['fake-2.0.0', ReviewedReturnDecision::class, 4, 0, 4, 4],
    ]);
});

it('labels the cases with past Resolutions, counting those whose Subject no longer exists as skipped', function () {
    reviewedReturn('Wore it to a wedding', RefundOutcome::Reject);
    reviewedReturn('Zip broke', RefundOutcome::Approve)->delete();
    app()->instance(Engine::class, abuseEngine(['Wore it to a wedding' => .90]));

    $report = Calibration::for(ReturnAbuse::class)->fromDataset('ignored.json')->fromResolutions()->run();

    expect($report->skipped)->toBe(1)
        ->and($report->results)->toHaveCount(1)
        ->and([$report->results[0]->cases, $report->results[0]->correct])->toBe([1, 1]);
});

it('leaves a builder and its earlier reports unchanged', function () {
    $base = Calibration::for(ReturnAbuse::class);
    $dataset = $base->fromDataset(fourReturns());

    $first = $dataset->run();
    $second = $dataset->decisions(ReviewedReturnDecision::class)->run();

    expect($first->results[0]->identity->decision)->toBe(ReturnDecision::class)
        ->and($first->results[0]->cases)->toBe(4)
        ->and($second->results[0]->identity->decision)->toBe(ReviewedReturnDecision::class)
        ->and(fn () => $base->run())->toThrow(InvalidCalibration::class, 'No labelled cases to calibrate '.ReturnAbuse::class.' against.');
});

it('refuses a class that is not a Judgment or not a Decision', function () {
    expect(fn () => Calibration::for(ReturnDecision::class))->toThrow(InvalidCalibration::class, ReturnDecision::class.' is not a Judgment.')
        ->and(fn () => Calibration::for(ReturnAbuse::class)->decisions(ReturnAbuse::class))->toThrow(InvalidCalibration::class, ReturnAbuse::class.' is not a Decision.');
});
