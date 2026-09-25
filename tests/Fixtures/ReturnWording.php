<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Likelihood;

final class ReturnWording extends Judgment
{
    public function __construct(public readonly ReturnRequest $request) {}

    public function evidence(): array
    {
        return ['reason' => $this->request->reason];
    }

    public function questions(): array
    {
        return ['polite' => Likelihood::that('Is the reason for the return worded politely?')];
    }
}
