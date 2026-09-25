<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\UntrustedText;

/** A Judgment cached for an hour, whose wording can change between deploys. */
class CachedListingTone extends Judgment
{
    public function __construct(
        public readonly string|UntrustedText $title,
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

    public function cacheFor(): int
    {
        return 3600;
    }
}
