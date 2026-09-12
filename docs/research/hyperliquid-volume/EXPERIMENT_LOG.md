# Hyperliquid Referral-Volume Research — Experiment Log

Status: ACTIVE LOG

Every experiment, including failures and superseded ideas, remains recorded. Do not delete prior failures when conclusions change.

## EXP-HL-000 — Safety/risk-engine foundation

- **Status:** PASS AS INFRASTRUCTURE / NOT ALPHA
- **Work:** local bot skeleton, ledger, risk engine, single-position guard, leverage guard, reconciliation checks, stress simulation and paper challenge.
- **Tests:** progressed from 8 to 16, 20, 28, 32, 34 and 36 passing tests as components were added.
- **Key result:** deterministic risk controls and kill-switch behavior worked in paper/stress scenarios.
- **Decision:** KEEP infrastructure. Do not confuse passing safety tests with evidence of trading edge.

## EXP-HL-001 — Initial market-structure research

- **Status:** EXPLORATORY / NO EDGE ACCEPTED
- **Question:** Can HH/HL and LL/LH structure plus confirmed BOS produce short-horizon directional edge?
- **Initial sample:** 44 structure signals; 14 long / 30 short.
- **Observation:** retest entries looked better than immediate breakout entries in the development sample. Retest conversion was ~68.2%.
- **Notable development result:** exact two-candle wait after BOS showed better average return than one- or three-plus-candle waits; low/medium retest body ratios also looked better than large bodies.
- **Decision:** freeze a simple V3 retest rule and test unseen data rather than continue tuning.

## EXP-HL-002 — V3 unseen 60-day holdout

- **Status:** FAIL
- **Frozen rule:** ordered HH-HL / LL-LH -> confirmed BOS -> wait exactly two 1m candles -> thesis-confirming retest -> body/range <= 0.60 -> next-candle entry; 15m outcome horizon.
- **Dataset:** unseen prior 60-day window.
- **Result:**
  - ALL: N=72, hit=41.7%, AvgRet=-0.038%, MedRet=-0.033%
  - LONG: N=29, hit=37.9%, AvgRet=-0.060%
  - SHORT: N=43, hit=44.2%, AvgRet=-0.024%
- **Decision:** REJECT V3 continuation as standalone alpha.

## EXP-HL-003 — V3 horizon/direction diagnostic

- **Status:** EXPLORATORY ONLY; DATASET BECAME SEEN
- **Question:** Was V3 direction wrong, or only the 15m horizon?
- **Finding:** continuation stayed weak while fade/reversal became positive at longer horizons in this seen sample.
- **Selected examples:**
  - fade ALL 15m: +0.038% average
  - fade LONG 15m: +0.060%
  - fade SHORT 15m: +0.024%
  - fade ALL 30m: +0.042%
  - fade SHORT 30m: +0.047%
- **Decision:** freeze one simple reversal hypothesis and test another unseen window; do not treat this diagnostic as validation.

## EXP-HL-004 — Fade V1 unseen holdout #2

- **Status:** FAIL
- **Frozen rule:** opposite V3 direction, fixed 15-minute hold.
- **Result:**
  - ALL: N=72, gross=-0.005%, maker-maker net=-0.035%, maker-taker net=-0.065%
  - original LONG -> fade SHORT: N=33, gross=+0.008%
  - original SHORT -> fade LONG: N=39, gross=-0.016%
- **Decision:** REJECT HH/HL/BOS continuation and simple fade as standalone alpha. Retain market structure only as possible context/regime information.

## EXP-HL-005 — Candle-level order-flow proxy

- **Status:** FAIL FOR DIRECTION
- **Question:** Do Binance 1m taker-buy volume, relative volume and price response provide 1-15m directional edge?
- **Dataset:** Jan-Feb 2026 research, Mar 2026 validation.
- **Signals:** continuation and exhaustion, split long/short and 1/3/5/10/15m horizons.
- **Result:** validation averages remained near zero, generally between roughly -0.01% and +0.006%; no robust fee-covering directional edge.
- **Decision:** REJECT candle-level order-flow proxy as sufficient directional alpha. Move to actual Hyperliquid L2 + trade microstructure.

## EXP-HL-006 — Hyperliquid microstructure recorder and markout study

- **Status:** PASS AS MICROSTRUCTURE EVIDENCE
- **Dataset:** ~120 minutes BTC Hyperliquid mainnet public market data, trading disabled; 7,155 usable 1-second rows.
- **Features:** best bid/ask, spread, L1/L3/L5 depth, imbalance, microprice, recent buy/sell aggressor flow and trade count.
- **Fill proxy:** opposite aggressor flow appears in the next second.
- **Finding:** alignment score was strongly monotonic in post-fill markout.
- **Same-session validation, possible fills only, 5s markout:**
  - score 0/3: -0.390 bp
  - score 1/3: -0.177 bp
  - score 2/3: +0.143 bp
  - score 3/3: +0.526 bp
- **Same-session validation, 10s markout:**
  - score 0/3: -0.594 bp
  - score 1/3: -0.223 bp
  - score 2/3: +0.149 bp
  - score 3/3: +0.420 bp
- **Research score 3/3:** +0.495 bp at 5s and +0.695 bp at 10s.
- **Validation score 3/3 side split, 5s:** BUY +0.593 bp; SELL +0.469 bp.
- **Decision:** KEEP 3-feature microstructure alignment. This is the strongest repeatable information edge found so far.

## EXP-HL-007 — First $100 -> $10K microstructure challenge simulator

- **Status:** INVALIDATED AS FILL MODEL / USEFUL DIAGNOSTIC
- **Problem:** simplistic queue assumption treated 100%/50%/25% of visible BBO as queue ahead and only retained the last trade price per second.
- **Result:** 100% and 50% queue assumptions generated zero fills; 25% produced only one round trip (~$800 volume) and ~-$0.11 PnL.
- **Decision:** do not infer strategy failure. Replace fill model with event-level L2 + individual trades.

## EXP-HL-008 — Event-level BTC queue research

- **Status:** PASS AS EXECUTION DIAGNOSTIC
- **Dataset:** 10 minutes BTC; 1,102 book updates and 420 individual trades.
- **Queue-aware results:**
  - conservative: 0 full fills at 5s/10s/30s
  - medium: 30s full-fill ~4.7%; average post-fill markout became negative (~-0.168 bp at 1s, ~-0.065 bp by 5-10s)
  - optimistic: 30s full-fill ~8.4%; average markout ~-0.295 bp at 1-3s and ~-0.281 bp at 5-10s
- **Interpretation:** short quotes rarely fill; longer quotes fill more often but become more adversely selected.
- **Decision:** BTC is structurally unattractive for this small-capital referral-volume objective. Search other Hyperliquid perps.

## EXP-HL-009 — Hyperliquid market scanner

- **Status:** PASS AS VENUE SCREEN
- **Question:** Which liquid perps combine sufficient activity, wider spread and thinner BBO queues than BTC?
- **Notable snapshot:**
  - PONS: 24h vol ~$56.6M, spread 5.26 bp, bid queue ~$408, ask queue ~$46
  - VVV: ~$33.7M, 4.66 bp, ~$216 / ~$160
  - ETHFI: ~$19.8M, 3.63 bp, ~$38 / ~$400
  - PUMP: ~$74.7M, 2.81 bp, ~$2.0K / ~$73
  - BTC: ~$3.58B, 0.13 bp, ~$225K / ~$529K
- **Decision:** prioritize PONS/VVV/ETHFI; keep PUMP/BTC as comparators. Do not assume $400 is optimal because small-market BBO queues can be smaller than the proposed order itself.

## EXP-HL-010 — Multi-market 30-minute event recorder

- **Status:** PASS
- **Markets:** PONS, VVV, ETHFI, PUMP, BTC.
- **Per-market books:** 3,297 each.
- **Trades:** PONS 1,372; VVV 517; ETHFI 632; PUMP 732; BTC 1,946.
- **Combined CSV rows:** 16,486 books including header; 5,200 trades including header.
- **Observation:** PONS combined unusually wide spread with high trade activity; VVV had lower trade activity but still attractive spread/queue characteristics.

## EXP-HL-011 — Initial multi-market round-trip screen

- **Status:** SCREEN ONLY / SAMPLE TOO SMALL
- **Model:** score 3/3 maker entry; maker exit first; taker fallback; medium queue/cancellation assumptions; $50/$100/$200/$400; 5/10/30s lifetimes.
- **Result:** most completed strategies had only 2-5 round trips and maker ratios near 50-62%.
- **Examples:** VVV projected P10K around -$2.51 in best small-sample row; BTC around -$3.06; PUMP around -$4.24.
- **Decision:** major leakage is taker fallback on exits. Small-sample P10K is not accepted as an estimate of real cost.

## EXP-HL-012 — Inside-spread screen

- **Status:** SCREEN / SIMULATOR LIMITATION FOUND
- **Idea:** improve quote one or more fractions inside a wide spread to jump queue while remaining passive.
- **Result:** no material economic improvement; best leaderboard rows remained negative (e.g. PUMP $50 JOIN about -$4.27 P10K; VVV $50 JOIN about -$4.88).
- **Model flaw discovered:** `probable_fill()` did not model queue-ahead for JOIN and for improvements that rounded back to BBO.
- **Decision:** fix queue treatment and narrow research to PONS/VVV/ETHFI.

## EXP-HL-013 — Candidate screen V2

- **Status:** PROMISING SCREEN / NOT VALIDATED
- **Model changes:** queue-aware JOIN; one-tick inside-spread improvement (`IMP1`) starts with zero queue ahead; no cancellation credit; $50/$100; score 2/3 or 3/3; JOIN/IMP1 entry and exit; 10s/30s exit lifetime.
- **Top small-sample leaderboard row:** VVV $100 S3 IMP1->JOIN E30s, RT=5, fill=11.9%, maker=60.0%, win=40.0%, Vol/h ~$2,001, T10K ~5.0h, P10K -$0.12, DD $0.30. Rejected as winner because RT=5 and maker ratio low.
- **Frozen primary candidate:** VVV $100 S3 IMP1->IMP1 E30s, RT=6, fill=13.6%, maker=83.3%, win=33.3%, Vol/h ~$2,400, T10K ~4.2h, P10K -$1.25, DD $0.34.
- **Frozen control:** VVV $100 S3 IMP1->JOIN E30s.
- **Other evidence:** VVV $50 S2 IMP1->IMP1 E30s produced RT=30 and maker=75% but P10K ~-$3.67; ETHFI high-maker rows were ~-$4.55 to -$4.66 P10K; PONS high-maker rows were worse (~-$5.53 and below).
- **Decision:** freeze VVV primary/control and validate on a clean unseen long holdout. No further tuning before holdout result.

## EXP-HL-014 — First VVV 8h holdout acquisition attempt

- **Status:** DATA ACQUISITION FAILURE / HOLDOUT NOT EVALUATED
- **Recorder:** original multi-market/event recorder under `caffeinate`.
- **Failure:** websocket/subscription stopped updating while Python loop remained alive; counter froze at 11,962 books / 2,641 trades.
- **Important decision:** do **not** run the strategy evaluator on the partial dataset, preserving the strategy rules from adaptation to this failed acquisition.
- **Action:** partial files renamed as stale/cadaver evidence; build resilient watchdog/reconnect recorder.

## EXP-HL-015 — Resilient recorder watchdog test

- **Status:** IN PROGRESS / OPERATIONAL VALIDATION
- **Features added:** stale detection, automatic reconnect, append-safe writes, trade-ID deduplication, duplicate-book protection, health log.
- **5-minute test observation:** book and trade counts increased normally, then watchdog detected `STALE book_age=30.1s` near the end of the test.
- **Current caution:** 30s book-only staleness may be too aggressive for a thin market; final production research recorder should consider a 60s/dual-feed health rule and confirm reconnect -> resume -> finish behavior.
- **Decision:** do not start the definitive 8h holdout until watchdog behavior is verified cleanly.

## Frozen unseen-holdout protocol

Primary:
- VVV
- $100 order notional
- score 3/3
- IMP1 maker-intent entry
- IMP1 maker-intent exit
- 30s exit lifetime before fallback

Control:
- VVV
- $100
- score 3/3
- IMP1 entry
- JOIN exit
- 30s exit lifetime

Acceptance gates fixed before the unseen holdout:
- completed round trips >= 50
- maker ratio >= 75%
- max drawdown <= $2.50
- projected time to $10K <= 6h
- **profit gate:** P10K >= $0
- **low-cost referral gate:** P10K >= -$2.00

No parameter is to be changed after the clean holdout begins and before it is evaluated.
