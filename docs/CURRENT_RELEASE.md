# Current Bitmomo Release Status

**Last updated:** 2026-09-14  
**Production authorization:** **HOLD / NO-GO**  
**Canonical coordination issue:** #131  
**Canonical release PR:** #135  
**Canonical release branch:** `release/whitelist-v1`

This file is the fast entry point for engineers. It records release topology and current acceptance state, not the broader product roadmap.

## Canonical source topology

- Default branch / ordinary engineering source of truth: `main`.
- `main` at the start of convergence: `eb58057012484db6fe2382582e392f2fa0300f28`.
- One and only active whitelist release line: `release/whitelist-v1` / PR #135.
- Initial semantic convergence commit: `cdc07a7a02ae35883b43b7987f143d1bc63513f2`.
- Canonical convergence record: `docs/RELEASE_CONVERGENCE_WHITELIST_V1.md`.
- Until the release is frozen for artifact generation, documentation-only commits may advance the PR head. Always use the exact current #135 head when recording CI/artifact/staging evidence.

## Release topology

```text
main
  └─ release/whitelist-v1  ← PR #135, the only release authority
       ↓
     SOURCE
       ↓
     ARTIFACT
       ↓
     STAGING
       ↓
     RUNTIME
       ↓
     BROWSER
       ↓
     PRODUCT READY
       ↓
     PRODUCTION AUTHORIZED
       ↓
     PRODUCTION VERIFIED
       ↓
     merge accepted state back to main
```

No engineer should create another whitelist integration/release branch unless #131 explicitly changes this topology.

## What was converged

PR #135 starts from current `main` and semantically reconciles the former release inputs:

- **#123:** institutional public/site convergence + BTC Market Context and integrity fixes — retained as product baseline.
- **historical staging integration `8bb9402e...`:** audited as evidence; required shared-chrome behavior is already present or superseded, so old ancestry was not merged wholesale.
- **#126:** exact three-file institutional Pro conversion delta — retained.
- **#129:** release provenance, staging parity, asset coherence, browser/readiness and CI-efficiency hardening — retained selectively.
- **#129 standalone Homepage Research workflow:** intentionally rejected as redundant because authoritative Full Release Safety already owns the same source contract.
- **current `main` governance:** PR template, contribution workflow, engineering operating model and release handoff contract — retained as canonical.

Detailed KEEP / REJECT / ALREADY-PRESENT rationale lives in `docs/RELEASE_CONVERGENCE_WHITELIST_V1.md`.

## Active release PR queue

Exactly **one** release PR is actionable:

- **#135 — `release: canonical whitelist v1 convergence` — DRAFT.**

Former release inputs #123, #126 and #129 are closed as **ABSORBED / SUPERSEDED**. Their branches, commits, discussions and historical evidence remain available for audit but are not independent release paths.

## Current release state

| Gate | State | Meaning |
|---|---|---|
| SOURCE | **CONVERGED / REVIEW REQUIRED** | One canonical source line exists; exact canonical head has not yet completed executable CI. |
| CI | **BLOCKED / NOT RUN on canonical head** | GitHub-hosted Actions capacity is unavailable; do not interpret zero-step runner failures as source failures or passes. |
| ARTIFACT | **NOT GENERATED** | No artifact from the canonical #135 head is accepted. |
| STAGING | **NOT DEPLOYED** | Canonical #135 artifact has not been deployed to staging. |
| RUNTIME | **NOT VERIFIED** | Source/tree/hash/cache/data parity not yet checked on the canonical candidate. |
| BROWSER | **NOT VERIFIED** | Responsive, keyboard, zoom, console and Axe acceptance not yet run on the canonical candidate. |
| PRODUCT READY | **NOT VERIFIED** | Whitelist/readiness profile has not yet passed on the canonical candidate. |
| PRODUCTION AUTHORIZED | **NO** | No production promotion is authorized. |
| PRODUCTION VERIFIED | **NO** | Production remains untouched by this convergence. |

## Release-critical issues

- **#131** — canonical whitelist release coordination and acceptance state.
- **#125** — GitHub Actions capacity/infrastructure blocker.
- **#134** — repository-admin enforcement: protect `main` and enable automatic branch cleanup.
- **#127** — paid-checkout trust gate; it does **not** block whitelist-only launch while checkout remains disabled.

## Rules while #135 is Draft

- Do not deploy any artifact from #119/#121/#122/#123 or historical staging branches.
- Do not create a second release candidate.
- Do not mark #135 Ready merely to trigger CI while the candidate is still changing.
- Product/component work that belongs in this launch must be reconciled deliberately into #135, not stacked into another release branch.
- Unrelated ordinary engineering still starts from current `main`.
- Production stays unchanged until the exact canonical artifact passes every staging gate and receives explicit production authorization.

## Next executable gate

When GitHub Actions capacity is operational:

1. freeze the exact #135 head;
2. mark the candidate Ready only when source is stable;
3. verify authoritative workflows actually receive a runner and execute;
4. require all applicable source/deterministic contracts to pass;
5. generate one deterministic artifact from that exact head and record commit/tree/hash;
6. deploy only that artifact to canonical staging;
7. verify filesystem/source/tree/hash, cache/CDN asset bytes and data/provider readiness;
8. run browser/accessibility/product-readiness acceptance;
9. request explicit production authorization;
10. promote and verify the exact accepted artifact;
11. merge the verified release state back to `main` and retire the release branch.

## Engineer start-here

Before touching launch/release surfaces:

1. read this file;
2. read #131;
3. inspect Draft PR #135;
4. pull current `main` for unrelated work;
5. do not infer authority from PR number, branch age, artifact age or the word “latest” in historical discussion.