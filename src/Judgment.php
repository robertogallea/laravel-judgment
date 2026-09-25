<?php

namespace RobertoGallea\Judgment;

use Illuminate\Database\Eloquent\Model;
use ReflectionObject;
use ReflectionProperty;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Judge;
use RobertoGallea\Judgment\Questions\LikelihoodSet;
use RobertoGallea\Judgment\Questions\Question;

/**
 * Declares which Questions to ask about its Subject, over which Evidence, and
 * which Decision applies by default. Constructed with its Subject, like a Mailable.
 */
abstract class Judgment
{
    /**
     * The Subject-specific material the Questions are asked over. Declared
     * explicitly, so adding a column never silently changes what is assessed.
     *
     * @return array<string, mixed>
     */
    abstract public function evidence(): array;

    /** @return array<string, Question|LikelihoodSet> */
    abstract public function questions(): array;

    /** @return class-string<Decision>|null */
    public function decision(): ?string
    {
        return null;
    }

    /**
     * The language the Evidence is written in (e.g. "it"), if known. It is not
     * translated: it is recorded with each Assessment, for Calibration to surface.
     */
    public function language(): ?string
    {
        return null;
    }

    /**
     * The Subject its Assessments are recorded against: by default the only
     * Eloquent model among the public properties, null if there is none or several.
     */
    public function subject(): ?Model
    {
        $models = array_filter(
            array_map(fn (ReflectionProperty $property) => $property->isInitialized($this) ? $property->getValue($this) : null,
                (new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC)),
            fn (mixed $value) => $value instanceof Model,
        );

        return count($models) === 1 ? reset($models) : null;
    }

    /** The Engine connection to ask, from judgment.engines; null asks the default connection. */
    public function engine(): ?string
    {
        return null;
    }

    public function assess(): Assessment|Unassessed
    {
        return app(Judge::class)->assess($this);
    }
}
