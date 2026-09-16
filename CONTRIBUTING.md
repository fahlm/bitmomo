# Contributing to Bitmomo

Bitmomo uses a local-first, trunk-oriented engineering workflow. The objective is fast feedback, exact releases, and zero routine GitHub-hosted runner spend.

## One-time setup

```bash
git checkout main
git pull --ff-only
bash scripts/setup-engineer.sh
```

This enables the repository pre-push guard, fast-forward-only pulls, remote-branch pruning and Git rerere for repeated conflict resolution.

## Daily loop

```bash
git checkout main
git pull --ff-only
git checkout -b feature/<scope>   # or fix/, refactor/, docs/, ops/

# while coding
bash scripts/bitmomo-check.sh quick

# once before review
bash scripts/bitmomo-check.sh test
```

Open a Draft PR early. Normal work targets `main`. Keep one PR to one reviewable outcome and avoid stacks deeper than two layers.

Do not use GitHub-hosted Actions as the development loop. A skipped workflow is not test evidence; local command output and deployed staging evidence are.

## Risk class

Before marking a PR Ready, classify it:

- **A:** source-only / low runtime risk — local validation may be sufficient;
- **B:** public/runtime behavior — staging and browser acceptance are required before promotion;
- **C:** money/access/data integrity — full exact-SHA validation, staging product/data acceptance and explicit production authorization are mandatory.

The PR template is the canonical checklist.

## Release loop

Normal development merges to `main` first. When a real release candidate exists, the release authority runs:

```bash
bash scripts/bitmomo-check.sh full <exact-40-char-sha>
```

The accepted candidate is then frozen as an immutable `rc-*` snapshot. Build one deterministic artifact, deploy that exact artifact to staging, perform runtime/browser/product acceptance, obtain explicit production authorization, and promote the same artifact without rebuilding.

A blocker creates a focused fix and a new RC. Never patch an accepted RC in place.

## Historical Whitelist release exception

`release/whitelist-v1` predates the trunk-oriented model and is temporarily preserved because it contains the staging-accepted Whitelist candidate lineage. Do not use it as a new development trunk.

Until Whitelist V1 is production-verified and its accepted source is converged back to `main`:

- preserved post-launch PRs against that branch remain **Draft**;
- do not mark them Ready merely to obtain hosted CI;
- validate locally;
- do not mutate the accepted release/RC;
- port/recreate the preserved work from canonical `main` after convergence.

The historical release branch is then retired.

## Local safety guard

The repository pre-push hook parses the **remote destination ref** and blocks ordinary pushes to `main`, `release/*`, and `rc-*`. This prevents refspec bypasses such as pushing a feature-branch HEAD directly to `main`.

The environment override `BITMOMO_ALLOW_PROTECTED_PUSH=1` exists only for explicit release authority/emergency operations; it is not a normal workflow.

Server-side branch protection remains the final enforcement layer once repository administration is configured. The local hook is defense-in-depth, not a security boundary.

## Ownership

Before editing a shared surface, read `docs/ARCHITECTURE_OWNERSHIP.md`. Business/scoring logic belongs to its canonical plugin/adapter owner; frontend code must not reimplement intelligence semantics merely to render them.

For the full release state machine and operational model, read `docs/PRODUCTION_PIPELINE.md` and `docs/ENGINEERING_OPERATING_MODEL.md`.
