# BTC Mode Research — Limitations

Status: ACTIVE

This file tracks limitations that may affect interpretation, reproducibility, or production suitability.

## Known limitations before research begins

### Short calibration window

V1 intentionally prioritizes the latest defensible 30 days of synchronized full-feature data because the product is short-horizon and the live engine depends on derivatives inputs with limited historical depth.

This creates a material sample-size limitation. Approximately 60 production-aligned session observations and ~180 four-hour robustness observations are expected before exclusions. Results must not be marketed as universal multi-cycle evidence.

Mitigation: use simple conditional/confirmation rules rather than fitted precise weights; report `n` for every conditional finding; begin append-only twice-daily forward validation immediately after launch.

### Outcome dependence / overlap

+6h/+12h/+24h/+72h windows overlap for closely spaced observations, especially in the 4-hour robustness sample. Rows are therefore not independent trials.

Mitigation: do not use naive significance claims based on row count alone; compare production-aligned and robustness samples; emphasize effect consistency and path-risk distributions.

### Historical derivatives coverage

Funding, premium/basis, open interest, global long/short ratio, and taker buy/sell history may not share identical availability and continuity even within the 30-day window. Research must report actual coverage and must not silently treat unavailable fields as neutral observations.

### Treasury knowledge-time precision

Historical Treasury observations may be defensible at daily resolution while exact intraday publication/availability time is unknown for some backfilled observations. Such rows must not be used to fabricate intraday point-in-time macro context.

### Experimental Yahoo-derived sources

MOVE, DXY, and Nasdaq data in Bond Intelligence are currently experimental. They may be analyzed separately, but V1 should not become operationally dependent on them without stronger reliability evidence.

### Regime and engine versioning

Historical replay must use documented engine/classifier rules. If research encounters rule-version differences, results must be separated or explicitly normalized; incompatible versions must not be silently pooled.

### Regime/state frequency imbalance

Some Market Regime, direction, or macro states may be rare within a 30-day window. Conditional results with very small `n` must be labeled exploratory and must not drive strong production rules.

### Market-cycle dependence

A relationship seen in one volatile month can fail in another environment. The 30-day V1 calibration is a launch calibration, not proof of cross-cycle durability.

Mitigation: freeze V1 conservatively and evaluate every future production observation append-only. Future methodology changes require a new version.

### Research robustness cadence differs from product cadence

The 4-hour research sample exists only to test whether relationships persist beyond the two official publication anchors. It is not a production schedule and must never be presented as six Bitmomo calls per day.

### Classification vs prediction

BTC Mode is intended to classify the current BTC risk environment for short-horizon decision support. It must not be evaluated or marketed solely as a binary next-price-direction prediction.

## Rule for new limitations

Append newly discovered limitations here as research proceeds. Do not erase a limitation merely because a workaround is later implemented; document the mitigation and the version/date instead.
