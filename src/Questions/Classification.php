<?php

namespace RobertoGallea\Judgment\Questions;

use BackedEnum;
use RobertoGallea\Judgment\Answers\ClassificationAnswer;
use RobertoGallea\Judgment\Exceptions\InvalidQuestion;

/** A Question answered by choosing among a closed set of labels, with a probability for each. */
final class Classification extends Question
{
    /**
     * @param  array<string, string|null>  $labels  label => description
     * @param  class-string<BackedEnum>|null  $enum  the enum the labels come from, if any
     */
    private function __construct(
        string $question,
        private readonly array $labels,
        private readonly ?string $enum,
    ) {
        parent::__construct($question);

        match (true) {
            count($labels) < 2 => throw InvalidQuestion::tooFewLabels(count($labels)),
            count($labels) > 255 => throw InvalidQuestion::tooManyLabels(count($labels)),
            default => null,
        };
    }

    /**
     * The closed set of labels is a list, a label => description map, or a backed
     * enum whose values are the labels, described by its description() method if any.
     *
     * @param  list<string>|array<string, string>|class-string<BackedEnum>  $labels
     */
    public static function of(string $question, array|string $labels): self
    {
        if (is_string($labels)) {
            return new self($question, self::describedCases($labels), $labels);
        }

        if (array_is_list($labels)) {
            return new self($question, self::undescribed($labels), null);
        }

        /** @var array<string, string> $labels a non-list is a label => description map */
        return new self($question, $labels, null);
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
