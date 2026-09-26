<?php

namespace RobertoGallea\Judgment\Calibration;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionNamedType;
use RobertoGallea\Judgment\Evidence;
use RobertoGallea\Judgment\Exceptions\InvalidCalibration;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Models\AssessmentRecord;

/**
 * Loads the labelled cases a Judgment is calibrated against.
 *
 * @internal
 */
final class Cases
{
    /**
     * Cases from a JSON list of {"subject": attributes or key, "expected": Outcome value}: each
     * Subject is built unsaved from its attributes, or found by its key, and the Judgment constructed with it.
     *
     * @param  class-string<Judgment>  $judgment
     * @return list<LabelledCase>
     */
    public static function fromDataset(string $judgment, string $path): array
    {
        $cases = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($cases) || ! array_is_list($cases)) {
            throw InvalidCalibration::unreadable($path);
        }

        $subject = self::subjectClass($judgment);

        return array_map(function (mixed $case, int $index) use ($judgment, $subject) {
            $expected = is_array($case) ? $case['expected'] ?? null : null;
            if (! is_string($expected) && ! is_int($expected)) {
                throw InvalidCalibration::invalidCase($index, 'names no expected Outcome');
            }

            return LabelledCase::labelled(new $judgment(self::subject($subject, $case['subject'] ?? null, $index)), (string) $expected);
        }, $cases, array_keys($cases));
    }

    /**
     * Cases from the Judgment's resolved records, one per Evidence labelled with its latest Resolution, asked over
     * the Evidence as recorded, its untrusted text marked again; over its Subject's current Evidence
     * when the Evidence was not stored. A record whose Subject no longer exists is skipped.
     *
     * @param  class-string<Judgment>  $judgment
     * @param  int  $skipped  set to how many records were skipped
     * @return list<LabelledCase>
     */
    public static function fromResolutions(string $judgment, int &$skipped = 0): array
    {
        $cases = [];
        $skipped = 0;
        $records = AssessmentRecord::query()->where('judgment', $judgment)->whereNotNull('resolved_at')
            ->orderByDesc('resolved_at')->orderByDesc('id')->lazy();

        foreach ($records as $record) {
            if (isset($cases[$record->evidence_fingerprint])) {
                continue;
            }
            if ($record->subject === null || $record->resolution === null) {
                $skipped++;

                continue;
            }

            $instance = new $judgment($record->subject);
            $cases[$record->evidence_fingerprint] = $record->evidence === null
                ? LabelledCase::labelled($instance, $record->resolution)
                : new LabelledCase($instance, self::marked($record->evidence, $record->untrusted_paths), $record->resolution, $record->language);
        }

        return array_values($cases);
    }

    /**
     * Recorded Evidence with the text at each untrusted path marked untrusted again.
     *
     * @param  array<string, mixed>  $evidence
     * @param  list<string>  $untrusted
     * @return array<string, mixed>
     */
    private static function marked(array $evidence, array $untrusted): array
    {
        foreach ($untrusted as $path) {
            data_set($evidence, $path, Evidence::untrusted((string) data_get($evidence, $path)));
        }

        return $evidence;
    }

    /**
     * The Eloquent model the Judgment is constructed with.
     *
     * @param  class-string<Judgment>  $judgment
     * @return class-string<Model>
     */
    private static function subjectClass(string $judgment): string
    {
        $type = (new ReflectionClass($judgment))->getConstructor()?->getParameters()[0]?->getType();
        if (! $type instanceof ReflectionNamedType || ! is_a($type->getName(), Model::class, true)) {
            throw InvalidCalibration::notEloquentSubject($judgment);
        }

        /** @var class-string<Model> */
        return $type->getName();
    }

    /** @param  class-string<Model>  $class */
    private static function subject(string $class, mixed $subject, int $index): Model
    {
        return match (true) {
            is_array($subject) => (new $class)->forceFill($subject),
            is_int($subject), is_string($subject) => $class::query()->find($subject) ?? throw InvalidCalibration::invalidCase($index, "names a Subject {$class} {$subject} that does not exist"),
            default => throw InvalidCalibration::invalidCase($index, 'names no Subject'),
        };
    }
}
