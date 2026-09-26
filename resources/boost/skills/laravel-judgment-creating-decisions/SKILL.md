---
name: laravel-judgment-creating-decisions
description: Create a Laravel Judgment Decision and its Outcome enum from a natural-language description of what should happen for each answer. Use when the user wants to act on a Judgment's Assessment (approve, reject, escalate, route, send to Review), asks for a new Decision or Outcome, or wants help setting a Decision's thresholds, drafting a calibration dataset or reading `judgment:eval` output.
---

# Creating a Decision

A Decision is a pure class that maps a Judgment's Assessment, plus facts from its Subject, to an Outcome: a backed enum the application acts on. The Engine never sees Outcomes or thresholds.

**The threshold rule.** Every number in a Decision comes from the user: one they state ("reject above 0.8"), or one they approve after reading `judgment:eval` output they ran. An Engine's probabilities differ per Question, so no default is sensible. Where the user gave no number, the arm stays an uncalibrated placeholder.

When the user wants help setting thresholds, drafting a dataset or reading `judgment:eval` output, follow [references/calibration.md](references/calibration.md) instead of the steps below.

## 1. Read the Judgment

Open the Judgment class (`app/Judgments`) and list its Question keys and kinds from `questions()`, and the Subject facts available through its properties. When no Judgment exists yet, create it first with the `laravel-judgment-creating-judgments` skill.

## 2. Create the Outcome enum

Reuse an existing Outcome enum when the user names one. Otherwise create `app/Enums/{Concern}Outcome.php`:

```php
namespace App\Enums;

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
```

An Outcome whose `requiresReview()` is true sends the recorded Assessment to a person. Ask which Outcomes require Review unless the user's words already say ("send borderline ones to a moderator"). When the user describes two automatic Outcomes with nothing between them, propose a Review Outcome for the uncertain band: identical requests can differ by 0.05 in probability, so a case near a single cut-off can flip between Approve and Reject.

## 3. Generate the class

```bash
php artisan make:decision RefundDecision --judgment=RefundAbuse --outcome=RefundOutcome
```

This writes `app/Decisions/RefundDecision.php`, whose `__invoke(Assessment $assessment, RefundAbuse $judgment): RefundOutcome` holds a placeholder arm and a `default` that throws `LogicException`.

## 4. Write the arms

Translate each rule the user described into one `match (true)` arm. Read [references/assessment-api.md](references/assessment-api.md) for reading each Question kind.

- Give every condition a named local (`$abusive`, `$doubtful`, `$frequent`) and write **one condition per arm**. Arm order is priority: the first arm that holds wins, so put the most severe Outcome first.
- Read Subject facts through `$judgment` (`$judgment->refund->customer->refundsThisYear()`), from data loaded with the Subject. The Decision stays pure: no queries, no clock, no state of its own. The fakes run each Decision twice and throw `ImpureDecision` when the Outcomes differ.
- A **stated threshold** goes in as a number with a trailing `// stated, not calibrated` comment.
- A **missing threshold** becomes a commented arm, and `default` keeps throwing while any placeholder remains, so an uncalibrated Decision never produces an Outcome:

```php
public function __invoke(Assessment $assessment, RefundAbuse $judgment): RefundOutcome
{
    $abusive = $assessment->likelihood('abusive');
    $frequent = $judgment->refund->customer->refundsThisYear() >= 3;

    return match (true) {
        $abusive->above(.80) => RefundOutcome::Reject,                  // stated, not calibrated
        // $abusive->above(/* calibrated */) => RefundOutcome::Escalate,
        $frequent => RefundOutcome::Escalate,
        default => throw new LogicException('RefundDecision is not calibrated yet: run php artisan judgment:eval.'),
    };
}
```

Once every arm has its threshold, replace the throwing `default` with the Outcome for everything left (usually the most lenient one, such as `RefundOutcome::Approve`).

Add a `version()` method returning `'1'`. Recorded Assessments store it, and Calibration keeps versions apart, so it is bumped each time a threshold changes:

```php
public function version(): string
{
    return '1';
}
```

Step 4 is complete when every rule the user described is exactly one arm, and every number in the file is one the user stated.

## 5. Wire the default Decision

Make the Judgment's `decision()` return the new class, so `$assessment->outcome()` applies it:

```php
public function decision(): ?string
{
    return RefundDecision::class;
}
```

## 6. Write the tests

Write one Pest test per arm with a stated threshold, scripting answers that reach that arm and no earlier one, named after the rule it covers. Once no placeholder remains, write one per arm, including the arms that read only a Subject fact: while a placeholder sits above such an arm, filling it in changes which cases reach it.

```php
use App\Enums\RefundOutcome;
use App\Judgments\RefundAbuse;
use App\Models\Refund;
use RobertoGallea\Judgment\Assessment;

it('rejects a refund request that is very likely abusive', function () {
    $assessment = Assessment::fake(new RefundAbuse(Refund::factory()->make()))
        ->likelihood('abusive', .85)
        ->make();

    expect($assessment->outcome())->toBe(RefundOutcome::Reject);
});
```

Script every Question the Decision reads, whichever arm the test targets: the named locals read them all before the `match`, and an unscripted one throws `UnscriptedQuestion`. Build the Subject with the facts the arms read. Skip the Decision tests while every arm is a placeholder: the Decision only throws. See [references/assessment-api.md](references/assessment-api.md) for scripting each kind.

Step 6 is complete when the tests pass with `php artisan test` (or `vendor/bin/pest`).

## 7. Report

Tell the user:

- the files written and which Outcomes require Review;
- each placeholder arm left, and each stated threshold that is not calibrated;
- how to act on it: `$assessment->outcome()` on the Assessment `assess()` returned; a dispatched Judgment is decided with its default Decision by the queue, and `$event->record?->decide(...)` in a queued listener of `AssessmentCompleted` applies another; each records the Outcome, starts Review when it requires one, and fires `AssessmentDecided`, where the application performs its Action;
- that thresholds are best set from labelled cases with `php artisan judgment:eval {Judgment}`, which you can help with by drafting a dataset from their examples and reading the report they paste back.
