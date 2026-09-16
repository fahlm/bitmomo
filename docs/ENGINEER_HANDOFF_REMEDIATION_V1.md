# Engineer Handoff — Whitelist V1 Remediation

## Source of truth
Branch: `release/whitelist-v1-remediation`

Read first:
1. `docs/WHITELIST_V1_REMEDIATION.md`
2. `docs/PRODUCT_ACCEPTANCE_MATRIX_V1.md`
3. `docs/REMEDIATION_PORT_PLAN.md`
4. `config/release-remediation.json`

## Baseline rule
Do not deploy historical artifact `10414093076`. Do not modify historical RC evidence.

## Engineering rule
Work only on the remediation branch or focused branches based directly on its current head. No work may be based on historical PR ancestry unless the port plan explicitly names it as a source of product decisions.

## Immediate execution order
1. Port PR #179 exact intended footer changes.
2. Port PR #180 exact intended BTC visualization/accountability changes.
3. Re-audit preserved PR #176 semantically and re-implement only approved Show-First decisions; never merge its whole historical stack.
4. Deploy one integrated candidate to staging.
5. Record actual staging findings against Product Acceptance Matrix.
6. Fix only observed launch-quality regressions.
7. Freeze exact new RC and run final release state machine.

## Non-negotiable boundaries
- whitelist persistence/consent/dedupe/confirmation unchanged;
- checkout OFF;
- WhatsApp OFF;
- Research Distribution excluded;
- Engineering System V2 excluded from packaged runtime;
- no engine/scoring retune without proven correctness bug;
- no public current-Pro leakage;
- stale/invalid intelligence fails closed;
- no fabricated content/data/proof.

## Completion definition
The work is incomplete until the exact staged artifact passes both technical gates and explicit human visual/product acceptance.