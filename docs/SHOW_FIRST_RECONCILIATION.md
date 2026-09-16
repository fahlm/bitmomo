# Show-First Reconciliation — PR #176 vs Whitelist V1 Remediation

PR #176 is a source of product decisions, not a merge input.

## Decision
Do **not** merge or cherry-pick #176 wholesale.

Its isolated CSS/enhancer architecture was explicitly designed as a reversible experiment and would create a second presentation system if carried into long-term production.

## BTC Intelligence — ALREADY SATISFIED / DO NOT PORT OVERRIDE

The current canonical BTC renderer on the remediation line already owns the approved product hierarchy:
- current BTC state;
- separate Market Pulse fast clock;
- Bias + Confidence;
- What Changed;
- Why It Matters;
- Watch Next;
- explicit delayed/fail-closed Major Brief boundary;
- current provenance;
- accountability below the current intelligence.

PR #180 now supplies the required compact visualization/accountability treatment.

Therefore do not port:
- `bitmomo-btc-intelligence-show-first.css`;
- the Show-First BTC asset loader added to `bitmomo-btc-intelligence.php`.

Any remaining BTC hierarchy defect must be fixed in the canonical renderer/CSS after actual staging review.

## Public Pro — PARTIALLY SATISFIED / CANONICAL FIX REQUIRED

Current canonical Pro already:
- uses real delayed/frozen proof;
- keeps current protected Pro data off the public path;
- explains Free vs Pro;
- preserves whitelist/checkout switching.

Remaining launch-quality gap:
1. real historical Decision View must appear before the five-item product/deliverables explanation;
2. eligible delayed proof should visualize Expected Range with BTC reference and settled +24h outcome;
3. eligible delayed proof should visualize real Bear / Base / Bull scenarios from the same frozen record;
4. invalidation remains explicit;
5. if eligible proof is unavailable, fail closed—never fabricate a demo.

Implementation rule: fold these behaviors into canonical `Bitmomo_Pro_Sales` renderer and canonical Pro sales stylesheet. Do not use the #176 output-rewriting enhancer as permanent architecture.

## Protected Pro dashboard — DEFER FROM WHITELIST REMEDIATION

The protected dashboard range visual from #176 is useful, but paid checkout is OFF and protected-member experience is not a blocker for the public Founding Whitelist release.

Carry this requirement into the paid-lifecycle release (#127 scope or successor):
- read current brief only after login + active entitlement;
- visualize Expected Range + BTC reference;
- Base/Bull/Bear as one scenario map;
- invalidation prominent;
- no older-brief fallback;
- no probability invention.

## Homepage Show-First files from #176 — DO NOT BLIND PORT

Do not port `home-show-first.css` or old homepage template deltas by ancestry.

Homepage/header/Research/article corrections are allowed only after current integrated remediation source (#179 + #180) is deployed to staging and inspected against the Product Acceptance Matrix.

## Accepted product rule

`STATE -> CHANGE -> MEANING -> WATCH -> CONTEXT -> PROOF -> METHODOLOGY`

This is a product hierarchy requirement, not a requirement to retain PR #176's implementation technique.