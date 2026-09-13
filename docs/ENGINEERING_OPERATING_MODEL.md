# Bitmomo Engineering Operating Model

**Status:** Canonical engineering workflow once merged to `main`  
**Purpose:** Make Bitmomo fast to change, easy to understand, and difficult to release incorrectly.

## 1. Source-of-truth hierarchy

When information conflicts, use this order:

1. **`main`** — canonical integrated source code and durable documentation.
2. **`docs/CURRENT_RELEASE.md`** — current release topology/status, including any temporary launch hold.
3. **The single active release issue** — coordination and acceptance state for the current launch.
4. **The single active `release/*` branch**, when one exists — release-only convergence/stabilization.
5. **Open feature/fix PRs** — proposed changes, not canonical behavior.
6. **Closed/merged PRs** — historical rationale and implementation archaeology.
7. Chat, screenshots, or agent-local state — useful context, never source of truth.

PR number order is not version order. A higher PR number is not automatically newer or more canonical than an earlier merged change.

## 2. Default development topology

Normal work must look like this:

```text
main
 ├─ feature/foo ── PR ──┐
 ├─ fix/bar ────── PR ──┼─> main
 └─ docs/baz ───── PR ──┘
```

The default rule is **branch from current `main`, merge back to `main`, then delete the working branch**.

Long-lived integration branches are an exception, not a workspace.

## 3. Stacked PR policy

Stacked PRs are allowed only when a child genuinely cannot be reviewed/built without an unmerged parent.

Required:

- PR body declares `Depends on: #XYZ`;
- dependency reason is code-level, not merely “this PR is newer”;
- stack depth should normally stay at **2 layers maximum**;
- no independent release candidate may be created from an arbitrary middle layer;
- when the parent merges, the child is promptly rebased/retargeted to `main`;
- if a later convergence PR fully absorbs a component PR, the component PR is closed as `ABSORBED / SUPERSEDED`.

Never leave an old stacked PR open purely as a bookmark.

## 4. One active release line

Bitmomo may have many feature PRs but only **one active release line per production objective**.

For the whitelist launch the intended topology is:

```text
main
  └─ release/whitelist-v1
        ├─ intentionally reconciled launch changes
        ├─ release-only fixes
        └─ staging acceptance
              ↓
        production authorization
              ↓
            main
```

Rules:

- release branch must be named explicitly in `docs/CURRENT_RELEASE.md` and the active release issue;
- release engineering may not create another candidate because the current one is inconvenient;
- component branches feed the release line through deliberate reconciliation, not manual ad-hoc stacking;
- once production is verified and canonical changes are in `main`, close the release PR and remove the release branch.

## 5. PR lifecycle

### Draft
Use while actively iterating. Draft PRs are discoverable collaboration surfaces, not release candidates.

A draft must already state:
- objective;
- intended base;
- dependency (if any);
- scope/non-goals;
- production impact.

### Ready for review
Only when:
- scope is stable;
- obvious local/static checks are complete;
- unrelated changes are removed;
- reviewer focus is clear;
- CI spend/reviewer attention is justified.

### Reviewed / accepted
Acceptance means the **source change** is acceptable. It does not imply staging or production acceptance.

### Merged
After merge:
- dependent PRs retarget/rebase toward `main` immediately;
- absorbed/superseded PRs close;
- branch should be deleted when possible;
- durable decisions are moved into canonical docs if they matter beyond the PR.

### Deferred
If useful work is intentionally postponed, close the PR with a `DEFERRED / ARCHIVED` note. Reopen only if the old ancestry is still safe; otherwise create a fresh branch from current `main` and port the relevant pieces.

## 6. Branch naming

Use purpose-first names:

- `feature/<scope>`
- `fix/<scope>`
- `refactor/<scope>`
- `ci/<scope>`
- `docs/<scope>`
- `release/<objective>`
- `hotfix/<scope>`

Avoid making the engineer/tool identity the primary branch taxonomy (`chatgpt/*`, `claude/*`, `codex/*`). Authorship is already recorded by Git/GitHub; branch names should communicate **what the work is**.

## 7. PR size and ownership

Prefer one PR = one reviewable outcome.

Good boundaries:
- one bug and its regression coverage;
- one user-facing capability;
- one refactor boundary;
- one release-governance improvement.

Avoid mixing:
- unrelated product changes;
- product feature + broad cleanup;
- release topology changes + speculative feature additions;
- infrastructure fixes + UI redesign unless they are inseparable.

When a PR changes a shared contract, explicitly name the canonical owner/file so parallel engineers do not create competing implementations.

## 8. Merge strategy

Default to **squash merge** for ordinary PRs. This keeps `main` readable as a sequence of outcomes rather than every iterative commit.

Use an explicit merge commit only when preserving branch ancestry materially helps release/integration auditability.

A merge method should not be chosen because it is easier in the moment; it should preserve the clearest long-term history.

## 9. Release state machine

A Bitmomo release progresses through explicit states. These states must never be conflated:

### SOURCE
Reviewed source exists. Production may still be untouched.

### ARTIFACT
A deterministic artifact has been generated from an exact source commit/tree. Artifact identity/hash is recorded.

### STAGING
That exact artifact is deployed to canonical staging.

### RUNTIME
Staging filesystem/runtime versions, data dependencies, cache/CDN bytes, and expected configuration match the candidate.

### BROWSER
Responsive, accessibility, interaction, console, and visual acceptance passes on the deployed runtime.

### PRODUCT READY
Product-specific business/trust gates pass (for example whitelist vs paid profile, legal pages, checkout availability, real data, fail-closed states).

### PRODUCTION AUTHORIZED
The owner/release authority explicitly approves production promotion of the exact accepted candidate.

### PRODUCTION VERIFIED
The exact production runtime is deployed, cache is coherent, critical journeys pass, and rollback was not needed (or rollback is documented).

A PR may truthfully be `SOURCE: PASS` while `PRODUCTION: NO-GO`.

## 10. Release handoff contract

Every release handoff must state:

- canonical branch and exact SHA;
- source tree/artifact identity when available;
- what changed;
- what was intentionally not changed;
- CI/source checks and whether they actually executed;
- staging deployment status;
- runtime/cache/browser/product status;
- production status;
- known blockers;
- rollback point/procedure.

Avoid vague phrases such as “latest version”, “final branch”, “should be fine”, or “all good” without an exact referent.

## 11. Parallel-engineer protocol

Before starting work, every engineer should answer:

1. What is the current canonical `main`?
2. Is there an active release hold/branch in `docs/CURRENT_RELEASE.md`?
3. Is another open PR already changing the same owner/files?
4. Does this task actually depend on unmerged code?
5. Who owns the final integration/release decision?

If two engineers need the same shared file, agree on ownership or sequencing before both build independent “final” implementations.

A component engineer owns their scoped implementation. The release engineer owns reconciliation/release mechanics. Neither role may silently redefine product behavior outside its scope.

## 12. Current-work visibility

An engineer should be able to understand the repository in minutes by reading:

1. `README.md`
2. `docs/CURRENT_RELEASE.md`
3. the active release issue (if any)
4. relevant open PRs
5. only then historical PRs if needed

Open PRs must therefore remain a clean actionable set. Historical/superseded/deferred work belongs in closed PR history.

## 13. CI efficiency

CI should maximize signal per runner-minute:

- draft iteration should not trigger expensive duplicate suites unless necessary;
- avoid running the same source under both push and PR triggers without a reason;
- one authoritative release suite is preferred over many overlapping “final” suites;
- heavy browser tests run when a candidate is worth testing, plus an appropriate scheduled production monitor;
- never weaken a required assertion merely because runner budget is exhausted.

A CI infrastructure failure must be reported separately from a source/test failure.

## 14. Production change discipline

Never deploy an arbitrary branch, local working tree, or superseded artifact.

Production promotion must identify the exact accepted artifact/source. After deployment:

- verify production identity/parity;
- test critical public/product paths;
- document cache purge/behavior;
- document rollback if used;
- update canonical release status.

## 15. Hotfixes

Urgent production fixes use:

```text
main -> hotfix/<scope> -> focused PR -> production acceptance -> main
```

Keep scope minimal. Do not smuggle unrelated refactors into a hotfix. After the incident, add regression coverage and record the root cause separately.

## 16. Repository hygiene

At least once per release cycle:

- close superseded/abandoned/deferred PRs;
- delete merged obsolete branches when safe;
- ensure no obsolete release candidate is still described as canonical;
- move durable decisions out of PR-only prose;
- reconcile duplicate issues/backlogs;
- ensure `docs/CURRENT_RELEASE.md` reflects reality.

## 17. Recommended GitHub repository settings

Where repository administration permits, use these guardrails on `main`:

- require pull requests before merge;
- block force-push/deletion of `main`;
- require conversations/review threads resolved;
- require selected release/source checks once CI capacity is available and stable;
- require branch to be up to date when appropriate for the chosen merge queue/workflow;
- automatically delete head branches after merge;
- prefer squash merge as the ordinary default;
- restrict direct pushes to `main` to emergency/admin use only.

Repository settings are enforcement; this document is the operating contract. Both matter.
