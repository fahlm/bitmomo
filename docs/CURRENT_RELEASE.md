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
- #136 (`fix: harden whitelist launch runtime readiness`) was squash-absorbed as `aeaa1270cb20aa4bd7dcac88a4c665fac658e733`.
- Deterministic validation then exposed and repaired three release-line defects before artifact generation:
  - #137 restored the missing Pro email-service closing brace; merged as `2a6d88c6a9dc907bf1939de09532a4bfcbdad3aa` after targeted lint + full managed PHP lint 124/124 PASS on Hostinger PHP 8.1.34.
  - #138 corrected the Pro sales performance test to treat `preg_match() === 0` as the valid no-match result; merged as `1d20dcb9ce1468b8694887248e61f4a97eee89f7` after targeted 104/104 PASS.
  - #139 removed stale duplicated runtime-count ownership from `authority-surface-safety.yml`; merged as `1aa75f9d0ac490b855767923aa8c2b6546332b2d` after YAML + manifest semantic validation.
- There is no active component release branch in the release decision path. #135 is the sole release authority.

## Release topology

```text
main
  └─ release/whitelist-v1  ← PR #135, sole release authority
       ↓
     SOURCE (frozen candidate)
       ↓
     DETERMINISTIC CHECKS
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

## What the canonical line contains

PR #135 semantically reconciles the launch work into one source line:

- **#123:** institutional public/site convergence + BTC Market Context and integrity fixes;
- **historical staging integration `8bb9402e...`:** audited as evidence, not merged wholesale because required shared-chrome behavior is already present or superseded;
- **#126:** institutional Pro conversion surface;
- **#129:** release provenance, staging parity, asset coherence, browser/readiness and CI-efficiency hardening, excluding the redundant standalone Homepage Research workflow;
- **current `main` governance:** PR template, engineering operating model and release handoff contract;
- **#136:** email-only Whitelist V1 default, dormant WhatsApp fail-closed capability, canonical Privacy consent and channel-aware confirmation copy, BTC ≤30h launch freshness gate, semantic Privacy/Disclaimer readiness, ≥2 qualified Market Research requirement, real whitelist persistence/dedupe/mail-generation staging probe with transport short-circuit, newsletter fail-closed behavior, product-copy truthfulness, configured support identity, Research/About IA cleanup, expanded browser acceptance, and restored non-WhatsApp whitelist regression coverage;
- **#137/#138/#139:** validation-discovered syntax, test-harness, and CI-contract repairs described above.

The canonical runtime contract remains **117 managed files** unless intentionally changed by a later reviewed release decision: theme 46 / AI 24 / BTC Intelligence 8 / Pro 27 / regime 12. Tests, docs, and workflows are not runtime payload.

## Active release PR queue

Exactly **one** release PR is actionable:

- **#135 — `release: canonical whitelist v1 convergence` — DRAFT.**

#136, #137, #138 and #139 are absorbed/merged historical evidence and are no longer independently actionable. Former release inputs #123, #126 and #129 also remain historical evidence only.

## Current release state

| Gate | State | Meaning |
|---|---|---|
| SOURCE | **FROZEN FOR FULL DETERMINISTIC VALIDATION** | Remediations #137/#138/#139 are absorbed. The exact #135 head after this status commit is the only candidate eligible for the next full validation run. Any source change invalidates the freeze. |
| CI | **BLOCKED / NOT RUN** | Latest workflows on the Draft release candidate are guard-skipped and provide no executable evidence. Earlier #125 forced-run evidence showed `runner_id=0` / no steps. A skipped job is not PASS. |
| ALTERNATE DETERMINISTIC CHECKS | **RESTART REQUIRED ON FINAL FROZEN HEAD** | Earlier partial runs found and repaired the defects above. Full authoritative source validation must now restart from zero on the exact final head; partial results must not be promoted to final PASS. |
| ARTIFACT | **NOT GENERATED** | No artifact from the post-remediation final candidate is accepted. The old #123 artifact/checksum is historical baseline only. |
| STAGING | **NOT DEPLOYED** | Canonical staging has not received the post-remediation artifact. |
| RUNTIME | **NOT VERIFIED** | Commit/tree/hash/filesystem/cache/data parity awaits deployment. |
| BROWSER | **NOT VERIFIED** | Responsive, keyboard, zoom, console, Axe and failure-state acceptance awaits the new staging candidate. |
| PRODUCT READY | **NOT VERIFIED** | Privacy/Disclaimer semantic sync, ≥2 qualified Research, BTC ≤30h, whitelist persistence/dedupe/mail-generation, checkout OFF and WhatsApp OFF must pass on staging. |
| PRODUCTION AUTHORIZED | **NO** | The CI-blocked staging exception never authorizes production. |
| PRODUCTION VERIFIED | **NO** | Production remains untouched. |

## Release-critical issues

- **#131** — canonical whitelist release coordination and acceptance state.
- **#125** — GitHub Actions capacity/infrastructure blocker.
- **#134** — repository-admin enforcement: protect `main` and enable automatic merged-branch cleanup.
- **#127** — paid-checkout trust gate; does not block whitelist-only staging/launch while checkout remains disabled.

## CI-blocked staging exception

GitHub Actions unavailability must not create a circular dependency where staging cannot be used to validate runtime behavior. Therefore **staging-only** deployment is allowed while #125 remains active, but only when all of the following are true:

1. exact current #135 head is frozen;
2. authoritative deterministic source checks execute outside GitHub-hosted Actions on that exact head and PASS without weakening assertions;
3. PHP runtime lint/tests, all `test-*.php` suites (including email-only whitelist, dormant WhatsApp enabled-mode, and regression-preservation suite), first-party JS syntax/contracts, navigation/footer, terminal-grade/public-surface contracts and artifact contract all pass as applicable;
4. the production artifact builder is run twice from that exact head and produces byte-identical artifacts;
5. source commit, source tree, runtime manifest/file count and artifact SHA-256 are recorded;
6. only that exact artifact is deployed to canonical staging;
7. staging then passes filesystem/source/tree/hash parity, cache/CDN coherence, runtime/data readiness, browser/accessibility and whitelist product-readiness gates;
8. production remains **NO-GO** until GitHub-hosted authoritative CI returns and executes successfully on the exact accepted candidate or a proven source-identical candidate.

A staging exception is never recorded as `CI PASS`; CI remains `BLOCKED / NOT RUN`.

## Next executable gate

1. record the exact current #135 commit + tree after this status update;
2. execute the full authoritative deterministic source suites from zero outside GitHub-hosted Actions on that exact head;
3. if any suite fails, stop and fix source before artifact generation, then establish a new freeze;
4. if all pass, build the runtime artifact twice and require byte-identical output;
5. record commit/tree/runtime file count/artifact SHA-256 and create one staging handoff;
6. back up canonical staging, then deploy only that artifact;
7. run staging artifact parity + asset coherence + runtime/readiness + browser/accessibility acceptance;
8. keep production on HOLD until authoritative GitHub CI is executable/green and explicit production authorization is granted.

## Engineer start-here

Before touching launch/release surfaces:

1. read this file;
2. read #131;
3. inspect Draft PR #135;
4. verify the exact frozen commit/tree recorded in PR #135 and #131 before running tests;
5. do not deploy historical artifacts or infer authority from branch age/PR number;
6. do not create another release candidate while #135 is active unless a failing gate requires a focused remediation PR.
