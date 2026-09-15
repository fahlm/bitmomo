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

A release is identified by exact commit, tree, artifact ID/hash, runtime file count, staging evidence and rollback point. Terms such as `latest`, `final`, or `should be good` are not release identities.

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

Immediately after production is verified, reconcile the accepted product source back into `main` while preserving the zero-cost governance/tooling commits already on `main`; validate the reconciled exact SHA locally, then retire `release/whitelist-v1` and temporary RC branches. Future releases should begin from integrated `main`, not a long-lived parallel product trunk.
