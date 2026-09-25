<?php

namespace RobertoGallea\Judgment\Console;

use Closure;

/**
 * Turns the class options of a generator into references the stub can use.
 */
trait ReferencesClasses
{
    /**
     * An existing or root-namespaced class is kept as given; a bare name is
     * placed by $qualify. Null when the option is not given.
     *
     * @param  Closure(string): string  $qualify
     */
    private function classOption(string $option, Closure $qualify): ?string
    {
        $class = $this->option($option);

        if (! is_string($class) || $class === '') {
            return null;
        }

        $class = str_replace('/', '\\', ltrim($class, '\\/'));

        return class_exists($class) || str_starts_with($class, $this->rootNamespace())
            ? $class
            : $qualify($class);
    }

    /**
     * How the stub refers to $class: imported under its short name, or fully
     * qualified when a name in $taken already claims it. Claims the name used.
     *
     * @param  list<string>  $taken
     * @return array{import: string, type: string}
     */
    private function reference(string $class, array &$taken): array
    {
        $name = class_basename($class);

        if (in_array(strtolower($name), array_map(strtolower(...), $taken), true)) {
            return ['import' => '', 'type' => '\\'.$class];
        }

        $taken[] = $name;

        return ['import' => "use {$class};\n", 'type' => $name];
    }
}
