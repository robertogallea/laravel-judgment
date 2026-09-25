<?php

namespace RobertoGallea\Judgment;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Judge as JudgeContract;
use RobertoGallea\Judgment\Exceptions\EngineNotConfigured;

class JudgmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/judgment.php', 'judgment');

        $this->app->bind(Engine::class, function (Application $app): Engine {
            $engine = $app->make('config')->get('judgment.engine') ?? throw EngineNotConfigured::make();

            return $app->make($engine);
        });

        $this->app->singleton(JudgeContract::class, fn (Application $app) => new Judge($app));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/judgment.php' => config_path('judgment.php'),
            ], 'judgment-config');
        }
    }
}
