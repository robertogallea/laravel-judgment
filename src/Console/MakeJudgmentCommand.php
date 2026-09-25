<?php

namespace RobertoGallea\Judgment\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generates a Judgment constructed with its Subject.
 */
#[AsCommand(name: 'make:judgment')]
class MakeJudgmentCommand extends GeneratorCommand
{
    use ReferencesClasses;

    protected $name = 'make:judgment';

    protected $description = 'Create a new Judgment class';

    protected $type = 'Judgment';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/judgment.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Judgments';
    }

    protected function buildClass($name): string
    {
        $subject = $this->classOption('subject', $this->qualifyModel(...));
        $taken = [class_basename($name), 'Judgment'];
        $reference = $subject === null ? ['import' => '', 'type' => 'mixed'] : $this->reference($subject, $taken);

        return str_replace(
            ['{{ subjectImport }}', '{{ subjectType }}', '{{ subjectVariable }}'],
            [$reference['import'], $reference['type'], $subject === null ? 'subject' : Str::camel(class_basename($subject))],
            parent::buildClass($name),
        );
    }

    /** @return array<int, array<int, mixed>> */
    protected function getOptions(): array
    {
        return [
            ['subject', 's', InputOption::VALUE_REQUIRED, 'The Subject class the Judgment is constructed with'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the Judgment already exists'],
        ];
    }
}
