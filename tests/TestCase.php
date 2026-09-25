<?php

namespace RobertoGallea\Judgment\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RobertoGallea\Judgment\JudgmentServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [JudgmentServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Judge' => \RobertoGallea\Judgment\Facades\Judge::class];
    }
}
