# Hyperliquid Referral-Volume Research — Results V0

Status: PRE-HOLDOUT / NO LIVE AUTHORIZATION

## Executive summary

The research has eliminated three weak directions and identified one materially stronger execution signal.

Rejected as standalone alpha:

1. HH/HL / LL/LH + BOS continuation
2. simple fade/reversal of that structure setup
3. 1-minute candle-level order-flow continuation/exhaustion

Strongest surviving finding:

> Hyperliquid L2 order-book imbalance + microprice displacement + recent aggressor-flow imbalance exhibit a monotonic relationship with short-horizon post-fill markout.

The problem has therefore shifted from "predict BTC direction" to "select maker quotes with lower adverse selection and sufficient fill probability."

## 1. Market-structure findings

### Development observations

The original structure study produced 44 signals. Retest entries appeared better than immediate breakout entries, especially around a two-candle wait and smaller retest bodies. Those patterns did not survive clean holdout validation.

### Unseen V3 holdout

| Slice | N | Hit | Avg return | Median return |
| --- | ---: | ---: | ---: | ---: |
| All | 72 | 41.7% | -0.038% | -0.033% |
| Long | 29 | 37.9% | -0.060% | -0.060% |
| Short | 43 | 44.2% | -0.024% | -0.023% |

Conclusion: reject continuation.

### Reversal follow-up

A seen diagnostic suggested longer-horizon fade might work, including +0.060% average on faded original LONG setups at 15m. A second unseen 60-day holdout failed:

| Slice | N | Gross | Maker-maker net | Maker-taker net |
| --- | ---: | ---: | ---: | ---: |
| All | 72 | -0.005% | -0.035% | -0.065% |
| Original LONG -> fade SHORT | 33 | +0.008% | -0.022% | -0.052% |
| Original SHORT -> fade LONG | 39 | -0.016% | -0.046% | -0.076% |

Conclusion: market structure may remain context, but not primary trade trigger.

## 2. Candle-level order-flow findings

Binance Futures 1m taker-buy volume, relative volume and price-response features were tested on Jan-Feb 2026 research and Mar 2026 validation.

Across continuation/exhaustion, long/short and 1/3/5/10/15m horizons, validation average returns stayed near zero, generally around -0.01% to +0.006%.

Conclusion: candle-level order flow does not provide enough edge after realistic fees.

## 3. Hyperliquid L2 microstructure finding

A ~120-minute BTC public-market-data recording produced 7,155 usable 1-second rows. Trading was disabled.

Three features were aligned to the hypothetical maker-quote side:

- 5-level order-book imbalance
- microprice displacement from mid
- recent aggressor-flow imbalance

A score from 0/3 to 3/3 was then compared against post-fill markout.

### Same-session validation — possible-fill proxy

| Alignment score | 5s markout | 10s markout |
| --- | ---: | ---: |
| 0/3 | -0.390 bp | -0.594 bp |
| 1/3 | -0.177 bp | -0.223 bp |
| 2/3 | +0.143 bp | +0.149 bp |
| 3/3 | **+0.526 bp** | **+0.420 bp** |

The ordering is monotonic and economically meaningful even though it does not by itself cover the full maker fee.

Research-half score 3/3 markout was +0.495 bp at 5s and +0.695 bp at 10s.

Validation side split for score 3/3 at 5s:

- BUY: +0.593 bp
- SELL: +0.469 bp

Conclusion: keep the three-feature alignment signal. This is the strongest evidence produced so far.

## 4. Why BTC was deprioritized

Event-level BTC data showed that queue access, not directional signal quality, became the bottleneck.

10-minute queue-aware dataset:

- 1,102 L2 book updates
- 420 individual trades

Full-fill rates under a queue-aware model:

- conservative: 0% at 5s/10s/30s
- medium: 0.9% at 10s, 4.7% at 30s
- optimistic: 0.9% at 10s, 8.4% at 30s

The longer-lived fills were more adversely selected. Medium 30s average markout was about -0.168 bp at 1s and -0.065 bp by 5-10s. Optimistic 30s was roughly -0.295 bp initially and about -0.281 bp at 5-10s.

Combined with a BTC spread near 0.13 bp and very large BBO queues, BTC is a poor small-account venue for this objective.

## 5. Cross-market venue scan

Snapshot screen of liquid Hyperliquid perps:

| Coin | 24h volume | Spread | Bid queue | Ask queue | Approx avg queue / $400 |
| --- | ---: | ---: | ---: | ---: | ---: |
| PONS | $56.6M | 5.26 bp | $408 | $46 | 0.6x |
| VVV | $33.7M | 4.66 bp | $216 | $160 | 0.5x |
| ETHFI | $19.8M | 3.63 bp | $38 | $400 | 0.5x |
| PUMP | $74.7M | 2.81 bp | $2.0K | $73 | 2.6x |
| BTC | $3.58B | 0.13 bp | $225.1K | $528.8K | 942.4x |

The main implication is not merely "choose the widest spread." For PONS/VVV/ETHFI a $400 order can itself be large relative to visible BBO, so smaller orders deserve preference.

## 6. 30-minute multi-market event sample

Per-market L2 updates: 3,297.

Individual trades:

| Coin | Trades |
| --- | ---: |
| PONS | 1,372 |
| VVV | 517 |
| ETHFI | 632 |
| PUMP | 732 |
| BTC | 1,946 |

PONS combined wide spread with high observed activity; VVV remained attractive because of wide spread and small visible queues.

## 7. Candidate execution screens

### Initial round-trip screen

Most strategies completed only 2-5 round trips. Maker ratios were usually around 50-62%, revealing that taker fallback on exit was the primary cost leak.

Examples of small-sample projected PnL per $10K volume:

- VVV: roughly -$2.51 in the best early row
- BTC: roughly -$3.06
- PUMP: roughly -$4.24

These were not accepted as stable estimates.

### Inside-spread screen

Naive inside-spread quoting did not solve the problem and exposed a fill-model bug around queue-ahead handling. Best visible rows remained negative, including roughly -$4.27 P10K for PUMP $50 JOIN and -$4.88 for VVV $50 JOIN.

### Candidate screen V2

Queue-aware JOIN and one-tick inside-spread improvement (`IMP1`) produced the first candidate with a high maker ratio.

Small-sample leaders included:

| Candidate | RT | Fill | Maker | Win | Vol/h | T10K | P10K | DD |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| VVV $100 S3 IMP1->JOIN E30s | 5 | 11.9% | 60.0% | 40.0% | ~$2,001 | 5.0h | -$0.12 | $0.30 |
| **VVV $100 S3 IMP1->IMP1 E30s** | **6** | **13.6%** | **83.3%** | **33.3%** | **~$2,400** | **4.2h** | **-$1.25** | **$0.34** |
| VVV $50 S2 IMP1->IMP1 E30s | 30 | 25.9% | 75.0% | 13.3% | ~$5,999 | 1.7h | -$3.67 | $1.12 |
| ETHFI $100 S2 JOIN->IMP1 E10s | 10 | 6.7% | 75.0% | 30.0% | ~$4,201 | 2.4h | -$4.55 | $0.98 |
| PONS $50 S3 IMP1->IMP1 E30s | 15 | 33.3% | 80.0% | 33.3% | ~$2,999 | 3.3h | -$5.53 | $0.86 |

The -$0.12 row is not considered the winner because it has only five round trips and only 60% maker legs.

The frozen **primary** candidate is VVV $100, score 3/3, IMP1 entry, IMP1 exit, 30s exit lifetime.

## 8. Current unseen holdout gates

The next clean VVV holdout will be evaluated once, with rules frozen in advance.

Required gates:

- completed round trips >= 50
- maker ratio >= 75%
- max drawdown <= $2.50
- projected time to $10K <= 6h

Outcomes:

- **PROFIT GATE:** P10K >= $0
- **LOW-COST REFERRAL GATE:** P10K >= -$2.00
- otherwise: FAIL

## 9. Current operational blocker

The first attempted 8-hour VVV holdout acquisition stalled silently at 11,962 books and 2,641 trades because the websocket feed stopped updating while the Python loop stayed alive. The partial holdout was deliberately not evaluated.

A resilient recorder was then built with stale detection, reconnect, append-safe output, trade deduplication and a health log. A 5-minute watchdog test successfully detected a stale book feed at 30.1s. Final reconnect/resume behavior still needs to be verified before starting the definitive 8-hour holdout.

## Bottom line

The evidence does **not** yet support a live profitable volume bot. It does support a narrower and more credible hypothesis:

> VVV one-tick-improved maker quoting, gated by 3/3 microstructure alignment, may be able to reach the referral volume target in roughly several hours with a low-single-digit-USDC expected cost or potentially positive PnL.

That hypothesis now requires a clean unseen event-level holdout. No more parameter tuning is justified before that test.
