from dataclasses import dataclass


@dataclass(frozen=True)
class SelectorConfig:
    """Provisional research thresholds for market selection.

    These are intentionally not production-frozen. Market-state metrics and
    execution-evidence metrics use different horizons so short-lived spread/queue
    changes do not erase the larger sample needed to estimate execution economics.
    """

    target_order_notional: float = 100.0

    # Warm-up / data health
    warmup_seconds: int = 300
    max_book_age_seconds: float = 5.0
    max_trade_age_seconds: float = 15.0  # informational only; not a transport gate
    min_book_observations: int = 120
    min_trade_observations: int = 20

    # Market-state window (spread / queue / flow / trade activity).
    market_window_seconds: int = 900

    # Execution-evidence window. Keep enough evidence to estimate fill/maker/
    # markout/P10K/T10K while still requiring recent attempts.
    execution_window_seconds: int = 14_400
    execution_max_samples: int = 100
    max_execution_age_seconds: float = 1_800.0

    # Market-quality prerequisites
    min_day_volume_usd: float = 10_000_000.0
    min_spread_bps: float = 1.0
    max_spread_bps: float = 12.0
    max_bbo_queue_multiple: float = 20.0
    min_trade_rate_per_minute: float = 3.0

    # Empirical execution metrics. UNKNOWN until enough samples exist.
    min_execution_samples: int = 30
    min_fill_rate: float = 0.05
    min_maker_ratio: float = 0.75
    min_markout_5s_bps: float = 0.0
    max_t10k_hours: float = 6.0
    min_p10k_usd: float = -2.0

    # Supervisor hysteresis.
    qualification_windows: int = 3
    degradation_windows: int = 2
    switch_confirmation_windows: int = 3
    challenger_margin: float = 8.0


DEFAULT_CONFIG = SelectorConfig()
