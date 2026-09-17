from market_selector.execution_boundary import reconcile_account
from market_selector.hyperliquid_adapter import (
    HyperliquidAccountAdapter,
    HyperliquidOrderAdapter,
)


class FakeInfo:
    def __init__(self):
        self.state = {
            "assetPositions": [
                {"position": {"coin": "BTC", "szi": "0.002"}},
                {"position": {"coin": "ETH", "szi": "0"}},
            ],
            "marginSummary": {"accountValue": "101.25"},
            "withdrawable": "88.75",
        }
        self.orders = [
            {"coin": "BTC", "oid": 123, "side": "B", "sz": "0.001"},
        ]
        self.fills = [
            {
                "time": 1_000,
                "px": "100",
                "sz": "1",
                "closedPnl": "0",
                "fee": "0.05",
            },
            {
                "time": 2_000,
                "px": "101",
                "sz": "1",
                "closedPnl": "1.0",
                "fee": "0.05",
            },
        ]

    def user_state(self, address):
        return self.state

    def open_orders(self, address):
        return self.orders

    def user_fills_by_time(self, address, start_time, end_time=None, aggregate_by_time=False):
        return [
            row for row in self.fills
            if row["time"] >= start_time and (end_time is None or row["time"] <= end_time)
        ]

    def query_order_by_oid(self, user, oid):
        return {"status": "order", "order": {"order": {"oid": oid}}}


class FakeExchange:
    def __init__(self):
        self.market_open_calls = []
        self.market_close_calls = []
        self.order_calls = []
        self.cancel_calls = []

    def order(self, coin, is_buy, sz, limit_px, order_type, reduce_only=False, **kwargs):
        self.order_calls.append((coin, is_buy, sz, limit_px, order_type, reduce_only))
        return {"status": "ok", "response": {"data": {"statuses": [{"resting": {"oid": 7}}]}}}

    def market_open(self, coin, is_buy, sz, px=None, slippage=0.05, **kwargs):
        self.market_open_calls.append((coin, is_buy, sz, px, slippage))
        return {
            "status": "ok",
            "response": {
                "data": {
                    "statuses": [
                        {"filled": {"oid": 8, "totalSz": str(sz), "avgPx": "100"}}
                    ]
                }
            },
        }

    def cancel(self, coin, oid):
        self.cancel_calls.append((coin, oid))
        return {"status": "ok"}

    def market_close(self, coin, sz=None, px=None, slippage=0.05, **kwargs):
        self.market_close_calls.append((coin, sz, px, slippage))
        return {
            "status": "ok",
            "response": {
                "data": {
                    "statuses": [
                        {"filled": {"oid": 9, "totalSz": str(sz or 1), "avgPx": "99"}}
                    ]
                }
            },
        }


def test_account_snapshot_normalizes_positions_orders_and_balances():
    adapter = HyperliquidAccountAdapter(FakeInfo(), account_address="0xabc")
    snap = adapter.account_snapshot()

    assert snap.positions == {"BTC": 0.002}
    assert snap.open_order_ids == frozenset({"123"})
    assert snap.account_value_usd == 101.25
    assert snap.withdrawable_usd == 88.75


def test_reconciliation_snapshot_fails_on_unexpected_exchange_state():
    adapter = HyperliquidAccountAdapter(FakeInfo(), account_address="0xabc")
    raw = adapter.reconciliation_snapshot(
        expected_positions={},
        expected_open_order_ids=frozenset(),
    )
    result = reconcile_account(raw)

    assert result.ok is False
    assert any(reason.startswith("position_mismatch:BTC") for reason in result.reasons)
    assert any(reason == "unexpected_orders:123" for reason in result.reasons)


def test_fill_economics_counts_actual_notional_and_fee_net_pnl():
    adapter = HyperliquidAccountAdapter(FakeInfo(), account_address="0xabc")
    econ = adapter.fill_economics(start_time_ms=900, end_time_ms=2_100)

    assert econ.fill_count == 2
    assert econ.notional_volume_usd == 201.0
    assert econ.closed_pnl_gross_usd == 1.0
    assert econ.fees_usd == 0.10
    assert round(econ.realized_pnl_net_usd, 8) == 0.90


def test_ioc_market_open_uses_official_helper_and_parses_fill():
    exchange = FakeExchange()
    adapter = HyperliquidOrderAdapter(exchange)
    result = adapter.market_open(
        coin="PONS",
        is_buy=False,
        size=12.5,
        slippage=0.002,
    )

    assert result.accepted is True
    assert result.filled_oid == "8"
    assert result.filled_size == 12.5
    assert result.average_price == 100.0
    assert exchange.market_open_calls == [("PONS", False, 12.5, None, 0.002)]


def test_flatten_market_passes_bounded_slippage():
    exchange = FakeExchange()
    adapter = HyperliquidOrderAdapter(exchange)
    result = adapter.flatten_market(coin="PONS", size=12.5, slippage=0.003)

    assert result.accepted is True
    assert result.filled_oid == "9"
    assert exchange.market_close_calls == [("PONS", 12.5, None, 0.003)]
