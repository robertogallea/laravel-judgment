![Laravel Judgment: probabilistic assessments, deterministic decisions](site/assets/banner.png)

# Laravel Judgment

Probabilistic assessments of unstructured evidence, with deterministic, application-owned decisions.

Some criteria can only be described, not coded: "is this refund request an attempt to abuse the policy?", "which team should handle this ticket?". An Engine answers typed Questions over the Evidence you declare; your own Decision class turns those answers into an Outcome. The Engine never sees your Outcomes or thresholds.

```php
final class RefundDecision implements Decision
{
    public function __invoke(Assessment $assessment, RefundAbuse $judgment): RefundOutcome
    {
        $abusive = $assessment->likelihood('abusive');                          // asked of the Engine
        $doubtful = $assessment->rating('credibility')->below(2.0);             // asked of the Engine
        $frequent = $judgment->refund->customer->refundsThisYear() >= 3;       // a fact you already know

        return match (true) {
            $abusive->above(.65) => RefundOutcome::Reject,
            $abusive->above(.30) => RefundOutcome::Escalate,
            $doubtful => RefundOutcome::Escalate,
            $frequent => RefundOutcome::Escalate,
            default => RefundOutcome::Approve,
        };
    }
}
```

## Features

- **[Typed Questions](https://robertogallea.github.io/laravel-judgment/#questions)**: Likelihood, Classification, Rating and Likelihood Set, with threshold helpers and a package-defined Confidence.
- **[Pure Decisions](https://robertogallea.github.io/laravel-judgment/#deciding)** you own, mapping answers and Subject facts to a backed-enum Outcome.
- **[Untrusted Evidence](https://robertogallea.github.io/laravel-judgment/#untrusted-evidence)**: end-user text is assessed as a claim, never followed as instructions.
- **[Engines](https://robertogallea.github.io/laravel-judgment/#engines)**: TypeSafe's hosted Jev, a self-hosted Laya server, or your own Engine, with pinned models.
- **[Failures as data](https://robertogallea.github.io/laravel-judgment/#when-the-engine-fails)**: an Engine failure is thrown or returned as `Unassessed`, never turned into a default Outcome.
- **[Audited Assessments](https://robertogallea.github.io/laravel-judgment/#persisted-assessments)**: Evidence, answers, Provenance and Outcome recorded, and replayable under another Decision.
- **[Review and Resolution](https://robertogallea.github.io/laravel-judgment/#review-and-resolution)**: uncertain Outcomes go to a person, whose Resolution is recorded next to the automatic one.
- **[Queued assessment](https://robertogallea.github.io/laravel-judgment/#queued-assessment)** and opt-in **[caching](https://robertogallea.github.io/laravel-judgment/#caching)**.
- **[Calibration](https://robertogallea.github.io/laravel-judgment/#calibration)** of thresholds against labelled cases with `judgment:eval`.
- **[Strict test fakes](https://robertogallea.github.io/laravel-judgment/#testing)**: `Assessment::fake()` and `Judge::fake()`, no Engine needed.
- **[Coding-agent skills](https://robertogallea.github.io/laravel-judgment/#coding-agents-laravel-boost)** for Laravel Boost, plus [events and logging](https://robertogallea.github.io/laravel-judgment/#events-and-logging) for every assessment.

## Use cases

- **Refund or claim abuse triage**: is this claim credible, is it an attempt to abuse the policy?
- **Support ticket routing**: which team should handle it, and how badly does it affect the customer?
- **Content screening**: which harms, if any, does a post contain?
- **Marketplace listing checks**: is the listed product counterfeit?

Which Questions each one asks, and what its Decision can do with them: [Use cases](https://robertogallea.github.io/laravel-judgment/#use-cases).

## When not to use it

> If you could write the rule, write the rule.

Validation, authorization and business rules run first. Judgment only sees what a person would still have to read to decide ([Philosophy](https://robertogallea.github.io/laravel-judgment/#philosophy)). Don't use it:

- **for anything a rule can express**: counting refunds is a query, not a probability;
- **to decide what to do**: Questions ask about the world, your Decision picks the Outcome;
- **when a wrong answer cannot be caught**: every answer is a probability, so uncertain cases need a Review Outcome;
- **when the answer must arrive in milliseconds**: an Engine round is a network call, so [queue it](https://robertogallea.github.io/laravel-judgment/#queued-assessment).

There is deliberately no Gate, Policy, validation rule, middleware or Blade integration: authorization and validation must stay deterministic ([ADR-0007](docs/adr/0007-no-gates-policies-or-validation-integration.md)).

## Installation

```bash
composer require robertogallea/laravel-judgment
php artisan vendor:publish --tag=judgment-migrations
php artisan migrate
```

Judgments are assessed on [Jev](https://docs.typesafe.ai) by default:

```dotenv
TYPESAFE_API_KEY=your-key
```

Publish the config with `php artisan vendor:publish --tag=judgment-config`. To run your own Engine instead, see [Laya](https://robertogallea.github.io/laravel-judgment/#laya).

## Quick start

Generate the classes:

```bash
php artisan make:judgment RefundAbuse --subject=Refund
php artisan make:decision RefundDecision --judgment=RefundAbuse --outcome=RefundOutcome
```

Declare the Judgment: its Subject, the Evidence to read, and the Questions to ask.

```php
final class RefundAbuse extends Judgment
{
    public function __construct(public readonly Refund $refund) {}

    public function evidence(): array
    {
        return [
            'order' => ['item' => $this->refund->item, 'amount_eur' => $this->refund->amount],
            'request' => ['explanation' => Evidence::untrusted($this->refund->explanation)],
        ];
    }

    public function questions(): array
    {
        return [
            'abusive' => Likelihood::that('Is this refund request an attempt to abuse the refund policy?'),
            'credibility' => Rating::of('How credible is the explanation in request.explanation?', levels: [
                'Not credible', 'Doubtful', 'Plausible', 'Fully credible',
            ]),
        ];
    }

    public function decision(): string
    {
        return RefundDecision::class;
    }
}
```

Declare the Outcomes, marking those a person must confirm:

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

Fill in the Decision (as shown at the top), then assess:

```php
$assessment = (new RefundAbuse($refund))->assess();   // or ->dispatch() to queue it

$outcome = $assessment->outcome();                    // RefundOutcome::Escalate, recorded for audit
```

## Designing Judgments

- **Write the rule if you can**: a Judgment only sees what validation, authorization and business rules let through ([Philosophy](https://robertogallea.github.io/laravel-judgment/#philosophy)).
- **Ask about the world, never about the action**: "is this claim credible?", not "should I approve this refund?" ([Declaring a Judgment](https://robertogallea.github.io/laravel-judgment/#declaring-a-judgment)).
- **Pick the Question by the shape of the answer**: a statement's probability → `Likelihood`, one of N → `Classification`, an ordered scale → `Rating`, several labels at once → `Likelihood::each()` ([Questions](https://robertogallea.github.io/laravel-judgment/#questions)).
- **Put in the Evidence only what a reader needs**: facts you already know, such as counts, go to the Decision through the Subject ([Declaring a Judgment](https://robertogallea.github.io/laravel-judgment/#declaring-a-judgment)).
- **Mark end-user text** with `Evidence::untrusted()`, so it is assessed as a claim and never followed as instructions ([Untrusted Evidence](https://robertogallea.github.io/laravel-judgment/#untrusted-evidence)).

## Designing Decisions

- **Combine signals**: mix answers with each other and with Subject facts, rather than banding a single Likelihood ([Deciding](https://robertogallea.github.io/laravel-judgment/#deciding)).
- **One condition per `match` arm**, each in a named local; the first arm that holds wins ([Writing Decisions](https://robertogallea.github.io/laravel-judgment/#writing-decisions)).
- **Read Ratings through `expected()`**, and send split answers to Review with `confidence()` ([Writing Decisions](https://robertogallea.github.io/laravel-judgment/#writing-decisions)).
- **Put a Review band around every threshold**: identical requests get slightly different answers, so a borderline case must never flip between Approve and Reject ([Thresholds are not exact](https://robertogallea.github.io/laravel-judgment/#thresholds-are-not-exact)).
- **Keep it pure**: no clock, no queries, no state; the fakes throw `ImpureDecision` otherwise ([Testing](https://robertogallea.github.io/laravel-judgment/#testing)).
- **Calibrate** every threshold with `php artisan judgment:eval`, and bump the Decision's `version()` when one changes ([Calibration](https://robertogallea.github.io/laravel-judgment/#calibration)).

## Documentation

The full reference covers every Question kind, Engine options, caching, persistence and replay, queues, Review, Calibration reports, events, logging and the test fakes: **[robertogallea.github.io/laravel-judgment](https://robertogallea.github.io/laravel-judgment/)**. Design decisions are recorded in [`docs/adr`](docs/adr).

## Package development

```bash
composer test      # Pest
composer analyse   # PHPStan
composer lint      # Pint (composer format to fix)
```
