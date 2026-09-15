# Bitmomo Hyperliquid Shadow Soak Gate V0

Date: 2026-09-16
Status: PREDECLARED OPERATIONAL GATE
Branch: `research/hyperliquid-referral-volume-v0`

## Purpose

The launch-week soak is an operational reliability test, not an alpha-performance test. It must prove that the autonomous scanner can stay alive, fail closed, recover transport, preserve coherent state, and remain shadow-only before any controlled canary is considered.

## Evidence source

`RuntimeStateStore` persists:

- atomic latest state JSON for operators;
- compact bounded JSONL operational history for soak analysis.

Default history path is the state JSON path plus `.history.jsonl`.

`market_selector.soak_assessor` evaluates that history deterministically.

## Frozen minimum gate

Default V0 promotion thresholds:

- observed duration >= 12 continuous hours;
- transport healthy on >= 99% of recorded samples;
- no sample gap > 180 seconds;
- no continuous unhealthy streak > 180 seconds;
- final transport state healthy;
- every recorded runtime mode is `SHADOW_ONLY`;
- `mainnet_order_submission` is false in every sample;
- watched universe is non-empty in every sample.

Reconnects, universe changes, decision transitions, HALT samples and suppressed duplicate counts are reported diagnostically rather than automatically failed in isolation. A reconnect is acceptable when recovery is fast and state remains coherent.

## Why HALT/IDLE are not failures by themselves

The runtime can briefly report HALT during feed initialization/reconnect and can legitimately remain IDLE for long periods when no market is qualified. The soak gate therefore evaluates health/recovery continuity rather than forcing ACTIVE trading decisions.

## Diagnostic outputs

The assessor reports:

- samples and observed duration;
- healthy fraction;
- largest sample gap;
- longest unhealthy streak;
- final transport health;
- shadow/mainnet safety invariants;
- reconnect events observed;
- universe changes;
- supervisor decision transitions;
- HALT sample count;
- book/trade duplicates suppressed.

## Promotion rule

Passing this gate is necessary but not sufficient for a mainnet canary. It proves only the public-data autonomous control plane and its operational persistence. Account reconciliation, risk controls, execution lifecycle and controlled exposure gates remain separate requirements.

Do not relax this gate after observing a failed soak without documenting a new version before the next soak starts.
