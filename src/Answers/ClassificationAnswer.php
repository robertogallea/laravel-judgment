<?php

namespace RobertoGallea\Judgment\Answers;

use BackedEnum;
use RobertoGallea\Judgment\Exceptions\UndeclaredLabel;

/** The probability of each label of a Classification. */
final class ClassificationAnswer implements Answer
{
    /**
     * @param  non-empty-array<string, float>  $probabilities
     * @param  class-string<BackedEnum>|null  $enum  the enum the labels come from, if any
     */
    public function __construct(
        private readonly array $probabilities,
        private readonly ?string $enum = null,
    ) {}

    /** The most probable label: an enum case when the labels come from a backed enum. */
    public function label(): string|BackedEnum
    {
        return $this->enum === null ? $this->top() : $this->enum::from($this->top());
    }

    public function is(string|BackedEnum $label): bool
    {
        return $this->top() === $this->key($label);
    }

    public function probabilityOf(string|BackedEnum $label): float
    {
        return $this->probabilities[$this->key($label)];
    }

    /** @return array<string, float> label => probability */
    public function probabilities(): array
    {
        return $this->probabilities;
    }

    /** How decisively the top label beats the runner-up: their probability margin (ADR-0005). */
    public function confidence(): float
    {
        return Confidence::of($this->probabilities);
    }

    private function top(): string
    {
        return (string) array_search(max($this->probabilities), $this->probabilities, true);
    }

    private function key(string|BackedEnum $label): string
    {
        $key = $label instanceof BackedEnum ? (string) $label->value : $label;

        if (! array_key_exists($key, $this->probabilities)) {
            throw UndeclaredLabel::for($key, array_map(strval(...), array_keys($this->probabilities)));
        }

        return $key;
    }
}
