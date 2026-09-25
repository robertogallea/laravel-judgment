# Helping calibrate a Decision

Calibration measures a Decision against labelled cases, so each threshold comes from evidence. `php artisan judgment:eval` asks the real Engine about every case, once per Engine connection compared, and each case is a paid Engine round. Every Decision compared is applied to the same answers, at no extra cost. **The user runs it.** Your part is to prepare the cases, hand over the command, read the report they paste back, and write the thresholds they approve.

## 1. Choose where the labels come from

- **Past Resolutions**: when the Judgment has run in production and reviewers resolved Assessments sent to Review, `judgment:eval RefundAbuse` needs no dataset. These labels cluster where the Decision was unsure, so propose a dataset as well to cover the clear cases.
- **A dataset**: examples the user gives or points to, each labelled with the Outcome a person says is right. Draft it with step 2.

## 2. Draft the dataset

Write `database/calibration/{judgment-in-kebab-case}.json` (for example `database/calibration/refund-abuse.json`), a JSON list of cases. Tell the user to commit it: the next recalibration reuses the same cases.

```json
[
    {"subject": {"item": "Headphones", "amount": 120, "explanation": "Arrived damaged."}, "expected": "approve"},
    {"subject": 42, "expected": "reject"}
]
```

- `subject` is either the model's attributes, which build an unsaved model, or the key of an existing record. Use keys whenever the Evidence or the Decision reads a relation (such as `customer`) or a loaded count: an unsaved model built from attributes has neither.
- `expected` is the value of a non-Review Outcome. A value outside the Decision's Outcome enum, or one whose `requiresReview()` is true, stops the run.
- Only a Judgment whose constructor takes an Eloquent model can use a dataset.

Before handing it over, warn the user when:

- a non-Review Outcome has **fewer than about 10 cases**: accuracy per band is then noise;
- the cases are all **clear-cut**: thresholds belong where cases are hard to call, so ask for borderline examples, the ones a reviewer would hesitate over.

The user decides whether to go ahead anyway.

## 3. Hand over the command

Give the command to run, with the number of Engine calls it makes (cases × Engines):

```bash
php artisan judgment:eval RefundAbuse --dataset=database/calibration/refund-abuse.json
```

- `--decision=RefundDecision --decision=StrictRefundDecision` compares candidate Decisions over the same answers. To compare candidate thresholds, draft each candidate as its own Decision class with the numbers the user proposes.
- `--engine=jev --engine=jev-next` compares Engine connections from `config/judgment.php`.
- Without `--decision`, the Judgment's default Decision applies. A bare name is looked up under `App\Judgments` or `App\Decisions`.

A Decision whose `default` still throws `LogicException` cannot be measured. Before handing the command over, ask the user for provisional numbers and a provisional `default` Outcome, and mark each such arm `// provisional, not calibrated`. They are replaced in step 5.

## 4. Read the report the user pastes back

The summary has one row per group (question-set fingerprint, model version, Decision and version, Evidence language), and groups are never mixed:

| Column | Meaning |
| --- | --- |
| Cases, Unassessed | the cases asked, and how many the Engine failed on |
| Review rate | the share of assessed cases whose Outcome requires Review |
| Accuracy | the share of Outcomes decided without Review that match the label |

Each band table then splits the cases by tenths of each answer: a Likelihood by its probability, each label of a Likelihood Set by its own probability, a Classification or Rating by its Confidence. `0.4–0.5` includes 0.4 but not 0.5.

```
RefundDecision · jev-1.13.0 · en · questions 3f2a9c1b
| Question | Band    | Cases | Expected            | Accuracy | Review rate |
| abusive  | 0.1–0.2 | 14    | approve 14          | 100.0%   | 0.0%        |
| abusive  | 0.4–0.5 | 6     | approve 2, reject 4 | —        | 100.0%      |
| abusive  | 0.9–1.0 | 9     | reject 9            | 100.0%   | 0.0%        |
```

- A band whose labels all agree can be decided automatically.
- A band with **mixed labels** is where a threshold belongs, or where a Review band earns its cost. Here, Reject from 0.9 and Review over 0.4–0.9 leave the mixed band to a person.
- Loosening a threshold trades Review rate against accuracy: state that trade in each proposal.

Propose thresholds with the bands that justify each one, and write only the numbers the user approves.

## 5. Write the approved thresholds

- Replace each placeholder or provisional number, and change its comment to `// calibrated: judgment:eval, {n} cases`.
- Bump the Decision's `version()`.
- Finish the `default` arm and the tests as steps 4 and 6 of [SKILL.md](../SKILL.md) describe, so each scripted answer still reaches its arm under the new numbers.
