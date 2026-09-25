<?php

use RobertoGallea\Judgment\Models\AssessmentRecord;

function recordAssessmentDaysAgo(int $days): void
{
    test()->travelTo(now()->subDays($days), fn () => returnAbuse()->assess());
}

it('prunes records older than the retention period', function () {
    config(['judgment.persistence.retention_days' => 30]);
    recordAssessmentDaysAgo(31);
    recordAssessmentDaysAgo(29);

    $this->artisan('model:prune', ['--model' => [AssessmentRecord::class]])->assertSuccessful();

    expect(AssessmentRecord::pluck('id')->all())->toBe([2]);
});

it('keeps every record when no retention period is set', function () {
    config(['judgment.persistence.retention_days' => null]);
    recordAssessmentDaysAgo(3650);

    $this->artisan('model:prune', ['--model' => [AssessmentRecord::class]])->assertSuccessful();

    expect(AssessmentRecord::count())->toBe(1);
});
