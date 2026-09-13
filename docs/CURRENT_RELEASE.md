# Current Bitmomo Release Status

**Last updated:** 2026-09-13  
**Production authorization:** **HOLD / NO-GO**  
**Canonical coordination issue:** #131 — P0 Governance: converge to one canonical whitelist release line

This file is the fast entry point for engineers. It records **release topology**, not product roadmap.

## Current canonical integrated source

- Repository default branch: `main`
- Current audited `main` head at the time this status was created: `e258cedaf72c4096ad8050758ea58950f1a269f5`
- `main` remains the canonical integrated source for ordinary engineering work.

## Why production is on hold

Two launch/release ancestries exist for the same whitelist/public-surface objective and they are materially diverged:

| Line | Ref | Status |
|---|---|---|
| staging/home+chrome integration | `release/staging-home-chrome-integration` @ `8bb9402e9668d335e4ac505538fd05d11b2222c7` | historical active release line; **not canonical alone** |
| integrated candidate | PR #123 / `chatgpt/integrated-release-candidate-v1` @ `725433ccc2510ace770b8871e84638844c16a60f` | convergence candidate; **not canonical alone** |

Git comparison showed real divergence: PR #123 carries 36 commits not present in the staging line, while the staging line carries 15 commits not present in PR #123 from their common ancestry.

Therefore no engineer should infer “latest = canonical.”

## Temporary rules until #131 is resolved

- Do not create a third release candidate.
- Do not promote either old release line to production by assumption.
- New ordinary engineering work starts from current `main` unless it is explicitly part of the #131 convergence.
- PR #129 is release-governance input, not a separate release authority.
- PR #126 is product input that must be deliberately reconciled into the single final launch candidate.
- Production remains unchanged until the exact single candidate passes the release state machine.

## Target topology

The release convergence should end with:

```text
main
  └─ release/whitelist-v1
       ├─ reconciled public UI/chrome/homepage
       ├─ reconciled BTC Market Context/integrity fixes
       ├─ reconciled Pro conversion work
       ├─ release-governance hardening
       └─ staging-only fixes if proven necessary
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
            main
```

## Open work classification

### Release-critical now
- #131 — release topology convergence / single coordination point
- #129 — release-governance hardening to be reconciled into the canonical line
- #126 — institutional Pro conversion surface to be reconciled into the canonical line
- #125 — GitHub Actions capacity/infrastructure blocker
- #127 — paid-checkout trust gate; does **not** block whitelist-only launch unless paid checkout is enabled

### Component/release inputs still intentionally open
- #116 — BTC Decision Ledger / delayed Pro proof baseline
- #119 — institutional header/footer chrome
- #120 — institutional homepage
- #123 — integrated candidate, currently held under #131

These are inputs/history during convergence. They must not remain independent release alternatives after #131 completes.

### Closed historical work
Superseded/absorbed/deferred PRs are intentionally closed even when their code/history is valuable. Closed does not mean erased; it means **not currently actionable**.

Notable cleanup on 2026-09-13:
- old Bitmomo Pro stack #27–#34, #36, #38 closed after integration through #39;
- old Regime stacked PRs #41/#42 closed after direct integrations #50/#51;
- Watchtower/hardening stack #43–#49 closed as deferred archive;
- component PRs #121/#122 closed after absorption into #123.

## What every engineer should do before starting work

1. Read this file.
2. Read #131 if touching launch/public/release surfaces.
3. Pull current `main`.
4. Search open PRs for file/scope overlap.
5. Use the PR template and explicitly declare base/dependency/release impact.

If this file and an open PR disagree, stop and resolve the source-of-truth conflict before creating another integration branch.
