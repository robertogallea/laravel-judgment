<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\UntrustedText;

/** A Judgment that may choose a non-default Engine connection. */
final class SpamCheck extends Judgment
{
    public function __construct(public readonly string|UntrustedText $message, private readonly ?string $connection = null) {}

    public function evidence(): array
    {
        return ['message' => $this->message];
    }

    public function questions(): array
    {
        return ['spam' => Likelihood::that('Is this message unsolicited advertising?')];
    }

    public function engine(): ?string
    {
        return $this->connection;
    }
}
