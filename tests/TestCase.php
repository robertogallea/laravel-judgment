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

    /** The tables of the test Subjects. */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /** The package's migration ships as a stub, which the Migrator does not load. */
    protected function afterRefreshingDatabase(): void
    {
        (require __DIR__.'/../database/migrations/create_judgment_assessments_table.php.stub')->up();
    }

    protected function getPackageAliases($app): array
    {
        return ['Judge' => Judge::class];
    }
}
