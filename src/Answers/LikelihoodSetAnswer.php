<?php

namespace RobertoGallea\Judgment\Answers;

use BackedEnum;
use RobertoGallea\Judgment\Exceptions\UndeclaredLabel;

/** The Likelihood of each label of a Likelihood Set, each answered independently of the others. */
final class LikelihoodSetAnswer implements Answer
{
    /**
     * @param  non-empty-array<string, LikelihoodAnswer>  $likelihoods  label => answer
     * @param  class-string<BackedEnum>|null  $enum  the enum the labels come from, if any
     */
    public function __construct(
        private readonly array $likelihoods,
        private readonly ?string $enum = null,
    ) {}

    public function of(string|BackedEnum $label): LikelihoodAnswer
    {
        $key = $label instanceof BackedEnum ? (string) $label->value : $label;

        return $this->likelihoods[$key]
            ?? throw UndeclaredLabel::for($key, array_map(strval(...), array_keys($this->likelihoods)), 'Likelihood Set');
    }

    /** The highest Likelihood in the set, for "any label at all" thresholds. */
    public function max(): LikelihoodAnswer
    {
        return new LikelihoodAnswer(max(array_map(fn (LikelihoodAnswer $answer) => $answer->probability(), $this->likelihoods)));
    }

    /**
     * The labels whose Likelihood is at or over the threshold, highest first.
     *
     * @return list<string|BackedEnum> enum cases when the labels come from a backed enum
     */
    public function above(float $threshold): array
    {
        $hits = array_filter($this->likelihoods, fn (LikelihoodAnswer $answer) => $answer->above($threshold));
        uasort($hits, fn (LikelihoodAnswer $a, LikelihoodAnswer $b) => $b->probability() <=> $a->probability());

        $enum = $this->enum;

        return array_map(
            fn (string|int $label) => $enum === null ? (string) $label : $enum::from($label),
            array_keys($hits),
        );
    }
}
