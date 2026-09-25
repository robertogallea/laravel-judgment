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

## When the Engine fails

A failed Engine call is never turned into a default Outcome. By default `assess()` throws `RobertoGallea\Judgment\Exceptions\EngineFailed`, wrapping whatever the Engine threw. A response that leaves a Question unanswered, or answers one that was not asked, throws `MalformedEngineResponse`, which extends `EngineFailed`.

To handle failures as data instead, set the failure mode to `unassessed`:

```dotenv
JUDGMENT_FAILURE=unassessed
```

`assess()` then returns an `Unassessed` result in place of an Assessment. It carries the Judgment and the exception, and has no answers and no Outcome, so the application has to decide what an unassessed Judgment means:

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

They go to the default log channel. Set `JUDGMENT_LOG_CHANNEL` to send them elsewhere, or to `null` to silence them. `$assessment->logContext()` returns the same context for your own log entries.

## Testing

Bind a fake Engine in your tests to script the answers:

```php
app()->instance(Engine::class, new MyFakeEngine(['abusive' => .80]));
```

```bash
composer test      # Pest
composer analyse   # PHPStan
composer lint      # Pint (composer format to fix)
```
