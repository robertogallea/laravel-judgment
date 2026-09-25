<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Questions\Question;

/** A Question kind other than Likelihood, standing in for Classification/Rating until they exist. */
final class Opinion extends Question
{
    public static function on(string $topic): self
    {
        return new self($topic);
    }
}
