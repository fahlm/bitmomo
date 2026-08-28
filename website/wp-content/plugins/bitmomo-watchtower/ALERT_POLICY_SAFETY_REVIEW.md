# Watchtower Alert Policy — Safety Review

**Scope:** the Integration Hardening Phase brief asked for a review of the 15-point confidence
threshold, 5% expected-range change threshold, 60-minute cooldown, and the invalidation/
data-quality cooldown bypass — with explicit instruction NOT to retune any of them "just
because they are arbitrary," and to document current behavior where it is ambiguous rather
than changing it. This is that review. The six scenarios it requested are implemented,
executable, and passing in `tests/test-bitmomo-watchtower-alert-safety.php` (20/20
assertions) — this document is the write-up of what they prove, especially the two places
current behavior is genuinely ambiguous.

## The three tunable numbers, as they exist today (unchanged)

| Constant | Value | Class | Role |
|---|---|---|---|
| `CONFIDENCE_CHANGE_THRESHOLD` | 15.0 points | `Bitmomo_Watchtower_Config` | A confidence move of ≥15 points, with regime/bias unchanged, classifies `CONFIDENCE_CHANGE` rather than `NO_CHANGE`/`MATERIAL_CONTEXT_UPDATE`. |
| `EXPECTED_RANGE_CHANGE_PCT` | 5.0 percent | `Bitmomo_Watchtower_Config` | A relative move of ≥5% in either bound of the expected range (checked independently per bound) counts toward `MATERIAL_CONTEXT_UPDATE`. |
| `ALERT_COOLDOWN_MINUTES` | 60 minutes | `Bitmomo_Watchtower_Config` | After an alert of a given class fires, another alert of the SAME class is suppressed until this many minutes pass — unless the class is cooldown-exempt. |

None of these were changed in this PR. This review's job was to test the *edges* of how they
interact, not to argue they should be different numbers.

## The six requested scenarios

All six are implemented as executable tests, run against the real
`Bitmomo_Watchtower_State_Transition_Engine` and `Bitmomo_Watchtower_Alert_Policy` classes
(plus a minimal in-memory stand-in for `Bitmomo_Watchtower_Alert_Outbox::get_last_for_class()`
that reproduces its real query shape exactly — see the test file's `Fake_Alert_Ledger` class
and its docblock). Four confirm the policy behaves as documented; two surface a genuine,
previously-undocumented ambiguity.

1. **Repeated same-class alert within cooldown** — confirmed as documented. A second
   `MATERIAL_CONTEXT_UPDATE` 20 minutes after the first (well inside the 60-minute cooldown)
   is suppressed, while still correctly reporting its `alert_class` for admin visibility.
2. **Stronger second event during cooldown** — **documented ambiguity, not fixed.** See
   "Ambiguity 1" below.
3. **Invalidation during cooldown** — confirmed as documented. A second invalidation just 5
   minutes after a prior `THESIS_INVALIDATED` alert still fires, because
   `ALERT_CLASS_THESIS_INVALIDATED` is in `COOLDOWN_EXEMPT_ALERT_CLASSES`.
4. **Data degradation during cooldown** — confirmed as documented. A second data-degraded
   evaluation just 3 minutes after a prior `DATA_QUALITY` alert still fires, for the same
   reason (`ALERT_CLASS_DATA_QUALITY` is also cooldown-exempt).
5. **Multiple domains creating the same transition** — **documented ambiguity, not fixed.**
   See "Ambiguity 2" below.
6. **`NO_CHANGE` never alerting** — confirmed as documented, in both the ordinary case (no
   prior alert history at all) and a defensive case (a `last_alert_at_for_class` value is
   supplied anyway) — `NO_CHANGE` has no entry in `TRANSITION_ALERT_CLASS_MAP` at all, so it
   is always `should_alert: false`, never a timing-dependent decision.

## Ambiguity 1: no severity-based cooldown bypass within an alert class

`Bitmomo_Watchtower_Alert_Policy::evaluate()` takes exactly three inputs: `$transition_type`,
`$last_alert_at_for_class`, and `$now`. **It never receives any measure of HOW BIG the
underlying change was** — not the confidence delta, not the expected-range percentage change,
not anything from `Bitmomo_Watchtower_State_Transition_Engine`'s `diff`. Two
`MATERIAL_CONTEXT_UPDATE` transitions map to the identical `alert_class`
(`MATERIAL_CHANGE`) and are subject to the identical cooldown check, regardless of whether
one was a borderline 5.1% range move and the other was an 80%+ range move.

The test suite proves this concretely: a weak `MATERIAL_CONTEXT_UPDATE` (+5.45% range change)
alerts, is recorded, and then a dramatically larger one (+81.8% range change) 15 minutes
later — still well inside the 60-minute cooldown — is suppressed exactly the same way a
marginal repeat would be.

This is not a bug — `MATERIAL_CHANGE` was never designed to be cooldown-exempt the way
`THESIS_INVALIDATED`/`DATA_QUALITY` are, and the brief explicitly said not to retune anything
"just because it looks arbitrary." But a founder or Codex reading only the alert stream could
reasonably assume "a much bigger version of the same kind of alert would surely re-notify me"
— it will not, within the 60-minute window, unless the change is also large enough to cross
into a *different* alert class (e.g. also moving `regime`/`directional_bias`, which reclassifies
as `THESIS_CHANGE` — a separate class with its own independent cooldown clock, confirmed by
the test suite's escalation-path assertions). **If magnitude-aware alert suppression is
wanted, it needs a new parameter on `Alert_Policy::evaluate()` (e.g. a severity/delta value)
and a decision about how much bigger "big enough to bypass" means — that is a policy design
question for the founder, not something this PR decides.**

## Ambiguity 2: cooldown is not domain-scoped

`Bitmomo_Watchtower_Alert_Policy::evaluate()` and the real
`Bitmomo_Watchtower_Alert_Outbox::get_last_for_class()` both key the cooldown lookup **only**
by `alert_class` — `get_last_for_class()`'s `meta_query` filters solely on
`_bitmomo_watchtower_alert_alert_class`, with no `domain` clause anywhere. `domain` is
tracked on each alert record (for analyst routing) but is never part of the cooldown
comparison.

Concretely: a `market`-domain event that triggers `MATERIAL_CONTEXT_UPDATE` and alerts at
10:00 will suppress a completely unrelated `derivatives`-domain event that also triggers
`MATERIAL_CONTEXT_UPDATE` at 10:10 — even though the two have nothing to do with each other
beyond sharing an alert class. The test suite proves this directly (Scenario 5): the
derivatives-domain change is correctly classified as its own `MATERIAL_CONTEXT_UPDATE`, but
the alert for it is suppressed purely because the market-domain alert of the same class fired
10 minutes earlier.

This means, in the current design, **one busy domain can silently suppress alerts from a
completely different domain** for up to 60 minutes, as long as both produce the same
transition type. Whether that is the intended behavior (one global "don't spam me" budget
per alert class, deliberately domain-agnostic) or a real gap (each domain should get its own
cooldown clock) is a product decision this PR does not make. **If per-domain cooldowns are
wanted, `Alert_Policy::evaluate()` would need a `$domain` parameter and
`Alert_Outbox::get_last_for_class()` would need a domain filter added to its query — both are
small, isolated changes if and when the founder decides this is the intended behavior, but
neither is made here per "do not retune."**

## What was and wasn't changed

**Nothing in `Bitmomo_Watchtower_Config`, `Bitmomo_Watchtower_Alert_Policy`, or
`Bitmomo_Watchtower_Alert_Outbox` was modified by this review.** This PR adds one new test
file (`tests/test-bitmomo-watchtower-alert-safety.php`, 20 assertions, all passing) and this
document. The two ambiguities above are reported for the founder's/Codex's awareness, per the
brief's explicit instruction — not treated as defects to silently patch.
