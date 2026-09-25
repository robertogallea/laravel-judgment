<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Evidence;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Likelihood;

final class ReturnAbuse extends Judgment
{
    public function __construct(public readonly ReturnRequest $request) {}

    public function evidence(): array
    {
        return [
            'item' => $this->request->item,
            'customer' => ['reason' => Evidence::untrusted($this->request->reason)],
        ];
    }

    public function questions(): array
    {
        return [
            'abusive' => Likelihood::that('Is this return an attempt to abuse the return policy?'),
            'department' => Classification::of('Which team should handle the return?', Department::class),
        ];
    }

    public function language(): string
    {
        return $this->request->language ?? 'en';
    }

    public function decision(): string
    {
        return ReturnDecision::class;
    }
}
