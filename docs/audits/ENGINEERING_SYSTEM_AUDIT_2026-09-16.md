# Engineering System Audit — 2026-09-16

## Executive assessment

Bitmomo's technical correctness culture is materially stronger than its historical delivery mechanics. The repository has unusually extensive deterministic contracts for its size, exact artifact provenance, fail-closed product behavior and strong staging gates. The main source of waste has been coordination: mutable release ancestry, branch/PR sprawl, duplicated hosted validation, CSS ownership debt and inconsistent source-of-truth state.

Engineering System V2 moves the normal path to local-first validation, immutable RCs, explicit architecture ownership and zero routine hosted-runner spend. A second audit pass found two important enforcement gaps in the first draft and one transitional repository exposure; those are now explicitly addressed rather than hidden.

The remaining structural risks are server-side Git enforcement, historical branch cleanup, post-Whitelist main convergence, retirement of the old release-branch CI topology, and frontend ownership/debt reduction.

## Scorecard

| Area | Initial | Current | Assessment |
|---|---:|---:|---|
| Correctness/testing mindset | 7/10 | 8/10 | Broad deterministic suites and fail-closed contracts; keep simplifying execution, not assertions. |
| Release engineering design | 7/10 | 9/10 | Exact SHA/tree/artifact + immutable RC + promote-same-artifact model is strong. |
| Release execution | 2/10 | 7/10 | Current RC is staged cleanly, but accepted release source has not yet converged back to trunk. |
| Git/branch governance | 2/10 | 3/10 | Policy is much better, but `main` is still unprotected and branch inventory is ~180. |
| CI cost efficiency | 2/10 | 8/10 | `main` is zero-cost; historical release branch can still run scoped hosted PR CI until retired. |
| PR topology | 3/10 | 8/10 | Deep stack collapsed; actionable set is bounded and post-launch branches are parked Draft. |
| Frontend maintainability | 5/10 | 5/10 | Modular owners exist, but legacy `custom.css` and cross-file cascade debt remain. |
| Source-of-truth hygiene | 3/10 | 8/10 | Release docs + machine state are synchronized; transitional trunk/release divergence remains explicit. |
| Developer experience | 4/10 | 8/10 | One local command surface replaces knowledge of many independent checks. |
| Overall | 4/10 RED | 7/10 AMBER | Delivery mechanics are coherent, but Git enforcement + trunk convergence + release-line retirement + frontend debt still prevent institutional-grade maturity. |

## Evidence and root causes

- The accepted Whitelist candidate and current `main` share an older merge base and are heavily diverged: the release line contains hundreds of commits not on `main`, while `main` contains newer governance/zero-cost commits. This is the clearest evidence that the old long-lived release branch became a second trunk.
- The repository currently carries roughly 180 remote branches. Most are historical implementation artifacts rather than active work.
- `main` is not server-side protected. Local hooks reduce accidental mutation, but they are not a security/governance boundary.
- The accepted theme has a 50,300-byte legacy `custom.css` in addition to many modular theme/plugin stylesheets. The historical CSS debt script inspected only `custom.css`, so total cascade debt was not observable as one system.
- The accepted release contains broad deterministic PHP suites plus explicit contracts for public adapters, freshness, regime, Pro/whitelist, BTC accountability, UI, staging readiness and artifact provenance. The problem was not lack of checks; it was where/how often they ran and how candidates moved underneath them.
- `release/whitelist-v1` still contains PR-triggered hosted workflows. A post-release chart PR demonstrated that a Ready PR targeting that historical branch can still allocate runner time even though `main` is locked. The PR has been returned to Draft/PARKED and the release branch must be retired after production convergence rather than edited now.

## Second-pass defects found and fixed in Engineering System V2

### 1. Pre-push destination bypass
The first local hook inspected only the currently checked-out branch. A refspec push such as `git push origin HEAD:main` could therefore bypass the intent of the guard from a feature branch.

The hook now parses Git's pre-push stdin and protects the **remote destination ref** (`refs/heads/main`, `refs/heads/release/*`, `refs/heads/rc-*`). This is materially stronger local defense-in-depth until server-side rulesets exist.

### 2. RC identity was recorded but the ref itself was not proven
The first machine release-state check verified commit/tree/artifact fields but did not prove that the named `rc-*` ref still resolved to that exact commit.

`check-release-state.mjs` now validates the immutable RC ref format, resolves the ref locally, requires it to equal the recorded candidate commit, binds artifact naming to the exact SHA, and keeps the human release ledger synchronized with machine state.

### 3. Historical release branch remains a temporary CI exception
The zero-cost model is fully true for `main`, but not yet globally true while `release/whitelist-v1` exists. The correct fix is **not** to mutate accepted release ancestry just to change CI. Instead all preserved post-launch work against that line stays Draft, local validation is used, and the release branch is retired immediately after production verification + trunk convergence.

## Changes introduced by Engineering System V2

1. `config/engineering-policy.json` is the small machine-readable operating contract.
2. `config/release-state.json` records exact release/RC/artifact/launch state as data rather than relying only on prose.
3. `scripts/check-engineering-policy.mjs` fails locally if the zero-cost trunk posture, canonical engineering files, local guard, source-of-truth records or legacy CSS ceiling regress.
4. `scripts/check-release-state.mjs` proves exact commit/tree/RC-ref/artifact identity and human-ledger synchronization.
5. `scripts/bitmomo-check.sh doctor|quick|test|full|equivalence|smoke` gives every engineer one interface from workstation health through exact-SHA release validation and post-release runtime equivalence.
6. `.githooks/pre-push` blocks normal pushes **to** `main`, `release/*`, and `rc-*` destinations until server-side rulesets are available.
7. `scripts/check-runtime-equivalence.py` proves the packaged production runtime is byte-identical across accepted and reconciled refs while allowing docs/tooling/governance history to differ.
8. `scripts/audit-frontend-debt.mjs` inventories all first-party CSS, not only `custom.css`, and freezes growth of the legacy compatibility file.
9. `docs/ARCHITECTURE_OWNERSHIP.md` establishes one owner per runtime/data/UI concern.
10. `docs/PRODUCTION_PIPELINE.md` replaces release-by-branch intuition with artifact promotion and explicit risk classes.
11. Production monitoring stays outside paid GitHub runners and treats stale/unavailable canonical intelligence as an operational failure on production.
12. The PR template encodes risk class, canonical owner, local evidence, immutable-RC behavior and the frozen-release boundary.

## Remaining mandatory work

**Before merging Engineering System V2:** run `setup-engineer.sh`, `bitmomo-check.sh doctor`, and `bitmomo-check.sh test` from the exact PR head and record real local evidence. Mergeability is not test evidence.

**After Whitelist production verification:** reconcile accepted product source into `main` while preserving governance/tooling, prove runtime equivalence against `c33d128...`, run one exact-SHA local full gate, and retire the release/RC line.

**Repository admin:** protect `main`, block force push/deletion, require PR-based change and resolved conversations, enable automatic deletion of merged head branches, and prefer squash. Do not require paid hosted checks in zero-cost mode.

**Branch cleanup:** reduce historical branches toward <=15 active refs after preserving open PR/release evidence.

**Frontend program:** do not rewrite CSS before launch. Freeze `custom.css` now, measure all CSS, then migrate by surface when those surfaces are next changed. This converts a risky big-bang cleanup into continuous ownership reduction.

## Target state

The goal is not to imitate another company's codebase. It is to adopt institutional operating properties: one canonical trunk, explicit data ownership, deterministic artifacts, immutable release identity, cheap/fast developer feedback, staged promotion, visible operational freshness, auditable rollback and minimal coordination surfaces.

The key criterion is not the number of controls. It is whether the normal path is the easiest safe path and whether incorrect release states become difficult to create accidentally.
