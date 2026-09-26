<?php

namespace RobertoGallea\Judgment\Console;

use Illuminate\Console\Command;
use RobertoGallea\Judgment\Calibration\Calibration;
use RobertoGallea\Judgment\Calibration\CalibrationBand;
use RobertoGallea\Judgment\Calibration\CalibrationIdentity;
use RobertoGallea\Judgment\Calibration\CalibrationResult;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Exceptions\InvalidCalibration;
use RobertoGallea\Judgment\Judgment;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Calibration: runs a Judgment and its Decisions against labelled cases with the configured
 * Engine, and reports how their thresholds behave, per Calibration Identity.
 */
#[AsCommand(name: 'judgment:eval')]
class CalibrateCommand extends Command
{
    protected $signature = 'judgment:eval
        {judgment : The Judgment to calibrate}
        {--dataset= : A JSON file of labelled cases; past Resolutions are the labels when omitted}
        {--decision=* : A Decision to calibrate instead of the Judgment\'s default; repeat to compare}
        {--engine=* : An Engine connection to ask instead of the Judgment\'s own; repeat to compare models}';

    protected $description = 'Calibrate a Judgment\'s Decisions against labelled cases';

    public function handle(): int
    {
        try {
            $calibration = Calibration::for($this->class($this->strings('judgment')[0] ?? '', 'Judgments', Judgment::class))
                ->engines(...$this->strings('engine'))
                ->decisions(...array_map(fn (string $name) => $this->class($name, 'Decisions', Decision::class), $this->strings('decision')));
            $dataset = $this->strings('dataset')[0] ?? null;

            $report = ($dataset === null ? $calibration->fromResolutions() : $calibration->fromDataset($dataset))->run();
        } catch (InvalidCalibration $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($report->skipped > 0) {
            $this->components->warn(sprintf('Skipped %d %s whose Subject no longer exists.', $report->skipped, str('Resolution')->plural($report->skipped)));
        }

        $this->summary($report->results);
        foreach ($report->results as $result) {
            $this->bands($result);
        }

        return self::SUCCESS;
    }

    /** @param  list<CalibrationResult>  $results */
    private function summary(array $results): void
    {
        $this->table(
            ['Questions', 'Model', 'Decision', 'Language', 'Cases', 'Unassessed', 'Review rate', 'Accuracy'],
            array_map(fn (CalibrationResult $result) => [
                substr($result->identity->questions, 0, 8),
                $result->identity->model,
                $this->decision($result->identity),
                $result->identity->language ?? '—',
                $result->cases,
                $result->unassessed,
                $this->percentage($result->reviewRate()),
                $this->percentage($result->accuracy()).($result->automatic > 0 ? " ({$result->correct}/{$result->automatic})" : ''),
            ], $results),
        );
    }

    private function bands(CalibrationResult $result): void
    {
        $identity = $result->identity;
        $this->newLine();
        $this->line(sprintf('<options=bold>%s</> · %s · %s · questions %s', $this->decision($identity), $identity->model, $identity->language ?? 'unknown language', substr($identity->questions, 0, 8)));

        $this->table(['Question', 'Band', 'Cases', 'Expected', 'Accuracy', 'Review rate'], array_map(fn (CalibrationBand $band) => [
            $band->question,
            sprintf('%.1f–%.1f', $band->from, $band->to),
            $band->cases,
            implode(', ', array_map(fn (string $outcome, int $cases) => "$outcome $cases", array_keys($band->expected), $band->expected)),
            $this->percentage($band->accuracy()),
            $this->percentage($band->reviewRate()),
        ], $result->bands));
    }

    /**
     * The values given to an argument or option, however many it takes.
     *
     * @return list<string>
     */
    private function strings(string $name): array
    {
        $value = $this->hasArgument($name) ? $this->argument($name) : $this->option($name);

        return array_values(array_filter(is_array($value) ? $value : [$value], is_string(...)));
    }

    /**
     * The class named, kept as given when it exists, else in the application's namespace for its kind.
     *
     * @template T of object
     *
     * @param  class-string<T>  $kind
     * @return class-string<T>
     *
     * @throws InvalidCalibration when it is not of the kind
     */
    private function class(string $name, string $namespace, string $kind): string
    {
        $class = str_replace('/', '\\', ltrim($name, '\\/'));
        if (! class_exists($class)) {
            $class = $this->laravel->getNamespace().$namespace.'\\'.$class;
        }

        if (! is_a($class, $kind, true)) {
            throw InvalidCalibration::notA(class_basename($kind), $name);
        }

        return $class;
    }

    private function decision(CalibrationIdentity $identity): string
    {
        return class_basename($identity->decision).($identity->decisionVersion === null ? '' : " v{$identity->decisionVersion}");
    }

    private function percentage(?float $share): string
    {
        return $share === null ? '—' : number_format($share * 100, 1).'%';
    }
}
