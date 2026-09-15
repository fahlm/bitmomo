from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Protocol

from .execution_boundary import ReconciliationSnapshot


@dataclass(frozen=True)
class FillEconomics:
    fill_count: int
    notional_volume_usd: float
    closed_pnl_gross_usd: float
    fees_usd: float
    realized_pnl_net_usd: float


@dataclass(frozen=True)
class ExchangeAccountSnapshot:
    positions: dict[str, float]
    open_order_ids: frozenset[str]
    account_value_usd: float | None
    withdrawable_usd: float | None


@dataclass(frozen=True)
class OrderSubmitResult:
    accepted: bool
    resting_oid: str | None = None
    filled_oid: str | None = None
    filled_size: float | None = None
    average_price: float | None = None
    error: str | None = None


class HyperliquidInfoLike(Protocol):
    def user_state(self, address: str) -> Any: ...
    def open_orders(self, address: str) -> Any: ...
    def user_fills_by_time(
        self,
        address: str,
        start_time: int,
        end_time: int | None = None,
        aggregate_by_time: bool | None = False,
    ) -> Any: ...
    def query_order_by_oid(self, user: str, oid: int) -> Any: ...


class HyperliquidExchangeLike(Protocol):
    def order(
        self,
        coin: str,
        is_buy: bool,
        sz: float,
        limit_px: float,
        order_type: dict,
        reduce_only: bool = False,
        **kwargs,
    ) -> Any: ...

    def cancel(self, coin: str, oid: int) -> Any: ...
    def market_close(self, coin: str, sz: float | None = None, **kwargs) -> Any: ...


class HyperliquidAccountAdapter:
    """Read-only normalization around the official Hyperliquid Info client.

    No key is accepted or stored here. The caller supplies an already-created Info
    object and the public account address. This keeps account reconciliation usable
    without granting order authority.
    """

    def __init__(self, info: HyperliquidInfoLike, *, account_address: str):
        if not account_address.strip():
            raise ValueError("account_address must be non-empty")
        self.info = info
        self.account_address = account_address

    def account_snapshot(self) -> ExchangeAccountSnapshot:
        raw_state = self.info.user_state(self.account_address)
        positions: dict[str, float] = {}
        for item in raw_state.get("assetPositions", []):
            position = item.get("position") or {}
            coin = str(position.get("coin", "")).strip()
            if not coin:
                continue
            size = float(position.get("szi", 0.0) or 0.0)
            if size != 0.0:
                positions[coin] = size

        raw_orders = self.info.open_orders(self.account_address)
        order_ids = frozenset(str(row["oid"]) for row in raw_orders if row.get("oid") is not None)

        margin = raw_state.get("marginSummary") or {}
        account_value = margin.get("accountValue")
        withdrawable = raw_state.get("withdrawable")
        return ExchangeAccountSnapshot(
            positions=positions,
            open_order_ids=order_ids,
            account_value_usd=None if account_value is None else float(account_value),
            withdrawable_usd=None if withdrawable is None else float(withdrawable),
        )

    def reconciliation_snapshot(
        self,
        *,
        expected_positions: dict[str, float],
        expected_open_order_ids: frozenset[str],
    ) -> ReconciliationSnapshot:
        actual = self.account_snapshot()
        return ReconciliationSnapshot(
            expected_positions=dict(expected_positions),
            actual_positions=actual.positions,
            expected_open_order_ids=expected_open_order_ids,
            actual_open_order_ids=actual.open_order_ids,
        )

    def fill_economics(self, *, start_time_ms: int, end_time_ms: int | None = None) -> FillEconomics:
        if start_time_ms <= 0:
            raise ValueError("start_time_ms must be positive")
        rows = self.info.user_fills_by_time(
            self.account_address,
            start_time_ms,
            end_time_ms,
            False,
        )
        notional = 0.0
        gross = 0.0
        fees = 0.0
        count = 0
        for row in rows:
            px = float(row.get("px", 0.0) or 0.0)
            sz = abs(float(row.get("sz", 0.0) or 0.0))
            fee = float(row.get("fee", 0.0) or 0.0)
            closed = float(row.get("closedPnl", 0.0) or 0.0)
            if px <= 0.0 or sz <= 0.0:
                continue
            notional += px * sz
            gross += closed
            fees += fee
            count += 1

        # Hyperliquid fill records expose closedPnl and fee separately. Mission
        # accounting uses fee-net realized trading PnL across *all* fills in the
        # mission window, so entry fees are included even when closedPnl is zero.
        return FillEconomics(
            fill_count=count,
            notional_volume_usd=notional,
            closed_pnl_gross_usd=gross,
            fees_usd=fees,
            realized_pnl_net_usd=gross - fees,
        )

    def order_status(self, oid: str) -> Any:
        return self.info.query_order_by_oid(self.account_address, int(oid))


class HyperliquidOrderAdapter:
    """Thin official-SDK order wrapper; policy/risk gates live above this layer.

    The adapter intentionally does not read environment variables, load a private
    key, or construct an Exchange signer. The live process must inject an already-
    initialized Exchange only after operator/risk controls have been established.
    """

    def __init__(self, exchange: HyperliquidExchangeLike):
        self.exchange = exchange

    @staticmethod
    def _parse_submit_response(raw: Any) -> OrderSubmitResult:
        if not isinstance(raw, dict) or raw.get("status") != "ok":
            return OrderSubmitResult(False, error="exchange_response_not_ok")
        try:
            statuses = raw["response"]["data"]["statuses"]
            status = statuses[0]
        except (KeyError, IndexError, TypeError):
            return OrderSubmitResult(False, error="malformed_exchange_response")

        if "resting" in status:
            return OrderSubmitResult(True, resting_oid=str(status["resting"]["oid"]))
        if "filled" in status:
            filled = status["filled"]
            return OrderSubmitResult(
                True,
                filled_oid=str(filled.get("oid")) if filled.get("oid") is not None else None,
                filled_size=float(filled.get("totalSz", 0.0) or 0.0),
                average_price=float(filled.get("avgPx", 0.0) or 0.0),
            )
        if "error" in status:
            return OrderSubmitResult(False, error=str(status["error"]))
        return OrderSubmitResult(False, error="unknown_exchange_status")

    def place_post_only(
        self,
        *,
        coin: str,
        is_buy: bool,
        size: float,
        limit_price: float,
        reduce_only: bool = False,
    ) -> OrderSubmitResult:
        if size <= 0 or limit_price <= 0:
            raise ValueError("size and limit_price must be positive")
        raw = self.exchange.order(
            coin,
            is_buy,
            size,
            limit_price,
            {"limit": {"tif": "Alo"}},
            reduce_only=reduce_only,
        )
        return self._parse_submit_response(raw)

    def cancel(self, *, coin: str, oid: str) -> bool:
        raw = self.exchange.cancel(coin, int(oid))
        return isinstance(raw, dict) and raw.get("status") == "ok"

    def flatten_market(self, *, coin: str, size: float | None = None) -> OrderSubmitResult:
        raw = self.exchange.market_close(coin, size)
        return self._parse_submit_response(raw)
