<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RobertoGallea\Judgment\Models\AssessmentRecord;
use RobertoGallea\Judgment\Tests\Fixtures\ReturnAbuse;

/** Run one of the package's migration stubs. */
function migrate(string $stub): void
{
    (require __DIR__."/../../database/migrations/$stub.php.stub")->up();
}

it('upgrades a v0.1.0 table to record Unassessed attempts, keeping the Assessments it holds', function () {
    Schema::drop('judgment_assessments');
    migrate('create_judgment_assessments_table');
    DB::table('judgment_assessments')->insert([
        'judgment' => ReturnAbuse::class,
        'evidence_fingerprint' => str_repeat('a', 64),
        'untrusted_paths' => '[]',
        'questions_fingerprint' => str_repeat('b', 64),
        'answers' => '{"abusive":0.42}',
        'engine' => 'jev',
        'model' => 'jev-1.13.0',
        'request_id' => 'req-1',
        'provenance_details' => '[]',
        'outcome_type' => 'App\Outcome',
        'outcome' => 'approve',
    ]);

    migrate('add_failures_to_judgment_assessments_table');

    expect(AssessmentRecord::sole())
        ->answers->toBe(['abusive' => .42])
        ->engine->toBe('jev')
        ->model->toBe('jev-1.13.0')
        ->request_id->toBe('req-1')
        ->outcome->toBe('approve');

    AssessmentRecord::create([
        'judgment' => ReturnAbuse::class,
        'evidence_fingerprint' => str_repeat('a', 64),
        'untrusted_paths' => [],
        'questions_fingerprint' => str_repeat('b', 64),
        'failure_type' => RuntimeException::class,
        'failure_message' => 'Engine unreachable.',
    ]);

    expect(AssessmentRecord::query()->latest('id')->first())
        ->answers->toBeNull()
        ->engine->toBeNull()
        ->model->toBeNull()
        ->provenance_details->toBeNull()
        ->failure_type->toBe(RuntimeException::class)
        ->failure_message->toBe('Engine unreachable.');
});
