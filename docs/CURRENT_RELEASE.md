# Current Bitmomo Release Status

**Last updated:** 2026-09-15 WIB  
**Production authorization:** **HOLD / NO-GO**  
**Staging promotion authorization:** **PENDING FRESH RELEASE ARTIFACT / NO-GO**  
**Canonical coordination issue:** #131  
**Canonical release PR:** #135  
**Canonical release branch:** `release/whitelist-v1`

> **FINAL INTEGRITY CLOSURE IN PROGRESS — DO NOT FREEZE OR DEPLOY YET**
>
> The main Launch Integrity remediation is integrated, but the final root-cause re-audit found a small set of residual integrity and CI-governance gaps. Focused PR #169 is closing those gaps. Any previously implied/frozen candidate identity is revoked until #169 is reviewed, merged into the canonical release line, and a new exact release SHA is frozen.

This file is the fast entry point for engineers. It records the actual release topology and acceptance state; it is not a roadmap and it must not be treated as deployment authorization.

## Canonical source topology

- Default branch / ordinary engineering source of truth: `main`.
- One and only active Whitelist V1 release line: `release/whitelist-v1` / PR #135.
- Main reviewed runtime/product remediation basis: `becdee740b5979da4795d9b13ec870d2282a86c7` (squash merge of PR #161).
- Focused final integrity closure: PR #169, targeting `release/whitelist-v1`; it is **not** a second release authority.
- PR #135 remains the sole release authority and must remain Draft while #169 or any other candidate mutation is in progress.
- Runtime contract in source remains **118 managed files** unless Full Release proves otherwise.
- No immutable candidate SHA is currently authorized. A new candidate may be recorded only after #169 is accepted and the release branch stops moving.

The ledger intentionally does not try to store its own commit SHA. The immutable candidate identity belongs in the PR #135 freeze record and Full Release provenance, where recording it does not mutate the source being identified.

## Remediation status

The main whole-product Launch Integrity remediation is integrated into the canonical release line. Final closure is in progress for residual root-cause gaps.

Already integrated reconciliations include:

- exactly one compact global-footer newsletter retained as free audience retention, separate from Founding Whitelist commercial acquisition;
- newsletter backend identity is environment-owned and fails closed;
- dormant affiliate CTAs default off;
- About and Research are publication/evidence-first rather than media-style surfaces;
- Research Hub navigation is corpus-backed and no longer advertises empty Research Programs/Domains;
- public copy ownership is source-owned rather than post-render mutation;
- Free BTC Intelligence follows **Now + Change + Meaning + One Watch** with Decision Ledger accountability;
- Pro remains the deeper monitoring/scenario/invalidation layer;
- visible delayed/stale intelligence fails closed;
- checkout remains OFF;
- WhatsApp remains OFF.

PR #169 closes the remaining source/governance gaps identified by the final audit:

- delayed public intelligence must also fail closed at the **public adapter contract**, not only in visible renderers;
- CI Governance itself must skip Draft so Draft work consumes zero intentional hosted-runner validation;
- local preflight must own routine CI-governance/design feedback before GitHub Actions;
- UI Browser Safety must be manual-only during pre-production and run only against an explicitly selected exact staging/production target;
- institutional design consistency must be wired into authoritative scoped/full validation;
- Open Graph/X preview completeness must be verified as rendered runtime behavior rather than assumed from SEO configuration.

## CI architecture / budget discipline

GitHub-hosted CI is an authoritative confirmation layer, not the engineering feedback loop.

- `bash scripts/preflight-source.sh fast` is the normal iteration loop.
- `bash scripts/preflight-source.sh full` is required before a focused PR is marked Ready.
- Draft PRs must perform **zero intentional hosted-runner validation work**; scoped workflows remain present but their jobs are draft-guarded/skipped.
- Full Release is manual-only and requires an explicit exact `candidate_sha`.
- Full Release rejects a selected ref whose resolved SHA differs from `candidate_sha`.
- Full Release is restricted to `main` or `release/*` and self-audits CI governance before expensive work.
- Theme / Authority / Regime are path-scoped PR feedback, draft-safe, auto-canceling and hard-time-limited.
- CI Governance is path-scoped, draft-safe, fail-closed for workflow inventory, and locally preflighted before hosted confirmation.
- Production Synthetic Monitor is operational monitoring only, not PR validation, and runs once daily plus manual dispatch.
- UI Browser Safety is **manual-only during Whitelist V1 pre-production** and is reserved for exact deployed-candidate acceptance.
- Do not rerun failed workflows blindly. Classify the failure first; spend another run only after the cause changed or evidence is still required.

## Latest reviewed evidence

Previous main remediation head: PR #161 head `b53a86aee513c15563fec81cb72f7ac4d03159b2`.

- Theme Safety run `34878672062` — **PASS**
- Authority Surface Safety run `34878671941` — **PASS**

The release branch also had green Theme / Authority / Regime / CI Governance evidence after PR #161 integration. Those runs remain useful historical evidence, but they do **not** validate the new focused #169 diff and they do not authorize a release candidate.

PR #169 remains Draft during source review. Its hosted workflow jobs are expected to be skipped until the PR is deliberately marked Ready once.

## Artifact state

- Historical artifact `10350132727` remains **REVOKED / SUPERSEDED FOR PROMOTION**.
- No current artifact is authorized.
- No staging deployment is authorized from historical evidence.
- Do **not** run Full Release while PR #169 is open/unmerged.
- The next accepted artifact must be generated by Full Release from the new exact frozen candidate SHA recorded in PR #135 after final closure.
- Artifact provenance must match exact source commit + source tree and deterministic build A/B must agree.

## Current release gates

| Gate | State | Meaning |
|---|---|---|
| SOURCE | **FOCUSED CLOSURE IN PROGRESS** | PR #169 must close the residual source/governance gaps before a new freeze. |
| SCOPED CI | **PRIOR PASS / NEW CLOSURE PENDING** | Prior remediation was green; #169 still requires one deliberate Ready-time scoped confirmation. |
| RELEASE-WIDE CI | **BLOCKED UNTIL NEW FREEZE** | Do not run against a moving release branch. |
| ARTIFACT | **NONE AUTHORIZED** | Historical artifacts are revoked. |
| STAGING | **NO-GO** | Wait for the fresh exact-candidate artifact. |
| RUNTIME | **PENDING EXACT-CANDIDATE VALIDATION** | Full Release must validate all managed runtime suites and provenance after the new freeze. |
| BROWSER | **PENDING STAGING** | Manual browser acceptance runs only after exact artifact is on canonical staging. |
| PRODUCT READY | **NO** | Staging/runtime/browser/data/conversion/newsletter/SEO/social/analytics acceptance remains. |
| CHECKOUT | **OFF** | Must remain off. |
| WHATSAPP | **OFF** | Must remain off. |
| PRODUCTION AUTHORIZED | **NO** | No production promotion authorization exists. |
| PRODUCTION VERIFIED | **NO** | No production promotion has occurred. |

## Exact next executable sequence

1. Finish source review on PR #169 while it remains Draft; do not use GitHub-hosted CI as the iteration loop.
2. Run local `bash scripts/preflight-source.sh full` from a real checkout and attach the result to PR #169.
3. Freeze #169 head and mark #169 Ready **once** so only its scoped Authority / CI Governance confirmation runs.
4. If #169 scoped CI fails, convert it back to Draft before any mutation, fix the cause locally, then perform one new Ready-time confirmation after a new head is frozen.
5. When #169 is accepted, merge it into `release/whitelist-v1`. Keep PR #135 Draft during that mutation.
6. Record the new immutable `release/whitelist-v1` head SHA in PR #135 and mark #135 Ready **once** for cumulative release-candidate scoped checks.
7. If any #135 candidate check fails, immediately return #135 to Draft, revoke that SHA, fix through one focused PR, and freeze a new SHA. Do not rerun blindly.
8. When candidate checks are green, run Full Release **once** from `release/whitelist-v1` with `candidate_sha` equal to the recorded frozen SHA.
9. Accept only an artifact whose manifest proves that exact source commit/tree and deterministic artifact hash.
10. Take/verify the staging rollback point, then deploy only that exact artifact to canonical staging.
11. Verify runtime parity, asset coherence, BTC/data readiness, Research qualification, whitelist, newsletter lifecycle, real mail, checkout OFF and WhatsApp OFF.
12. Run manual browser QA at 360x800, 390x568, 390x844, 768x1024, 1024x900 and 1440x1000 plus 200% text zoom.
13. Require Axe serious/critical = 0, keyboard/focus/reduced-motion acceptance, no clipping, no first-party console errors, correct SEO/OG/X metadata, analytics receipt at the configured sink, and screenshot evidence.
14. Only after all staging gates pass may production authorization be considered.
15. After the exact production artifact is verified, converge the accepted source tree back to `main` and retire PR #135 / `release/whitelist-v1`.

## Known governance constraint

GitHub repository rulesets/branch protection are not available for this private repository under the current plan. Do not compensate by increasing CI volume. The release safety model therefore uses exact candidate SHA identity, draft-before-mutation discipline, candidate revocation on any change, deterministic artifact provenance, and explicit staging/production authorization as the compensating controls.

**PRODUCTION REMAINS NO-GO.**
