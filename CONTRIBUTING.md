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

## Release loop

Normal development merges to `main` first. When a real release candidate exists, the release authority runs:

```bash
bash scripts/bitmomo-check.sh full <exact-40-char-sha>
```

The accepted candidate is then frozen as an immutable `rc-*` snapshot. Build one deterministic artifact, deploy that exact artifact to staging, perform runtime/browser/product acceptance, obtain explicit production authorization, and promote the same artifact without rebuilding.

A blocker creates a focused fix and a new RC. Never patch an accepted RC in place.

## Local safety guard

The repository pre-push hook blocks ordinary direct pushes from `main`, `release/*`, and `rc-*`. Work on a purpose-named branch and use a PR. The environment override `BITMOMO_ALLOW_PROTECTED_PUSH=1` exists only for explicit release authority/emergency operations; it is not a normal workflow.

Server-side branch protection remains the final enforcement layer once repository administration is configured.

## Ownership

Before editing a shared surface, read `docs/ARCHITECTURE_OWNERSHIP.md`. Business/scoring logic belongs to its canonical plugin/adapter owner; frontend code must not reimplement intelligence semantics merely to render them.

For the full release state machine and operational model, read `docs/PRODUCTION_PIPELINE.md` and `docs/ENGINEERING_OPERATING_MODEL.md`.
