# Bitmomo

Bitmomo is a Bitcoin market-intelligence and AI-research platform. This repository is the canonical source for the product runtime, research systems, release identity, engineering contracts and operational documentation.

## Engineering — start here

A new engineer should be able to understand the active system without reading historical PRs or chat logs.

1. Read [`docs/CURRENT_RELEASE.md`](docs/CURRENT_RELEASE.md) for the current release objective and exact candidate/artifact state.
2. Read [`config/release-state.json`](config/release-state.json) for the machine-readable release identity.
3. Read [`docs/ENGINEERING_OPERATING_MODEL.md`](docs/ENGINEERING_OPERATING_MODEL.md) for Git/PR/release rules.
4. Read [`docs/ARCHITECTURE_OWNERSHIP.md`](docs/ARCHITECTURE_OWNERSHIP.md) before changing a shared runtime/UI/data contract.
5. Read [`CONTRIBUTING.md`](CONTRIBUTING.md) for the daily loop and risk-class requirements.
6. Search current open PRs/issues before creating overlapping work.

One-time local setup:

```bash
git checkout main
git pull --ff-only
bash scripts/setup-engineer.sh
```

Canonical engineer commands:

```bash
bash scripts/bitmomo-check.sh doctor
bash scripts/bitmomo-check.sh quick
bash scripts/bitmomo-check.sh test
bash scripts/bitmomo-check.sh full <exact-40-char-sha>
bash scripts/bitmomo-check.sh equivalence <accepted-ref> <candidate-ref>
bash scripts/bitmomo-check.sh smoke <base-url>
```

`config/engineering-policy.json` is the compact machine-readable engineering policy. Human documentation explains the model; local checks make its highest-cost invariants executable.

## Default development model

```text
main -> focused purpose-named branch -> local quick/test -> Draft PR -> review -> squash -> main
```

Use a non-`main` base only for a real documented code dependency. Normal stacks stay at two layers or less. A historical branch is not documentation, and a higher PR number is not a source-of-truth signal.

GitHub-hosted Actions are not the ordinary development loop. Canonical `main` is intentionally local-first/zero-routine-runner. Staging/browser acceptance is used only when runtime behavior requires it.

## Release model

Release stages are distinct:

**SOURCE → ARTIFACT → STAGING → RUNTIME → BROWSER → PRODUCT READY → PRODUCTION AUTHORIZED → PRODUCTION VERIFIED**

A real candidate is identified by exact commit/tree plus an immutable `rc-*` identity. Build a deterministic artifact once, deploy that exact artifact to staging, and promote the **same artifact** to production after explicit authorization. Never patch an accepted RC in place or rebuild production from an arbitrary working tree.

See [`docs/PRODUCTION_PIPELINE.md`](docs/PRODUCTION_PIPELINE.md) for risk classes, runtime equivalence, rollback, release-ancestry retention and the current Whitelist V1 convergence exception.

## Repository structure

- `.github/` — PR template and intentionally locked hosted-workflow stubs.
- `.githooks/` — local defense-in-depth for protected destination refs.
- `config/` — machine-readable engineering, release and production-runtime contracts.
- `docs/` — architecture, product/research, release and operating documentation.
- `prompts/` — reusable AI prompts and evaluation specifications.
- `research/` — isolated research implementations and experiments where present.
- `scripts/` — canonical local validation, release, monitoring and repository utilities.
- `website/` — deployable WordPress theme/plugin source code.

## Security

Never commit API keys, passwords, private keys, `.env` files containing secrets, database credentials, or other sensitive credentials. Engineering policy checks tracked secret/private-key filename patterns as an additional guard; it does not replace proper secret management.

## Source-of-truth rule

Current integrated engineering truth starts at `main`. Current release identity is recorded explicitly in `docs/CURRENT_RELEASE.md` and `config/release-state.json`. Open PRs are proposals, not canonical behavior. Closed PRs and old branches are archaeology.

Do not infer authority from PR number, branch age, chat history, or whichever candidate was edited most recently.
