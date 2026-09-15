# Whitelist V1 Release Convergence

**Status:** canonical semantic convergence record  
**Coordination issue:** #131  
**Canonical branch:** `release/whitelist-v1`  
**Initial convergence commit:** `cdc07a7a02ae35883b43b7987f143d1bc63513f2`  
**First parent / canonical source base:** `main` @ `eb58057012484db6fe2382582e392f2fa0300f28`

This document records how the previously diverged whitelist-launch sources were reconciled. It is intentionally semantic: historical branches remain evidence, but the release does not merge old ancestry merely to make graphs look connected.

## Decision matrix

| Source | Decision | Release treatment |
|---|---|---|
| current `main` | **KEEP / CANONICAL BASE** | First parent. Preserves current CI-cost guardrails, PR template, engineering operating model, release handoff contract and current-release governance. |
| PR #123 `725433ccc2510ace770b8871e84638844c16a60f` | **KEEP** | Product/site convergence baseline: institutional public surfaces, BTC Market Context and integrity fixes, current deterministic release contracts. |
| historical staging integration `8bb9402e9668d335e4ac505538fd05d11b2222c7` | **SEMANTICS VERIFIED / NO WHOLESALE MERGE** | Reviewed shared-chrome semantics were compared against #123. Required behavior is already present or superseded in #123; old ancestry is not reintroduced. |
| PR #126 `24e7df266693002b035003c6443d8939021b6e9f` | **KEEP** | Exact three-file institutional Pro conversion delta is overlaid onto the product baseline. |
| PR #129 `5acc962cc5b81ca87193bf617ed856ced27094f2` | **KEEP SELECTIVELY** | Immutable artifact/source provenance, staging parity, asset coherence, browser acceptance, readiness profiles and CI-cost hardening are retained. |
| #129 standalone Homepage Research workflow | **REJECT / REDUNDANT** | The same qualification contract is already owned by authoritative Full Release Safety. A second automatic workflow would spend another runner for duplicate evidence. |
| #132/#133 governance on `main` | **KEEP** | Repository front door, source-of-truth rules and current-release status remain canonical and must not be rolled back by older release branches. |

## Historical staging-chrome review

The staging line and PR #123 had diverged Git history, but endpoint review showed the apparent staging-only chrome work was not a reason to restore the old branch wholesale:

- `footer.php` is byte-identical between the two endpoints;
- #123 retains the canonical 1180px shell;
- header geometry remains 64px desktop / 60px mobile;
- mobile navigation remains viewport-bounded with `100dvh` and vertical scrolling;
- touch targets remain tokenized, with 44px mobile control geometry and 48px mobile navigation actions;
- Terms remains fail-closed and only renders when a published canonical page exists;
- social destinations remain fail-closed when unconfigured/invalid;
- #123's design-system layer is broader and newer than the historical staging version.

Therefore the staging line remains historical evidence, not a second release authority.

## Exact Pro delta retained from #126

- `website/wp-content/plugins/bitmomo-pro/assets/css/bitmomo-pro-sales.css`
- `website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php`
- `website/wp-content/plugins/bitmomo-pro/tests/test-bitmomo-pro-sales.php`

No checkout provider, entitlement, database migration or paid activation mechanism is added by this convergence.

## Release-governance capabilities retained from #129

The canonical line retains the useful #129 hardening, including:

- exact source commit/tree and artifact provenance;
- deterministic artifact verification;
- staging artifact/source/tree/hash parity checks;
- staging first-party asset/CDN byte coherence checks;
- browser acceptance with short-height mobile coverage and social fail-closed expectations;
- explicit `whitelist` versus `paid` staging-readiness profiles;
- reduced/controlled GitHub Actions consumption and draft-PR runner suppression;
- canonical release-governance documentation.

The separate Homepage Research workflow is intentionally not restored because the authoritative release suite already runs the same source contract.

## Release state at convergence

- **SOURCE:** CONVERGED, not yet CI-verified on the canonical head.
- **CI:** BLOCKED / NOT RUN on the canonical head while GitHub-hosted Actions capacity is unavailable.
- **ARTIFACT:** NOT GENERATED for the canonical head.
- **STAGING:** NOT DEPLOYED from the canonical head.
- **RUNTIME:** NOT VERIFIED.
- **BROWSER:** NOT VERIFIED.
- **PRODUCT READY:** NOT VERIFIED.
- **PRODUCTION AUTHORIZED:** NO.
- **PRODUCTION VERIFIED:** NO.

Production is intentionally untouched by this convergence.

## Next gate

When GitHub Actions capacity is operational:

1. freeze the exact canonical release head;
2. run the authoritative source/release checks and verify that runners actually execute;
3. generate and record one deterministic artifact from that exact head;
4. deploy only that artifact to canonical staging;
5. verify staging filesystem/source/tree/hash and first-party asset coherence;
6. run responsive, keyboard, zoom, accessibility, console, data-failure and product-readiness acceptance;
7. request explicit production authorization only after all staging gates pass;
8. promote the exact accepted artifact and verify production;
9. merge the accepted release state back to `main` and retire the temporary release branch.

No superseded artifact or historical branch may bypass this sequence.