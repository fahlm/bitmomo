# Bitmomo Intelligence Show-First V1

This branch is based on whitelist release candidate commit `bdc42f2` and must not be merged into the in-flight #853 browser-acceptance candidate.

## Scope

Presentation hierarchy only. No intelligence scoring, Free adapter schema, freshness semantics, entitlement, whitelist lifecycle, payment behavior, or production methodology changes in this PR.

The work covers three surfaces:

1. Public BTC Intelligence.
2. Public Bitmomo Pro sales page.
3. Protected Bitmomo Pro Decision View for entitled members.

## Product rule

**State -> Change -> Meaning -> Watch -> Proof.**

Show the intelligence before explaining the intelligence, but do not confuse "show" with dumping every metric. Engine complexity belongs behind a compressed interface. The default visitor/member path must communicate decision value in seconds and progressively disclose evidence/proof only when it adds comprehension.

## BTC Intelligence default hierarchy

1. Compact BTC Intelligence identity.
2. Current BTC state: price/freshness, Market Pulse, Bias, Confidence.
3. What Changed, Why It Matters, Watch Next.
4. Compact current context/evidence using the existing public-safe driver projection.
5. 30D state tape.
6. One delayed historical Pro Decision View as product proof.
7. Aggregate proof: overall + rolling 30 only.
8. Compact Pro bridge.
9. Methodology as progressive disclosure.

## Public Pro default hierarchy

1. Compact Pro / Founding identity and conversion action.
2. One real delayed/frozen historical Decision View.
3. Visual Expected Range rail with reference and settled +24h markers when available.
4. Real Bear / Base / Bull scenario lanes from that same frozen brief.
5. Compact Free -> Pro decision-depth boundary.
6. Compact accountability trust strip.
7. Founding economics / whitelist.
8. Buyer FAQ and trust links.

The old five-card feature catalogue is deliberately removed from the default visual journey. If the historical product proof can demonstrate a capability, do not explain the same capability again in a marketing card.

## Protected Pro Decision View hierarchy

1. Directional state and BTC reference price as the dominant read.
2. Confidence context as supporting evidence, not a competing headline.
3. Expected Range as a visual rail when a ready/fresh entitled brief contains a valid range.
4. Base / Bull / Bear as one scenario map. Base receives priority without implying probability.
5. Thesis Invalidation as an explicit risk boundary.
6. What Changed as supporting decision context.
7. Freshness/timestamp provenance.

The protected range visualizer is allowed to read the current Pro brief only after login + canonical Pro entitlement gates pass. Anonymous and inactive-member output must stay untouched. Readiness/freshness ownership remains in `Bitmomo_Pro_Briefs::get_current_brief_for_display()`.

## Deliberately removed from the default journey

- Row-by-row Decision Ledger table. The ledger remains an immutable accountability source, but it is an audit instrument rather than a primary visitor artifact.
- Duplicate Clock & Freshness panel when freshness is already visible in the terminal header.
- Duplicate Major Brief metric when the session identity is already visible in the section header.
- Multiple historical Pro cards in one default view.
- Direction-by-direction aggregate scorecard tiles in the default flow.
- Public Pro feature cards that repeat capabilities visible in the historical Decision View.
- Legacy navigation to hidden Decision Ledger anchors.

## Public contract guardrails

- Real H1 remains in the document.
- BTC Intelligence canonical renderer remains source owner; its Show-First layer is presentation-only and does not recalculate intelligence.
- Market Pulse, Bias, Confidence, freshness, What Changed, Why It Matters, Watch Next and strongest drivers continue to use existing canonical output.
- Raw Opportunity percentile/range internals remain hidden.
- Current Market State classifier taxonomy/certainty remains outside the public headline surface.
- Public Pro visualization uses only delayed/frozen accountability proof.
- No current protected Pro data is queried by the public sales path.
- Existing fail-closed behavior remains unchanged.
- Detailed audit/accountability data can remain available in storage or a future secondary drill-down without competing with the primary visitor journey.

## Protected contract guardrails

- Paid fields are still included server-side only after login + entitlement checks. No paid value is protected merely by CSS.
- The Show-First protected enhancer repeats login/entitlement gates before making an additional current-brief read.
- No fallback to an older Pro brief is introduced.
- No support/resistance value is relabeled as Expected Range.
- No scenario probability is invented.
- If the visual enhancer cannot produce a valid range, canonical text output remains visible.

## Browser acceptance

### BTC Intelligence
- Mobile 390px: no page-level horizontal overflow or clipped text.
- Bias and Confidence remain legible side-by-side; decision questions stack cleanly.
- No nested audit table is required for the default visitor flow.
- Delayed Pro proof remains clearly labelled historical, not current guidance.
- Desktop visual hierarchy makes the current state dominant over explanation and methodology.

### Public Pro
- Historical Decision View is visible before feature explanation / founding economics.
- Expected Range rail cannot clip reference/outcome labels at 390px.
- Scenario lanes remain readable without horizontal scrolling.
- Public proof is unmistakably delayed historical evidence.
- Whitelist conversion remains accessible and unchanged.

### Protected Pro
- Desktop uses available space without becoming a wall of cards.
- State, BTC reference, Expected Range, scenarios and invalidation are visually distinguishable at a glance.
- Mobile 390px collapses scenarios to one column and range-marker labels remain contained.
- Logged-out and inactive states remain unchanged and never receive paid visual data.

## Exit criterion for override layers

These isolated Show-First CSS/enhancer layers are deliberately reversible for browser testing. They are not intended to become a permanent second design system.

After staging browser acceptance proves the hierarchy, stable presentation decisions should be folded into canonical renderer/style ownership in a follow-up cleanup, and redundant override rules should be removed. Do not merge an ever-growing override stack into long-term production.