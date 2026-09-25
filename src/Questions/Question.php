<?php

namespace RobertoGallea\Judgment\Questions;

use RobertoGallea\Judgment\Exceptions\InvalidQuestion;

abstract class Question
{
    protected function __construct(public readonly string $instructions)
    {
        if (trim($instructions) === '') {
            throw InvalidQuestion::noInstructions();
        }
    }

    /** Refuse to be asked while still missing what the Engine needs to answer it. */
    public function ensureAnswerable(string $key): void {}
}
