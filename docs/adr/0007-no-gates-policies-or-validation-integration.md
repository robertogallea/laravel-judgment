# No integration with Gates, Policies or Validation

Judgment deliberately does not plug into Gates/Policies, validation rules, middleware or Blade directives. Authorization and validation must remain deterministic; a Policy or rule that consults an Engine converts a probability into a hard allow/deny or pass/fail without an application-owned Decision in between (contradicting ADR-0001). The only sanctioned overlap is an ordinary Policy governing who may record a Resolution on a stored Assessment. These are the first integrations people will ask for; this is why they are absent.
