<?php

namespace RobertoGallea\Judgment\Models;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;
use RobertoGallea\Judgment\Events\AssessmentResolved;
use RobertoGallea\Judgment\Exceptions\InvalidResolution;
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
 * @property ?int $cached_from_id
 * @property ?class-string<Decision> $decision
 * @property ?string $decision_version
 * @property ?class-string<Outcome> $outcome_type
 * @property ?string $outcome
 * @property ?CarbonImmutable $review_requested_at
 * @property ?string $resolution
 * @property ?CarbonImmutable $resolved_at
 * @property-read ?Model $subject
 * @property-read ?Model $resolver
 * @property-read ?AssessmentRecord $cachedFrom
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
     * Record a reviewer's Resolution: the Outcome a person decided, a case of the same enum
     * as the automatic Outcome, kept alongside it. The application's Policy decides who may,
     * through its "resolve" ability on the record.
     *
     * @throws AuthorizationException when the reviewer may not resolve the record
     * @throws InvalidResolution when the record is not awaiting Review, or the Resolution is of another enum or itself requires Review
     */
    public function resolve(Outcome $resolution, Authenticatable&Model $reviewer): void
    {
        app(Gate::class)->forUser($reviewer)->authorize('resolve', $this);

        match (true) {
            $this->resolved_at !== null => throw InvalidResolution::alreadyResolved($this),
            $this->review_requested_at === null => throw InvalidResolution::notAwaitingReview($this),
            $resolution::class !== $this->outcome_type => throw InvalidResolution::otherOutcome($this, $resolution),
            $resolution->requiresReview() => throw InvalidResolution::requiresReview($this, $resolution),
            default => null,
        };

        // Conditional, so of two reviewers resolving at once only the first is recorded.
        $resolved = static::query()->whereKey($this->getKey())->whereNotNull('review_requested_at')->whereNull('resolved_at')->update([
            'resolution' => (string) $resolution->value,
            'resolver_type' => $reviewer->getMorphClass(),
            'resolver_id' => $reviewer->getKey(),
            'resolved_at' => now()->toImmutable(),
        ]);

        $this->refresh();
        if ($resolved === 0) {
            throw $this->resolved_at === null ? InvalidResolution::notAwaitingReview($this) : InvalidResolution::alreadyResolved($this);
        }

        event(new AssessmentResolved($this, $resolution::from((string) $this->outcome), $resolution, $reviewer));
    }

    /** Whether the record's Outcome requires Review and no Resolution has been recorded yet. */
    public function isAwaitingReview(): bool
    {
        return $this->review_requested_at !== null && $this->resolved_at === null;
    }

    /**
     * Records awaiting Review, e.g. for a reviewer's queue.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function awaitingReview(Builder $query): void
    {
        $query->whereNotNull('review_requested_at')->whereNull('resolved_at');
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

    /**
     * For a cache hit, the record the Engine's Assessment was first stored as: its Provenance
     * is this record's too, without its details. Null for an Assessment the Engine produced, or once the original is pruned.
     *
     * @return BelongsTo<AssessmentRecord, $this>
     */
    public function cachedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cached_from_id');
    }

    /** @return MorphTo<Model, $this> the reviewer who recorded the Resolution */
    public function resolver(): MorphTo
    {
        return $this->morphTo();
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
            'review_requested_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
