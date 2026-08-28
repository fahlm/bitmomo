# Bitmomo Market Regime — Input Field Semantics

**Scope:** every field `Bitmomo_Regime_Input::validate()` accepts (`REQUIRED_FIELDS` and
`OPTIONAL_FIELDS`), documented precisely enough that Codex's runtime adapter never has to
guess a unit, a window, or a convention. This document adds no new fields and changes no
code — it is a semantics audit of the existing contract in
`includes/class-bitmomo-regime-input.php`, `includes/class-bitmomo-regime-config.php`, and
`includes/class-bitmomo-regime-classifier.php`.

Where Product A itself does not fix a convention (e.g. exact lookback windows, which
exchange/instrument, how a categorical judgment like `structure_state` is derived), that is
stated explicitly as "not specified by Product A" — Codex must pick one and keep it
**consistent across every evaluation**, because the classifier's thresholds were tuned
assuming whatever convention actually gets used stays the same evaluation to evaluation.
Changing the convention (e.g. switching from spot to perp price for `return_1d`, or
redefining the volatility lookback) without bumping `Bitmomo_Regime_Config::CLASSIFIER_VERSION`
would silently change what old and new history records mean.

---

## Required fields (`Bitmomo_Regime_Input::REQUIRED_FIELDS`)

### `return_1d`
- **Definition:** Percentage price return over the trailing 1-day window.
- **Unit:** Percent, expressed as a plain number (`-12.0` means -12%, not -0.12).
- **Time window:** 1 day. Exact boundary (rolling 24h vs. calendar-day close-to-close) is
  **not specified by Product A** — Codex must fix one and keep it stable.
- **Allowed range:** No hard bound is enforced by `validate()` (only "is numeric" is
  checked). Practically bounded below at -100 (can't lose more than the full position) but
  unbounded above.
- **Source expectation:** A BTC price series — Product A does not specify spot vs.
  perpetual-futures mark price; Codex must pick one and use it consistently, since
  `Bitmomo_Regime_Config::CAP_SHARP_DROP_1D = -8.0` and related thresholds were chosen
  against *some* price basis, not both.
- **Required / optional:** Required.
- **Derived / raw:** Raw calculation from price history (not itself a percentile or score).

### `return_7d`
Same definition/unit/source conventions as `return_1d`, 7-day window. Used by
`EXP_RETURN_7D_MIN` (>= 8.0), `CAP_SHARP_DROP_7D` (<= -15.0), `DIST_STALL_RETURN_7D_MAX`
(<= 3.0). Required. Raw.

### `return_30d`
Same conventions, 30-day window. Used only by `ACCUM_RANGE_RETURN_ABS` (`|return_30d| <=
8.0`). Required. Raw.

### `volatility_percentile`
- **Definition:** Where current realized volatility ranks against a trailing lookback
  distribution of realized volatility.
- **Unit:** Percentile, 0–100.
- **Time window:** The realized-volatility calculation window (e.g. 14D, 30D annualized
  stdev of returns) and the lookback distribution length are both **not specified by Product
  A**. Codex's methodology must stay fixed once chosen.
- **Allowed range:** Enforced 0–100 by `validate()` (`PERCENTILE_FIELDS`). Values outside
  0–100 fail validation outright.
- **Source expectation:** A volatility model over BTC price history — not defined by Product
  A.
- **Required / optional:** Required.
- **Derived / raw:** Derived (percentile rank of a computed volatility figure, not the raw
  volatility number itself).

### `volatility_change`
- **Definition:** The percentage-**point** delta in `volatility_percentile` versus its prior
  value (e.g. percentile moved from 60 to 85 → `volatility_change = 25`).
- **Unit:** Percentile points, signed. **Not** a percent-of-percent and **not** a percent
  change in raw volatility — it is a difference of two 0–100 percentile numbers.
- **Time window:** The comparison interval (day-over-day is the natural reading, matching
  the daily-evaluation cadence Product A's history/hysteresis layer assumes) is **not
  specified by Product A**.
- **Allowed range:** **Not enforced** — unlike `volatility_percentile` itself,
  `volatility_change` is not in `PERCENTILE_FIELDS`, so validation only checks it is
  numeric. A value like `250` will pass validation even though it is not a realistic
  percentile-point delta. Flagged as a genuine ambiguity in the security/defect audit
  (Task 11) — an adapter-side sanity check is Codex's responsibility, not something Product
  A itself will catch.
- **Source expectation:** Derived from two consecutive `volatility_percentile` readings.
- **Required / optional:** Required.
- **Derived / raw:** Derived.

### `volume_percentile`
Same shape as `volatility_percentile` but for traded volume: 0–100 percentile vs. a trailing
lookback, enforced range, lookback window not specified by Product A, source not specified
(spot volume, perp volume, or aggregated — Codex's choice, kept consistent). Required.
Derived.

### `momentum_score`
- **Definition:** A signed momentum indicator. The class docblock comment states a range of
  -100..100, but **`validate()` does not enforce this range** — `momentum_score` is not in
  `PERCENTILE_FIELDS` and has no other bound check, only a numeric check. This is a real gap
  between the documented intent and the enforced contract: a value like `-500` will pass
  validation and be compared directly against `EXP_MOMENTUM_STRONG_POS = 40.0` and
  `CAP_MOMENTUM_STRONG_NEG = -40.0` as if it were in range. Flagged here and in the Task 11
  audit; Codex's adapter should enforce -100..100 itself even though Product A won't.
- **Unit:** Unitless score, caller-derived — the class comment explicitly says
  "caller-derived", meaning Product A does not define the formula (not RSI, not MACD
  specifically — any consistent signed momentum construction is acceptable as long as its
  scale matches the -100..100 convention the thresholds assume).
- **Time window:** Whatever window the caller's momentum formula uses — not specified by
  Product A.
- **Allowed range:** -100..100 by documented intent, **unenforced** in code (see above).
- **Source expectation:** A momentum calculation over BTC price/volume — methodology is
  Codex's/bitmomo-ai's choice.
- **Required / optional:** Required.
- **Derived / raw:** Derived.

### `range_position_pct`
- **Definition:** Where current price sits within its recent trading range.
- **Unit:** Percentile, 0–100 (0 = at the recent low, 100 = at the recent high).
- **Time window:** The class comment gives "e.g. 30D range" as an illustrative example only
  — the exact window is **not fixed by Product A**.
- **Allowed range:** Enforced 0–100 (`PERCENTILE_FIELDS`).
- **Source expectation:** `(price - recent_low) / (recent_high - recent_low) * 100` over
  whatever window Codex fixes, or an equivalent construction.
- **Required / optional:** Required.
- **Derived / raw:** Derived.

### `structure_state`
- **Definition:** A categorical judgment of current price structure.
- **Unit:** String enum — one of `Bitmomo_Regime_Input::STRUCTURE_STATES`: `range`,
  `breakout_up`, `breakout_down`, `breakdown`, `unknown`.
- **Time window:** N/A (categorical, not a windowed numeric calculation) — though whatever
  produces this value necessarily looks at some window of price action; that methodology is
  not defined by Product A.
- **Allowed range:** Enforced — any value outside the 5-item enum fails validation. Empty
  string is treated as "missing required field" (fails validation), not defaulted to
  `unknown`.
- **Source expectation:** A structure/pattern classifier — not defined here. `unknown` is
  the honest "no structural read yet" value, not an error state; Codex should send `unknown`
  rather than guessing when its own structure detection has no confident read, since
  `unknown` correctly scores zero points in every regime rule that checks `structure_state`.
- **Required / optional:** Required.
- **Derived / raw:** Derived (a judgment, not a raw number).

### `directional_bias`
- **Definition:** Which direction the market is expected to move — a **separate axis from
  regime** (see the taxonomy class docblock: "Accumulation is not assumed bullish;
  distribution is not assumed bearish"). Never used as a scoring input anywhere in
  `Bitmomo_Regime_Classifier` — it is validated, normalized, and passed through to the
  output unchanged.
- **Unit:** String enum — one of `Bitmomo_Regime_Taxonomy::BIASES`: `bullish`, `neutral`,
  `bearish`.
- **Allowed range:** Enforced. Empty string fails validation as a missing required field
  (see `structure_state` above — the same rule applies). Note: `Bitmomo_Regime_Classifier::
  evaluate()` has its own internal fallback to `neutral` for an empty/unset
  `directional_bias` (line: `isset($input['directional_bias']) && '' !== ... ? ... :
  BIAS_NEUTRAL`), but that fallback is **unreachable through the documented contract** —
  `validate()` already rejects an empty `directional_bias` before `evaluate()` is ever
  called with the caller's normalized input. It only matters to a caller that skips
  `validate()` and calls `evaluate()` directly, which the class docblock explicitly says not
  to do.
- **Source expectation:** Bitmomo's existing directional-bias axis (bitmomo-ai / bitmomo-pro
  concept, per the taxonomy docblock) — Product A does not compute this itself, only
  transports it.
- **Required / optional:** Required.
- **Derived / raw:** Derived (a judgment from whatever upstream directional model Codex
  feeds in).

---

## Optional fields (`Bitmomo_Regime_Input::OPTIONAL_FIELDS`)

Every optional field is `null` when omitted — **never fabricated or defaulted to a non-null
value**. The classifier degrades gracefully (simply awards zero points for that rule) when
any optional field is `null`.

### `open_interest_change_pct`
- **Definition:** Percentage change in aggregate open interest (derivatives positioning).
- **Unit:** Percent, plain number (matches the `return_*` convention, not the fractional
  `funding_rate_pct` convention — see below).
- **Time window:** Not specified by Product A; `EXP_OI_CHANGE_MIN = 5.0` is compared with a
  strict `>` (not `>=`).
- **Allowed range:** Not enforced beyond "numeric".
- **Source expectation:** A derivatives/futures OI feed (e.g. exchange API aggregate OI).
  Optional because spot-only data sources have no OI figure at all.
- **Derived / raw:** Raw (or a simple % change of two raw OI readings).

### `funding_rate_pct`
- **Definition:** The perpetual-futures funding rate.
- **Unit — READ CAREFULLY:** Despite the `_pct` suffix, this is a **fractional** value, not
  an already-multiplied-by-100 percentage: the class comment gives `0.01 = 1% per funding
  period` as the example. All of `Bitmomo_Regime_Config`'s funding thresholds confirm this
  fractional convention: `ACCUM_FUNDING_NEUTRAL_HIGH = 0.02`, `DIST_EXTREME_FUNDING_POS =
  0.05`, `CAP_EXTREME_FUNDING_NEG = -0.03`. Sending `1.0` to mean "1%" (instead of `0.01`)
  would be silently treated as a 100% funding rate and would trip every "extreme" threshold
  in the classifier. This is the single most likely unit-convention mistake in this whole
  contract — Codex's adapter must divide by 100 before sending a raw exchange percentage.
- **Display caveat:** `Bitmomo_Regime_Classifier::fmt()` formats evidence strings by
  rounding the *raw fractional* value to 1 decimal place without multiplying by 100 — so a
  funding rate of `0.07` (7%) renders in evidence text as `"(0.1%)"`, and any value with
  `abs(value) < 0.05` renders as `"(0%)"` or `"(0.0%)"` regardless of its true magnitude.
  This is a real display defect (flagged in the Task 11 audit, not fixed there per "no
  aesthetic refactor" — it does not affect scoring, only the human-readable evidence text).
  Do not parse evidence strings for the true funding-rate magnitude; read the normalized
  input field itself.
- **Time window:** "per funding period" (typically 8h on most perpetual venues) — the exact
  period is not fixed by Product A; use whatever period the source venue actually funds on.
- **Allowed range:** Not enforced beyond "numeric".
- **Source expectation:** A perpetual-futures funding-rate feed.
- **Derived / raw:** Raw.

### `basis_pct`
- **Definition:** Futures basis (spread between futures and spot), annualized.
- **Unit:** Percent, annualized — not otherwise specified (assume the same plain-percent
  convention as `return_*`/`open_interest_change_pct`, not the fractional
  `funding_rate_pct` convention, since the comment says "% annualized" directly, but this
  is Product A's own ambiguity: **no scoring rule references `basis_pct` at all in V1**, so
  its exact unit convention has never been exercised end-to-end).
- **Reserved:** The class comment says explicitly: *"Reserved for a future rule; not yet
  scored in V1."* Sending this field today has **zero effect** on classification — it is
  accepted and normalized but never read by `Bitmomo_Regime_Classifier`. Codex may still
  populate it for forward-compatibility with a future classifier version, but should not
  expect it to change today's regime/confidence/evidence output.
- **Allowed range:** Not enforced beyond "numeric".
- **Derived / raw:** Raw (typically `(futures_price - spot_price) / spot_price`, annualized).

### `liquidation_pressure`
- **Definition:** A categorical bucketing of current liquidation activity/pressure.
- **Unit:** String enum — one of `Bitmomo_Regime_Input::LIQUIDATION_PRESSURES`: `none`,
  `elevated`, `extreme`.
- **Scoring weight:** The single largest optional-field swing in the capitulation score:
  `extreme` contributes +25, `elevated` contributes +12, `none`/`null` contribute 0 (see
  `Bitmomo_Regime_Config`/`Bitmomo_Regime_Classifier::score_capitulation()`).
- **Allowed range:** Enforced enum (invalid string values fail validation); `null` is valid
  (treated the same as absent).
- **Source expectation:** Not defined by Product A — the none/elevated/extreme cutoffs
  against raw liquidation volume/notional are entirely Codex's (or a future Watchtower
  liquidation-cascade detector's) judgment to define and keep consistent.
- **Derived / raw:** Derived (a judgment bucketing of raw liquidation data).

### `crowding_score`
- **Definition:** A positioning-crowding indicator (how one-sided current market
  positioning is).
- **Unit:** 0–100.
- **Allowed range:** Enforced 0–100 when present (checked in a separate `foreach ( array(
  'crowding_score', 'directional_confidence' ) as $field )` block in `validate()`, not via
  `PERCENTILE_FIELDS` — same effective enforcement, different code path).
- **Source expectation:** Not defined by Product A — typically a composite of OI, funding,
  and long/short ratio data; methodology is Codex's/bitmomo-ai's choice.
- **Derived / raw:** Derived.

### `directional_confidence`
- **Definition:** Confidence level for the passthrough `directional_bias` value.
- **Unit:** 0–100.
- **Allowed range:** Enforced 0–100 when present (same code path as `crowding_score`).
- **Scoring role:** Never used in regime scoring — passthrough only, alongside
  `directional_bias`.
- **Source expectation:** Whatever upstream directional model produces `directional_bias`.
- **Derived / raw:** Derived.

### `source_record_id`
- **Definition:** A traceability identifier for the upstream record this evaluation's input
  was built from (e.g. a bitmomo-ai record ID, a timestamped feed snapshot ID).
- **Unit:** Free-form string.
- **Scoring role:** Never used in scoring. Persisted for traceability once the record is
  stored (`Bitmomo_Regime_State_Store`, PR2 scope).
- **Allowed range:** Not validated beyond "non-empty string if present" — any string passes.
- **Derived / raw:** Neither — an identifier, not a market-data value.

### `as_of`
- **Definition:** A timestamp for when the input data was as-of.
- **Unit:** String — the fixtures in this PR use ISO-8601
  (`"2026-08-20T00:00:00Z"`), but **Product A does not parse or format-validate this
  field at all**: any non-empty string passes `validate()`, including a malformed or
  non-chronological one. Codex should use a consistent, sortable timestamp format (ISO-8601
  UTC recommended, matching the fixtures) since nothing downstream will catch a malformed
  one.
- **Scoring role:** Never used in scoring. Traceability only.
- **Derived / raw:** Neither — a timestamp, not a market-data value.

---

## Summary table

| Field | Required | Unit | Enforced range | Scoring role |
|---|---|---|---|---|
| `return_1d` | yes | % (plain) | none | capitulation |
| `return_7d` | yes | % (plain) | none | expansion, distribution, capitulation |
| `return_30d` | yes | % (plain) | none | accumulation |
| `volatility_percentile` | yes | 0–100 pctl | 0–100 | accumulation, capitulation |
| `volatility_change` | yes | pctl points | **none** (gap) | capitulation |
| `volume_percentile` | yes | 0–100 pctl | 0–100 | accumulation, expansion, distribution |
| `momentum_score` | yes | -100..100 (documented, **unenforced**) | none | expansion, distribution, capitulation |
| `range_position_pct` | yes | 0–100 pctl | 0–100 | accumulation, expansion, distribution |
| `structure_state` | yes | enum(5) | enforced | accumulation, expansion, capitulation |
| `directional_bias` | yes | enum(3) | enforced | passthrough only |
| `open_interest_change_pct` | no | % (plain) | none | expansion |
| `funding_rate_pct` | no | **fraction** (0.01=1%) | none | accumulation, distribution, capitulation |
| `basis_pct` | no | % annualized (unexercised) | none | **not scored in V1** |
| `liquidation_pressure` | no | enum(3) | enforced | capitulation |
| `crowding_score` | no | 0–100 | 0–100 | distribution |
| `directional_confidence` | no | 0–100 | 0–100 | passthrough only |
| `source_record_id` | no | string | none | traceability only |
| `as_of` | no | string (format unenforced) | none | traceability only |

**Two concrete ambiguities worth Codex's attention before building the adapter**, both
carried into the Task 11 audit:

1. `momentum_score` and `volatility_change` are documented/implied to have a bounded range
   but `Bitmomo_Regime_Input::validate()` does not enforce one — an out-of-spec value will
   silently pass through to scoring. The adapter contract test in this PR (Task 2) enforces
   the documented -100..100 bound for `momentum_score` at the adapter layer, ahead of what
   Product A itself checks.
2. `funding_rate_pct` uses a fractional convention (`0.01 = 1%`) that is easy to confuse
   with the plain-percent convention every other `*_pct`/`*pct` field in this contract uses.
