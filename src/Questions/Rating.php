<?php

namespace RobertoGallea\Judgment\Questions;

use RobertoGallea\Judgment\Answers\RatingAnswer;
use RobertoGallea\Judgment\Exceptions\InvalidQuestion;

/** A Question answered with a position on an ordered scale of described levels. */
final class Rating extends Question
{
    /** @var list<string> */
    private array $levels = [];

    public static function of(string $question): self
    {
        return new self($question);
    }

    /** The ordered levels, lowest first, each described so the Engine reads the scale as intended. */
    public function levels(string ...$levels): self
    {
        if (count($levels) < 2 || count($levels) > 10) {
            throw InvalidQuestion::levelsOutOfRange(count($levels));
        }

        $rating = clone $this;
        $rating->levels = array_values($levels);

        return $rating;
    }

    public function ensureAnswerable(string $key): void
    {
        if ($this->levels === []) {
            throw InvalidQuestion::noLevels($key);
        }
    }

    /** @return list<string> level descriptions, lowest first */
    public function criteria(): array
    {
        return $this->levels;
    }

    /** @param  non-empty-list<float>  $probabilities  a probability per level, lowest first, as the Engine answered */
    public function answer(array $probabilities): RatingAnswer
    {
        return new RatingAnswer($probabilities);
    }
}
