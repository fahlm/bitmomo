# BTC Mode Research — Experiment Log

Status: ACTIVE LOG

Every experiment, including rejected/failed experiments, is recorded here. Do not delete prior entries when conclusions change; append a new experiment or superseding note.

## EXP-000 — First 30-day P0.2B replay

- **Status:** SUPERSEDED FOR CALIBRATION / RETAINED FOR AUDIT
- **Question:** Can the initial 60 production-aligned + 180 four-hour sample calibrate BTC Mode using +6h/+12h/+24h/+72h outcomes?
- **Result:** The run established public-archive acquisition, PIT procedures, provenance, missingness, and experiment infrastructure, but the outcome cadence was too slow for the intended active-trader use case and parts of Direction/Regime replay used substitute semantics.
- **Decision:** RETAIN artifacts, REJECT as canonical `btc-mode-v1` calibration evidence.

## EXP-001 — Corrected canonical-style hourly intraday replay

- **Status:** PASS AS RESEARCH / NO MODE FREEZE
- **Question:** Do current Direction, BTC Core and Market Regime outputs discriminate short-horizon BTC direction/path when reproduced from repository rules?
- **Dataset:** 2026-08-02 00:10 through 2026-08-31 23:10 America/New_York.
- **Coverage:** 720 hourly snapshots.
- **Outcomes:** +15m/+30m/+1h/+2h/+4h/+6h with MAE/MFE/realized-volatility/time-to-excursion.
- **Result:** Current BTC Core direction does not preserve robust directional ordering across chronological market-state changes. Market Regime adds useful conflict/context information but cannot be mapped one-to-one to BTC Mode.
- **Decision:** REJECT direct `Bullish -> Risk-On`, `Bearish -> Risk-Off` mapping.

## EXP-002 — Transition as directional conflict

- **Status:** PROMISING CONTEXT RULE
- **Question:** Does Market Regime Transition identify periods where following BTC Core direction should be downgraded?
- **Result:** In the observed sample, following Core direction for the first 0.3% move within +1h was below chance in Transition in both calibration and later periods (~39% and ~35%).
- **Decision:** KEEP as a candidate abstention/downgrade rule. Do not yet freeze as universal production behavior.

## EXP-003 — Early versus mature Expansion

- **Status:** PROMISING / NEEDS FORWARD VALIDATION
- **Question:** Does full multi-timeframe agreement improve intraday continuation during Expansion?
- **Result:** Expansion while 1D bias remained Neutral showed materially stronger continuation in the observed calibration period (~61% first-0.3% +1h directional hit, ~+0.22% mean +1h return). Once 1D bias also became Bullish, continuation weakened materially in both calibration and later-period observations.
- **Decision:** Existing multi-timeframe Confidence is not a calibrated intraday win probability. Treat this as a context/lag hypothesis requiring forward validation.

## EXP-004 — Movement-intensity discrimination

- **Status:** PASS / USEFUL
- **Question:** Are current BTC-native inputs more useful for predicting movement magnitude than direction?
- **Result:** YES in this sample. ATR 1H and several structural/context variables showed materially more stable relationship with future realized volatility than with return sign. BTC Core score also retained positive relationship with future realized volatility across calibration and later period.
- **Decision:** KEEP. Build an internal Opportunity/Activity research layer separate from Directional Stance.

## EXP-005 — Exploratory 15-minute tactical layer

- **Status:** PASS FOR OPPORTUNITY / FAIL FOR DIRECTION
- **Question:** Can existing public 5-minute BTC/derivatives data justify faster active-trader intelligence without adding a new provider?
- **Coverage:** 2,880 15-minute research snapshots using 5-minute BTC returns/range, taker-buy imbalance, volume activity, OI changes, long/short/taker context plus slower Core/Regime context.
- **Directional result:** Weak. Regularized holdout AUC for return sign was ~0.53 at +15m, ~0.53 at +30m, and ~0.47 at +1h. First-excursion direction was also near chance. Shallow nonlinear models did not materially improve generalization.
- **Movement result:** Promising. Holdout AUC for detecting a >=0.3% excursion in either direction was ~0.75 within 30m, ~0.79 within 1h, and ~0.84 within 2h.
- **Explainable check:** Using calibration quartiles, the top quartile of trailing 60-minute range had a >=0.3% following-hour move ~77% in calibration and ~86% later, versus ~13% and ~19% in the bottom quartile.
- **Decision:** KEEP tactical layer for Opportunity/Activity research. REJECT using it as a standalone high-frequency directional signal.

## EXP-006 — One weighted mega-score

- **Status:** FAIL / REJECTED FOR V1 RESEARCH
- **Question:** Should Direction, Regime, derivatives and Bond be collapsed immediately into fixed weights for BTC Mode?
- **Result:** NOT SUPPORTED. Directional relationships are context-dependent and movement probability is much more robust than direction.
- **Decision:** REJECT. Continue with two-stage Opportunity/Activity + selective Directional Stance architecture, then test Bond by horizon.

## Next planned sequence

### EXP-007 — Selective Directional Stance

- **Status:** PLANNED
- **Question:** Can simple deterministic context rules produce directional edge only when evidence is strong, while abstaining elsewhere?
- **Starting hypotheses:** Transition downgrade; early-vs-mature Expansion; price/OI/taker-flow divergence; material state changes.

### EXP-008 — Bond by horizon

- **Status:** PLANNED AFTER EXP-007
- **Question:** Does Bond Intelligence add incremental information at multi-hour horizons without being forced into 15-minute decisions?

### EXP-009 — BTC Mode three-state sanity

- **Status:** PLANNED AFTER SELECTIVE DIRECTION IS DEFENSIBLE
- **Question:** Does the final deterministic rule produce useful Risk-On / Wait & See / Risk-Off separation with sensible abstention frequency and stable path-risk outcomes?

No experiment may silently promote an input into production. Production methodology is frozen separately in `docs/intelligence/BTC_MODE_V1.md` only after explicit acceptance.
