# Contributing to Bitmomo

Bitmomo uses a **local-first, zero-hosted-runner** engineering loop. GitHub is the source/review record; normal validation happens on the engineer's machine or on the deployed staging candidate.

Before code changes, read `docs/CURRENT_RELEASE.md` and `docs/ENGINEERING_OPERATING_MODEL.md`.

## Daily engineer loop

1. Pull current `main`.
2. Check open PRs for overlapping owner/files.
3. Create one focused branch from `main`.
4. While coding:

```bash
bash scripts/bitmomo-check.sh quick
```

5. Before marking the PR Ready:

```bash
bash scripts/bitmomo-check.sh test
```

6. Paste meaningful local evidence/failures fixed into the PR. Do **not** wait for or rerun GitHub-hosted Actions.
7. Prefer one PR = one outcome. Stack only for a real code dependency and normally no deeper than 2 layers.
8. Squash ordinary work into `main`; retarget dependent work immediately and delete obsolete branches when possible.

## Release engineer loop

A real candidate is an exact SHA, not “latest”. From a clean checkout:

```bash
bash scripts/bitmomo-check.sh full <exact-40-char-sha>
```

Then deploy that exact artifact to staging and perform runtime/browser/product acceptance. Cheap route smoke can be run locally:

```bash
bash scripts/bitmomo-check.sh smoke https://seagreen-snail-158456.hostingersite.com
```

An accepted candidate becomes an immutable `rc-*` snapshot. A blocker gets a focused fix and a **new RC**; never patch an accepted RC in place.

## Branch naming

Use `feature/`, `fix/`, `refactor/`, `ci/`, `docs/`, `hotfix/`, `release/`, and immutable `rc-*` candidate names. Branch names describe the work, not the engineer/tool.

## Cost rule

Normal development must consume **zero GitHub-hosted runner minutes**. PR/push/scheduled hosted Actions remain locked. Continuous monitoring belongs outside GitHub Actions. Re-enabling paid hosted checks requires an explicit owner decision, not engineer convenience.

## Production rule

A source merge is not a deployment. Release state remains:

`SOURCE → ARTIFACT → STAGING → RUNTIME → BROWSER → PRODUCT READY → PRODUCTION AUTHORIZED → PRODUCTION VERIFIED`

Always preserve an exact artifact identity and rollback point.
