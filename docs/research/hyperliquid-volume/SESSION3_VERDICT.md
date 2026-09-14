# Hyperliquid Selector — Session 3 Frozen Verdict

Date frozen: 2026-09-14

Status: **FROZEN — NO QUALIFIED MARKET / IDLE**

Session 3 is now SEEN data. Do not use it as an unseen holdout for future policy changes.

## Purpose

Session 3 was the fresh validation session for the dual-window selector introduced after Session 2 exposed the single-window evidence-retention flaw.

The frozen selector/evaluator rules were not relaxed during the session:

- market-state window: 15 minutes;
- execution-evidence window: 4 hours, latest 100 attempts;
- execution evidence freshness: <= 30 minutes;
- minimum execution samples: 30;
- fill >= 5%;
- maker ratio >= 75%;
- 5s markout >= 0 bp;
- T10K <= 6h;
- P10K >= -$2;
- no qualified market => supervisor IDLE.

The standardized execution probe remained `shadow-probe-join-3of3-e10-x30-v0`.

## Evidence integrity

Local Session 3 evidence was preserved across a process restart rather than discarded:

- original Session 3 JSONL: 625 rows;
- first continuation segment: 19 rows;
- merged resume seed: 644 unique rows;
- duplicates removed during merge: 0;
- selector test suite before continuation: 32 passed;
- resume seed was successfully loaded (`external_loaded=644`);
- new continuation events were accepted after reload, restoring fresh execution evidence;
- final observed transport state: healthy, generation 1, reconnects 0;
- final supervisor state: IDLE, new entries disabled.

Raw JSONL captures remain local and are not committed to GitHub.

## Frozen final checkpoint

| Market | Verdict | Spread | Queue/$100 | Trades/min | ExecN | Fill | Maker | Markout5 | P10K | T10K |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| PONS | DEGRADED | 2.67 bp | 7.80x | 61.67 | 88 | 22.73% | 65.00% | -9.38 bp | -$10.09 | 4.26h |
| VVV | DEGRADED | 1.75 bp | 4.00x | 39.87 | 100 | 5.00% | 70.00% | -9.54 bp | -$8.19 | 10.86h |
| ETHFI | REJECT | 3.38 bp | 2.75x | 10.33 | 90 | 4.44% | 62.50% | -5.31 bp | -$5.05 | 21.30h |
| PUMP | REJECT | 2.75 bp | 33.36x | 56.27 | 100 | 11.00% | 54.55% | -2.64 bp | -$6.63 | 7.74h |
| BTC | REJECT | 0.13 bp | 5,288.56x | 210.93 | 100 | 10.00% | 55.00% | -0.74 bp | -$3.63 | 8.52h |

## Interpretation

### PONS

PONS became the clearest structurally accessible market in the final checkpoint, but the standardized execution policy failed the economics gates decisively:

- maker ratio 65.00% < 75%;
- 5s markout -9.38 bp < 0;
- P10K -$10.09 < -$2.

Fill and nominal throughput were not the main problem. The dominant problem was adverse selection / toxic passive fills.

### VVV

VVV was also structurally accessible at the final checkpoint but failed execution economics:

- maker ratio 70.00% < 75%;
- 5s markout -9.54 bp < 0;
- P10K -$8.19 < -$2;
- reported T10K was also above the 6h gate.

This is consistent with the earlier frozen VVV holdout: throughput can be generated, but not at acceptable expected cost/risk under the tested policies.

### ETHFI

ETHFI remained REJECT under the frozen evaluator. Its execution evidence was also weak: fill 4.44%, maker 62.50%, negative markout, and P10K -$5.05.

### PUMP

PUMP remained structurally unsuitable at the final checkpoint because Queue/$100 was 33.36x, above the 20x gate. Its execution economics were also negative.

### BTC

BTC remained structurally unsuitable for approximately $100 notional because the spread was only 0.13 bp and Queue/$100 exceeded 5,000x. Execution economics were also negative.

## Important T10K restart caveat

After process restart, historical execution events were rehydrated into a newly-created observer. The current observer implementation uses the new process `started_ms` as one bound when calculating elapsed time for `volume_per_hour` and T10K.

Therefore resumed-session T10K can be temporarily biased because historical round-trip volume is loaded while the elapsed-time denominator starts at the new process. **Do not use the post-restart T10K values as the sole basis for this verdict.**

The Session 3 conclusion is robust without relying on T10K: PONS and VVV independently fail maker-ratio, markout, and P10K gates; the other markets are rejected by the frozen evaluator and also show poor execution economics.

This restart-accounting issue should be fixed before a future session relies on rehydrated T10K for qualification.

## Frozen conclusion

**Session 3 verdict: NO QUALIFIED MARKET / IDLE.**

The dual-window architecture itself passed its intended validation objective: execution evidence persisted beyond the 15-minute market-state window and accumulated to useful sample sizes. The failure is now economic rather than architectural.

Do not weaken selector thresholds to force a market through. Session 3 indicates that the next research iteration should target the **execution policy / adverse-selection problem**, especially passive entry quality, rather than lowering market-quality gates.

## Next research direction

The next iteration should be a new, explicitly versioned policy research cycle using Session 3 only as SEEN diagnostic evidence.

Primary hypothesis to test:

> The 3/3 microstructure signal contains directional information, but JOIN passive fills are negatively selected. A better entry policy must reduce toxic maker fills before further exit-policy tuning can matter.

Recommended research order:

1. Fix restart-safe throughput accounting so rehydrated evidence does not distort volume/hour or T10K.
2. Freeze a small set of pre-declared entry-policy variants aimed at adverse-selection reduction, without changing the selector thresholds.
3. Compare them on SEEN diagnostic/replay data only to choose one candidate.
4. Freeze the chosen candidate before collecting a new Session 4 unseen shadow run.
5. Mainnet remains prohibited until an independent policy passes economics, drawdown, reliability, reconciliation, and risk gates.
