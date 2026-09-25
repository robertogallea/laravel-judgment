<?php

namespace RobertoGallea\Judgment\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Judge as JudgeContract;
use RobertoGallea\Judgment\EngineManager;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Testing\FakeSequence;
use RobertoGallea\Judgment\Testing\JudgeFake;
use RobertoGallea\Judgment\Testing\PreventedEngine;

/**
 * @method static \RobertoGallea\Judgment\Assessment|\RobertoGallea\Judgment\Unassessed assess(\RobertoGallea\Judgment\Judgment $judgment)
 * @method static \Illuminate\Foundation\Bus\PendingDispatch dispatch(\RobertoGallea\Judgment\Judgment $judgment)
 * @method static void assertAssessed(class-string<\RobertoGallea\Judgment\Judgment> $judgment, (\Closure(\RobertoGallea\Judgment\Judgment): bool)|null $callback = null)
 * @method static void assertNotAssessed(class-string<\RobertoGallea\Judgment\Judgment> $judgment, (\Closure(\RobertoGallea\Judgment\Judgment): bool)|null $callback = null)
 * @method static void assertNothingAssessed()
 * @method static void assertDispatched(class-string<\RobertoGallea\Judgment\Judgment> $judgment, (\Closure(\RobertoGallea\Judgment\Judgment): bool)|null $callback = null)
 * @method static void assertNotDispatched(class-string<\RobertoGallea\Judgment\Judgment> $judgment, (\Closure(\RobertoGallea\Judgment\Judgment): bool)|null $callback = null)
 *
 * @see JudgeContract
 * @see JudgeFake
 */
class Judge extends Facade
{
    /**
     * Replace the Judge with a fake answering each Judgment from its script, and
     * prevent any real Engine call.
     *
     * @param  array<class-string<Judgment>, mixed>  $scripts  Judgment class => answers, closure or sequence
     */
    public static function fake(array $scripts = []): JudgeFake
    {
        $fake = new JudgeFake(app(), $scripts);

        static::swap($fake);
        app()->instance(Engine::class, new PreventedEngine);
        app(EngineManager::class)->prevent(new PreventedEngine);

        return $fake;
    }

    /**
     * Scripts for successive assessments of one Judgment, used once each, in order.
     *
     * @param  array<string, mixed>|Closure  ...$scripts  answers, or a closure given the Judgment
     */
    public static function sequence(array|Closure ...$scripts): FakeSequence
    {
        return new FakeSequence(...$scripts);
    }

    protected static function getFacadeAccessor(): string
    {
        return JudgeContract::class;
    }
}
