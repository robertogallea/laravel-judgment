<?php

namespace RobertoGallea\Judgment;

use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Judge;
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

    /** @return array<string, Question> */
    abstract public function questions(): array;

    /** @return class-string<Decision>|null */
    public function decision(): ?string
    {
        return null;
    }

    public function assess(): Assessment
    {
        return app(Judge::class)->assess($this);
    }
}
