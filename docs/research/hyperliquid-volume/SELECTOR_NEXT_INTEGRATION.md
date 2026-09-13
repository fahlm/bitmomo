# Selector V0 — Local Integration Contract

The next integration step in `~/bitmomo-hl-volume-bot` is to connect the existing dry-run queue/fill simulator to the selector's `ExecutionObservation` stream.

## Required mapping

For every dry-run quote attempt emit one `ExecutionObservation` with:

- `filled`
- `maker_entry`
- `maker_exit`
- `markout_5s_bps`
- `round_trip_volume_usd`
- `pnl_usd`

The observer then derives rolling:

- fill rate
- maker ratio
- 5s markout
- volume/hour
- T10K
- P10K

No market should become QUALIFIED until the configured minimum execution sample count is reached.

## Integration invariant

The selector may choose or revoke an ACTIVE market, but must never submit an order directly. A separate execution authority may act only when:

1. supervisor `allow_new_entries == True`
2. market equals `active_market`
3. risk guardian allows trading
4. account/reconciler state is known
5. no cross-market inventory conflict exists

## Current safety status

Until this mapping is wired and validated, the live selector is intentionally expected to show WATCH/UNKNOWN for execution economics rather than QUALIFIED.
