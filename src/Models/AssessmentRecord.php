<?php

namespace RobertoGallea\Judgment\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Exceptions\UnrebuildableAssessment;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Support\AssessmentRecorder;

/**
 * The stored record of an Assessment (ADR-0003), kept apart from the immutable
 * Assessment value (ADR-0009) with what audit, replay and Calibration need.
 *
 * @property int $id
 * @property class-string<Judgment> $judgment
 * @property ?array<string, mixed> $evidence
 * @property string $evidence_fingerprint
 * @property list<string> $untrusted_paths
 * @property ?string $language
 * @property string $questions_fingerprint
 * @property array<string, mixed> $answers
 * @property string $engine
 * @property string $model
 * @property ?string $request_id
 * @property array<string, mixed> $provenance_details
 * @property ?class-string<Decision> $decision
 * @property ?string $decision_version
 * @property ?string $outcome
 * @property-read ?Model $subject
 */
class AssessmentRecord extends Model
{
    use MassPrunable;

    protected $table = 'judgment_assessments';

    protected $guarded = [];

    /**
     * Rebuild the immutable Assessment, e.g. to apply another Decision to it. The
     * Judgment is constructed with the Subject, unless one is given.
     */
    public function assessment(?Judgment $judgment = null): Assessment
    {
        $judgment ??= $this->subject === null
            ? throw UnrebuildableAssessment::noSubject($this)
            : new $this->judgment($this->subject);

        return app(AssessmentRecorder::class)->rebuild($this, $judgment);
    }

    /**
     * Apply the Judgment's default Decision to the recorded Assessment and record
     * its Outcome: how a queued listener, holding only a copy, records one.
     */
    public function outcome(?Judgment $judgment = null): Outcome
    {
        return $this->linked($judgment)->outcome();
    }

    /** Apply the given Decision to the recorded Assessment and record its Outcome. */
    public function decide(Decision $decision, ?Judgment $judgment = null): Outcome
    {
        return $this->linked($judgment)->decide($decision);
    }

    /**
     * Records older than judgment.persistence.retention_days, removed by model:prune; none when it is null.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('judgment.persistence.retention_days');

        return is_numeric($days)
            ? static::query()->where('created_at', '<', now()->subDays((int) $days))
            : static::query()->whereKey([]);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** The rebuilt Assessment, linked for a single decision so a what-if never records. */
    private function linked(?Judgment $judgment): Assessment
    {
        return app(AssessmentRecorder::class)->link($this->assessment($judgment), $this);
    }

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'untrusted_paths' => 'array',
            'answers' => 'array',
            'provenance_details' => 'array',
        ];
    }
}
