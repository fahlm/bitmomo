from __future__ import annotations

from .config import SelectorConfig
from .models import (
    EligibilityResult,
    MarketHistory,
    RankedMarket,
    SupervisorDecision,
    SupervisorState,
    Verdict,
)
from .ranker import best_qualified, rank_markets


class MarketSupervisor:
    """Single-authority market supervisor with hysteresis.

    This class does not submit orders. It only decides whether new entries may be
    attempted and which market would be active once inventory is flat.
    """

    def __init__(self, cfg: SelectorConfig):
        self.cfg = cfg
        self.active_market: str | None = None
        self.pending_market: str | None = None
        self.histories: dict[str, MarketHistory] = {}
        self.state = SupervisorState.WARMUP

    def _history(self, coin: str) -> MarketHistory:
        if coin not in self.histories:
            self.histories[coin] = MarketHistory()
        return self.histories[coin]

    def _update_histories(self, results: list[EligibilityResult]) -> None:
        seen = {r.coin for r in results}
        for result in results:
            history = self._history(result.coin)
            if result.verdict == Verdict.QUALIFIED:
                history.qualify_streak += 1
                history.degrade_streak = 0
            elif result.verdict in {Verdict.DEGRADED, Verdict.REJECT, Verdict.UNKNOWN}:
                history.qualify_streak = 0
                history.degrade_streak += 1
            else:
                history.qualify_streak = 0
                history.degrade_streak = 0

        for coin, history in self.histories.items():
            if coin not in seen:
                history.qualify_streak = 0
                history.degrade_streak += 1

    def _stable_qualified(self, ranking: list[RankedMarket]) -> list[RankedMarket]:
        return [
            market
            for market in ranking
            if (
                market.verdict == Verdict.QUALIFIED
                and self._history(market.coin).qualify_streak >= self.cfg.qualification_windows
            )
        ]

    def decide(
        self,
        results: list[EligibilityResult],
        *,
        inventory_flat: bool,
        hard_fault: str | None = None,
    ) -> SupervisorDecision:
        self._update_histories(results)
        ranking = rank_markets(results)

        if hard_fault:
            self.state = SupervisorState.HALT
            return SupervisorDecision(
                state=self.state,
                active_market=self.active_market,
                candidate_market=None,
                allow_new_entries=False,
                action="HALT",
                reason=f"hard_fault:{hard_fault}",
                ranking=ranking,
            )

        result_by_coin = {result.coin: result for result in results}
        stable = self._stable_qualified(ranking)
        stable_best = stable[0] if stable else None

        if self.active_market is None:
            if stable_best is None:
                self.state = SupervisorState.IDLE
                return SupervisorDecision(
                    state=self.state,
                    active_market=None,
                    candidate_market=None,
                    allow_new_entries=False,
                    action="IDLE",
                    reason="no_stably_qualified_market",
                    ranking=ranking,
                )

            if not inventory_flat:
                self.state = SupervisorState.DRAIN
                return SupervisorDecision(
                    state=self.state,
                    active_market=None,
                    candidate_market=stable_best.coin,
                    allow_new_entries=False,
                    action="DRAIN",
                    reason="inventory_not_flat_before_activation",
                    ranking=ranking,
                )

            self.active_market = stable_best.coin
            self.state = SupervisorState.ACTIVE
            return SupervisorDecision(
                state=self.state,
                active_market=self.active_market,
                candidate_market=None,
                allow_new_entries=True,
                action="ACTIVATE",
                reason="stable_qualified_market_selected",
                ranking=ranking,
            )

        active_result = result_by_coin.get(self.active_market)
        active_history = self._history(self.active_market)

        active_bad = (
            active_result is None
            or active_result.hard_fail
            or active_result.verdict in {Verdict.REJECT, Verdict.UNKNOWN}
            or active_history.degrade_streak >= self.cfg.degradation_windows
        )

        if active_bad:
            self.state = SupervisorState.STOP_NEW_ENTRY
            self.pending_market = stable_best.coin if stable_best else None

            if not inventory_flat:
                return SupervisorDecision(
                    state=self.state,
                    active_market=self.active_market,
                    candidate_market=self.pending_market,
                    allow_new_entries=False,
                    action="STOP_NEW_ENTRY_AND_DRAIN",
                    reason="active_market_degraded",
                    ranking=ranking,
                )

            old = self.active_market
            self.active_market = None

            if self.pending_market:
                self.active_market = self.pending_market
                self.pending_market = None
                self.state = SupervisorState.ACTIVE
                return SupervisorDecision(
                    state=self.state,
                    active_market=self.active_market,
                    candidate_market=None,
                    allow_new_entries=True,
                    action="SWITCH",
                    reason=f"replaced_degraded_market:{old}",
                    ranking=ranking,
                )

            self.state = SupervisorState.IDLE
            return SupervisorDecision(
                state=self.state,
                active_market=None,
                candidate_market=None,
                allow_new_entries=False,
                action="IDLE",
                reason="active_market_degraded_no_replacement",
                ranking=ranking,
            )

        # Challenger hysteresis: do not rotate merely because rank #1 changed once.
        if stable_best and stable_best.coin != self.active_market and active_result:
            margin = stable_best.score - active_result.score
            history = self._history(self.active_market)

            if margin >= self.cfg.challenger_margin:
                count = history.challenger_streaks.get(stable_best.coin, 0) + 1
                history.challenger_streaks = {stable_best.coin: count}

                if count >= self.cfg.switch_confirmation_windows:
                    self.pending_market = stable_best.coin
                    if not inventory_flat:
                        self.state = SupervisorState.DRAIN
                        return SupervisorDecision(
                            state=self.state,
                            active_market=self.active_market,
                            candidate_market=self.pending_market,
                            allow_new_entries=False,
                            action="DRAIN_FOR_SWITCH",
                            reason="persistent_superior_challenger",
                            ranking=ranking,
                        )

                    old = self.active_market
                    self.active_market = self.pending_market
                    self.pending_market = None
                    self.state = SupervisorState.ACTIVE
                    return SupervisorDecision(
                        state=self.state,
                        active_market=self.active_market,
                        candidate_market=None,
                        allow_new_entries=True,
                        action="SWITCH",
                        reason=f"persistent_challenger_replaced:{old}",
                        ranking=ranking,
                    )
            else:
                history.challenger_streaks = {}

        self.state = SupervisorState.ACTIVE
        return SupervisorDecision(
            state=self.state,
            active_market=self.active_market,
            candidate_market=self.pending_market,
            allow_new_entries=True,
            action="HOLD_ACTIVE",
            reason="active_market_remains_eligible",
            ranking=ranking,
        )
