<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Likelihood;

final class PostModeration extends Judgment
{
    public function __construct(public readonly string $post) {}

    public function evidence(): array
    {
        return ['post' => $this->post];
    }

    public function questions(): array
    {
        return [
            'flags' => Likelihood::each(Flag::class),
            'topics' => Likelihood::each([
                'politics' => 'Is the post about politics?',
                'sport' => 'Is the post about sport?',
            ]),
        ];
    }
}
