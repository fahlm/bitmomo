# BTC Mode Research — Results V1

Status: INTRADAY REPLAY EXECUTED — MODE FORMULA NOT YET FROZEN

This document is the canonical summary of empirical findings used to accept or reject the first production BTC Mode methodology.

## 2026-09-12 corrected intraday replay

The first P0.2B run centered on +6h/+12h/+24h/+72h outcomes and used non-canonical substitute semantics in parts of the replay. It remains preserved as an exploratory artifact, but it is superseded for BTC Mode calibration.

The corrected replay was rebuilt from the portable public-archive bundle and the current repository rules.

### Research window and samples

Window:

- 2026-08-02 00:10 through 2026-08-31 23:10 `America/New_York`
- BTC remains a 24/7 market; weekends are included

Primary canonical-style sample:

- 720 hourly PIT-safe feature snapshots
- forward outcomes: +15m, +30m, +1h, +2h, +4h, +6h
- MAE, MFE, realized volatility, time-to-MAE, time-to-MFE, and first-excursion ordering reconstructed from 5-minute BTC futures candles

Exploratory tactical sample:

- 2,880 snapshots at 15-minute spacing
- uses existing 5-minute public BTC price/volume/taker-flow and derivatives archive data to test whether a faster tactical layer is justified
- this does **not** imply current production already computes BTC Mode every 15 minutes

### Canonical parity improvements versus the first exploratory replay

The corrected replay uses repository behavior rather than the earlier substitutes:

- Direction axis uses the production 4H `+DI/-DI/ADX` score, not an average of 1H/4H/1D direction scores.
- 1H and 1D directional bias are used as production confirmation context rather than blended into the Direction axis.
- Structure uses the production 20-candle breakout / EMA20 / EMA50 state logic.
- BTC Core uses the production weights: Direction 35%, Carry 15%, Structure 30%, Crowding 20%.
- Production bias thresholds and direction-strength thresholds are preserved.
- Production confidence formula is reproduced.
- Market Regime momentum input uses the canonical BTC Direction score.
- Market Regime V1 scoring, transition margin, confidence formula, and hysteresis thresholds are reproduced from the repository.

### Remaining replay limitations

The replay is materially closer to production but is not claimed to be byte-for-byte historical production reconstruction:

- historical basis is reconstructed from hourly mark/index closes rather than an instantaneous historical premium-index snapshot
- daily volatility/volume percentiles have roughly April-August warm-up in the portable archive, shorter than the production code's maximum 252-day percentile lookback
- historical derivatives availability uses a documented conservative knowledge-time assumption; a zero-delay sensitivity check changed numeric Core scores often but changed the final Core bias on only 31/720 hourly rows and did not change the main conclusions
- liquidation pressure is unavailable
- Market Regime V1 receives `crowding_score = null`, matching the current production runtime adapter

These limitations must remain visible in any production-methodology decision.

## Coverage

Hourly replay:

- BTC Core rows: 720/720
- outcomes through +6h: 720/720
- Core bias distribution:
  - Bullish: 379
  - Neutral: 155
  - Bearish: 186
- Market Regime candidate distribution:
  - Accumulation: 443
  - Expansion: 162
  - Transition: 65
  - Distribution: 50
  - Capitulation: 0
- Carry score: 0 on all 720 rows under the reconstructed funding/basis data

The absence of Capitulation and the concentration of regimes mean this 30-day window cannot validate every future Risk-Off condition.

## Finding 1 — current BTC Core is not a robust intraday direction engine

The corrected short-horizon replay does not support treating the current BTC Core label as a direct Risk-On/Risk-Off directional mapping.

Directional ordering changes materially across time blocks. For example, mean +4h return while BTC Core was Bullish was approximately:

- first 5-day block: -0.09%
- second 5-day block: +0.01%
- strong mid-window trend block: +0.69%
- fifth block: -0.03%
- final block: -0.29%

A simple chronological calibration/holdout benchmark also failed to preserve directional discrimination. Regularized directional models based on Direction/BTC Core/Regime were near or below chance on several holdout first-excursion tests.

This means `Bullish -> Risk-On` and `Bearish -> Risk-Off` is **not empirically defensible** as a universal intraday production rule from this sample.

## Finding 2 — Market Regime is useful as context, but not as a one-to-one Mode alias

The replay supports the earlier product decision that Market State is related to, but not identical to, BTC Mode.

One especially important pattern is Transition:

- when Regime was Transition, following the current Core direction for the first 0.3% move within +1h had roughly 39% hit rate in calibration and 35% in the later period
- Transition therefore behaves more like a conflict/abstention context than a directional confirmation

Distribution is not automatically Risk-Off. In the observed late-window Distribution sample, forward returns were not uniformly negative and Core direction sometimes performed better than it did in Transition.

Therefore:

- Transition is a strong candidate for `Wait & See` / downgrade behavior
- Distribution must not be hard-mapped to Risk-Off
- Accumulation must not be hard-mapped to Risk-On

## Finding 3 — multi-timeframe agreement can be late for intraday decisions

A material finding concerns the current confidence-confirmation semantics.

During Expansion with 1D directional bias still Neutral, Core-following behavior was materially stronger in the observed calibration period:

- first-0.3% direction hit within +1h: ~61%
- mean +1h return: ~+0.22%
- mean +4h return: ~+0.92%

Once the 1D bias was also Bullish, continuation weakened materially:

Calibration subset:
- +1h mean return: ~-0.05%
- +4h mean return: ~-0.24%

Later-period subset:
- +1h mean return: ~-0.01%
- +4h mean return: ~+0.06%
- first-0.3% direction hit within +1h: ~41%

This does **not** prove that daily alignment is universally bearish. It does show that the current production confirmation bonus is evidence-agreement confidence, not a calibrated probability of profitable intraday continuation. BTC Mode must not present the existing Confidence percentage as win probability.

## Finding 4 — the current stack is much better at forecasting movement intensity than direction

The most robust signal in the corrected research is future movement / volatility, not future sign.

Examples of Spearman relationship with future realized volatility:

`ATR 1H`:
- +1h: calibration ~0.60, later period ~0.34
- +2h: calibration ~0.61, later period ~0.33
- +4h: calibration ~0.59, later period ~0.33

`BTC Core score`:
- +1h: calibration ~0.29, later period ~0.25
- +2h: calibration ~0.29, later period ~0.26
- +4h: calibration ~0.30, later period ~0.29

This is operationally valuable for active traders: the existing stack contains useful information about **whether the next period is likely to offer meaningful movement**, even when it cannot reliably determine the direction of that movement.

## Finding 5 — a 15-minute tactical layer is justified for opportunity detection, not yet for direction

An exploratory 15-minute research layer was constructed from data already present in the portable archive:

- 5-minute BTC return/range
- taker-buy imbalance
- volume activity
- short-horizon OI change
- long/short and taker-ratio context
- existing slower BTC Core / Regime context

Directional result:

- regularized tactical model holdout AUC for return sign:
  - +15m: ~0.53
  - +30m: ~0.53
  - +1h: ~0.47
- first-0.3% direction tests were also near chance
- shallow nonlinear models did not materially rescue directional generalization

Therefore the current data does **not** justify a high-frequency Buy/Sell-style directional engine.

Movement-probability result is very different:

Probability of at least a 0.3% excursion in either direction:

- within +30m: tactical holdout AUC ~0.75
- within +1h: tactical holdout AUC ~0.79
- within +2h: tactical holdout AUC ~0.84

A very simple explainable feature shows the same relationship. Using the calibration 75th percentile of the trailing 60-minute BTC range (~0.554%):

- top quartile: a >=0.3% move in the following hour occurred ~77% in calibration and ~86% in the later period
- bottom quartile (<= ~0.208% recent 60m range): the same move occurred ~13% in calibration and ~19% in the later period

This is a strong V1 candidate for an internal **Opportunity / Activity** dimension.

Daily holdout AUC for the tactical +1h / 0.3%-movement model varied, with median around 0.71. It is promising but still requires forward validation.

## Finding 6 — active-trader architecture should be two-stage, not one weighted mega-score

The evidence does not support a single fixed formula such as:

`BTC Core weight + Regime weight + Bond weight = Risk-On/Risk-Off`.

The next architecture to test is:

1. **Opportunity / Activity layer**
   - fast
   - answers whether a meaningful tradable move is likely soon
   - 5m/15m price-range, volatility and derivatives activity can contribute
2. **Directional Stance layer**
   - selective
   - answers which side, if any, has enough evidence
   - must be allowed to abstain
3. **Market Regime**
   - phase/context modifier
   - especially useful for conflict/downgrade behavior
4. **Bond / Macro**
   - test primarily at horizons where it is economically plausible (roughly multi-hour rather than forcing it into 15-minute decisions)
5. **BTC Mode**
   - Risk-On only when opportunity and directional evidence align
   - Risk-Off only when hostile/downside evidence is sufficiently aligned
   - otherwise Wait & See

This preserves one simple customer-facing verdict while avoiding false precision behind it.

## Product implication

`Wait & See` is not a fallback failure state. It is necessary to prevent the product from converting a movement-intensity signal into an unsupported directional call.

The research also strengthens the case for **state-change alerts** rather than repeated static labels.

The 08:10/20:10 session editions remain valuable as deep-context checkpoints. A faster tactical computation layer may run between them, with customer-facing updates only when a material state change occurs.

## What is not yet proven

The evidence is not sufficient to freeze `btc-mode-v1` because:

- a robust Risk-Off directional rule is not yet validated
- no Capitulation regime occurs in this 30-day sample
- the strongest directional relationships are context-dependent
- the tactical layer strongly predicts movement probability but not direction
- Bond incremental value has not yet been tested at the corrected horizons
- archival percentile warm-up and basis reconstruction are not perfect production parity

## Next research step

Do **not** spend Codex or Replit compute on another generic weighted backtest.

Next:

1. keep the corrected hourly canonical replay as structural context
2. keep the 15-minute tactical layer as the candidate Opportunity/Activity engine
3. research selective Directional Stance rules, starting with:
   - transition/conflict downgrade
   - early versus mature expansion
   - price/OI/taker-flow divergence and state changes
   - only then consider one additional microstructure source if existing public Binance data remains insufficient
4. evaluate Bond only at the horizons where short-horizon BTC-native evidence alone does not explain the path
5. freeze BTC Mode only after a deterministic abstaining rule separates Risk-On / Wait & See / Risk-Off without pretending every interval has directional edge

## Current conclusion

**No BTC Mode production formula is frozen yet.**

However, the corrected intraday research has produced a clearer architecture:

- current BTC Core / Regime = structural context
- short-horizon range/volatility/activity = promising opportunity detector
- directional call = unresolved and must remain selective
- `Wait & See` = first-class state
- Bond = horizon-specific candidate modifier, not automatically a universal backbone

The prior P0.2B results remain preserved for audit but are superseded for V1 calibration.
