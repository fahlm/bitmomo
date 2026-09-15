# Current Bitmomo Release Status

**Last updated:** 2026-09-15 WIB  
**Production authorization:** **HOLD / NO-GO**  
**Staging promotion authorization:** **PENDING FULL RELEASE ARTIFACT / NO-GO**
**Canonical coordination issue:** #131  
**Canonical release PR:** #135  
**Canonical release branch:** `release/whitelist-v1`

> **SOURCE CONVERGING AFTER FULL RELEASE FINDING — DO NOT DEPLOY**
>
> PR #168, #169, and #170 have been reconciled into the canonical release line with semantic conflict review. Full Release run `34931625847` for candidate `1c410ba9555af561eff54b5b62352a48920998a8` passed exact identity, CI governance, and managed PHP lint, then failed in the deterministic BTC Intelligence surface-context suite. Follow-up run `34931913433` for candidate `80dd5ca9a5e6afb6bdff5e85b844299e227e8252` proved that suite fixed, then found the companion BTC Intelligence page contract still expected the old fail-closed copy. Follow-up run `34932103930` for candidate `300ba044ca42db3d2106b984adfa8b3af7eaa852` proved those BTC content contracts fixed, then found the layout contract still expected literal rail offsets instead of the canonical header tokens. Follow-up run `34932342048` for candidate `f1cfb859983e3c9100d3aeac50e79fbd420ba186` proved the layout contract fixed, then found BTC Intelligence cache version still needed a deterministic bump. Follow-up run `34932575375` for candidate `66fb31a753ead09aabbd92633c8367147bbeec7d` proved the version bump fixed, then found session-brief runtime needed case-robust Opportunity normalization and explicit delayed-current copy. Follow-up run `34932852380` for candidate `4d8c8620ccc2b17da28e3b0441c6974e753a7616` proved BTC session-brief contracts fixed, then found dormant checkout rendering still used the wrong enabled-checkout CTA label. All six candidates are revoked for promotion. The next SHA after this checkout-boundary fix and ledger update must be frozen as the new exact candidate.

This file is the fast entry point for engineers. It records the actual release topology and acceptance state; it is not a roadmap and it must not be treated as deployment authorization.

## Canonical source topology

- Default branch / ordinary engineering source of truth: `main`.
- One and only active Whitelist V1 release line: `release/whitelist-v1` / PR #135.
- Main reviewed runtime/product remediation basis: `becdee740b5979da4795d9b13ec870d2282a86c7` (squash merge of PR #161).
- Focused BTC Intelligence hardening: PR #168 reconciled into the release line.
- Focused final integrity closure: PR #169 reconciled into the release line; it is **not** a second release authority.
- Singular public freshness provenance: PR #170 merged into the release line before this convergence.
- PR #135 remains the sole release authority and must remain Draft during exact-candidate Full Release validation.
- Runtime contract in source remains **118 managed files** unless Full Release proves otherwise.
- The immutable candidate SHA is the exact `release/whitelist-v1` head after this ledger update is committed and pushed. Do not change source after that point unless the candidate is explicitly revoked.

The ledger intentionally does not try to store its own commit SHA. The immutable candidate identity belongs in the PR #135 freeze record and Full Release provenance, where recording it does not mutate the source being identified.

## Remediation status

The main whole-product Launch Integrity remediation and final BTC Intelligence closure are integrated into the canonical release line.

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

Final closure integrated from PR #168/#169/#170:

- Market Pulse and Opportunity are read from the canonical session snapshot, with point-in-time lineage retained internally and sanitized fail-closed.
- Delayed public intelligence fails closed at the public adapter contract, not only in visible renderers.
- The public BTC surface keeps a singular canonical freshness timestamp and does not expose independent Opportunity observation timestamps.
- BTC Intelligence visitor rendering keeps Market Pulse activity in visitor language and makes missing directional snapshots explicitly fail closed.
- CI Governance skips Draft so Draft work consumes zero intentional hosted-runner validation.
- Local preflight owns routine CI-governance/design feedback before GitHub Actions.
- UI Browser Safety is manual-only during pre-production and runs only against an explicitly selected exact staging/production target.
- Institutional design consistency is wired into authoritative scoped/full validation.
- Open Graph/X preview completeness is verified as rendered runtime behavior rather than assumed from SEO configuration.

## CI architecture / budget discipline

GitHub-hosted CI is an authoritative confirmation layer, not the engineering feedback loop.

- `bash scripts/preflight-source.sh fast` is the normal iteration loop.
- `bash scripts/preflight-source.sh full` is required before a focused PR is marked Ready.
- Draft PRs must perform **zero intentional hosted-runner validation work**; scoped workflows remain present but their jobs are draft-guarded/skipped.
- A focused PR is marked Ready once after local `full`, so only the path-scoped authoritative jobs relevant to that diff run.
- The long-lived canonical release PR stays Draft during candidate validation; do not pay for cumulative Theme/Authority/Regime checks immediately before a Full Release that already supersets them.
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

The release branch also had green Theme / Authority / Regime / CI Governance evidence after PR #161 integration. Those runs remain useful historical evidence, but they do **not** authorize the new converged candidate.

Local source checks available in the release-integrator environment passed for design consistency, CI governance, M2 launch-surface contracts, UI architecture, institutional copy, home research boundary, theme source, authority source, and social-preview syntax. PHP is not available in that environment, so deterministic PHP suites must be proven by Full Release.

Full Release run `34931625847` for exact candidate `1c410ba9555af561eff54b5b62352a48920998a8` is **FAILED / REVOKED FOR PROMOTION**. Passing portions: exact candidate identity, CI governance, managed PHP lint. Failing portion: deterministic BTC Intelligence surface-context suite expected explicit visitor-language Market Pulse activity and explicit fail-closed directional copy.

Full Release run `34931913433` for exact candidate `80dd5ca9a5e6afb6bdff5e85b844299e227e8252` is **FAILED / REVOKED FOR PROMOTION**. Passing portions before failure: exact candidate identity, CI governance, managed PHP lint, and deterministic BTC Intelligence surface-context suite. Failing portion: deterministic BTC Intelligence page contract still expected the superseded unavailable-brief copy.

Full Release run `34932103930` for exact candidate `300ba044ca42db3d2106b984adfa8b3af7eaa852` is **FAILED / REVOKED FOR PROMOTION**. Passing portions before failure: exact candidate identity, CI governance, managed PHP lint, deterministic BTC Intelligence surface-context suite, and BTC Intelligence page contract. Failing portion: BTC Intelligence layout contract still expected literal rail offsets instead of canonical header tokens with fallbacks.

Full Release run `34932342048` for exact candidate `f1cfb859983e3c9100d3aeac50e79fbd420ba186` is **FAILED / REVOKED FOR PROMOTION**. Passing portions before failure: exact candidate identity, CI governance, managed PHP lint, deterministic BTC Intelligence content contracts, and BTC Intelligence layout contract. Failing portion: BTC Intelligence market-context contract required a deterministic asset version bump.

Full Release run `34932575375` for exact candidate `66fb31a753ead09aabbd92633c8367147bbeec7d` is **FAILED / REVOKED FOR PROMOTION**. Passing portions before failure: exact candidate identity, CI governance, managed PHP lint, deterministic BTC Intelligence content/layout contracts, and market-context asset-version contract. Failing portion: BTC Intelligence session-brief contract required case-robust Opportunity activity rendering and explicit delayed-current fail-closed copy.

Full Release run `34932852380` for exact candidate `4d8c8620ccc2b17da28e3b0441c6974e753a7616` is **FAILED / REVOKED FOR PROMOTION**. Passing portions before failure: exact candidate identity, CI governance, managed PHP lint, and deterministic BTC Intelligence suites through session brief. Failing portion: Pro whitelist checkout-priority contract required the enabled-checkout CTA to replace whitelist/WhatsApp acquisition with the contracted Founding purchase label.

## Artifact state

- Historical artifact `10350132727` remains **REVOKED / SUPERSEDED FOR PROMOTION**.
- Candidate `1c410ba9555af561eff54b5b62352a48920998a8` is **REVOKED** after failed Full Release run `34931625847`.
- Candidate `80dd5ca9a5e6afb6bdff5e85b844299e227e8252` is **REVOKED** after failed Full Release run `34931913433`.
- Candidate `300ba044ca42db3d2106b984adfa8b3af7eaa852` is **REVOKED** after failed Full Release run `34932103930`.
- Candidate `f1cfb859983e3c9100d3aeac50e79fbd420ba186` is **REVOKED** after failed Full Release run `34932342048`.
- Candidate `66fb31a753ead09aabbd92633c8367147bbeec7d` is **REVOKED** after failed Full Release run `34932575375`.
- Candidate `4d8c8620ccc2b17da28e3b0441c6974e753a7616` is **REVOKED** after failed Full Release run `34932852380`.
- No current artifact is authorized.
- No staging deployment is authorized from historical evidence.
- Run Full Release once from the exact frozen release-head SHA after this ledger update is pushed.
- The next accepted artifact must be generated by Full Release from the new exact frozen candidate SHA recorded in PR #135 / release-integrator handoff.
- Artifact provenance must match exact source commit + source tree and deterministic build A/B must agree.

## Current release gates

| Gate | State | Meaning |
|---|---|---|
| SOURCE | **FIXING FULL RELEASE FINDING / FREEZE AFTER LEDGER COMMIT** | PR #168/#169/#170 remain reconciled; candidates `1c410ba9555af561eff54b5b62352a48920998a8`, `80dd5ca9a5e6afb6bdff5e85b844299e227e8252`, `300ba044ca42db3d2106b984adfa8b3af7eaa852`, `f1cfb859983e3c9100d3aeac50e79fbd420ba186`, `66fb31a753ead09aabbd92633c8367147bbeec7d`, and `4d8c8620ccc2b17da28e3b0441c6974e753a7616` are revoked and the next post-fix SHA must be frozen. |
| SCOPED CI | **SUPERSEDED BY FULL RELEASE FOR CANDIDATE** | Prior focused evidence was useful; release-wide validation now belongs to one exact-candidate Full Release. |
| RELEASE-WIDE CI | **READY AFTER NEW SHA FREEZE** | Run once after this source fix and ledger update are committed, pushed, and frozen as a new exact SHA. |
| ARTIFACT | **NONE AUTHORIZED** | Historical artifacts are revoked. |
| STAGING | **NO-GO** | Wait for the fresh exact-candidate artifact. |
| RUNTIME | **PENDING EXACT-CANDIDATE VALIDATION** | Full Release must validate all managed runtime suites and provenance for the frozen SHA. |
| BROWSER | **PENDING STAGING** | Manual browser acceptance runs only after exact artifact is on canonical staging. |
| PRODUCT READY | **NO** | Staging/runtime/browser/data/conversion/newsletter/SEO/social/analytics acceptance remains. |
| CHECKOUT | **OFF** | Must remain off. |
| WHATSAPP | **OFF** | Must remain off. |
| PRODUCTION AUTHORIZED | **NO** | No production promotion authorization exists. |
| PRODUCTION VERIFIED | **NO** | No production promotion has occurred. |

## Exact next executable sequence

1. Commit and push this ledger update as the final source mutation for the candidate.
2. Record the resulting immutable `release/whitelist-v1` head SHA in PR #135 / release handoff. **Do not mark #135 Ready merely to trigger cumulative scoped CI.**
3. Run Full Release **once** from `release/whitelist-v1` with `candidate_sha` equal to that recorded frozen SHA. Full Release is the release-wide deterministic/source/artifact gate and supersets the scoped candidate checks.
4. If Full Release fails, revoke that candidate SHA, keep #135 Draft, fix the actual cause through one focused PR, and freeze a new SHA. Do not rerun blindly.
5. Accept only an artifact whose manifest proves that exact source commit/tree and deterministic artifact hash.
6. Take/verify the staging rollback point, then deploy only that exact artifact to canonical staging.
7. Verify runtime parity, asset coherence, BTC/data readiness, Research qualification, whitelist, newsletter lifecycle, real mail, checkout OFF and WhatsApp OFF.
8. Run manual browser QA at 360x800, 390x568, 390x844, 768x1024, 1024x900 and 1440x1000 plus 200% text zoom.
9. Require Axe serious/critical = 0, keyboard/focus/reduced-motion acceptance, no clipping, no first-party console errors, correct SEO/OG/X metadata, analytics receipt at the configured sink, and screenshot evidence.
10. Only after all staging gates pass may production authorization be considered.
11. After the exact production artifact is verified, mark PR #135 Ready only for its genuine final review/merge to `main`, then converge/retire the release line without mutating the already accepted source tree.

## Known governance constraint

GitHub repository rulesets/branch protection are not available for this private repository under the current plan. Do not compensate by increasing CI volume. The release safety model therefore uses exact candidate SHA identity, draft-before-mutation discipline, candidate revocation on any change, deterministic artifact provenance, and explicit staging/production authorization as the compensating controls.

**PRODUCTION REMAINS NO-GO.**
