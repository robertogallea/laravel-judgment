<?php

use Illuminate\Support\Facades\File;
use RobertoGallea\Judgment\Contracts\Judge;

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

it('publishes its migration', function () {
    $published = fn () => File::glob(database_path('migrations/*_create_judgment_assessments_table.php'));
    File::delete($published());

    $this->artisan('vendor:publish', ['--tag' => 'judgment-migrations'])->assertSuccessful();

    expect($published())->toHaveCount(1);

    File::delete($published());
});
