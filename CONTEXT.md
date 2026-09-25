# Judgment

Judgment is a Laravel primitive for assessing uncertain, unstructured evidence against criteria that can only be described, not written as rules. It sits after validation, authorization and business rules, and never decides consequences itself.

## Language

### Asking

**Question**:
A single typed inquiry, asked independently of every other Question in the same Judgment, about uncertain facts concerning a subject, phrased about the world and never about what to do.
_Avoid_: Prompt, check, rule

**Likelihood**:
A Question answered with the probability that a statement is true.
_Avoid_: Boolean question, yes/no

**Classification**:
A Question answered by choosing among a closed set of labels, with a probability for each.
_Avoid_: Category question, enum question

**Rating**:
A Question answered with a position on an ordered scale of described levels.
_Avoid_: Score (as a domain term), grade

**Subject**:
The application entity a Judgment is constructed with and about which its Questions are asked.
_Avoid_: Target, model, resource

**Likelihood Set**:
A group of independent Likelihoods over a closed set of labels, each with its own complete question, used for multi-label questions.
_Avoid_: Multi-label question, flags, tags

**Evidence**:
The subject-specific material that Questions are asked over.
_Avoid_: State, context, input, payload

### Answering

**Untrusted text**:
Parts of the Evidence authored by the end user, marked so the Engine and reviewers treat them as claims rather than instructions.
_Avoid_: User input, raw text

**Assessment**:
The recorded answers to a set of Questions for one piece of Evidence; probabilistic and free of consequence.
_Avoid_: Verdict, JudgmentResult, result

**Confidence**:
How decisively a Classification or Rating answer favours its top option over the runner-up (top probability minus second), defined by this package independently of any Engine; a Likelihood has none, its probability being the measure.
_Avoid_: Certainty, engine confidence

**Provenance**:
The record of which Engine, model version and engine request produced an Assessment.
_Avoid_: Metadata, raw result

**Unassessed**:
The state of a Judgment whose Engine failed to produce an Assessment; never silently mapped to an Outcome.
_Avoid_: Default outcome, fallback

**Judgment**:
The definition that declares which Questions to ask, how Evidence is gathered, and which Decision applies — the counterpart of a Policy.
_Avoid_: Judge, evaluator, classifier

### Acting

**Decision**:
The deterministic, application-owned mapping from an Assessment to an Outcome.
_Avoid_: Policy, threshold config, verdict

**Outcome**:
A named member of a closed, enumerable, application-defined set produced by a Decision; the same set a reviewer chooses a Resolution from.
_Avoid_: Result, status, action

**Review**:
The human step an Assessment enters when its Decision yields an Outcome that requires a person to decide.
_Avoid_: Moderation, approval queue, escalation

**Resolution**:
The final Outcome recorded by a human reviewer, kept alongside the automatic Outcome it may overturn.
_Avoid_: Override, manual decision

**Calibration**:
Measuring a Judgment and its Decision against labelled cases (typically past Resolutions) to set and verify thresholds.
_Avoid_: Eval, benchmark, training

**Action**:
The side effect an application performs for an Outcome; outside the package's responsibility.
_Avoid_: Consequence, handler

**Engine**:
The execution backend that answers Questions over Evidence; Jev is the first.
_Avoid_: Provider, model, driver (as a domain term)
