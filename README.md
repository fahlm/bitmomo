# Bitmomo

Bitmomo is a crypto and AI media/research project focused on turning high-quality research, market intelligence, and AI-assisted analysis into useful public content and products.

## Engineering entry points

Before starting engineering or release work:

1. Read [`docs/CURRENT_RELEASE.md`](docs/CURRENT_RELEASE.md) for the current canonical release topology and launch status.
2. Read [`docs/ENGINEERING_OPERATING_MODEL.md`](docs/ENGINEERING_OPERATING_MODEL.md) for branch, PR, stacking, CI, staging, production, and handoff rules.
3. Read [`CONTRIBUTING.md`](CONTRIBUTING.md) for the day-to-day contribution workflow.
4. Search current open PRs/issues before creating overlapping work.

**Default engineering rule:** branch from current `main`, open a focused PR back to `main`, and use a non-`main` base only for a documented code dependency. An open PR means actionable work; superseded/deferred work belongs in closed history.

**Release rule:** source acceptance, artifact generation, staging deployment, runtime parity, browser acceptance, product readiness, production authorization, and production verification are separate states. Never collapse them into one “done” claim.

## Project goals

- Build a scalable crypto + AI content engine.
- Develop structured AI market research and Bitcoin signal products.
- Improve the Bitmomo website as the central publishing and research platform.
- Grow Bitmomo's distribution through X/Twitter and YouTube.
- Establish repeatable systems that allow AI agents to handle operational work while human judgment remains in strategic and financial decisions.

## Repository structure

- `.github/` — pull-request and CI/release workflow conventions.
- `docs/` — project documentation, strategy, brand, content, research, release status, and engineering operating rules.
- `prompts/` — reusable AI prompts and evaluation specifications.
- `data/` — project datasets and derived research data. Do not commit secrets or sensitive credentials.
- `scripts/` — automation, release checks, and utility scripts.
- `website/` — Bitmomo WordPress/theme/plugin source code.

## Security

Never commit API keys, passwords, private keys, `.env` files containing secrets, database credentials, or other sensitive credentials.

## Status

Bitmomo uses this repository as the canonical engineering and knowledge-management backbone. Current release status is always recorded in [`docs/CURRENT_RELEASE.md`](docs/CURRENT_RELEASE.md); do not infer release authority from PR number, branch age, or whichever candidate was modified most recently.
