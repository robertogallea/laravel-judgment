<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

final class Refund
{
    public function __construct(
        public readonly string $item,
        public readonly int $amountEur,
        public readonly string $explanation,
    ) {}
}
