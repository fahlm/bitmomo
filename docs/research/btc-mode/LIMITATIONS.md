# BTC Mode Research — Limitations

Status: ACTIVE

This file tracks limitations that may affect interpretation, reproducibility, or production suitability.

## Known limitations before research begins

### Historical derivatives coverage

Funding, premium/basis, open interest, global long/short ratio, and taker buy/sell history may not share the same historical depth. Research must report actual coverage and must not silently treat unavailable fields as neutral observations.

### Treasury knowledge-time precision

Historical Treasury observations may be defensible at daily resolution while exact intraday publication/availability time is unknown for backfilled observations. Such rows must not be used to fabricate intraday point-in-time macro context.

### Experimental Yahoo-derived sources

MOVE, DXY, and Nasdaq data in Bond Intelligence are currently experimental. They may be analyzed separately, but V1 should not become operationally dependent on them without stronger reliability evidence.

### Regime and engine versioning

Historical replay must use documented engine/classifier rules. If research spans multiple production-rule versions, results must be separated or explicitly normalized; incompatible versions must not be silently pooled.

### Regime frequency imbalance

Some Market Regime states may be rare. Conditional results with small samples must be labeled as such and should not drive strong production rules.

### Market-cycle dependence

A relationship that works in one BTC or macro cycle may fail in another. Walk-forward results and period-level breakdowns are required before treating a relationship as durable.

### Outcome overlap

6h/24h/72h/7d forward windows overlap for closely spaced observations. Statistical interpretation must account for this dependence rather than treating every row as fully independent evidence.

### Classification vs prediction

BTC Mode is intended to classify the current BTC risk environment. It must not be evaluated or marketed solely as a binary next-price-direction prediction.

## Rule for new limitations

Append newly discovered limitations here as research proceeds. Do not erase a limitation merely because a workaround is later implemented; document the mitigation and the version/date instead.
