<?php

namespace RobertoGallea\Judgment\Tests\Fixtures\Readme;

use RobertoGallea\Judgment\Evidence;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\Questions\Rating;

final class RefundAbuse extends Judgment
{
    public function __construct(public readonly Refund $refund) {}

    public function evidence(): array
    {
        return [
            'order' => ['item' => $this->refund->item, 'amount_eur' => $this->refund->amount],
            'request' => ['explanation' => Evidence::untrusted($this->refund->explanation)],
        ];
    }

    public function questions(): array
    {
        return [
            'abusive' => Likelihood::that('Is this refund request an attempt to abuse the refund policy?')
                ->means(true: 'Likely a claim the customer is not entitled to', false: 'A good-faith claim'),
            'credibility' => Rating::of('How credible is the explanation in request.explanation?', levels: [
                'Not credible', 'Doubtful', 'Plausible', 'Fully credible',
            ]),
        ];
    }

    public function decision(): string
    {
        return RefundDecision::class;
    }
}
