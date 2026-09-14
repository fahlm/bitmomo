# Current Bitmomo Release Status

**Last updated:** 2026-09-14  
**Production authorization:** **HOLD / NO-GO**  
**Staging promotion authorization:** **AUDIT HOLD / NO-GO**  
**Canonical coordination issue:** #131  
**Canonical release PR:** #135  
**Canonical release branch:** `release/whitelist-v1`

> **AUDIT HOLD — DO NOT DEPLOY THE CURRENT ARTIFACT**
>
> A whole-product engineering audit found a release-contract contradiction after the latest source candidate was built: the documented public-surface and browser contracts require exactly one compact newsletter subscription surface in the global footer, while the latest footer implementation and source-level navigation/footer check removed and prohibited that surface. The current source/CI evidence remains useful, but the artifact is **not an accepted product candidate** and must not be promoted to staging or production until the audit is reconciled in one reviewed remediation pass.

This file is the fast entry point for engineers. It records the actual release topology and current acceptance state, not the broader product roadmap.

## Canonical source topology

- Default branch / ordinary engineering source of truth: `main`.
- One and only active whitelist release line: `release/whitelist-v1` / PR #135.
- Canonical release head at audit hold: `82430e288a85ce0a11be01e3fa0d3cabf2d6f100`.
- Canonical source tree: `2bd796be1b7fa7139b8e945944d15e5de69138c2`.
- PR #135 synthetic merge at audit hold: `eaf5bea77dccd9eeabc2960db08f3f4c7ea9151c`.
- Synthetic merge tree: `2bd796be1b7fa7139b8e945944d15e5de69138c2`.
- Current runtime contract in source: **118 managed files** — theme 46 / AI 24 / BTC Intelligence 8 / Pro 28 / regime 12.
- PR #135 remains the sole release authority. Do not create another release line for this audit.

## Latest source / CI evidence

The current audit-hold head passed all five release-authoritative workflows:

- Full Release Safety `34851208287` — PASS
- Authority Surface Safety `34851208223` — PASS
- Theme Safety Checks `34851208319` — PASS
- Regime Safety Checks `34851208291` — PASS
- Production Synthetic Monitor `34851208249` — PASS

Full Release also passed managed PHP lint, deterministic plugin suites, JS/Python/Bash syntax, M2 39/39, navigation/footer source contract, UI/WCAG source contract, terminal-grade public contract, institutional copy contract, homepage Research 8/8, CSS-debt baseline, deterministic build A/B and provenance verification.

**Important:** green CI proves that source satisfies the assertions currently encoded in CI. The audit found that at least one of those assertions is itself wrong: `check-navigation-footer.mjs` prohibits the newsletter surface even though `docs/PUBLIC_SURFACE_CONTRACT.md`, `docs/RELEASE_ACCEPTANCE_MATRIX.md`, `scripts/check-public-ui.mjs`, and the retained frontend architecture explicitly require one compact footer newsletter. Therefore green CI does not authorize promotion of this candidate.

## Current artifact evidence — VALID BINARY, REVOKED PRODUCT CANDIDATE

- Full Release run: `34851208287`
- Artifact ID: `10350132727`
- Artifact name: `bitmomo-runtime-eaf5bea77dccd9eeabc2960db08f3f4c7ea9151c`
- ZIP SHA-256: `8183db532b19ff74371bde8ee651a1786c2f8274cecbb1955e5a3cbfa7521456`
- Runtime TAR SHA-256: `e2beacbe76eb5f0a398fdebc6c9f907dddfe4514b382061c8f938ca07817ceff`
- Runtime files: 118

This artifact is reproducible and source-identical to the recorded candidate, but it is now **REVOKED FOR STAGING/PRODUCTION PROMOTION** because the product contract is under audit. Do not deploy it.

All earlier artifacts remain superseded as well.

## Audit-triggering contradiction

The intended retention architecture is:

- Founding Whitelist = commercial acquisition path for Bitmomo Pro;
- Newsletter = free audience retention/distribution utility;
- exactly one compact newsletter form in the global footer;
- no legacy newsletter popup/modal;
- newsletter presentation remains visually secondary to the commercial Pro/whitelist action;
- legacy `/subscribe`, `#subscribe`, and `#newsletter` destinations resolve to the footer subscribe surface, not to Founding Whitelist.

Current implementation incorrectly removed the footer newsletter and redirected legacy newsletter destinations to Founding Whitelist. This must be corrected together with any other P0 findings from the whole-product audit before a new artifact is built.

## Current release state

| Gate | State | Meaning |
|---|---|---|
| SOURCE | **AUDIT HOLD** | Current source is reproducible but not yet accepted as the final product contract. |
| CI | **PASS, CONTRACT REVIEW REQUIRED** | 5/5 authoritative workflows passed, but at least one source assertion is known to encode the wrong product requirement. |
| ARTIFACT | **REVOKED FOR PROMOTION** | Artifact `10350132727` is valid evidence but must not be deployed. |
| STAGING | **DO NOT DEPLOY CURRENT ARTIFACT** | Wait for consolidated audit remediation + fresh artifact. |
| RUNTIME | **AWAITING FINAL AUDIT CANDIDATE** | 118-file source contract is current but may change intentionally. |
| BROWSER | **AWAITING FINAL AUDIT CANDIDATE** | Full rendered acceptance must run only after final remediation deploy. |
| PRODUCT READY | **NO** | Whole-product audit and final staging acceptance remain open. |
| CHECKOUT | **OFF** | Must remain off. |
| WHATSAPP | **OFF** | Must remain off. |
| PRODUCTION AUTHORIZED | **NO** | Production remains untouched. |
| PRODUCTION VERIFIED | **NO** | No production promotion has occurred. |

## Engineering audit rule

Until the audit closes:

1. do not patch individual visual symptoms directly on staging;
2. do not add WordPress Custom CSS;
3. do not deploy artifact `10350132727`;
4. do not create another release branch;
5. collect contradictions and P0 defects across product value, IA, copy, visual system, retention, conversion, trust, responsive behavior, accessibility, performance, SEO/social preview, analytics, security/privacy, content/data state and release governance;
6. reconcile them in one reviewed remediation pass on the canonical release line;
7. rerun the entire authoritative suite from zero;
8. build one new deterministic artifact;
9. deploy only that exact artifact to canonical staging;
10. run full browser/product acceptance and produce a screenshot/evidence dossier;
11. production remains NO-GO until explicit owner authorization after staging acceptance.

## Next executable gate

Complete the **Bitmomo Launch Integrity Audit** and publish one prioritized remediation matrix. Source changes should begin only after P0 boundaries and intended product behavior are explicit enough to avoid another local fix that damages another part of the product system.
