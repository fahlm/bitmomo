# P0.2A Session-Aware BTC Intelligence

## Runtime contract

Bitmomo generates BTC Intelligence every calendar day. Bitcoin's 24/7 market is authoritative for scheduling; the US cash-equity calendar is contextual metadata only.

- Canonical timezone: `America/New_York`
- `us_pre_open` anchor: 08:10 New York time
- `us_post_close` anchor: 20:10 New York time
- Scheduling: one-time WordPress events recalculated after each run, so UTC time follows US daylight-saving transitions
- US market context: `regular_session_day`, `weekend`, `holiday`, or `early_close`
- Closed-day presentation label: `US MARKETS CLOSED - BTC UPDATE`

The scheduler does not skip weekends, holidays, or early-close days.

## Canonical edition schema

Each valid record in the existing `bitmomo_ai_editions` history has:

```json
{
  "edition_id": "bitmomo-ai:20260910T081000-0400:us_pre_open:abc123def0",
  "schema_version": "2.0",
  "session_type": "us_pre_open",
  "session_label": "US PRE-OPEN",
  "session_anchor": "2026-09-10T08:10:00-04:00",
  "market_timezone": "America/New_York",
  "us_market_status": "regular_session_day",
  "generated_at": "2026-09-10T08:10:00-04:00",
  "latest_attempt_status": "success",
  "canonical_status": "valid",
  "valid_snapshot_lineage": {
    "source_timestamp": "2026-09-10T12:10:00+00:00",
    "source_provenance": "recorded_live",
    "comparison_edition_id": "bitmomo-ai:previous-post-close"
  },
  "market_state": null,
  "directional_bias": "bullish",
  "confidence": 70,
  "freshness": "fresh",
  "provenance": "recorded_live"
}
```

`market_state` remains null when the independent Regime classifier has not supplied a state. It is never inferred from directional bias. The public adapter continues to project the independently stored Regime state.

Records are append-only within the bounded canonical option. Legacy `morning` and `us_session` identities are read as `us_pre_open` and `us_post_close`; existing consumers that require legacy Regime edition values still receive them through the compatibility adapter.

## Pre-open Free payload

```json
{
  "session": {
    "type": "us_pre_open",
    "label": "US PRE-OPEN",
    "anchor": "2026-09-10T08:10:00-04:00",
    "market_timezone": "America/New_York",
    "us_market_status": "regular_session_day"
  },
  "session_intelligence": {
    "current_setup": {
      "market_state": "expansion",
      "directional_bias": "bullish",
      "direction_strength": "bullish",
      "confidence": 70,
      "strongest_drivers": ["Observed driver summary"],
      "structural_state": "hh_hl"
    },
    "what_changed": [],
    "comparison": {"status": "insufficient_history"},
    "known_events": [],
    "what_to_watch": ["directional_consistency", "structure_continuity"],
    "bond_context": null
  }
}
```

## Post-close Free payload

```json
{
  "session": {
    "type": "us_post_close",
    "label": "US POST-CLOSE",
    "anchor": "2026-09-10T20:10:00-04:00",
    "market_timezone": "America/New_York",
    "us_market_status": "regular_session_day"
  },
  "session_intelligence": {
    "what_happened": {
      "observed_window": "trailing_24h",
      "btc_change_pct": 1.25,
      "volatility_state": "normal",
      "ending_directional_bias": "bearish",
      "ending_structural_state": "lh_ll"
    },
    "what_changed": [
      {"field": "directional_bias", "from": "bullish", "to": "bearish"},
      {"field": "confidence", "from": 70, "to": 55, "delta": -15}
    ],
    "why_it_matters": ["directional_context_changed", "evidence_strength_changed"],
    "known_events": [],
    "bond_context": null
  }
}
```

The 24-hour observation window is named explicitly because P0.2A does not fabricate a precise cash-session price series or causal explanation.

## Comparison contract

```json
{
  "status": "compared",
  "edition_id": "bitmomo-ai:20260910T081000-0400:us_pre_open:abc123def0",
  "session_type": "us_pre_open",
  "generated_at": "2026-09-10T08:10:00-04:00"
}
```

Pre-open selects the latest valid preceding post-close record. Post-close selects the latest valid preceding pre-open record. Selection requires an opposite session identity, earlier timestamp, valid canonical status, accepted quality, recorded-live provenance, and age no greater than 30 hours. Missing history returns `insufficient_history`; future records and adjacent failed attempts are excluded.

## Free and Pro boundary

Free is intentionally useful enough to stand on its own. The split is by decision depth rather than by hiding half of every sentence.

The Free session brief may expose:

- current state, Bias and Confidence;
- up to two strongest public-safe drivers on the BTC Intelligence renderer;
- concise observed context;
- up to two material, human-readable changes on the renderer;
- up to two translated `why_it_matters` explanations;
- trusted event names when available;
- timestamp and freshness;
- exactly **one public watch context** on the renderer;
- the Pro CTA.

The canonical payload may continue carrying up to two deterministic `what_to_watch` candidates for compatibility and downstream use. The public renderer must publish at most one and only from the explicit Free allowlist:

- `directional_consistency`
- `structure_continuity`

That rendered item is observational context, not an actionable trigger. Unknown codes fail closed.

Pro retains the complete monitoring/watch set plus detailed axes and risk levels through the existing entitlement-gated projection. Pro is also the home for current scenarios, expected range/decision levels when methodology-qualified, invalidation, monitoring conditions, and entitlement-gated alerts when operationally enabled. P0.2A itself does not invent nullable scenario values and does not change pricing or entitlement.

Canonical product shorthand:

- **Free = Now + Change + Meaning + One Watch**
- **Pro = Full Watch + Scenarios + Levels + Invalidation + Monitoring**

`public watch context` and Pro `monitoring_conditions` are separate contracts. Free must never copy arbitrary current Pro monitoring text.

## Cadence language

Session Intelligence and Opportunity are different clocks:

- Session Intelligence publishes two canonical major briefs per day at the New York anchors above.
- Opportunity / Market Pulse consumes 5-minute candles but evaluates canonically every 15 minutes.

Public copy may say **“evaluasi 15 menit dari candle 5 menit”**. It must not state that the canonical Opportunity engine evaluates every five minutes.

## Data quality and extension points

P0.1 freshness remains unchanged: at most 6 hours is fresh, over 6 through 30 hours is delayed, and over 30 hours is unavailable. A blocked attempt updates only latest-attempt state and never replaces the latest valid edition or its timestamp.

`known_events` remains `[]` until a trusted event source is approved. `tradfi_context.observations` preserves each supplied observation timestamp and freshness state, including closed-market observations. Crypto source observations preserve their own timestamps independently.

The exact future Bond integration point is:

```json
{"bond_context": null}
```

No Bond endpoint, credential, HTTP request, or fallback value exists in P0.2A.
