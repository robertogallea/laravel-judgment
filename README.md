# Laravel Judgment

Probabilistic assessments of unstructured evidence, with deterministic, application-owned decisions.

Judgment is for criteria that can only be described and hard rules can be implemented: "is this refund request an attempt to abuse the policy?", "which team should handle this ticket?". It sits after validation, authorization and business rules. An Engine answers typed Questions over the Evidence you declare; your own Decision class turns those answers into an Outcome. The Engine never sees your Outcomes or thresholds.

## Installation

```bash
composer require robertogallea/laravel-judgment
```

Point the package at an Engine, a class implementing `RobertoGallea\Judgment\Contracts\Engine`:

```dotenv
JUDGMENT_ENGINE="App\Judgment\MyEngine"
```

To publish the config file: `php artisan vendor:publish --tag=judgment-config`.

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

A failed Engine call is never turned into a default Outcome. By default `assess()` throws `RobertoGallea\Judgment\Exceptions\EngineFailed`, wrapping the exception the Engine threw. Errors such as a `TypeError` are bugs, not Engine failures, and pass through unwrapped. A response that leaves a Question unanswered, or answers one that was not asked, throws `MalformedEngineResponse`, which extends `EngineFailed`.

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

## Events and logging

Every assessment fires an event:

- `RobertoGallea\Judgment\Events\AssessmentCompleted`, with `$judgment` and `$assessment`
- `RobertoGallea\Judgment\Events\AssessmentFailed`, with `$judgment` and `$exception`, in both failure modes

The package also writes log entries you can trace an assessment by:

| Message | Level | Context |
| --- | --- | --- |
| `Judgment assessed.` | info | `judgment`, `engine`, `model`, `request_id` |
| `Judgment unassessed.` | warning | `judgment`, `exception`, plus `engine`, `model`, `request_id` when the Engine responded |
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

Each scripted answer is checked against the Judgment's declared Questions: an undeclared key, the wrong kind, an undeclared label or a level outside the scale throws. A Decision that reads a Question the test did not script throws `UnscriptedQuestion`.

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

Assessing a Judgment with no script throws `UnscriptedJudgment`, and a sequence that runs out throws `ExhaustedSequence`. While the fake is active the Engine binding throws `RealEngineCallPrevented`, so no test reaches a real Engine. Assessments from the fake fire `AssessmentCompleted` and check every Decision for purity, like `Assessment::fake()`.

Assert what was assessed:

```php
Judge::assertAssessed(RefundAbuse::class);
Judge::assertAssessed(RefundAbuse::class, fn (RefundAbuse $judgment) => $judgment->refund->is($refund));
Judge::assertNotAssessed(ProductReview::class);
Judge::assertNothingAssessed();
```

### Package development

```bash
composer test      # Pest
composer analyse   # PHPStan
composer lint      # Pint (composer format to fix)
```
