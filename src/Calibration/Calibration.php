<?php

namespace RobertoGallea\Judgment\Calibration;

use Illuminate\Contracts\Container\Container;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Exceptions\InvalidCalibration;
use RobertoGallea\Judgment\Judgment;

/**
 * Calibration of a Judgment's Decisions against labelled cases: past Resolutions by default, a
 * dataset, or cases in memory. Every case is asked of each Engine connection chosen, and each
 * Decision chosen is applied to its answers. Nothing is cached, recorded, logged or announced.
 * Each call returns a new builder.
 */
final class Calibration
{
    private ?string $dataset = null;

    /** @var list<LabelledCase>|null */
    private ?array $cases = null;

    /** @var list<string> */
    private array $engines = [];

    /** @var list<class-string<Decision>> */
    private array $decisions = [];

    /** @param  class-string<Judgment>  $judgment */
    public function __construct(
        private readonly Container $container,
        private readonly string $judgment,
    ) {}

    /**
     * @param  string  $judgment  a Judgment class
     *
     * @throws InvalidCalibration when it is not a Judgment
     */
    public static function for(string $judgment): self
    {
        if (! is_a($judgment, Judgment::class, true)) {
            throw InvalidCalibration::notA('Judgment', $judgment);
        }

        return app(self::class, ['judgment' => $judgment]);
    }

    /** Label the cases with the Judgment's past Resolutions, one per Evidence; the default. */
    public function fromResolutions(): self
    {
        $calibration = clone $this;
        $calibration->dataset = null;
        $calibration->cases = null;

        return $calibration;
    }

    /** Take the cases from a JSON list of {"subject": attributes or key, "expected": Outcome value}. */
    public function fromDataset(string $path): self
    {
        $calibration = clone $this;
        $calibration->dataset = $path;
        $calibration->cases = null;

        return $calibration;
    }

    /**
     * Calibrate against these cases, for example Resolutions the application selected itself.
     *
     * @param  iterable<LabelledCase>  $cases
     *
     * @throws InvalidCalibration when one is not a labelled case of the Judgment
     */
    public function cases(iterable $cases): self
    {
        $calibration = clone $this;
        $calibration->dataset = null;
        $calibration->cases = [];
        foreach (array_values([...$cases]) as $index => $case) {
            $calibration->cases[] = $case->judgment instanceof $this->judgment
                ? $case
                : throw InvalidCalibration::notALabelledCase($index, $this->judgment);
        }

        return $calibration;
    }

    /** Ask each of these Engine connections instead of the Judgment's own, to compare their models. */
    public function engines(string ...$connections): self
    {
        $calibration = clone $this;
        $calibration->engines = array_values($connections);

        return $calibration;
    }

    /**
     * Apply each of these Decisions instead of the Judgment's default, to compare them.
     *
     * @param  string  ...$decisions  Decision classes
     *
     * @throws InvalidCalibration when one is not a Decision
     */
    public function decisions(string ...$decisions): self
    {
        $calibration = clone $this;
        $calibration->decisions = [];
        foreach ($decisions as $decision) {
            $calibration->decisions[] = is_a($decision, Decision::class, true) ? $decision : throw InvalidCalibration::notA('Decision', $decision);
        }

        return $calibration;
    }

    /** @throws InvalidCalibration when there are no cases, or a case cannot be calibrated against */
    public function run(): CalibrationReport
    {
        $skipped = 0;
        $cases = match (true) {
            $this->cases !== null => $this->cases,
            $this->dataset !== null => Cases::fromDataset($this->judgment, $this->dataset),
            default => Cases::fromResolutions($this->judgment, $skipped),
        };
        if ($cases === []) {
            throw InvalidCalibration::noCases($this->judgment);
        }

        $results = $this->container->make(Runner::class)->run($cases, $this->engines ?: [null], $this->decisions ?: [null]);

        return new CalibrationReport(array_map(fn (PendingResult $result) => $result->result(), $results), $skipped);
    }
}
