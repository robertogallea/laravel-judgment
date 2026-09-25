# Confidence is defined by the package, and only where it means something

`confidence()` exists on Classification and Rating answers only, as our own documented measure — the margin between the top and runner-up probabilities — computed from the answer distribution so thresholds stay portable across Engines. Jev's own confidence (for Choice it is effectively the top probability; its formula is undocumented) is preserved in Provenance. Likelihood answers have no confidence: the probability itself is the measure, and |2p−1| merely re-expresses p while assuming 0.5 is the uncertain point.

The Jev reality-check prototype (20 labelled refund scenarios, EN/IT, 3 repeats) showed margin predicting correctness about as well as entropy, max-probability or Jev's own value for Rating (AUC ≈ 0.79–0.82) and Classification (AUC 1.0), but not for Likelihood (AUC 0.56): Likelihood errors were confident errors, and the uncertain zone sat at 0.1–0.5, not around 0.5.

No `reasoning()`/`explanation()` exists: Jev produces none, and letting another Engine fabricate one would be worse.
