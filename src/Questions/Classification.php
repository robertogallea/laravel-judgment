<?php

namespace RobertoGallea\Judgment\Questions;

use BackedEnum;
use RobertoGallea\Judgment\Answers\ClassificationAnswer;
use RobertoGallea\Judgment\Exceptions\InvalidQuestion;

/** A Question answered by choosing among a closed set of labels, with a probability for each. */
final class Classification extends Question
{
    /** @var array<string, string|null> label => description */
    private array $labels = [];

    /** @var class-string<BackedEnum>|null */
    private ?string $enum = null;

    public static function of(string $question): self
    {
        return new self($question);
    }

    /**
     * The closed set of labels: a list, a label => description map, or a backed
     * enum whose values are the labels, described by its description() method if any.
     *
     * @param  list<string>|array<string, string>|class-string<BackedEnum>  $labels
     */
    public function labels(array|string $labels): self
    {
        $classification = clone $this;
        $classification->enum = is_string($labels) ? $labels : null;

        if (is_string($labels)) {
            $classification->labels = self::describedCases($labels);
        } elseif (array_is_list($labels)) {
            $classification->labels = self::undescribed($labels);
        } else {
            /** @var array<string, string> $labels a non-list is a label => description map */
            $classification->labels = $labels;
        }

        match (true) {
            count($classification->labels) < 2 => throw InvalidQuestion::tooFewLabels(count($classification->labels)),
            count($classification->labels) > 255 => throw InvalidQuestion::tooManyLabels(count($classification->labels)),
            default => null,
        };

        return $classification;
    }

    public function ensureAnswerable(string $key): void
    {
        if ($this->labels === []) {
            throw InvalidQuestion::noLabels($key);
        }
    }

    /** @return array<string, string|null> label => description, so the Engine reads each label as intended */
    public function criteria(): array
    {
        return $this->labels;
    }

    /** @param  non-empty-array<string, float>  $probabilities  label => probability, as the Engine answered */
    public function answer(array $probabilities): ClassificationAnswer
    {
        return new ClassificationAnswer($probabilities, $this->enum);
    }

    /**
     * @param  list<string>  $labels
     * @return array<string, null>
     */
    private static function undescribed(array $labels): array
    {
        foreach (array_count_values($labels) as $label => $count) {
            if ($count > 1) {
                throw InvalidQuestion::repeatedLabel((string) $label);
            }
        }

        return array_fill_keys($labels, null);
    }

    /** @return array<string, string|null> */
    private static function describedCases(string $enum): array
    {
        if (! is_subclass_of($enum, BackedEnum::class)) {
            throw InvalidQuestion::notLabels($enum);
        }

        $labels = [];
        foreach ($enum::cases() as $case) {
            $labels[(string) $case->value] = method_exists($case, 'description') ? $case->description() : null;
        }

        return $labels;
    }
}
