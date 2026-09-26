---
name: laravel-judgment-creating-judgments
description: Create a Laravel Judgment class from a natural-language description of what to assess. Use when the user wants to detect, classify, rate or flag something in unstructured content (text a person would otherwise have to read), or asks for a new Judgment, its Evidence or its Questions.
---

# Creating a Judgment

A Judgment declares the Evidence an Engine reads and the typed Questions it answers about one Subject. It never decides what happens: that is the Decision's job (the `laravel-judgment-creating-decisions` skill).

## 1. Test that it is a Judgment

A Judgment fits a criterion you can only **describe**, over evidence a person would otherwise have to **read**. Split the user's request into its criteria and sort each one:

| Criterion | Where it goes |
| --- | --- |
| "Is the explanation believable?", "Is this post spam?", "Which team does this ticket belong to?" | a Question |
| "More than three refunds this year", "ordered over 30 days ago", "amount above €500" | a fact the Decision reads from the Subject |
| "The email field is valid", "the user owns this order" | validation or authorization, before the Judgment runs |

When every criterion is a fact or a rule, stop and propose the plain code instead. A Judgment asked to count, compare dates or check permissions is slower, costs money, and answers with a probability where the app already has the truth.

## 2. Generate the class

Pick the Subject: the Eloquent model the Judgment is about. Ask when the request leaves it open. Name the Judgment after the concern, not the action (`RefundAbuse`, `TicketRouting`, `ListingQuality`), then run:

```bash
php artisan make:judgment RefundAbuse --subject=Refund
```

This writes `app/Judgments/RefundAbuse.php`, constructed with the Subject, with empty `evidence()`, `questions()` and `decision()`.

## 3. Declare the Evidence

Return, as an explicit array, only the fields a person would read to answer the Questions. Wrap every piece of end-user text in `Evidence::untrusted()`. Leave out the facts from step 1: the Decision reads them from the Subject. Read [references/evidence.md](references/evidence.md) before writing `evidence()`.

Step 3 is complete when every field a Question needs is present, and every value an end user wrote is untrusted.

## 4. Declare the Questions

Write one Question per describable criterion, keyed by a short snake_case name. Read [references/questions.md](references/questions.md) for choosing the kind and for the constructors.

Every Question asks about the **world**, never about what to do. When the user dictates an action-shaped question, rewrite it and tell them you did:

| The user says | Ask instead |
| --- | --- |
| "Should we refund this?" | `Likelihood::that('Is this refund request an attempt to abuse the refund policy?')` |
| "Should this post be removed?" | `Likelihood::each(Harm::class)`, one Likelihood per kind of harm |
| "Should this ticket be escalated?" | `Rating::of('How badly does the problem affect the customer?', levels: [...])` |

Step 4 is complete when every describable criterion from step 1 has exactly one Question and every fact has a named place on the Subject.

## 5. Point to the Decision

Leave `decision()` returning `null` until a Decision exists. When the user also wants what happens next (reject, escalate, route), continue with the `laravel-judgment-creating-decisions` skill, which sets `decision()` for you.

## 6. Write the test

Write a Pest test that pins the declared Question keys and Evidence fields, scripts every Question with `Assessment::fake()`, which throws on the wrong kind, and asserts that the end-user text is marked untrusted:

```php
use App\Judgments\RefundAbuse;
use App\Models\Refund;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\UntrustedText;

it('declares the refund abuse questions over untrusted explanations', function () {
    $judgment = new RefundAbuse(Refund::factory()->make());

    expect(array_keys($judgment->questions()))->toBe(['abusive', 'credibility'])
        ->and($judgment->evidence())->toHaveKeys(['order.item', 'order.amount_eur', 'request.explanation']);

    Assessment::fake($judgment)
        ->likelihood('abusive', .5)
        ->rating('credibility', 2)
        ->make();

    expect($judgment->evidence()['request']['explanation'])->toBeInstanceOf(UntrustedText::class);
});
```

Build the Subject with its factory when it has one, otherwise with `new Refund([...])`. The test never calls a real Engine. Step 6 is complete when it passes with `php artisan test` (or `vendor/bin/pest`).

## 7. Report

Tell the user:

- the files written, and every Question you rewrote from an action-shaped one;
- which criteria became Subject facts, so the Decision can read them;
- how to assess it: `(new RefundAbuse($refund))->assess()`, or `Judge::assess(new RefundAbuse($refund))`, returns an `Assessment`, or `Unassessed` when the Engine fails and `judgment.throw_on_failure` is off. An Engine round takes around 0.7 s, so where no person is waiting for the answer, `->dispatch()` it to the queue and act on the Outcome from a listener of `AssessmentCompleted`. Adding the `HasAssessments` trait to the Subject model gives it an `assessments()` relation. Failed attempts are recorded there too, without answers, so `latestAssessment()` may return one (`$record->isUnassessed()`); `assessments()->answered()` keeps only records with answers.
