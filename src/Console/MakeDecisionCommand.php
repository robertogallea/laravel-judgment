<?php

namespace RobertoGallea\Judgment\Console;

use Illuminate\Console\GeneratorCommand;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Judgment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates an uncalibrated Decision from a Judgment to an Outcome.
 */
#[AsCommand(name: 'make:decision')]
class MakeDecisionCommand extends GeneratorCommand
{
    use ReferencesClasses;

    protected $name = 'make:decision';

    protected $description = 'Create a new Decision class';

    protected $type = 'Decision';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/decision.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Decisions';
    }

    protected function buildClass($name): string
    {
        $taken = [class_basename($name), 'Assessment', 'Decision', 'LogicException'];
        $judgment = $this->reference($this->classOption('judgment', fn (string $class) => $this->rootNamespace().'Judgments\\'.$class) ?? Judgment::class, $taken);
        $outcome = $this->reference($this->classOption('outcome', fn (string $class) => $this->rootNamespace().'Enums\\'.$class) ?? Outcome::class, $taken);

        return str_replace(
            ['{{ imports }}', '{{ judgmentType }}', '{{ outcomeType }}'],
            [$judgment['import'].$outcome['import'], $judgment['type'], $outcome['type']],
            parent::buildClass($name),
        );
    }

    /** @return array<int, array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['judgment', 'j', InputOption::VALUE_REQUIRED, 'The Judgment the Decision applies to'],
            ['outcome', 'o', InputOption::VALUE_REQUIRED, 'The Outcome enum the Decision returns'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the Decision already exists'],
        ];
    }
}
