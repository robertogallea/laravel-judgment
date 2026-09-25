<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Likelihood;

final class ProductListing extends Judgment
{
    public function __construct(public readonly string $title) {}

    public function evidence(): array
    {
        return ['title' => $this->title];
    }

    public function questions(): array
    {
        return [
            'counterfeit' => Likelihood::that('Is the listed product counterfeit?'),
            'tone' => Classification::of('What is the tone of the listing title?', labels: ['neutral', 'hyped']),
        ];
    }
}
