# Whitelist V1 Remediation Release

## Status

This branch is the sole pre-launch product-remediation line for Whitelist V1.

Baseline:
- technical baseline commit: `c33d128d5681909337ffc8b0811647012532fe9e`
- historical artifact: `10414093076`
- historical technical gates: PASS
- human visual/product acceptance: FAIL
- historical artifact production authorization: REVOKED / DO NOT DEPLOY

The baseline remains immutable evidence. This remediation branch exists because technical acceptance did not equal product acceptance.

## Objective

Produce the smallest coherent Whitelist V1 candidate that is technically safe **and** visually/product acceptable to a cold visitor before production.

This is not a general feature merge and not a new development trunk.

## In scope

1. Port Footer Institutional V2 from PR #179.
2. Port BTC Intelligence Visualization V2 from PR #180.
3. Selectively re-implement only still-valid Show-First decisions from preserved PR #176 where they improve visitor hierarchy without weakening current trust/data boundaries.
4. Fix homepage, header, Research, article-reading, Pro, and shared public-surface regressions only when supported by actual staging/product acceptance evidence.
5. Preserve the existing Whitelist V1 persistence, consent, dedupe, confirmation, checkout-OFF and WhatsApp-OFF semantics.
6. Preserve fail-closed BTC/public-data behavior and current engine/scoring unless a concrete correctness defect is proven.

## Explicitly out of scope

- Research Distribution / acquisition stack.
- Engineering System V2 in packaged runtime.
- 11-agent expansion / Watchtower expansion.
- payment/paid-entitlement launch.
- broad engine/scoring retuning.
- speculative redesigns not tied to observed product-acceptance defects.
- merging every historical PR.

## Product hierarchy requirement

Public BTC Intelligence should converge toward:

`STATE -> CHANGE -> MEANING -> WATCH -> CONTEXT -> PROOF -> METHODOLOGY`

A cold visitor should understand the current market reading and why it matters before being asked to inspect methodology or large accountability tables.

Unavailable/degraded intelligence must read as an intentional fail-closed quality state, not as a broken product.

## Release discipline

- Branch from exact rejected technical baseline `c33d128...`.
- Every remediation change must have one canonical owner and a bounded diff.
- Do not modify or overwrite historical artifact `10414093076`.
- No production deployment from this branch until a new immutable RC is cut.
- Build a new artifact only after source scope is frozen.
- Stage the exact artifact; never rebuild for staging or production.
- Production requires explicit human visual/product acceptance in addition to deterministic/browser/accessibility gates.

## Required acceptance

### Technical
- exact source/tree/artifact provenance;
- deterministic full validation PASS;
- whitelist regression suite PASS;
- BTC public-data/fail-closed contracts PASS;
- no protected Pro data leakage;
- checkout OFF and WhatsApp OFF;
- console errors = 0;
- Axe serious/critical = 0.

### Visual / product
Verify actual staging at 360 / 390 / 768 / 1024 / 1440 and 200% zoom.

Required outcomes:
- no clipping, overlap, hidden content, or page-level horizontal overflow;
- homepage has clear product-first hierarchy;
- header/navigation is precise and stable;
- footer reads as an institutional trust/navigation layer;
- BTC Intelligence shows intelligence before methodology;
- Market Context chart is understandable and responsive;
- accountability is compact but complete;
- unavailable/degraded BTC state is understandable and trustworthy;
- Pro shows real product proof before roadmap/features;
- Research navigation does not present generic legacy `Riset` as the institutional research destination;
- qualified article reading remains comfortable and free from legacy template pollution;
- whitelist journey remains functional and visually coherent.

### Final authority
A candidate is not production-ready until both are true:
1. technical release gates PASS;
2. explicit human product/visual acceptance PASS.

Technical PASS alone is insufficient.