<?php

namespace RobertoGallea\Judgment\Questions;

/** A Question answered with the probability that a statement is true. */
final class Likelihood extends Question
{
    /** @var array{true: string, false: string}|null */
    private ?array $criteria = null;

    public static function that(string $statement): self
    {
        return new self($statement);
    }

    /** Describe what "true" and "false" mean, so the Engine reads the Question as intended. */
    public function means(string $true, string $false): self
    {
        $likelihood = clone $this;
        $likelihood->criteria = ['true' => $true, 'false' => $false];

        return $likelihood;
    }

    /** @return array{true: string, false: string}|null */
    public function criteria(): ?array
    {
        return $this->criteria;
    }
}
