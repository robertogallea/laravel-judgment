<?php

namespace RobertoGallea\Judgment\Facades;

use Illuminate\Support\Facades\Facade;
use RobertoGallea\Judgment\Contracts\Judge as JudgeContract;

/**
 * @method static \RobertoGallea\Judgment\Assessment assess(\RobertoGallea\Judgment\Judgment $judgment)
 *
 * @see JudgeContract
 */
class Judge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return JudgeContract::class;
    }
}
