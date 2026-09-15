# BTC Intelligence Show-First V1

This branch is based on whitelist release candidate commit `bdc42f2` and must not be merged into the in-flight #853 browser-acceptance candidate.

## Scope

Presentation hierarchy only. No intelligence scoring, adapter schema, freshness semantics, entitlement, whitelist lifecycle, payment behavior, or current protected Pro fields change in this PR.

## Product rule

**State -> Change -> Meaning -> Watch -> Proof.**

Show the intelligence before explaining the intelligence, but do not confuse "show" with dumping every metric. The default visitor path must communicate the current decision state in seconds and progressively disclose evidence/proof only when it adds comprehension.

## Default visitor hierarchy

1. Compact BTC Intelligence identity.
2. Current BTC state: price/freshness, Market Pulse, Bias, Confidence.
3. What Changed, Why It Matters, Watch Next.
4. Compact current context/evidence using the existing public-safe driver projection.
5. 30D state tape.
6. One delayed historical Pro Decision View as product proof.
7. Aggregate proof: overall + rolling 30 only.
8. Compact Pro bridge.
9. Methodology as progressive disclosure.

## Deliberately removed from the default journey

- Row-by-row Decision Ledger table. The ledger remains an immutable accountability source, but it is an audit instrument rather than a primary visitor artifact.
- Duplicate Clock & Freshness panel when freshness is already visible in the terminal header.
- Duplicate Major Brief metric when the session identity is already visible in the section header.
- Multiple historical Pro cards in one default view.
- Direction-by-direction aggregate scorecard tiles in the default flow.

## Public contract guardrails

- Real H1 remains in the document.
- Existing canonical renderer remains source owner; no shortcode-output rewriting.
- Market Pulse, Bias, Confidence, freshness, What Changed, Why It Matters, Watch Next and strongest drivers continue to use existing canonical output.
- Raw Opportunity percentile/range internals remain hidden.
- Current Market State classifier taxonomy/certainty remains outside the public headline surface.
- Delayed Pro archive remains historical/delayed only.
- No current protected Pro data is queried or exposed.
- Existing fail-closed behavior remains unchanged.
- Detailed audit/accountability data can remain available in storage or future secondary drill-down without competing with the primary visitor journey.

## Browser acceptance

- Mobile 390px: no page-level horizontal overflow or clipped text.
- Bias and Confidence remain legible side-by-side; decision questions stack cleanly.
- No nested audit table is required for the default visitor flow.
- Delayed Pro proof remains clearly labelled historical, not current guidance.
- Desktop visual hierarchy makes the current state dominant over explanation and methodology.
