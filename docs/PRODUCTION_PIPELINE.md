# Production Pipeline

This is Bitmomo's institutional release path. It optimizes for fast local feedback, immutable evidence, and promotion of the same artifact across environments.

## Normal development

```text
main -> focused branch -> quick/test locally -> Draft PR -> review -> squash -> main
```

Ordinary development uses zero GitHub-hosted runner minutes. `quick` is the repeated coding loop; `test` is the pre-review gate. Staging is not a substitute for source tests and source tests are not a substitute for staging when runtime behavior matters.

## Risk classes

**Class A — source-only / low runtime risk.** Documentation, isolated research tooling, behavior-preserving refactors. Local `test` can be sufficient.

**Class B — public/runtime behavior.** Theme, BTC page, public adapters, caching, asset ownership. Local `test` plus deployed staging/browser acceptance before promotion.

**Class C — money/access/data integrity.** Entitlement, payment/checkout, whitelist writes, canonical market-data semantics, migrations. Full exact-SHA gate, staging runtime/product acceptance, explicit production authorization and a bounded production canary are mandatory.

## Release path

```text
reviewed main
  -> full <exact SHA>
  -> immutable rc-* snapshot
  -> deterministic artifact + manifest/hash
  -> record exact identity in config/release-state.json
  -> staging deploy of that exact artifact
  -> parity / safety / browser / product acceptance
  -> explicit owner authorization
  -> production deploy of the same artifact (no rebuild)
  -> cache/CDN purge
  -> production smoke + bounded real integration canary
  -> external runtime monitor
  -> release record closed
```

An accepted RC is immutable. A blocker is fixed on a focused branch and produces a new RC. No engineer patches an accepted candidate in place.

## Promotion evidence

`config/release-state.json` is the machine-readable release identity. It records the accepted commit/tree, artifact ID/name/hashes, managed runtime file count, staging state, production authorization state and launch-profile switches. `scripts/check-release-state.mjs` verifies the recorded Git tree and packaging file count and fails when `docs/CURRENT_RELEASE.md` drifts from that identity.

A release is identified by exact commit, tree, artifact ID/hash, runtime file count, staging evidence and rollback point. Terms such as `latest`, `final`, or `should be good` are not release identities.

## Runtime equivalence

When governance/docs/tooling work must be reconciled around an already accepted runtime, do not rely on a broad Git diff or human judgment. Prove that the production package is unchanged:

```bash
bash scripts/bitmomo-check.sh equivalence <accepted-ref> <candidate-ref>
```

The equivalence gate loads the accepted `config/production-runtime.json`, requires the candidate packaging definition to be byte-identical, enumerates every packaged file across all runtime components, and compares Git blob identities. Any added, removed or changed packaged runtime byte fails the gate. Changes confined to excluded tests/docs/ops tooling may pass.

This gate is specifically useful for the Whitelist V1 post-production trunk convergence: it can prove that governance reconciliation did not silently alter the already accepted runtime before a new full exact-SHA validation is run.

## Release ancestry retention

A verified release commit must remain reachable from canonical history after temporary release/RC refs are deleted. Release evidence must not depend on a branch that later disappears.

For ordinary future releases cut directly from integrated `main`, this happens naturally because the accepted candidate is already an ancestor of later `main`.

For the historical Whitelist V1 parallel-trunk exception, convergence must preserve the accepted commit `c33d128d5681909337ffc8b0811647012532fe9e` as an ancestor of the reconciled trunk. Use an explicit reconciliation **merge commit** for this one exceptional integration rather than squash-copying the release files. Resolve conflicts so:

- product/runtime content remains byte-equivalent to the accepted production source;
- newer zero-cost governance/tooling on `main` is preserved;
- the resulting reconciliation commit has both histories as parents;
- `c33d128...` is provably an ancestor of the reconciled commit;
- runtime equivalence and the exact-SHA full gate both PASS before the reconciliation reaches canonical `main`.

Only after production is verified, convergence is merged, ancestry is proven, and machine release state records the reconciled trunk SHA may temporary `release/*` / `rc-*` refs be retired.

This explicit merge-commit exception does **not** change the normal squash-merge policy for ordinary feature/fix/docs/ops PRs.

## Rollback

Rollback is preferred over live editing when production-only behavior is unsafe. Restore the last known-good runtime/backup, record the production-only failure, fix from a focused branch, run the relevant deterministic gate, and cut a new RC. Never hide an emergency edit by later making source resemble production.

## Monitoring

Continuous runtime monitoring runs outside GitHub-hosted Actions. `scripts/production-monitor-local.sh` checks launch-critical routes, error leakage, environment leakage, noindex, public BTC contract consistency and production intelligence freshness. Binance reachability remains diagnostic because upstream/provider reachability is not itself a Bitmomo code failure.

## Operating targets

- hosted runner minutes for ordinary development: **0**;
- normal PR stack depth: **<= 2**;
- actionable open PRs: target **<= 5**;
- active branch inventory after cleanup: target **<= 15**;
- accepted release candidates per attempt: **one immutable RC at a time**;
- expensive full validation: **once per real candidate**, repeated only after source changes;
- production deploy: **artifact promotion, never rebuild**.

These targets reduce coordination and rework without weakening correctness assertions.

## Current Whitelist V1 exception and exit

Whitelist V1 predates this model and its accepted release source is materially diverged from current `main`. Do not reconcile it before production promotion because that would invalidate accepted staging evidence.

Immediately after production is verified:

1. create a dedicated reconciliation branch from then-current `main`;
2. merge the accepted Whitelist source/history into that branch with an explicit merge commit so `c33d128...` remains an ancestor; preserve current governance/tooling while resolving conflicts;
3. run `bash scripts/bitmomo-check.sh equivalence c33d128d5681909337ffc8b0811647012532fe9e <reconciled-sha>` and require PASS;
4. run one `bash scripts/bitmomo-check.sh full <reconciled-sha>` exact-SHA gate;
5. verify the reconciled product semantics against the just-verified production runtime;
6. merge the validated reconciliation into canonical `main` while preserving that ancestry;
7. record the canonical converged main SHA + runtime-equivalence proof in machine release state;
8. prove `git merge-base --is-ancestor c33d128d5681909337ffc8b0811647012532fe9e <converged-main-sha>`;
9. only then retire `release/whitelist-v1` and temporary RC refs.

Future releases begin from integrated `main`, not a long-lived parallel product trunk.
