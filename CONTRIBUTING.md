# Contributing to Bitmomo

Bitmomo uses GitHub as the canonical engineering and release record. The goal is fast iteration **without losing source-of-truth clarity**.

Before changing code, read [`docs/ENGINEERING_OPERATING_MODEL.md`](docs/ENGINEERING_OPERATING_MODEL.md). For current launch/release status, read [`docs/CURRENT_RELEASE.md`](docs/CURRENT_RELEASE.md).

## Start here

1. Pull the latest `main`.
2. Check the current release status and relevant open issue.
3. Search existing open PRs for overlapping work.
4. Branch from `main` unless your change has a real code dependency on an unmerged PR.
5. Open a **draft PR early** using the repository PR template.
6. Keep one PR focused on one reviewable outcome.
7. Run the local source preflight while the PR is still draft.
8. Mark it Ready for review only when the change is frozen enough to justify CI/reviewer attention.

## Local-first validation

GitHub-hosted CI is an authoritative gate, not the first place to discover routine source failures.

During normal iteration run:

```bash
bash scripts/preflight-source.sh fast
```

Before a PR is marked Ready for review run:

```bash
bash scripts/preflight-source.sh full
```

`fast` performs deterministic syntax, lint, architecture, copy, UI-source, navigation, research-boundary, and CSS-debt checks. `full` adds every supported deterministic PHP test suite for the managed plugins.

The local preflight deliberately does **not** build a production artifact, run staging/browser acceptance, access production, or authorize a release. Those remain separate release gates. Do not mark a draft PR Ready merely to obtain basic lint/test feedback that can be produced locally.

## Branch naming

Use short-lived branches:

- `feature/<scope>` — new capability
- `fix/<scope>` — bug/correctness fix
- `refactor/<scope>` — behavior-preserving structural work
- `ci/<scope>` — CI/release tooling
- `docs/<scope>` — docs/research only
- `release/<name>` — only for an explicitly declared active release line
- `hotfix/<scope>` — urgent production repair

Do not encode engineer/tool identity into the branch name as the primary classification. The work type and scope matter more than whether it was created by ChatGPT, Claude, Codex, or a human engineer.

## Base-branch rule

**Default: `main` → working branch → PR → `main`.**

A PR may target another branch only when it truly requires code that is not yet in `main`. In that case:

- declare `Depends on: #PR` in the PR body;
- keep the stack shallow (normally no more than 2 dependent layers);
- do not create a release candidate on top of an arbitrary component branch;
- once the parent merges, promptly rebase/retarget the child to `main`.

## PR hygiene

An open PR means **actionable work**. A PR that is merged elsewhere, absorbed, abandoned, or deferred should be closed with a short reason. Git history and closed PRs remain available for archaeology.

Use draft PRs for work in progress. Avoid repeatedly creating new PRs just to represent a newer state of the same release candidate; update/reconcile the declared canonical candidate instead.

## Review and merge

Prefer **squash merge** for ordinary feature/fix/refactor/docs/CI PRs so `main` remains easy to scan. Use an explicit merge commit only when preserving multi-branch release/integration ancestry materially helps auditability.

Do not merge because a PR is “latest.” Merge because:

- its base is intentional;
- its scope is understood;
- relevant tests pass;
- it does not silently regress newer canonical work;
- its release state is truthfully documented.

## Production rule

A source merge is not a production deployment. Bitmomo release states are separate:

`SOURCE → ARTIFACT → STAGING → RUNTIME → BROWSER → PRODUCT READY → PRODUCTION AUTHORIZED → PRODUCTION VERIFIED`

No engineer may collapse these states into a single “done” claim. See the operating model for definitions and rollback expectations.

## Communication rule

Durable product/architecture/release decisions belong in repository docs or a canonical issue, not only in chat or a PR conversation. PR descriptions should be concise enough that a new engineer can understand the change, dependency, risk, test evidence, and deployment state in minutes.
