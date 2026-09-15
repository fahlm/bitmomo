# Engineering System Audit — 2026-09-16

## Executive assessment

Bitmomo's technical correctness culture is materially stronger than its historical delivery mechanics. The repository has unusually extensive deterministic contracts for its size, exact artifact provenance, fail-closed product behavior and strong staging gates. The main source of waste has been coordination: mutable release ancestry, branch/PR sprawl, duplicated hosted validation, CSS ownership debt and inconsistent source-of-truth state.

The operating system has now moved to local-first validation, immutable RCs and zero routine hosted-runner spend. The remaining structural risks are server-side Git enforcement, branch cleanup, post-Whitelist main convergence and frontend ownership/debt reduction.

## Scorecard

| Area | Initial | Current | Assessment |
|---|---:|---:|---|
| Correctness/testing mindset | 7/10 | 8/10 | Broad deterministic suites and fail-closed contracts; keep simplifying execution, not assertions. |
| Release engineering design | 7/10 | 9/10 | Exact SHA/tree/artifact + immutable RC + promote-same-artifact model is strong. |
| Release execution | 2/10 | 7/10 | Current RC is staged cleanly, but accepted release source has not yet converged back to trunk. |
| Git/branch governance | 2/10 | 3/10 | Policy is much better, but `main` is still unprotected and branch inventory is ~180. |
| CI cost efficiency | 2/10 | 9/10 | Automatic hosted runners are locked; development validation is local-first. |
| PR topology | 3/10 | 8/10 | Deep research stack collapsed; only a small actionable PR set remains. |
| Frontend maintainability | 5/10 | 5/10 | Modular owners exist, but legacy `custom.css` and cross-file cascade debt remain. |
| Source-of-truth hygiene | 3/10 | 8/10 | Current release docs/authority are synchronized; transitional trunk/release divergence remains explicit. |
| Overall | 4/10 RED | 7/10 AMBER | Delivery system is now coherent, but Git enforcement + trunk convergence + frontend debt still prevent institutional-grade maturity. |

## Evidence and root causes

- The accepted Whitelist candidate and current `main` share an older merge base and are heavily diverged: the release line contains hundreds of commits not on `main`, while `main` contains the newer governance/zero-cost commits. This is the clearest evidence that the old long-lived release branch became a second trunk.
- The repository currently carries roughly 180 remote branches. Most are historical implementation artifacts rather than active work.
- `main` is not server-side protected. Local hooks reduce accidental mutation, but they are not a security/governance boundary.
- The accepted theme has a 50,300-byte legacy `custom.css` in addition to many modular theme/plugin stylesheets. The historical CSS debt script inspected only `custom.css`, so total cascade debt was not observable as one system.
- The accepted release contains broad deterministic PHP suites plus explicit contracts for public adapters, freshness, regime, Pro/whitelist, BTC accountability, UI, staging readiness and artifact provenance. The problem was not lack of checks; it was where/how often they ran and how candidates moved underneath them.

## Changes introduced by Engineering System V2

1. `config/engineering-policy.json` becomes the small machine-readable operating contract.
2. `scripts/check-engineering-policy.mjs` fails locally if automated hosted Actions return, canonical release records disappear, or legacy `custom.css` grows.
3. `scripts/bitmomo-check.sh doctor|quick|test|full|smoke` gives every engineer one interface from workstation health through exact-SHA release validation.
4. `.githooks/pre-push` blocks normal direct pushes from `main`, `release/*`, and `rc-*` until server-side rulesets are available.
5. `scripts/audit-frontend-debt.mjs` inventories all first-party CSS, not only `custom.css`, and freezes growth of the legacy compatibility file.
6. `docs/ARCHITECTURE_OWNERSHIP.md` establishes one owner per runtime/data/UI concern.
7. `docs/PRODUCTION_PIPELINE.md` replaces release-by-branch intuition with artifact promotion and explicit risk classes.
8. Production monitoring stays outside paid GitHub runners and now treats stale/unavailable canonical intelligence as an operational alert on production.

## Remaining mandatory work

**After Whitelist production verification:** reconcile accepted product source into `main` while preserving current governance/tooling, run one exact-SHA local full gate, and retire the release/RC line.

**Repository admin:** protect `main`, block force push/deletion, require PR-based change and resolved conversations, enable automatic deletion of merged head branches, and prefer squash. Do not require paid hosted checks in zero-cost mode.

**Branch cleanup:** reduce historical branches toward <=15 active refs after preserving open PR/release evidence.

**Frontend program:** do not rewrite CSS before launch. Freeze `custom.css` now, measure all CSS, then migrate by surface when those surfaces are next changed. This converts a risky big-bang cleanup into continuous ownership reduction.

## Target state

The goal is not to imitate another company's codebase. It is to adopt institutional operating properties: one canonical trunk, explicit data ownership, deterministic artifacts, immutable release identity, cheap/fast developer feedback, staged promotion, visible operational freshness, auditable rollback and minimal coordination surfaces. Reaching that state would put Bitmomo's engineering process in a materially different maturity class from the original audit.
