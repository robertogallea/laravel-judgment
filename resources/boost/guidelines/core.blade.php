## Laravel Judgment

This app uses `robertogallea/laravel-judgment` to assess uncertain, unstructured evidence against criteria that can only be described, not written as rules ("is this refund request abusive?", "which team should handle this ticket?").

- A Judgment belongs after validation, authorization and business rules. When a rule, query or comparison can express the criterion, write that instead: a count, a date window or an amount limit is never a Judgment.
- The layers: a **Judgment** (`app/Judgments`) declares the Evidence and the Questions; an Engine answers them as an **Assessment** of probabilities; a pure **Decision** (`app/Decisions`) maps the Assessment to an **Outcome**, a backed enum implementing `RobertoGallea\Judgment\Contracts\Outcome`. Questions ask about the world, never about what to do; only the Decision chooses what happens.
- Never invent a threshold number in a Decision: each one comes from the user, stated or read from `judgment:eval` output they ran.
- To create a Judgment, use the `laravel-judgment-creating-judgments` skill. To create a Decision, its Outcome enum, or to help set its thresholds, use `laravel-judgment-creating-decisions`.
