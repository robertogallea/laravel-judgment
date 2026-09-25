# Evidence

`evidence()` returns a nested array of the material the Questions are asked over. Every field is named explicitly, so adding a column to the Subject never silently changes what is assessed.

```php
use RobertoGallea\Judgment\Evidence;

public function evidence(): array
{
    return [
        'order' => ['item' => $this->refund->item, 'amount_eur' => $this->refund->amount],
        'request' => ['explanation' => Evidence::untrusted($this->refund->explanation)],
    ];
}
```

## What goes in

- Fields a person would read to answer the Questions, grouped under keys that name what they are (`order`, `request`, `listing`). Questions can then refer to them by dotted path (`request.explanation`).
- Short context a reader needs to interpret the text: the item, the category, the amount.

## What stays out

- **Facts the app already knows**: counts, dates, flags, totals. The Decision reads them from the Subject (`$judgment->refund->customer->refundsThisYear()`), because a fact the app knows should never come back as a probability, and Engines tend to underweight counts.
- Identifiers, `toArray()` dumps, and personal data no Question needs. Every field is sent to the Engine and stored with each Assessment.

## Untrusted text

Wrap every value an end user wrote (a message, a review, a listing description, an explanation) in `Evidence::untrusted()`. The Engine then treats it as a claim to assess, never as instructions, which defends against prompt injection. The text stays at the path where it is declared.

Text the application itself wrote (a product name from your catalogue, a status label) stays plain.

## Language

When the Subject knows the language of its text, override `language()` with an ISO code. Evidence is not translated; the language is recorded with each Assessment, so Calibration can report per language:

```php
public function language(): ?string
{
    return $this->refund->locale;
}
```

## Queued assessment

A dispatched Judgment serialises the Eloquent models in its properties by reference and reloads them when the job runs, so `evidence()` reads the Subject as it is then. Load the relations and counts the Decision reads (such as a `withCount()` column) with the Subject before assessing or dispatching.
