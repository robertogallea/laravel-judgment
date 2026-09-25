# Engine-agnostic contract, Jev as first driver

Application code depends on Judgment/Question/Assessment, not on Jev. Engines sit behind a contract expressed in our own concepts; Jev is the first implementation alongside a fake. The contract's litmus test: a trained classifier served over HTTP could implement it. We rejected a Jev-only design (lock-in leaks through config and results; Jev is early-access with a moving model and young SDKs) and a thin Jev extension (already served by ~7 community SDKs).
