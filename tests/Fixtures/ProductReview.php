<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Likelihood;

/** A second Judgment, deliberately without a default Decision. */
final class ProductReview extends Judgment
{
    public function __construct(public readonly string $body) {}

    public function evidence(): array
    {
        return ['review' => $this->body];
    }

    public function questions(): array
    {
        return [
            'spam' => Likelihood::that('Is the review unsolicited promotion?'),
        ];
    }
}
