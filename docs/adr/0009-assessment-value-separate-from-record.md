# Assessment is an immutable value, separate from its stored record

Decisions and their tests operate on an immutable Assessment value with no database behind it; an Eloquent record stores it together with the Outcome, Review state, Resolution and polymorphic Subject link. A single Eloquent model was simpler but would let Decisions read mutable lifecycle state and force Eloquent into pure Decision tests, undermining ADR-0004.
