from dataclasses import dataclass, field
from enum import Enum
from typing import Dict, Optional


class Verdict(str, Enum):
    WARMUP = "WARMUP"
    QUALIFIED = "QUALIFIED"
    WATCH = "WATCH"
    DEGRADED = "DEGRADED"
    REJECT = "REJECT"
    UNKNOWN = "UNKNOWN"


class SupervisorState(str, Enum):
    WARMUP = "WARMUP"
    IDLE = "IDLE"
    ACTIVE = "ACTIVE"
    STOP_NEW_ENTRY = "STOP_NEW_ENTRY"
    DRAIN = "DRAIN"
    SWITCH_PENDING = "SWITCH_PENDING"
    HALT = "HALT"


@dataclass
class RollingMetrics:
    coin: str
    timestamp_ms: int

    observation_seconds: float = 0.0
    book_observations: int = 0
    trade_observations: int = 0
    book_age_seconds: float = float("inf")
    trade_age_seconds: float = float("inf")

    day_volume_usd: Optional[float] = None
    spread_median_bps: Optional[float] = None
    spread_p90_bps: Optional[float] = None
    bbo_queue_usd: Optional[float] = None
    bbo_queue_multiple: Optional[float] = None
    trade_rate_per_minute: Optional[float] = None

    book_imbalance: Optional[float] = None
    microprice_edge_bps: Optional[float] = None
    aggressor_flow: Optional[float] = None

    execution_samples: int = 0
    fill_rate: Optional[float] = None
    maker_ratio: Optional[float] = None
    markout_5s_bps: Optional[float] = None
    volume_per_hour_usd: Optional[float] = None
    t10k_hours: Optional[float] = None
    p10k_usd: Optional[float] = None

    data_stale: bool = False
    hard_fault: Optional[str] = None


@dataclass
class EligibilityResult:
    coin: str
    verdict: Verdict
    score: float
    reasons: list[str] = field(default_factory=list)
    hard_fail: bool = False
    metrics: Optional[RollingMetrics] = None


@dataclass
class RankedMarket:
    coin: str
    rank: int
    score: float
    verdict: Verdict
    reasons: list[str]


@dataclass
class SupervisorDecision:
    state: SupervisorState
    active_market: Optional[str]
    candidate_market: Optional[str]
    allow_new_entries: bool
    action: str
    reason: str
    ranking: list[RankedMarket] = field(default_factory=list)


@dataclass
class MarketHistory:
    qualify_streak: int = 0
    degrade_streak: int = 0
    challenger_streaks: Dict[str, int] = field(default_factory=dict)
