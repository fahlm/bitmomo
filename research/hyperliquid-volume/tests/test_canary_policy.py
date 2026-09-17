from market_selector.canary_policy import (
    CanaryIntent,
    CanaryPolicyConfig,
    MacroPrior,
    aligned_direction,
    evaluate_short_only_entry,
)


def _state(now_ms=1_000_000):
    return {
        "updated_at_ms": now_ms - 1_000,
        "mode": "SHADOW_ONLY",
        "mainnet_order_submission": False,
        "transport": {"healthy": True},
        "decision": {
            "allow_new_entries": True,
            "active_market": "PONS",
        },
        "ranking": [
            {"coin": "PONS", "verdict": "QUALIFIED", "rank": 1, "score": 90.0}
        ],
        "metrics": {
            "PONS": {
                "book_imbalance": -0.20,
                "microprice_edge_bps": -1.5,
                "aggressor_flow": -0.25,
                "data_stale": False,
            }
        },
    }


def test_aligned_direction_matches_frozen_3of3_definition():
    assert aligned_direction({
        "book_imbalance": -0.10,
        "microprice_edge_bps": -0.2,
        "aggressor_flow": -0.07,
    }) == -1
    assert aligned_direction({
        "book_imbalance": 0.10,
        "microprice_edge_bps": 0.2,
        "aggressor_flow": 0.07,
    }) == 1
    assert aligned_direction({
        "book_imbalance": -0.10,
        "microprice_edge_bps": 0.2,
        "aggressor_flow": -0.07,
    }) == 0


def test_bearish_macro_plus_bearish_micro_allows_short():
    state = _state()
    signal = evaluate_short_only_entry(state, now_ms=1_000_000)
    assert signal.intent == CanaryIntent.ENTER_SHORT
    assert signal.coin == "PONS"
    assert signal.direction == -1


def test_bullish_micro_never_opens_long_in_short_only_canary():
    state = _state()
    state["metrics"]["PONS"].update({
        "book_imbalance": 0.20,
        "microprice_edge_bps": 1.5,
        "aggressor_flow": 0.25,
    })
    signal = evaluate_short_only_entry(state, now_ms=1_000_000)
    assert signal.intent == CanaryIntent.IDLE
    assert signal.direction == 1


def test_unqualified_active_market_is_blocked():
    state = _state()
    state["ranking"][0]["verdict"] = "WATCH"
    signal = evaluate_short_only_entry(state, now_ms=1_000_000)
    assert signal.intent == CanaryIntent.IDLE
    assert signal.reason == "active_market_not_qualified"


def test_stale_or_unhealthy_scanner_halts():
    state = _state()
    signal = evaluate_short_only_entry(state, now_ms=1_200_000)
    assert signal.intent == CanaryIntent.HALT
    assert signal.reason == "scanner_state_stale"

    state = _state()
    state["transport"]["healthy"] = False
    signal = evaluate_short_only_entry(state, now_ms=1_000_000)
    assert signal.intent == CanaryIntent.HALT
    assert signal.reason == "transport_unhealthy"


def test_macro_prior_cannot_be_overridden_by_micro_signal():
    state = _state()
    cfg = CanaryPolicyConfig(macro_prior=MacroPrior.NEUTRAL)
    signal = evaluate_short_only_entry(state, now_ms=1_000_000, cfg=cfg)
    assert signal.intent == CanaryIntent.IDLE
    assert signal.reason == "macro_prior_not_bearish"


def test_supervisor_must_explicitly_allow_entry():
    state = _state()
    state["decision"]["allow_new_entries"] = False
    signal = evaluate_short_only_entry(state, now_ms=1_000_000)
    assert signal.intent == CanaryIntent.IDLE
    assert signal.reason == "supervisor_blocks_entry"
