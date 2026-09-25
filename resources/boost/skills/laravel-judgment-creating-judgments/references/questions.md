# Questions

All constructors live in `RobertoGallea\Judgment\Questions`. `questions()` returns `key => Question`, where each key is a string (or a backed-enum value) that the Decision later reads the answer by.

## Choosing the kind

| The criterion is… | Kind | The Decision reads |
| --- | --- | --- |
| whether one statement is true | **Likelihood** | a probability |
| which one of a closed set of options applies (exactly one) | **Classification** | the winning label and its Confidence |
| where it sits on an ordered scale | **Rating** | the expected level and its Confidence |
| which of several independent labels apply (any number, including none) | **Likelihood Set** | one probability per label |

- A yes/no criterion is a Likelihood, never a two-label Classification.
- Options that can hold together ("spam" and "hate" in one post) make a Likelihood Set. Options that exclude each other ("billing" or "technical") make a Classification.
- Degrees ("how severe", "how credible") make a Rating, so the Decision can compare against an expected level instead of juggling labels.

## Phrasing

- Each Question is asked **independently**: it cannot refer to another Question or its answer.
- Refer to Evidence by its dotted path when it helps ("How credible is the explanation in request.explanation?").
- Ask what a careful human reader could answer from the Evidence alone. A Question that needs data outside the Evidence gets a guess back.

## Likelihood

```php
Likelihood::that('Is this refund request an attempt to abuse the refund policy?')
    ->means(true: 'Likely a claim the customer is not entitled to', false: 'A good-faith claim')
```

`means()` is optional. Add it whenever "true" could be read two ways: it states what each answer stands for.

## Classification

2 to 255 distinct labels, required as the `labels:` argument. Pass a list, a `label => description` map, or a backed enum class:

```php
Classification::of('In which language is the ticket written?', labels: ['english', 'italian', 'other'])
Classification::of('Which team should handle this ticket?', labels: [
    'billing' => 'Invoices, charges and refunds',
    'technical' => 'Bugs, outages and how-to questions',
    'other' => 'Anything else',
])
Classification::of('Which team should handle this ticket?', labels: Department::class)
```

A backed enum's values are the labels. The package reads a `description(): string` method on the enum, if present, to describe each case. Prefer descriptions whenever a label name alone is ambiguous, and add an `other` label when the options might not cover every case.

## Rating

2 to 10 described levels, **lowest first**, required as the `levels:` argument. Levels are read from 0:

```php
Rating::of('How badly does the problem affect the customer?', levels: [
    'Cosmetic', 'Annoying', 'Degrading their work', 'Blocking their work',
])
```

Describe each level concretely enough that two readers would place the same case on the same level.

## Likelihood Set

One independent Likelihood per label. Each label supplies its complete question: there is no templating. Pass a `label => question` map or a backed enum with a `question(): string` method:

```php
use RobertoGallea\Judgment\Questions\Likelihood;

'harms' => Likelihood::each(Harm::class),
'topics' => Likelihood::each([
    'politics' => 'Is the post about politics?',
    'sport' => 'Is the post about sport?',
]),
```

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
```

Labels are sent as dotted keys (`harms.hate`), so a label cannot contain a dot.

## Changing Questions later

Every recorded Assessment stores a fingerprint of the Questions (key, kind, wording, labels, levels, meanings), and Calibration never mixes fingerprints. Rewording a Question or reordering levels therefore starts a fresh calibration history: tell the user when an edit you make to an existing Judgment does this.
