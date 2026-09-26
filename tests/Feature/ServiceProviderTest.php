<?php

use Illuminate\Support\Facades\File;
use RobertoGallea\Judgment\Contracts\Judge;
use RobertoGallea\Judgment\JudgmentServiceProvider;

/** Leave no published migration behind, even from a failed test, for the next test to migrate. */
afterEach(fn () => File::delete(File::glob(database_path('migrations/*_judgment_assessments_table.php'))));

it('registers the Judge contract as a single shared instance', function () {
    expect(app(Judge::class))->toBeInstanceOf(Judge::class)
        ->toBe(app(Judge::class));
});

it('backs the Judge facade with the Judge contract', function () {
    expect(RobertoGallea\Judgment\Facades\Judge::getFacadeRoot())->toBe(app(Judge::class));
});

it('publishes its config file', function () {
    File::delete(config_path('judgment.php'));

    $this->artisan('vendor:publish', ['--tag' => 'judgment-config'])->assertSuccessful();

    expect(config_path('judgment.php'))->toBeFile()
        ->and((require config_path('judgment.php')))->toHaveKey('engine');

    File::delete(config_path('judgment.php'));
});

it('publishes its migration timestamped at publish time', function () {
    $published = fn () => File::glob(database_path('migrations/*_create_judgment_assessments_table.php'));
    File::delete(File::glob(database_path('migrations/*_judgment_assessments_table.php')));

    $this->artisan('vendor:publish', ['--tag' => 'judgment-migrations'])->assertSuccessful();

    expect($published())->toHaveCount(1)
        ->and(basename($published()[0]))->toStartWith(now()->format('Y_m_d_'));
});

it('publishes the migration recording Unassessed attempts to run after the table is created', function () {
    File::delete(File::glob(database_path('migrations/*_judgment_assessments_table.php')));

    $this->artisan('vendor:publish', ['--tag' => 'judgment-migrations'])->assertSuccessful();

    $published = array_map(basename(...), File::glob(database_path('migrations/*_judgment_assessments_table.php')));
    sort($published);
    expect($published)->toHaveCount(2)
        ->and($published[0])->toEndWith('_create_judgment_assessments_table.php')
        ->and($published[1])->toEndWith('_add_failures_to_judgment_assessments_table.php');
});

it('publishes the migration recording Unassessed attempts after a create migration published earlier', function () {
    File::delete(File::glob(database_path('migrations/*_judgment_assessments_table.php')));
    File::put(database_path('migrations/2020_01_01_000000_create_judgment_assessments_table.php'), '<?php');
    app()->register(JudgmentServiceProvider::class, force: true);

    $this->artisan('vendor:publish', ['--tag' => 'judgment-migrations'])->assertSuccessful();

    expect(File::glob(database_path('migrations/*_add_failures_to_judgment_assessments_table.php')))->toHaveCount(1)
        ->and(basename(File::glob(database_path('migrations/*_add_failures_to_judgment_assessments_table.php'))[0]))->toStartWith(now()->format('Y_m_d_'))
        ->and(File::get(database_path('migrations/2020_01_01_000000_create_judgment_assessments_table.php')))->toBe('<?php');
});

it('overwrites its published migration when republished', function () {
    $published = fn () => File::glob(database_path('migrations/*_create_judgment_assessments_table.php'));
    File::delete(File::glob(database_path('migrations/*_judgment_assessments_table.php')));
    File::put(database_path('migrations/2020_01_01_000000_create_judgment_assessments_table.php'), '<?php');
    app()->register(JudgmentServiceProvider::class, force: true);

    $this->artisan('vendor:publish', ['--tag' => 'judgment-migrations', '--force' => true])->assertSuccessful();

    expect($published())->toHaveCount(1)
        ->and(basename($published()[0]))->toStartWith('2020_01_01_000000_')
        ->and(File::get($published()[0]))->toContain('judgment_assessments');
});
