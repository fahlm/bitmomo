# Dual-window selector behavior

Current research defaults:

- market-state horizon: 15 minutes
- execution-evidence horizon: 4 hours
- execution sample cap: latest 100 attempts
- execution freshness requirement: latest attempt <= 30 minutes
- minimum execution samples: 30

The intent is to keep spread/queue/flow responsive to current conditions while retaining enough empirical execution evidence to estimate fill rate, maker ratio, markout, P10K and T10K. Stale execution evidence cannot qualify a market even when older samples remain inside the 4-hour evidence window.
