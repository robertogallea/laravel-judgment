<?php

namespace RobertoGallea\Judgment\Tests\Fixtures\Readme;

final class Refund
{
    public function __construct(
        public readonly string $item,
        public readonly int $amount,
        public readonly string $explanation,
        public readonly Customer $customer,
    ) {}
}
