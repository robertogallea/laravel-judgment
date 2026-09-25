<?php

use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Tests\Fixtures\ConstantEngine;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnRequest;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnWording;

it("lists a Subject's Assessments", function () {
    $judgment = returnAbuse();
    $judgment->assess();
    $judgment->assess();
    returnAbuse()->assess();

    expect($judgment->request->assessments)->toHaveCount(2)
        ->and($judgment->request->assessments->pluck('judgment')->unique()->all())->toBe([ReturnAbuse::class]);
});

it('finds the latest Assessment of a Subject for a given Judgment', function () {
    $request = returnAbuse()->request;
    app()->instance(Engine::class, new ConstantEngine);

    (new ReturnAbuse($request))->assess();
    (new ReturnAbuse($request))->assess();
    (new ReturnWording($request))->assess();
    (new ReturnAbuse(ReturnRequest::create(['item' => 'Boots', 'reason' => 'Too tight.'])))->assess();

    $latest = $request->latestAssessment(ReturnAbuse::class);

    expect($latest?->id)->toBe(2)
        ->and($request->latestAssessment(ReturnWording::class)?->id)->toBe(3)
        ->and(ReturnRequest::create(['item' => 'Hat', 'reason' => 'Faded.'])->latestAssessment(ReturnAbuse::class))->toBeNull();
});
