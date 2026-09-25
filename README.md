# Laravel Judgment

Probabilistic assessments of unstructured evidence, with deterministic, application-owned decisions.

Judgment is for criteria that can only be described and hard rules can be implemented: "is this refund request an attempt to abuse the policy?", "which team should handle this ticket?". It sits after validation, authorization and business rules. An Engine answers typed Questions over the Evidence you declare; your own Decision class turns those answers into an Outcome. The Engine never sees your Outcomes or thresholds.

## Installation

```bash
composer require robertogallea/laravel-judgment
```

Judgments are assessed on [Jev](https://docs.typesafe.ai) by default. Set your API key:

```dotenv
TYPESAFE_API_KEY=your-key
```

To publish the config file: `php artisan vendor:publish --tag=judgment-config`. See [Engines](#engines) for the Jev options and for other Engines.

Every Assessment is recorded in the database, so publish and run the migration:

```bash
php artisan vendor:publish --tag=judgment-migrations
php artisan migrate
```

## Declaring a Judgment

A Judgment is constructed with its Subject, like a Mailable. It declares its Evidence explicitly, the Questions to ask, and optionally a default Decision.

```php
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Likelihood;

final class RefundAbuse extends Judgment
{
    public function __construct(public readonly Refund $refund) {}

    public function evidence(): array
    {
        return [
            'order' => ['item' => $this->refund->item, 'amount_eur' => $this->refund->amount],
            'request' => ['explanation' => $this->refund->explanation],
        ];
    }

    public function questions(): array
    {
        return [
            'abusive' => Likelihood::that('Is this refund request an attempt to abuse the refund policy?')
                ->means(true: 'Likely a claim the customer is not entitled to', false: 'A good-faith claim'),
        ];
    }

    public function decision(): string
    {
        return RefundDecision::class;
    }
}
```

Every Question is asked independently, and is phrased about the world, never about what to do.

### Untrusted Evidence

Mark text written by an end user with `Evidence::untrusted()`, so the Engine treats it as a claim to assess and never as instructions:

```php
use RobertoGallea\Judgment\Evidence;

'request' => ['explanation' => Evidence::untrusted($this->refund->explanation)],
```

The text stays at the path where you declared it, so a Question can still refer to `request.explanation`. The Jev driver sends it at that path, fenced in tags that the text cannot close and prefaced by a note on how to read it. Other Engines receive an `UntrustedText` value, which serialises to the plain text.

## Questions

**Likelihood**: the probability that a statement is true.

```php
Likelihood::that('Is the listed product counterfeit?')
```

**Classification**: a choice among 2 to 255 labels, with a probability for each. Labels are a list, a `label => description` map, or a backed enum (described by its `description()` method, if any).

```php
Classification::of('In which language is the ticket written?', labels: ['english', 'italian', 'other'])
Classification::of('Which team should handle this ticket?', labels: Department::class)
```

**Rating**: a position on an ordered scale of 2 to 10 described levels, lowest first.

```php
Rating::of('How badly does the problem affect the customer?', levels: [
    'Cosmetic', 'Annoying', 'Degrading their work', 'Blocking their work',
])
```

**Likelihood Set**: independent Likelihoods over a closed set of labels, for multi-label questions. Each label supplies its complete question (there is no templating), either from a `label => question` map or from a backed enum with a `question()` method.

```php
enum Harm: string
{
    case Hate = 'hate';
    case Spam = 'spam';

    public function question(): string
    {
        return match ($this) {
            self::Hate => 'Does the post attack people based on a protected characteristic?',
            self::Spam => 'Is the post unsolicited promotion?',
        };
    }
}

'harms' => Likelihood::each(Harm::class),
'topics' => Likelihood::each([
    'politics' => 'Is the post about politics?',
    'sport' => 'Is the post about sport?',
]),
```

The Engine receives one Likelihood per label under dotted keys (`harms.hate`, `harms.spam`), so labels cannot contain a dot.

## Reading an Assessment

```php
$assessment = (new RefundAbuse($refund))->assess();

$assessment->likelihood('abusive')->probability();       // 0.72
$assessment->likelihood('abusive')->above(.65);          // true (at or over)

$department = $assessment->classification('department');
$department->label();                                    // Department::Billing
$department->probabilityOf(Department::Technical);       // 0.20
$department->confidence();                               // top probability minus runner-up

$severity = $assessment->rating('severity');
$severity->level();                                      // most probable level, from 0
$severity->expected();                                   // probability-weighted mean level

$harms = $assessment->likelihoodSet('harms');
$harms->of(Harm::Spam)->above(.40);                      // one label's Likelihood
$harms->max()->above(.80);                               // the highest Likelihood in the set
$harms->labelsAbove(.40);                                // [Harm::Spam, ...], highest first
```

Keys can be strings or backed-enum cases. Reading an undeclared key, or a Question as the wrong kind, throws an exception naming what is declared. `$assessment->provenance` records the Engine, model and request that produced the answers.

## Deciding

A Decision is a plain, pure class that maps an Assessment to an Outcome. Outcomes are a backed enum implementing `Outcome`.

```php
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Contracts\Decision;
use RobertoGallea\Judgment\Contracts\Outcome;

enum RefundOutcome: string implements Outcome
{
    case Approve = 'approve';
    case Escalate = 'escalate';
    case Reject = 'reject';

    public function requiresReview(): bool
    {
        return $this === self::Escalate;
    }
}

final class RefundDecision implements Decision
{
    public function __invoke(Assessment $assessment, RefundAbuse $judgment): RefundOutcome
    {
        $abusive = $assessment->likelihood('abusive');

        return match (true) {
            $abusive->above(.65) => RefundOutcome::Reject,
            $abusive->above(.30) => RefundOutcome::Escalate,
            default => RefundOutcome::Approve,
        };
    }
}

$outcome = $assessment->outcome();                        // the Judgment's default Decision
$outcome = $assessment->decide(new StrictRefundDecision()); // another Decision over the same answers
```

## Generating Judgments and Decisions

```bash
php artisan make:judgment RefundAbuse --subject=Refund
php artisan make:decision RefundDecision --judgment=RefundAbuse --outcome=RefundOutcome
```

`make:judgment` writes `app/Judgments/RefundAbuse.php`, constructed with the Subject, with empty `evidence()`, `questions()` and `decision()` to fill in. A bare `--subject` is placed under `App\Models` (or `App` when there is no `app/Models` directory); without it the Subject is typed `mixed`.

`make:decision` writes `app/Decisions/RefundDecision.php`, type-hinting the Judgment (`App\Judgments\…`) and returning the Outcome enum (`App\Enums\…`). Pass a fully qualified class to use another namespace; a class whose name clashes with an import is written fully qualified. Without the options it falls back to the `Judgment` and `Outcome` contracts.

A generated Decision contains no thresholds, only a commented placeholder arm, and throws a `LogicException` until you replace it. An Engine's probabilities differ per Question, so there are no sensible default numbers: calibrate each threshold against labelled examples with `php artisan judgment:eval`.

## When the Engine fails

A failed Engine call is never turned into a default Outcome. By default `assess()` throws `RobertoGallea\Judgment\Exceptions\EngineFailed`, wrapping the exception the Engine threw. Errors such as a `TypeError` are bugs, not Engine failures, and pass through unwrapped. A response that leaves a Question unanswered, answers one that was not asked, or cannot be read (such as a Classification answered with undeclared labels) throws `MalformedEngineResponse`, which extends `EngineFailed`.

The Jev driver reports each kind of error with its own exception. All of them extend `EngineFailed`, and each message carries the Engine request id:

| Jev status | Exception |
|---|---|
| 400, 422 | `EngineRejectedRequest`: the request is invalid (such as an unknown model), so retrying will not help |
| 401, 403 | `EngineUnauthorized`: the API key is wrong or missing |
| 429 | `EngineRateLimited`, once the retries are used up |
| 529 | `EngineOverloaded`, once the retries are used up |

To handle failures as data instead, set the failure mode to `unassessed`:

```dotenv
JUDGMENT_FAILURE=unassessed
```

`assess()` then returns an `Unassessed` state in place of an Assessment. It carries the Judgment and the exception, and has no answers and no Outcome, so the application has to decide what an unassessed Judgment means:

```php
use RobertoGallea\Judgment\Unassessed;

$result = (new RefundAbuse($refund))->assess();

if ($result instanceof Unassessed) {
    report($result->exception);

    return $this->sendToManualReview($refund);
}

$outcome = $result->outcome();
```

`assess()` is typed `Assessment|Unassessed` in both modes; in the default mode it never returns `Unassessed`.

## Engines

An Engine answers a Judgment's Questions. Each connection in `judgment.engines` names its driver, and `judgment.engine` (`JUDGMENT_ENGINE`) picks the default connection. The package ships a `jev` connection:

| Option | Env | Default |
|---|---|---|
| `key` | `TYPESAFE_API_KEY` | none, required |
| `url` | `TYPESAFE_BASE_URL` | `https://api.typesafe.ai` |
| `model` | `JUDGMENT_JEV_MODEL` | `jev-1.13.0` |
| `allow_aliases` | `JUDGMENT_JEV_ALLOW_ALIASES` | `false` |
| `timeout` (seconds, per attempt) | `JUDGMENT_JEV_TIMEOUT` | `10` |
| `retries` | `JUDGMENT_JEV_RETRIES` | `3` |

**Pin the model.** You calibrate thresholds against one model version, so the model is pinned to an exact version by default. An alias such as `jev-latest` can change underneath those thresholds, so it throws `UnpinnedModel` in production and logs a warning in other environments, unless you set `allow_aliases` on the connection.

**Retries.** A rate-limited (429) or overloaded (529) request is retried up to `retries` times. The driver waits as long as Jev's `retry-after-ms` or `retry-after` header asks, capped at a minute. Without a header it backs off exponentially from half a second. Every other error fails at once.

**Provenance.** Each Assessment records the exact model that answered and Jev's `x-typesafe-request-id`, for correlating with TypeSafe support. `$assessment->provenance->details` also holds the token `usage`, Jev's own `confidence` per Classification and Rating, and the raw `response`. Jev's confidence is kept for audit only: `confidence()` on an answer is always the package's own measure.

A Judgment can choose another connection:

```php
public function engine(): ?string
{
    return 'jev-eu';
}
```

A connection's `driver` is `jev`, a driver you register, or a class implementing `RobertoGallea\Judgment\Contracts\Engine`, which is resolved from the container. An Engine implements `answer()`, and `model()` returning the exact model version it answers with, which keys the [cache](#caching):

```php
use RobertoGallea\Judgment\EngineManager;

// config/judgment.php: 'engines' => ['classifier' => ['driver' => 'classifier', 'url' => '...']]
app(EngineManager::class)->extend('classifier', fn ($app, array $config, string $connection) => new ClassifierEngine($config['url']));
```

## Caching

A Judgment can reuse the Assessment of identical Evidence and Questions instead of paying for another Engine round. Caching is off by default. Opt in with `cacheFor()`, returning seconds, a `DateInterval` or an expiry:

```php
public function cacheFor(): int
{
    return 3600;
}
```

The cache key covers:
- the Judgment class;
- its question fingerprint;
- the Evidence fingerprint and the paths of its untrusted text;
- the Engine class and its pinned `model()`.

Changing a Question's wording or criteria, the Evidence (including marking text as untrusted), the Engine or its pinned model misses the cache.

A cache hit is never a silent copy. It is stored as a new record pointing at the original record through `cached_from_id`. It carries the original's Provenance (engine, model, request id), but not the Provenance details: the token usage and raw response stay on the original only, so a hit is never counted as a second Engine round.

```php
$record->cachedFrom;   // the original AssessmentRecord; null for an Engine answer, or once the original is pruned
```

A hit is its own Assessment: it fires `AssessmentCompleted` with its own record, and deciding it records its own Outcome and, if that Outcome requires it, its own Review. It logs `Judgment assessed from cache.` instead of `Judgment assessed.`.

Nothing is cached when the Engine fails. Cached Assessments go to the application's default cache store, which holds the answers and the engine, model and request id, never the Evidence. Caching works with persistence off too. Something cached while persistence was off is not reused once it is on, since a hit would have no original record to point at. `Judge::fake()` answers from its scripts and never caches.

## Persisted Assessments

Every Assessment is recorded as an `RobertoGallea\Judgment\Models\AssessmentRecord` in the `judgment_assessments` table, for audit, replay and Calibration. The Assessment itself stays an immutable value with no database behind it. A record stores:

| Column | What |
| --- | --- |
| `judgment` | the Judgment class |
| `subject_type`, `subject_id` | the Subject, polymorphic |
| `evidence`, `evidence_fingerprint` | the Evidence as the Engine was asked (untrusted text as plain text), and its SHA-256 |
| `untrusted_paths` | the dotted paths of the untrusted text, e.g. `["request.explanation"]` |
| `language` | the Evidence language the Judgment declares |
| `questions_fingerprint` | a SHA-256 of each Question's key, kind, instructions and criteria (labels, levels, meanings) |
| `answers` | the probabilities, per Question |
| `engine`, `model`, `request_id`, `provenance_details` | the Provenance |
| `cached_from_id` | for a [cache hit](#caching), the record the Engine's Assessment was first stored as |
| `decision`, `decision_version`, `outcome_type`, `outcome` | the Decision last applied, and its Outcome's enum and value |
| `review_requested_at` | when an Outcome requiring Review put the record in Review |
| `resolution`, `resolver_type`, `resolver_id`, `resolved_at` | the reviewer's Resolution, who recorded it, and when |

The Subject is the only Eloquent model among the Judgment's public instance properties. If the Judgment has none, or more than one, or the model is not saved yet, no Subject is recorded. Override `subject()` to choose it. The language is not detected or translated. A Judgment declares it by overriding `language()`:

```php
public function language(): ?string
{
    return $this->refund->locale;
}
```

The Decision, its version and its Outcome are written when you call `outcome()` or `decide()` on the Assessment that `assess()` returned. A Decision declares a version with a `version()` method. Bump it when you change the thresholds, so Calibration never mixes the two:

```php
final class RefundDecision implements Decision
{
    public function __invoke(Assessment $assessment, RefundAbuse $judgment): RefundOutcome { /* ... */ }

    public function version(): string
    {
        return '2';
    }
}
```

Add `HasAssessments` to the Subject's model to reach its records:

```php
use RobertoGallea\Judgment\Concerns\HasAssessments;

class Refund extends Model
{
    use HasAssessments;
}

$refund->assessments;                                // every record, of every Judgment
$record = $refund->latestAssessment(RefundAbuse::class); // or null
```

A record rebuilds its Assessment, so you can apply another Decision to past answers:

```php
$assessment = $record->assessment();                  // constructs RefundAbuse with the recorded Subject
$assessment = $record->assessment(new RefundAbuse($refund)); // or over a Judgment you construct

$assessment->decide(new StrictRefundDecision());
```

A rebuilt Assessment is not linked to its record, so deciding it never overwrites the recorded Outcome. Rebuilding throws `UnrebuildableAssessment` in three cases: the record belongs to another Judgment, the Judgment's Questions have changed since it was recorded (the fingerprints differ), or there is no Subject to construct the Judgment with.

Persistence is configured under `judgment.persistence`:

| Key | Env | Default | |
| --- | --- | --- | --- |
| `enabled` | `JUDGMENT_PERSIST` | `true` | record Assessments at all |
| `evidence` | `JUDGMENT_PERSIST_EVIDENCE` | `true` | `false` stores only the Evidence fingerprint and untrusted paths, e.g. when the Evidence holds personal data |
| `retention_days` | `JUDGMENT_RETENTION_DAYS` | `365` | records older than this are pruned; `null` keeps them forever |

Pruning uses Laravel's `model:prune`. The package's model is not in `app/Models`, so name it when you schedule the command:

```php
Schedule::command('model:prune', ['--model' => [AssessmentRecord::class]])->daily();
```

## Queued assessment

Assess a Judgment off the request cycle:

```php
use RobertoGallea\Judgment\Facades\Judge;

Judge::dispatch(new RefundAbuse($refund));
(new RefundAbuse($refund))->dispatch()->onQueue('judgments')->delay(now()->addMinute());
```

Both return Laravel's `PendingDispatch`, so you can chain `onConnection()`, `onQueue()` and `delay()`. The queued job assesses the Judgment and fires the same `AssessmentCompleted` and `AssessmentFailed` events as `assess()`. With `judgment.failure = throw` (the default) a failed assessment fails the job. With `unassessed` the job completes.

A Judgment is queued like a Mailable. The Eloquent models and Eloquent Collections held directly in its properties are serialised by reference and fetched fresh from the database when the job runs, so the Evidence is read as it is then, not as it was when dispatched. Anything else is serialised whole:

- A model nested inside an array or another object comes back as it was at dispatch.
- A closure cannot be queued at all.
- Laravel restores only the private properties declared on the Judgment's own class, not on a parent class between it and `Judgment`. Make those protected.

If the Subject is deleted before the job runs, the job fails with a `ModelNotFoundException` while being restored. No `AssessmentFailed` fires, because there is no Judgment left to assess.

| Key | Env | Default | |
| --- | --- | --- | --- |
| `queue.connection` | `JUDGMENT_QUEUE_CONNECTION` | `null` | the application's default connection |
| `queue.queue` | `JUDGMENT_QUEUE` | `null` | the connection's default queue |
| `queue.tries` | `JUDGMENT_QUEUE_TRIES` | `1` | each attempt is a paid Engine round, and the Jev driver already retries rate-limited and overloaded requests |

A queued listener of `AssessmentCompleted` receives a copy of the Assessment, which is no longer linked to its record. To record the Outcome, decide through the record the event carries:

```php
final class ActOnRefundAbuse implements ShouldQueue
{
    public function handle(AssessmentCompleted $event): void
    {
        $outcome = $event->record?->outcome();                          // the default Decision, recorded
        // or $event->record?->decide(new StrictRefundDecision());        // another Decision, recorded
    }
}
```

Which call records the Outcome:

| Where | Call | Records the Outcome |
| --- | --- | --- |
| The same process as `assess()` | `$assessment->outcome()` / `->decide()` | yes |
| Across a queue boundary | `$record->outcome()` / `->decide()` | yes |
| What-if or Calibration | `$record->assessment()->decide()` | no |

Like `assessment()`, the record's `outcome()` and `decide()` take an optional Judgment to rebuild over.

## Review and Resolution

An Outcome whose `requiresReview()` returns true needs a person to decide before its Action is taken:

```php
enum RefundOutcome: string implements Outcome
{
    case Approve = 'approve';
    case Escalate = 'escalate';
    case Reject = 'reject';

    public function requiresReview(): bool
    {
        return $this === self::Escalate;
    }
}
```

When a recorded Decision yields such an Outcome (through `outcome()` or `decide()`, as in the table above), the record enters Review and `AssessmentAwaitingReview` fires. A record enters Review once, even when two copies of it are decided at the same moment. From then on it keeps the Outcome and Decision that sent it to Review: deciding it again records nothing and fires no event. A what-if on a rebuilt Assessment never starts Review. The package ships no review UI: notify reviewers from the event.

```php
use RobertoGallea\Judgment\Events\AssessmentAwaitingReview;

final class NotifyReviewers implements ShouldQueue
{
    public function handle(AssessmentAwaitingReview $event): void
    {
        if ($event->record === null) {
            return; // persistence is off: nothing to resolve
        }

        Notification::send(User::reviewers()->get(), new RefundNeedsReview($event->record, $event->outcome, $event->requestedAt));
    }
}
```

The event carries `$judgment`, `$assessment`, `$outcome`, `$record` and `$requestedAt`. `$record` is null when persistence is off or under `Judge::fake()`, and a record is needed to resolve. Find the records awaiting Review with the `awaitingReview` scope:

```php
AssessmentRecord::awaitingReview()->where('judgment', RefundAbuse::class)->oldest('review_requested_at')->get();
$record->isAwaitingReview();
```

A reviewer records the Resolution, a case of the same Outcome enum, which may overturn the automatic Outcome:

```php
$record->resolve(RefundOutcome::Approve, $request->user());
```

Who may resolve is up to your application, through the `resolve` ability of an ordinary Policy on `AssessmentRecord`. Until you define one, every reviewer is refused with an `AuthorizationException`:

```php
use RobertoGallea\Judgment\Models\AssessmentRecord;

final class AssessmentRecordPolicy
{
    public function resolve(User $user, AssessmentRecord $record): bool
    {
        return $user->can_review && $record->judgment === RefundAbuse::class;
    }
}

Gate::policy(AssessmentRecord::class, AssessmentRecordPolicy::class); // e.g. in AppServiceProvider::boot()
```

`resolve()` throws `InvalidResolution` if the record is not awaiting Review or is already resolved, or if the Resolution is a case of another enum or itself requires Review. It checks and records in a single conditional update, so when two reviewers resolve the same record at once, only the first is recorded. The record keeps both Outcomes: `outcome` for the automatic one, `resolution` and `resolver` for the person's.

Once recorded, `AssessmentResolved` fires with `$record`, `$outcome` (the automatic Outcome), `$resolution` and `$reviewer`. Perform the Action for the Resolution there:

```php
public function handle(AssessmentResolved $event): void
{
    match ($event->resolution) {
        RefundOutcome::Approve => $this->refunds->approve($event->record->subject),
        RefundOutcome::Reject => $this->refunds->reject($event->record->subject),
        RefundOutcome::Escalate => null,
    };
}
```

## Calibration

An Engine's probabilities differ per Question, so each threshold should come from evidence. `judgment:eval` asks the configured Engine about labelled cases, applies the Decision to each answer, and reports how the Decision's thresholds behave:

```bash
php artisan judgment:eval RefundAbuse                                   # past Resolutions are the labels
php artisan judgment:eval RefundAbuse --dataset=storage/refunds.json    # a labelled dataset
php artisan judgment:eval RefundAbuse --decision=RefundDecision --decision=StrictRefundDecision
php artisan judgment:eval RefundAbuse --engine=jev --engine=jev-next     # compare two pinned models
```

A bare Judgment name is looked up under `App\Judgments`, and a bare Decision name under `App\Decisions`.

**Labels.** Without `--dataset`, the Judgment's resolved `AssessmentRecord`s are the cases: one per Evidence, labelled with its latest Resolution. Only Assessments sent to Review get a Resolution, so these labels cluster where the Decision was unsure; add a dataset to cover the confident bands. Each case is asked over the Evidence as it was recorded, with untrusted text marked again. When the Evidence was not stored, it is asked over the Subject's current Evidence. A record whose Subject no longer exists is skipped, and the report says how many were. A dataset is a JSON list of cases. Each case gives a Subject, either as attributes (built unsaved) or as a key (found), and the Outcome value a person says is right:

```json
[
    {"subject": {"item": "Headphones", "amount_eur": 120, "explanation": "Arrived damaged."}, "expected": "approve"},
    {"subject": 42, "expected": "reject"}
]
```

A dataset works with a Judgment constructed with an Eloquent model. An expected Outcome that is not a value of the Decision's Outcome enum stops the run, so a typo cannot quietly lower accuracy. So does one that requires Review, which no Resolution can be.

**Decisions.** A set of thresholds is a Decision, so compare candidates by passing several `--decision` options. Without the option, the Judgment's default Decision applies. Nothing is cached, recorded, logged or announced. A case the Engine fails on is counted as unassessed, under the model version the Engine reported for the other cases.

**Engines.** Each `--engine` names a connection in `judgment.engines`. Every case is asked of each one, so two pinned model versions are compared on the same cases. Without the option, each Judgment's own Engine is asked.

**The report.** Results are grouped by question-set fingerprint, model version, Decision and version, and Evidence language, and groups are never mixed (ADR-0008). A summary table gives one row per group:

| Column | Meaning |
| --- | --- |
| Questions | the first characters of the question-set fingerprint |
| Cases, Unassessed | the cases asked, and how many of them the Engine failed on |
| Review rate | the share of assessed cases whose Outcome requires Review |
| Accuracy | the share of Outcomes decided without Review that match the label, with the counts |

Accuracy leaves out cases sent to Review, because a person decides those. Loosening a threshold trades Review rate against accuracy. For each group, a band table then splits the cases by tenths of each answer (`0.4–0.5` includes 0.4 but not 0.5; the last band includes 1.0). A Likelihood is split by its probability, each Likelihood of a Set by its own probability (`flags.hate`), and a Classification or Rating by its Confidence. Each band shows its cases, the labels they carry, and the Decision's accuracy and Review rate there. A band whose labels are mixed is where a threshold belongs, or where Review earns its cost:

```
ReviewedReturnDecision · jev-1.13.0 · en · questions 3f2a9c1b
| Question   | Band    | Cases | Expected             | Accuracy | Review rate |
| abusive    | 0.1–0.2 | 14    | approve 14           | 100.0%   | 0.0%        |
| abusive    | 0.4–0.5 | 6     | approve 2, reject 4  | —        | 100.0%      |
| abusive    | 0.9–1.0 | 9     | reject 9             | 100.0%   | 0.0%        |
```

## Events and logging

Every assessment fires an event:

- `RobertoGallea\Judgment\Events\AssessmentCompleted`, with `$judgment`, `$assessment` and `$record` (the `AssessmentRecord`, or null when persistence is off or under `Judge::fake()`)
- `RobertoGallea\Judgment\Events\AssessmentFailed`, with `$judgment` and `$exception`, in both failure modes

The Review lifecycle fires two more: `AssessmentAwaitingReview` and `AssessmentResolved` (see [Review and Resolution](#review-and-resolution)).

The package also writes log entries you can trace an assessment by:

| Message | Level | Context |
| --- | --- | --- |
| `Judgment assessed.` | info | `judgment`, `engine`, `model`, `request_id` |
| `Judgment unassessed.` | warning | `judgment`, `exception`, plus `engine`, `model`, `request_id` when the Engine responded |
| `Judgment assessed from cache.` | info | as `Judgment assessed.`, plus `cached_from`: the original record's id |
| `Judgment decided.` | info | `judgment`, `engine`, `model`, `request_id`, `decision`, `outcome` |

They go to the default log channel. Set `JUDGMENT_LOG_CHANNEL` to send them elsewhere, or `JUDGMENT_LOG=false` to turn them off. `$assessment->logContext()` returns the same context for your own log entries.

## Testing

The package ships two fakes. Both are strict: a test cannot pass by reading an answer nobody scripted.

### Unit-testing a Decision with `Assessment::fake()`

Script the answers of a Judgment's Questions, with no Engine and no database:

```php
use RobertoGallea\Judgment\Assessment;

$assessment = Assessment::fake(new RefundAbuse($refund))
    ->likelihood('abusive', .80)
    ->make();

expect($assessment->outcome())->toBe(RefundOutcome::Reject);
```

Every Question kind can be scripted:

```php
Assessment::fake(new SupportTicket($ticket))
    ->classification('department', Department::Billing, confidence: .9)   // winning label, beating the runner-up by .9
    ->classification('language', ['english' => .15, 'italian' => .85])   // or a probability per label (unlisted: 0)
    ->rating('severity', 2, confidence: .9)                               // most probable level, from 0
    ->rating('severity', [0, .5, .5, 0])                                  // or a probability per level
    ->make();

Assessment::fake(new PostModeration($post))
    ->likelihoodSet('harms', ['spam' => .90])                             // a Likelihood per label (unlisted: 0)
    ->make();

Assessment::fake($judgment)->answers(['abusive' => .80, 'severity' => 2]); // several at once
```

Each scripted answer is checked against the Judgment's declared Questions: an undeclared key, the wrong kind, an undeclared label, a level outside the scale, or a probability or Confidence outside 0 to 1 throws. A Decision that reads a Question the test did not script throws `UnscriptedQuestion`.

A fake Assessment runs each Decision twice and throws `ImpureDecision` if the two Outcomes differ, catching a Decision whose Outcome depends on the clock, the database or its own state rather than its Assessment.

### Feature-testing with `Judge::fake()`

Swap the Judge for a fake that answers each Judgment from a script:

```php
use RobertoGallea\Judgment\Facades\Judge;

Judge::fake([
    RefundAbuse::class => ['abusive' => .80],                                         // static answers
    SupportTicket::class => fn (SupportTicket $judgment) => [                         // a closure given the Judgment
        'department' => $judgment->ticket->subject === 'Invoice' ? 'billing' : 'other',
    ],
    PostModeration::class => Judge::sequence(['harms' => ['spam' => .9]], ['harms' => []]), // one script per assessment
]);
```

Answers are written as in `answers()` above. A closure can also return an `Assessment::fake($judgment)` builder, or throw an `EngineFailed` to test failure handling: the fake then fires `AssessmentFailed` and throws or returns `Unassessed` as `judgment.failure` says.

Assessing a Judgment with no script throws `UnscriptedJudgment`, and a sequence that runs out throws `ExhaustedSequence`. While the fake is active every Engine connection throws `RealEngineCallPrevented`, so no test reaches a real Engine. Assessments from the fake fire `AssessmentCompleted` and check every Decision for purity, like `Assessment::fake()`. They are not recorded, so a feature test needs no migration for them.

Assert what was assessed:

```php
Judge::assertAssessed(RefundAbuse::class);
Judge::assertAssessed(RefundAbuse::class, fn (RefundAbuse $judgment) => $judgment->refund->is($refund));
Judge::assertNotAssessed(ProductReview::class);
Judge::assertNothingAssessed();
```

Assert that a Decision applied to a faked Assessment sent it to Review. The callback receives the Judgment and the Outcome:

```php
Judge::assertAwaitingReview(RefundAbuse::class);
Judge::assertAwaitingReview(RefundAbuse::class, fn (RefundAbuse $judgment, RefundOutcome $outcome) => $outcome === RefundOutcome::Escalate);
```

A faked Assessment is not recorded, so it cannot be resolved. `AssessmentAwaitingReview` still fires, with a null `$record`. A Decision unit test on `Assessment::fake()` never enters Review.

The fake dispatches Judgments through the queue like the Judge. On the sync queue the job runs and the fake answers from its script. Under `Queue::fake()` nothing runs. Either way you can assert what was dispatched:

```php
Judge::assertDispatched(RefundAbuse::class);
Judge::assertDispatched(RefundAbuse::class, fn (RefundAbuse $judgment) => $judgment->refund->is($refund));
Judge::assertNotDispatched(ProductReview::class);
```

### Package development

```bash
composer test      # Pest
composer analyse   # PHPStan
composer lint      # Pint (composer format to fix)
```
