<?php

namespace RobertoGallea\Judgment\Console;

use Illuminate\Console\Command;
use RobertoGallea\Judgment\Calibration\Calibration;
use RobertoGallea\Judgment\Calibration\Cases;
use RobertoGallea\Judgment\Calibration\Group;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Exceptions\InvalidCalibration;
use RobertoGallea\Judgment\Judgment;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Calibration: runs a Judgment and its Decisions against labelled cases with the configured
 * Engine, and reports how their thresholds behave, per calibration identity.
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

    public function handle(Calibration $calibration): int
    {
        try {
            $judgment = $this->class($this->strings('judgment')[0] ?? '', 'Judgments', Judgment::class);
            $decisions = array_map(fn (string $name) => $this->class($name, 'Decisions', Decision::class), $this->strings('decision'));
            $dataset = $this->strings('dataset')[0] ?? null;
            $skipped = 0;
            $cases = $dataset === null ? Cases::fromResolutions($judgment, $skipped) : Cases::fromDataset($judgment, $dataset);
            if ($cases === []) {
                throw InvalidCalibration::noCases($judgment);
            }

            $groups = $calibration->run($cases, $this->strings('engine') ?: [null], $decisions ?: [null]);
        } catch (InvalidCalibration $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($skipped > 0) {
            $this->components->warn(sprintf('Skipped %d %s whose Subject no longer exists.', $skipped, str('Resolution')->plural($skipped)));
        }

        $this->summary($groups);
        foreach ($groups as $group) {
            $this->bands($group);
        }

        return self::SUCCESS;
    }

    /** @param  list<Group>  $groups */
    private function summary(array $groups): void
    {
        $this->table(
            ['Questions', 'Model', 'Decision', 'Language', 'Cases', 'Unassessed', 'Review rate', 'Accuracy'],
            array_map(fn (Group $group) => [
                substr($group->questions, 0, 8),
                $group->model,
                $this->decision($group),
                $group->language ?? '—',
                $group->tally->cases + $group->tally->unassessed,
                $group->tally->unassessed,
                $this->percentage($group->tally->reviewRate()),
                $this->percentage($group->tally->accuracy()).($group->tally->automatic > 0 ? " ({$group->tally->correct}/{$group->tally->automatic})" : ''),
            ], $groups),
        );
    }

    private function bands(Group $group): void
    {
        $this->newLine();
        $this->line(sprintf('<options=bold>%s</> · %s · %s · questions %s', $this->decision($group), $group->model, $group->language ?? 'unknown language', substr($group->questions, 0, 8)));

        $rows = [];
        foreach ($group->bands as $question => $bands) {
            foreach ($bands as $band => $tally) {
                $rows[] = [
                    $question,
                    sprintf('%.1f–%.1f', $band / 10, ($band + 1) / 10),
                    $tally->cases,
                    implode(', ', array_map(fn (string $outcome, int $cases) => "$outcome $cases", array_keys($tally->expected), $tally->expected)),
                    $this->percentage($tally->accuracy()),
                    $this->percentage($tally->reviewRate()),
                ];
            }
        }

        $this->table(['Question', 'Band', 'Cases', 'Expected', 'Accuracy', 'Review rate'], $rows);
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

    private function decision(Group $group): string
    {
        return class_basename($group->decision).($group->version === null ? '' : " v{$group->version}");
    }

    private function percentage(?float $share): string
    {
        return $share === null ? '—' : number_format($share * 100, 1).'%';
    }
}
