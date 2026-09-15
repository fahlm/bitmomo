# Bitmomo Engineering Operating Model

**Status:** Canonical engineering workflow on `main`  
**Purpose:** Make Bitmomo fast to change, easy to understand, difficult to release incorrectly, and cheap to operate.

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
        └─ immutable rc-* snapshot
              ↓
        staging acceptance
              ↓
        production authorization
              ↓
            main
```

Rules:

- release branch must be named explicitly in `docs/CURRENT_RELEASE.md` and the active release issue;
- an accepted candidate is an immutable `rc-*` snapshot at an exact SHA/tree;
- a blocker is fixed on a focused branch and produces a new RC; accepted RCs are never patched in place;
- component branches feed the release line through deliberate reconciliation, not manual ad-hoc stacking;
- once production is verified and canonical changes are in `main`, close the release PR and remove the release branch.

## 5. PR lifecycle

### Draft
Use while actively iterating. Draft PRs are discoverable collaboration surfaces, not release candidates.

A draft must state objective, intended base, real dependency if any, scope/non-goals, and production impact.

### Ready for review
Only when:

- scope is stable;
- `bash scripts/bitmomo-check.sh test` passes locally;
- unrelated changes are removed;
- reviewer focus is clear.

### Reviewed / accepted
Acceptance means the **source change** is acceptable. It does not imply staging or production acceptance.

### Merged
After merge, dependent PRs retarget/rebase to `main`, absorbed/superseded PRs close, and the working branch is deleted when possible.

### Deferred
If useful work is postponed, close it with a `DEFERRED / ARCHIVED` note. Prefer a fresh branch from current `main` later rather than reviving stale ancestry.

## 6. Branch naming

Use purpose-first names:

- `feature/<scope>`
- `fix/<scope>`
- `refactor/<scope>`
- `ci/<scope>`
- `docs/<scope>`
- `release/<objective>`
- `rc-<objective>-<date>-<sequence>`
- `hotfix/<scope>`

Avoid engineer/tool names as branch taxonomy. Git already records authorship.

## 7. PR size and ownership

Prefer one PR = one reviewable outcome. Avoid mixing product work, broad cleanup, release topology changes, and speculative additions unless inseparable.

When a PR changes a shared contract, name the canonical owner/file so parallel engineers do not create competing implementations.

## 8. Merge strategy

Default to **squash merge** for ordinary PRs. Use an explicit merge commit only when preserving branch ancestry materially helps release/integration auditability.

## 9. Release state machine

A Bitmomo release progresses through explicit states:

**SOURCE → ARTIFACT → STAGING → RUNTIME → BROWSER → PRODUCT READY → PRODUCTION AUTHORIZED → PRODUCTION VERIFIED**

These states must never be conflated. A source PR may be accepted while production remains NO-GO.

## 10. Release handoff contract

Every release handoff states exact branch/SHA/tree, artifact identity/hash if available, checks that actually executed, staging/runtime/browser/product status, production status, blockers, and rollback point.

Avoid phrases such as “latest version”, “final branch”, or “all good” without an exact referent.

## 11. Parallel-engineer protocol

Before starting work, answer:

1. What is current `main`?
2. Is there an active release/RC?
3. Is another open PR already changing the same owner/files?
4. Does this task truly depend on unmerged code?
5. Who owns final integration/release?

A component engineer owns scoped implementation. The release engineer owns reconciliation/release mechanics. Neither silently redefines product behavior outside its scope.

## 12. Current-work visibility

An engineer should understand the repository in minutes by reading `README.md`, `docs/CURRENT_RELEASE.md`, the active release issue, then relevant open PRs. Historical PRs are archaeology, not daily workflow.

## 13. Zero-cost validation model

**GitHub-hosted Actions are not the development loop.** Normal validation runs on the engineer's existing machine or the existing staging host, so runner spend stays at zero during ordinary work.

Canonical commands:

```bash
# seconds: syntax/static checks only for touched files
bash scripts/bitmomo-check.sh quick

# normal pre-review gate: quick + deterministic suites for touched plugins
bash scripts/bitmomo-check.sh test

# exact candidate/release gate; clean checkout required
bash scripts/bitmomo-check.sh full <exact-40-char-sha>

# cheap runtime smoke against staging or production
bash scripts/bitmomo-check.sh smoke https://example.com
```

Rules:

- PR, push, and scheduled GitHub-hosted runner triggers stay disabled in zero-cost mode;
- engineers never rerun hosted Actions to diagnose normal source failures;
- `quick` is used repeatedly while coding; `test` once before Ready/review;
- `full` is used only for a real release candidate, not every commit;
- browser/a11y acceptance runs only against an actually deployed candidate;
- an external/provider monitor must live outside GitHub-hosted CI if continuous monitoring is needed;
- never weaken assertions because hosted CI is unavailable.

A skipped GitHub workflow is not validation evidence. Local command output or staging evidence is.

## 14. Production change discipline

Never deploy an arbitrary branch, local working tree, or superseded artifact. Production promotion identifies the exact accepted artifact/source and has a rollback point.

After deployment verify parity, critical journeys, cache/CDN behavior, and production-only integrations.

## 15. Hotfixes

Urgent production fixes use:

```text
main -> hotfix/<scope> -> focused PR -> production acceptance -> main
```

Keep scope minimal and add regression coverage after the incident.

## 16. Repository hygiene

At least once per release cycle:

- close superseded/abandoned/deferred PRs;
- delete merged obsolete branches when safe;
- keep one canonical release authority;
- reconcile duplicate issues/backlogs;
- ensure `docs/CURRENT_RELEASE.md` reflects reality.

## 17. Repository settings

Where repository administration permits:

- require PRs before normal merge to `main`;
- block force-push/deletion of `main`;
- require conversations resolved;
- automatically delete merged head branches;
- prefer squash merge;
- restrict direct pushes to emergency/admin use only.

Do **not** make paid hosted checks required while the repository operates in zero-cost mode. Enforcement must not force engineers to spend money to merge correct code.
