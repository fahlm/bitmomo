# Dynamic Market Selector V0 — Current Status

Date: 2026-09-13

## Current implementation state

A read-only selector core now exists on branch `research/hyperliquid-referral-volume-v0`.

Implemented:

- rolling market observer
- explicit provisional selector config
- fail-closed eligibility engine
- deterministic leaderboard/ranker
- hysteretic supervisor
- ACTIVE / IDLE / STOP_NEW_ENTRY / DRAIN / SWITCH / HALT decision states
- no-qualified => IDLE
- switch only when flat
- stale-feed hard reject
- UNKNOWN execution economics => no qualification
- public-market-data-only live dry-run adapter
- unit tests covering warm-up, stale feed, unknown metrics, idle, degradation, flat-before-switch and hysteresis

Not yet implemented/integrated in this research branch:

- online `ExecutionObservation` producer using the local bot's queue/fill simulator
- production risk guardian/reconciler
- order execution
- wallet handling
- mainnet authorization

## Important consequence of latest research

No token is currently hard-coded as production qualified.

VVV is **not** considered qualified merely because prior screening selected it. The unseen 8-hour holdout failed the economics and drawdown gates, and the subsequent SEEN state-aware exit diagnostic remained materially below the required P10K/DD gates.

Therefore the selector starts fail-closed and requires new empirical execution evidence before any market can move from WATCH to QUALIFIED.
