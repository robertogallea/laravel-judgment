<?php

namespace RobertoGallea\Judgment\Tests\Fixtures\Readme;

final class Customer
{
    public function __construct(private readonly int $refundsThisYear) {}

    public function refundsThisYear(): int
    {
        return $this->refundsThisYear;
    }
}
