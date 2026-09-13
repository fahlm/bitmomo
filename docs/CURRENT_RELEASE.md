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
- Active component patch under review: **#136 — whitelist launch runtime/readiness hardening**, based directly on #135. It is not a second release authority and must be absorbed into #135 before staging artifact freeze.
- Until the release is frozen for artifact generation, component/documentation commits may advance the candidate. Always record the exact current #135 head when producing artifact/staging evidence.

## Release topology

```text
main
  └─ release/whitelist-v1  ← PR #135, the only release authority
       └─ #136 component patch (temporary; absorb into #135)
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
- **#136 component patch:** email-only Whitelist V1 default, dormant WhatsApp fail-closed capability, legal/research/BTC freshness staging gates, safer whitelist write/mail readiness probe, product-copy truthfulness, newsletter fail-closed behavior, and expanded runtime/browser acceptance. It must be reviewed and absorbed into #135 before artifact generation.

Detailed KEEP / REJECT / ALREADY-PRESENT rationale for initial convergence lives in `docs/RELEASE_CONVERGENCE_WHITELIST_V1.md`. Whitelist staging runtime expectations live in `docs/WHITELIST_STAGING_RUNTIME_READINESS.md` once #136 is absorbed.

## Active release PR queue

Exactly **one** release authority is actionable:

- **#135 — `release: canonical whitelist v1 convergence` — DRAFT.**

One temporary component patch is allowed because it is based directly on the canonical release branch and is explicitly destined to collapse into #135:

- **#136 — `fix: harden whitelist launch runtime readiness` — DRAFT component patch; not an independent release line.**

Former release inputs #123, #126 and #129 are closed as **ABSORBED / SUPERSEDED**. Their branches, commits, discussions and historical evidence remain available for audit but are not independent release paths.

## Current release state

| Gate | State | Meaning |
|---|---|---|
| SOURCE | **CONVERGED + COMPONENT REVIEW IN PROGRESS** | #135 is canonical; #136 is the only temporary launch component patch and must be absorbed before freeze. |
| CI | **BLOCKED / NOT RUN** | GitHub-hosted Actions currently fail before runner assignment (`runner_id=0`, no steps). This is infrastructure evidence, not source PASS or source FAIL. |
| LOCAL/ALTERNATE DETERMINISTIC CHECKS | **REQUIRED BEFORE STAGING DEPLOY** | While Actions is blocked, staging may use a documented CI-blocked exception only after the exact absorbed #135 head passes the same deterministic source contracts outside GitHub-hosted Actions. |
| ARTIFACT | **NOT GENERATED FROM POST-#136 CANONICAL HEAD** | Older #123 artifact/checksum is historical evidence only. |
| STAGING | **NOT DEPLOYED FROM POST-#136 CANONICAL HEAD** | Canonical staging must receive only the new exact artifact. |
| RUNTIME | **NOT VERIFIED** | Source/tree/hash/cache/data parity awaits the new candidate. |
| BROWSER | **NOT VERIFIED** | Responsive, keyboard, zoom, console and Axe acceptance awaits the new staging candidate. |
| PRODUCT READY | **NOT VERIFIED** | Legal semantic sync, ≥2 qualified Research, BTC ≤30h, real whitelist persistence/dedupe/mail-generation and other whitelist-profile gates must pass on staging. |
| PRODUCTION AUTHORIZED | **NO** | CI-blocked staging exception never authorizes production. |
| PRODUCTION VERIFIED | **NO** | Production remains untouched. |

## Release-critical issues

- **#131** — canonical whitelist release coordination and acceptance state.
- **#125** — GitHub Actions capacity/infrastructure blocker.
- **#134** — repository-admin enforcement: protect `main` and enable automatic branch cleanup.
- **#127** — paid-checkout trust gate; it does **not** block whitelist-only launch while checkout remains disabled.

## CI-blocked staging exception

GitHub Actions unavailability must not create a circular dependency where staging cannot be used to validate runtime behavior. Therefore **staging-only** deployment is allowed while #125 remains active when all of the following are true:

1. #136 (or any approved launch component patch) has completed final diff-level review and is absorbed into #135;
2. the exact resulting #135 head is frozen;
3. all authoritative deterministic source checks are executed outside GitHub-hosted Actions on that exact head and PASS without weakening assertions;
4. the production artifact builder is run twice from that exact head and produces byte-identical artifacts;
5. source commit, source tree, runtime manifest/file count and artifact SHA-256 are recorded;
6. only that artifact is deployed to canonical staging;
7. production remains **NO-GO** until GitHub Actions returns and authoritative CI executes successfully on the exact accepted candidate (or a proven byte/source-identical candidate).

A staging exception is never recorded as `CI PASS`; CI remains `BLOCKED / NOT RUN`.

## Rules while #135 is Draft

- Do not deploy any artifact from #119/#121/#122/#123 or historical staging branches.
- Do not create a second release candidate.
- Do not mark #135 Ready merely to create zero-step Actions failures.
- Launch component work must be reconciled deliberately into #135, not become another release authority.
- Unrelated ordinary engineering still starts from current `main`.
- Production stays unchanged until the exact accepted artifact passes every staging gate, authoritative CI is executable and green, and explicit production authorization is granted.

## Next executable gate

1. finish #136 final diff review and restore any regression coverage lost during full-file refactors;
2. absorb/squash #136 into `release/whitelist-v1` and retire #136;
3. freeze the exact new #135 head;
4. execute authoritative deterministic source checks outside GitHub-hosted Actions while #125 remains active;
5. build the runtime artifact twice and require byte-identical output; record commit/tree/hash/file count;
6. deploy only that artifact to canonical staging;
7. verify staging filesystem/source/tree/hash and cache/CDN asset bytes;
8. run staging readiness for legal semantic sync, qualified Research count, BTC freshness, whitelist persistence/dedupe/mail-generation cleanup, checkout OFF and WhatsApp OFF;
9. run browser/accessibility/product acceptance;
10. hold production until GitHub-hosted authoritative CI is executable and green on the exact accepted candidate or a proven source-identical head;
11. request explicit production authorization, promote the exact accepted artifact, verify production, merge the verified release state back to `main`, and retire the release branch.

## Engineer start-here

Before touching launch/release surfaces:

1. read this file;
2. read #131;
3. inspect Draft PR #135 and any explicitly declared component patch such as #136;
4. pull current `main` for unrelated work;
5. do not infer authority from PR number, branch age, artifact age or the word “latest” in historical discussion.
