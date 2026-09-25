# One Judgment is one round of independent Questions

A Judgment maps to exactly one Engine call of mutually independent Questions and produces exactly one Assessment. Staged flows ("classify, then ask category-specific questions") are two Judgments joined by ordinary application code. This mirrors Jev (questions within a call never see each other's answers), keeps persistence, faking and Review one-to-one, and keeps branching between stages deterministic and visible. A pipeline primitive may be layered on later without breaking this.
