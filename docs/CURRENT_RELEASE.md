# Current Bitmomo Release Status

**Last updated:** 2026-09-15 WIB  
**Production authorization:** **HOLD / NO-GO**  
**Staging promotion authorization:** **PENDING FULL RELEASE ARTIFACT / NO-GO**  
**Canonical coordination issue:** #131  
**Canonical release PR:** #135  
**Canonical release branch:** `release/whitelist-v1`

> **SOURCE CLOSURE COMPLETE — FREEZE AFTER THIS LEDGER MERGES**
>
> Whitelist V1 source remediation is converged. The final BTC public-clock/trust-boundary closure is integrated through PR #173. There is no authorized artifact yet. After this docs-only ledger PR merges, the resulting exact `release/whitelist-v1` head becomes the immutable candidate. Record that SHA in PR #135 and run Full Release once against that exact SHA. Any later source mutation revokes the candidate.

This file is the fast source-of-truth for release state. It does not authorize staging or production by itself.

## Canonical source topology

- Ordinary engineering source of truth: `main`.
- Sole Whitelist V1 release line: `release/whitelist-v1` / PR #135.
- PR #135 remains Draft during source freeze and exact-candidate Full Release validation.
- No focused PR is currently authorized to mutate the release line after this ledger closure.
- Runtime contract remains **118 managed files** unless Full Release proves otherwise.
- The ledger intentionally does not store its own commit SHA. Candidate identity belongs in PR #135 / Full Release provenance so recording it does not mutate the source being identified.

## Integrated release-critical remediation

The canonical release line includes the reviewed launch-integrity work, including:

- one compact footer newsletter, separated from Founding Whitelist acquisition;
- environment-owned newsletter backend identity with fail-closed behavior;
- dormant affiliate CTAs default-off;
- publication/evidence-first Research and About surfaces;
- source-owned public copy instead of post-render mutation;
- Free BTC Intelligence = **Now + Change + Meaning + One Watch** plus Decision Ledger accountability;
- Pro = deeper monitoring, scenarios, Expected Range/levels, invalidation, alerts/archive;
- delayed/stale current assessment fails closed at the public data boundary;
- strict canonical session/bias/numeric validation;
- Market Pulse/Opportunity fast clock separated from Major Brief slow-clock lineage;
- frozen Major Brief Opportunity retained internally for point-in-time lineage;
- fast public Opportunity exposes no independent observation timestamp or record id;
- insufficient-sample accuracy withheld at the public adapter boundary;
- Opportunity polling is explicit opt-in through `BITMOMO_AI_OPPORTUNITY_ENABLED=true`; Whitelist V1 does not silently add 15-minute Binance/WP-Cron polling;
- BTC provenance keeps one canonical public freshness timestamp;
- checkout remains **OFF**;
- WhatsApp remains **OFF**.

## CI / release guardrails

- Draft PRs perform zero intentional hosted validation work.
- Focused PRs use path-scoped Theme / Authority / Regime / CI Governance checks.
- Full Release is **manual-only** and requires an explicit exact `candidate_sha`.
- Full Release rejects a selected ref whose resolved SHA differs from `candidate_sha`.
- Full Release is restricted to `main` or `release/*` and checks CI governance before expensive work.
- Production Synthetic Monitor is operational monitoring only and runs daily plus manual dispatch.
- UI Browser Safety is manual-only during pre-production and runs against the exact deployed artifact.
- CI workflow inventory, trigger policy, finite timeouts, concurrency cancellation, and monitoring cadence are fail-closed governance contracts.
- Failed release workflows are diagnosed before another run; there is no blind rerun policy.

## Final scoped evidence before ledger freeze

Final focused closure PR #173 was validated on exact head `f7978049a48b6107fa719b1e3fe165e812fb7a0e` before squash merge:

- Authority Surface Safety run `34934082617` — **PASS**
- Regime Safety run `34934082626` — **PASS**
- Theme Safety run `34934082630` — **PASS**
- CI Governance run `34934082661` — **PASS**

These scoped runs prove the focused change and its source contracts. They do **not** replace Full Release for the final merged candidate.

## Historical Full Release evidence — revoked candidates

The following candidates are historical diagnostic evidence only and are **REVOKED FOR PROMOTION**:

- `1c410ba9555af561eff54b5b62352a48920998a8` — run `34931625847`
- `80dd5ca9a5e6afb6bdff5e85b844299e227e8252` — run `34931913433`
- `300ba044ca42db3d2106b984adfa8b3af7eaa852` — run `34932103930`
- `f1cfb859983e3c9100d3aeac50e79fbd420ba186` — run `34932342048`
- `66fb31a753ead09aabbd92633c8367147bbeec7d` — run `34932575375`
- `4d8c8620ccc2b17da28e3b0441c6974e753a7616` — run `34932852380`
- `0745245058b1a148ee00c50635a71fd3f57ec2c8` — run `34933235177`
- `e074bee0d9ffd0fa777f99fb142238b7299caef0` — run `34933609385`

Those runs progressively exposed and closed integration/test-contract issues. None produced an artifact authorized for the current source.

## Current artifact and release gates

| Gate | State | Meaning |
|---|---|---|
| SOURCE | **READY TO FREEZE AFTER LEDGER MERGE** | Release-critical fixes are integrated; this ledger update is the intended final source mutation. |
| FOCUSED CI | **PASS** | Final #173 Authority / Regime / Theme / CI Governance evidence is green. |
| FULL RELEASE | **PENDING FINAL EXACT SHA** | No Full Release exists yet for the post-#173 final candidate. |
| ARTIFACT | **NONE AUTHORIZED** | Historical artifacts/candidates are revoked. |
| STAGING | **NO-GO** | Wait for a green Full Release and exact deterministic artifact. |
| RUNTIME | **PENDING EXACT ARTIFACT** | Validate runtime only after the authoritative artifact exists. |
| BROWSER | **PENDING STAGING** | Manual responsive/a11y/browser acceptance follows exact-artifact staging deploy. |
| PRODUCT READY | **NO** | Runtime/browser/data/conversion/newsletter/SEO/social/analytics acceptance remains. |
| CHECKOUT | **OFF** | Must remain off. |
| WHATSAPP | **OFF** | Must remain off. |
| PRODUCTION AUTHORIZED | **NO** | No production promotion authorization exists. |
| PRODUCTION VERIFIED | **NO** | No production promotion has occurred. |

## Exact next executable sequence

1. Merge this docs-only ledger closure into `release/whitelist-v1` while #135 remains Draft.
2. Confirm there are zero other open PRs targeting the release branch.
3. Record the resulting exact release-head SHA and tree in PR #135 without changing source.
4. Freeze source: no commits, merges, version bumps, test edits, copy edits, or docs edits after that point unless the candidate is explicitly revoked.
5. Run **Full Release once** on `release/whitelist-v1` with `candidate_sha` equal to that exact frozen SHA.
6. Accept only a run whose candidate identity, CI governance, all managed deterministic/source suites, deterministic build A/B, runtime file contract, source commit/tree provenance, and artifact hashes all pass.
7. Take/verify a staging rollback point and deploy only that exact artifact to canonical staging.
8. Verify runtime parity, assets/cache coherence, BTC/data readiness, Research qualification, whitelist, newsletter lifecycle/consent, real mail, checkout OFF, and WhatsApp OFF.
9. Run manual browser acceptance at 360x800, 390x568, 390x844, 768x1024, 1024x900, and 1440x1000 plus 200% text zoom.
10. Require Axe serious/critical = 0, keyboard/focus/reduced-motion acceptance, no clipping, no first-party console errors, correct SEO/OG/X runtime metadata, and analytics receipt at the configured sink.
11. Only after every staging gate passes may production authorization be considered.
12. After exact production artifact verification, converge the accepted source to `main`, close #135, and retire the release line.

**No historical Full Release or artifact authorizes staging or production.**
