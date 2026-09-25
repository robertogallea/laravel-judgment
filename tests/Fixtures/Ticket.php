<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

final class Ticket
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {}
}
