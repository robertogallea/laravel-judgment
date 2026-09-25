<?php

namespace RobertoGallea\Judgment\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use RobertoGallea\Judgment\Facades\Judge;
use RobertoGallea\Judgment\JudgmentServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [JudgmentServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    /** The package's published migration, plus the tables of the test Subjects. */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom([__DIR__.'/../database/migrations', __DIR__.'/database/migrations']);
    }

    protected function getPackageAliases($app): array
    {
        return ['Judge' => Judge::class];
    }
}
