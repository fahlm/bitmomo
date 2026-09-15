from __future__ import annotations

import argparse
import json
import time
from dataclasses import asdict, dataclass
from pathlib import Path


@dataclass(frozen=True)
class RuntimeHealth:
    healthy: bool
    reasons: tuple[str, ...]
    state_age_seconds: float | None
    transport_healthy: bool
    mode: str | None
    mainnet_order_submission: bool | None
    universe_count: int

    def to_dict(self) -> dict:
        return asdict(self)


def check_runtime_state(
    path: str | Path,
    *,
    now_ms: int | None = None,
    max_state_age_seconds: float = 120.0,
) -> RuntimeHealth:
    if max_state_age_seconds <= 0:
        raise ValueError("max_state_age_seconds must be positive")
    target = Path(path)
    if not target.exists():
        return RuntimeHealth(
            False,
            ("state_missing",),
            None,
            False,
            None,
            None,
            0,
        )

    try:
        payload = json.loads(target.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return RuntimeHealth(
            False,
            ("state_unreadable",),
            None,
            False,
            None,
            None,
            0,
        )

    now_ms = int(time.time() * 1000) if now_ms is None else int(now_ms)
    updated_at = payload.get("updated_at_ms")
    age = None
    reasons: list[str] = []
    if not isinstance(updated_at, int):
        reasons.append("updated_at_missing")
    else:
        age = max(0.0, (now_ms - updated_at) / 1000.0)
        if age > max_state_age_seconds:
            reasons.append(f"state_stale:{age:.1f}s")

    mode = payload.get("mode")
    if mode != "SHADOW_ONLY":
        reasons.append(f"unexpected_mode:{mode}")

    mainnet = payload.get("mainnet_order_submission")
    if mainnet is not False:
        reasons.append("mainnet_submission_not_false")

    transport_healthy = (payload.get("transport") or {}).get("healthy") is True
    if not transport_healthy:
        reasons.append("transport_unhealthy")

    universe = payload.get("universe") or []
    universe_count = len(universe) if isinstance(universe, list) else 0
    if universe_count == 0:
        reasons.append("empty_universe")

    return RuntimeHealth(
        healthy=not reasons,
        reasons=tuple(reasons),
        state_age_seconds=age,
        transport_healthy=transport_healthy,
        mode=mode if isinstance(mode, str) else None,
        mainnet_order_submission=mainnet if isinstance(mainnet, bool) else None,
        universe_count=universe_count,
    )


def main() -> None:
    parser = argparse.ArgumentParser(description="Check autonomous shadow runtime health.")
    parser.add_argument(
        "state_json",
        nargs="?",
        default="data/runtime/autonomous_state.json",
    )
    parser.add_argument("--max-age", type=float, default=120.0)
    args = parser.parse_args()

    result = check_runtime_state(args.state_json, max_state_age_seconds=args.max_age)
    print(json.dumps(result.to_dict(), indent=2, sort_keys=True))
    raise SystemExit(0 if result.healthy else 2)


if __name__ == "__main__":
    main()
