<?php

namespace RobertoGallea\Judgment;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RobertoGallea\Judgment\Console\MakeDecisionCommand;
use RobertoGallea\Judgment\Console\MakeJudgmentCommand;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Judge as JudgeContract;
use RobertoGallea\Judgment\Engines\JevEngine;

class JudgmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/judgment.php', 'judgment');

        $this->app->singleton(EngineManager::class, fn (Application $app) => (new EngineManager($app))
            ->extend('jev', JevEngine::connect(...)));
        $this->app->bind(Engine::class, fn (Application $app): Engine => $app->make(EngineManager::class)->engine());

        $this->app->singleton(JudgeContract::class, fn (Application $app) => new Judge($app));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/judgment.php' => config_path('judgment.php'),
            ], 'judgment-config');

            $this->commands([MakeJudgmentCommand::class, MakeDecisionCommand::class]);
        }
    }
}
