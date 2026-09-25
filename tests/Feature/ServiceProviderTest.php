<?php

use Illuminate\Support\Facades\File;
use RobertoGallea\Judgment\Contracts\Judge;
use RobertoGallea\Judgment\JudgmentServiceProvider;

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
    File::delete($published());

    $this->artisan('vendor:publish', ['--tag' => 'judgment-migrations'])->assertSuccessful();

    expect($published())->toHaveCount(1)
        ->and(basename($published()[0]))->toStartWith(now()->format('Y_m_d_'));

    File::delete($published());
});

it('overwrites its published migration when republished', function () {
    $published = fn () => File::glob(database_path('migrations/*_create_judgment_assessments_table.php'));
    File::delete($published());
    File::put(database_path('migrations/2020_01_01_000000_create_judgment_assessments_table.php'), '<?php');
    app()->register(JudgmentServiceProvider::class, force: true);

    $this->artisan('vendor:publish', ['--tag' => 'judgment-migrations', '--force' => true])->assertSuccessful();

    expect($published())->toHaveCount(1)
        ->and(basename($published()[0]))->toStartWith('2020_01_01_000000_')
        ->and(File::get($published()[0]))->toContain('judgment_assessments');

    File::delete($published());
});
