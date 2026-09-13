from .models import EligibilityResult, RankedMarket, Verdict


def rank_markets(results: list[EligibilityResult]) -> list[RankedMarket]:
    """Rank eligible markets without ever promoting a failed market.

    Only QUALIFIED markets are switch candidates. WATCH/DEGRADED/REJECT remain
    visible to the leaderboard but cannot become ACTIVE.
    """

    verdict_priority = {
        Verdict.QUALIFIED: 0,
        Verdict.WATCH: 1,
        Verdict.DEGRADED: 2,
        Verdict.WARMUP: 3,
        Verdict.UNKNOWN: 4,
        Verdict.REJECT: 5,
    }

    ordered = sorted(
        results,
        key=lambda x: (
            verdict_priority[x.verdict],
            -x.score,
            x.coin,
        ),
    )

    return [
        RankedMarket(
            coin=item.coin,
            rank=index + 1,
            score=item.score,
            verdict=item.verdict,
            reasons=list(item.reasons),
        )
        for index, item in enumerate(ordered)
    ]


def best_qualified(ranking: list[RankedMarket]):
    for market in ranking:
        if market.verdict == Verdict.QUALIFIED:
            return market
    return None
