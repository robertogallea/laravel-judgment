<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Likelihood;

/** A Judgment whose wording can change between deploys. */
final class ListingTone extends Judgment
{
    public function __construct(
        public readonly string $title,
        private readonly string $question = 'Is the tone of the listing title hyped?',
    ) {}

    public function evidence(): array
    {
        return ['title' => $this->title];
    }

    public function questions(): array
    {
        return ['hyped' => Likelihood::that($this->question)];
    }
}
