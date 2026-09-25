<?php

namespace RobertoGallea\Judgment\Testing;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Assert as PHPUnit;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Judge;
use RobertoGallea\Judgment\Events\AssessmentCompleted;
use RobertoGallea\Judgment\Events\AssessmentFailed;
use RobertoGallea\Judgment\Exceptions\EngineFailed;
use RobertoGallea\Judgment\Exceptions\UnscriptedJudgment;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Unassessed;

/**
 * Stands in for the Judge in feature tests: answers each Judgment from its script,
 * never calling an Engine, and records what was assessed for assertions.
 */
final class JudgeFake implements Judge
{
    /**
     * @param  array<class-string<Judgment>, array<string, mixed>|Closure|FakeSequence>  $scripts  answers, a closure given
     *                                                                                             the Judgment and returning answers or a FakeAssessment, or a sequence of either
     */
    public function __construct(private readonly Container $container, private readonly array $scripts = []) {}

    /** @var list<Judgment> */
    private array $assessed = [];

    public function assess(Judgment $judgment): Assessment|Unassessed
    {
        $this->assessed[] = $judgment;

        try {
            $assessment = $this->script($judgment)->make();
        } catch (EngineFailed $e) {
            return $this->fail($judgment, $e);
        }

        $this->container->make(Dispatcher::class)->dispatch(new AssessmentCompleted($judgment, $assessment));

        return $assessment;
    }

    /**
     * Assert the Judgment was assessed, at least once matching the callback if given.
     *
     * @param  class-string<Judgment>  $judgment
     * @param  (Closure(Judgment): bool)|null  $callback
     */
    public function assertAssessed(string $judgment, ?Closure $callback = null): void
    {
        $assessed = $this->assessedOf($judgment);
        PHPUnit::assertNotEmpty($assessed, "Expected {$judgment} to be assessed, but it was not.");

        if ($callback !== null) {
            PHPUnit::assertNotEmpty(
                array_filter($assessed, $callback),
                sprintf('Expected %s to be assessed matching the callback, but none of the %d assessed matched.', $judgment, count($assessed)),
            );
        }
    }

    /**
     * Assert the Judgment was not assessed, or never matching the callback if given.
     *
     * @param  class-string<Judgment>  $judgment
     * @param  (Closure(Judgment): bool)|null  $callback
     */
    public function assertNotAssessed(string $judgment, ?Closure $callback = null): void
    {
        $assessed = $this->assessedOf($judgment);
        $matching = $callback === null ? $assessed : array_filter($assessed, $callback);

        PHPUnit::assertEmpty($matching, sprintf(
            'Expected %s not to be assessed%s, but it was, %d %s.',
            $judgment,
            $callback === null ? '' : ' matching the callback',
            count($matching),
            count($matching) === 1 ? 'time' : 'times',
        ));
    }

    public function assertNothingAssessed(): void
    {
        PHPUnit::assertEmpty($this->assessed, sprintf(
            'Expected nothing to be assessed, but %d %s: %s.',
            count($this->assessed),
            count($this->assessed) === 1 ? 'Judgment was' : 'Judgments were',
            implode(', ', array_unique(array_map(fn (Judgment $judgment) => $judgment::class, $this->assessed))),
        ));
    }

    /**
     * @param  class-string<Judgment>  $judgment
     * @return list<Judgment>
     */
    private function assessedOf(string $judgment): array
    {
        return array_values(array_filter($this->assessed, fn (Judgment $assessed) => $assessed instanceof $judgment));
    }

    private function script(Judgment $judgment): FakeAssessment
    {
        $script = $this->scripts[$judgment::class] ?? throw UnscriptedJudgment::for($judgment, array_keys($this->scripts));
        if ($script instanceof FakeSequence) {
            $script = $script->next($judgment);
        }
        $answers = $script instanceof Closure ? $script($judgment) : $script;

        return $answers instanceof FakeAssessment ? $answers : Assessment::fake($judgment)->answers($answers);
    }

    /** A scripted Engine failure ends as the Judge's would: thrown, or Unassessed as configured. */
    private function fail(Judgment $judgment, EngineFailed $exception): Unassessed
    {
        $this->container->make(Dispatcher::class)->dispatch(new AssessmentFailed($judgment, $exception));

        if ($this->container->make('config')->get('judgment.failure') !== 'unassessed') {
            throw $exception;
        }

        return new Unassessed($judgment, $exception);
    }
}
