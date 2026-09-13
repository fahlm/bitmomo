# Current Bitmomo Release Status

**Last updated:** 2026-09-13  
**Production authorization:** **HOLD / NO-GO**  
**Canonical coordination issue:** #131 — P0 Governance: converge to one canonical whitelist release line

This file is the fast entry point for engineers. It records **release topology**, not product roadmap.

## Current canonical integrated source

- Repository default branch: `main`
- Governance baseline merged to `main`: `418c0074665bb2a5aed77592dc19bef334f3fb93`
- `main` is the canonical integrated source for ordinary engineering work.
- Historical release/component branches remain evidence/input only unless #131 explicitly promotes their semantics into the new canonical release line.

## Why production is on hold

Two historical launch/release ancestries exist for the same whitelist/public-surface objective and they are materially diverged:

| Line | Ref | Status |
|---|---|---|
| staging/home+chrome integration | `release/staging-home-chrome-integration` @ `8bb9402e9668d335e4ac505538fd05d11b2222c7` | release input; **not canonical alone** |
| integrated candidate | PR #123 / `chatgpt/integrated-release-candidate-v1` @ `725433ccc2510ace770b8871e84638844c16a60f` | draft convergence input; **not canonical alone** |

Git comparison showed real divergence: PR #123 carries 36 commits not present in the staging line, while the staging line carries 15 commits not present in PR #123 from their common ancestry.

Therefore no engineer should infer “latest = canonical.”

## Temporary rules until #131 is resolved

- Do not create a third independent release candidate.
- Do not promote either historical line to production by assumption.
- New ordinary engineering work starts from current `main` unless it is explicitly part of the #131 convergence.
- PR #129 is release-governance input, not a separate release authority.
- PR #126 is a Draft product delta that must be deliberately ported/reconciled into the single final launch candidate.
- PR #123 remains Draft and held under #131.
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

## Active release PR queue

Exactly three release-related PRs remain intentionally open, and all are **Draft** pending #131 convergence:

- #123 — integrated site + BTC Market Context candidate; one side of the divergence.
- #126 — institutional Pro conversion delta; must be ported/rebased into the canonical line.
- #129 — release-governance/staging-acceptance hardening; must be reconciled into the canonical line.

An engineer should not create another release/integration PR without updating #131 and this file first.

## Release-critical issues

- #131 — release topology convergence / single coordination point.
- #125 — GitHub Actions capacity/infrastructure blocker.
- #127 — paid-checkout trust gate; does **not** block whitelist-only launch unless paid checkout is enabled.

## Historical release inputs now closed

Closed does not mean erased. It means **not independently actionable**. Branches, commits, PR discussion, tests, artifacts and rationale remain available for semantic reconciliation and archaeology.

Governance cleanup on 2026-09-13 closed:

- old Bitmomo Pro stack #27–#34, #36, #38 after integration through #39;
- Regime stacked PRs #41/#42 after direct integrations #50/#51;
- Watchtower/hardening #43–#49 as deferred archive;
- public/frontend alternative #111 as a superseded parallel architecture path;
- release ancestors/components #103, #116, #119, #120, #121, #122 as absorbed/superseded release inputs.

## Repository governance now active

Merged through #132:

- `.github/pull_request_template.md` requires base/dependency/supersession/release-state/safety/rollback context;
- `CONTRIBUTING.md` defines the short day-to-day workflow;
- `docs/ENGINEERING_OPERATING_MODEL.md` defines the canonical source-of-truth, branch/stacking, PR, CI and release model;
- `docs/RELEASE_HANDOFF_TEMPLATE.md` defines the exact staging/production handoff format.

## What every engineer should do before starting work

1. Read this file.
2. Read #131 if touching launch/public/release surfaces.
3. Pull current `main`.
4. Search open PRs for file/scope overlap.
5. Branch from `main` unless a real code dependency requires otherwise.
6. Use the PR template and explicitly declare base/dependency/release impact.

If this file and an open PR disagree, resolve the source-of-truth conflict before creating another integration branch.
