# Reading and scripting an Assessment

Keys are the Question keys from the Judgment's `questions()`, as strings or backed-enum cases. Reading an undeclared key, or a Question as the wrong kind, throws an exception naming what is declared.

## Reading, per kind

| Kind | Read with | Compare |
| --- | --- | --- |
| Likelihood | `$assessment->likelihood('abusive')` | `->above(.65)` (at or over), `->below(.30)` (strictly under), `->between(.30, .65)` (from inclusive, to exclusive), `->probability()` |
| Classification | `$assessment->classification('department')` | `->is(Department::Billing)`, `->label()`, `->probabilityOf('billing')`, `->confidence()` |
| Rating | `$assessment->rating('credibility')` | `->above(2.0)` / `->below(2.0)` / `->between()` on the expected level, `->expected()`, `->level()`, `->confidence()` |
| Likelihood Set | `$assessment->likelihoodSet('harms')` | `->of(Harm::Spam)->above(.40)`, `->max()->above(.80)`, `->labelsAbove(.40)` (highest first) |

- **Read a Rating through its expected level.** `above()`, `below()` and `between()` compare against `expected()`, the probability-weighted mean level (from 0). `level()` is only the most probable level, so an answer split between two levels jumps from one to the other.
- **Confidence** is the top probability minus the runner-up's, for a Classification or a Rating. Use it for a Review arm when a split answer should reach a person: `$department->confidence() < /* calibrated */ => TicketOutcome::Triage`.
- **A Likelihood has no Confidence.** Its probability is the measure: uncertainty shows as a probability in the middle of the scale, which is what a Review band between two thresholds is for.

The shape of a finished Decision combining several answers with Subject facts, where each `/* calibrated */` stands for a number from the user and two Likelihood thresholds put a Review band between Reject and Approve:

```php
$abusive = $assessment->likelihood('abusive');
$doubtful = $assessment->rating('credibility')->below(/* calibrated */);
$frequent = $judgment->refund->customer->refundsThisYear() >= 3;

return match (true) {
    $abusive->above(/* calibrated */) => RefundOutcome::Reject,
    $abusive->above(/* calibrated */) => RefundOutcome::Escalate,
    $doubtful => RefundOutcome::Escalate,
    $frequent => RefundOutcome::Escalate,
    default => RefundOutcome::Approve,
};
```

## Scripting, per kind

`Assessment::fake($judgment)` scripts answers with no Engine and no database; `->make()` builds the Assessment. Each scripted answer is checked against the declared Questions, and reading an unscripted Question throws `UnscriptedQuestion`.

```php
Assessment::fake(new SupportTicket($ticket))
    ->likelihood('urgent', .80)
    ->classification('department', Department::Billing, confidence: .9)   // winning label, beating the runner-up by .9
    ->classification('language', ['english' => .15, 'italian' => .85])   // or a probability per label (unlisted: 0)
    ->rating('severity', 2, confidence: .9)                               // most probable level, from 0
    ->rating('severity', [0, .5, .5, 0])                                  // or a probability per level
    ->likelihoodSet('harms', ['spam' => .90])                             // a probability per label (unlisted: 0)
    ->make();
```

When the Decision reads a Rating's expected level, script it as a probability per level, so the test states the exact value compared: `[0, 0, .3, .7]` has an expected level of 2.7.

A feature test that goes through the application's own code uses `Judge::fake([RefundAbuse::class => ['abusive' => .80]])` instead, with the same answer shapes.
