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

    /**
     * A Likelihood Set: one independent Likelihood per label, each label supplying its complete question.
     *
     * @param  array<string, string>|list<string>|class-string<\BackedEnum>  $labels  label => question, or a backed enum with a question() method
     */
    public static function each(array|string $labels): LikelihoodSet
    {
        return LikelihoodSet::of($labels);
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
