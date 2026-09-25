<?php

namespace RobertoGallea\Judgment\Questions;

use RobertoGallea\Judgment\Answers\RatingAnswer;
use RobertoGallea\Judgment\Exceptions\InvalidQuestion;

/** A Question answered with a position on an ordered scale of described levels. */
final class Rating extends Question
{
    /** @param  list<string>  $levels */
    private function __construct(string $question, private readonly array $levels)
    {
        parent::__construct($question);

        if (count($levels) < 2 || count($levels) > 10) {
            throw InvalidQuestion::levelsOutOfRange(count($levels));
        }
    }

    /** @param  list<string>  $levels  the ordered levels, lowest first, each described so the Engine reads the scale as intended */
    public static function of(string $question, array $levels): self
    {
        return new self($question, $levels);
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
