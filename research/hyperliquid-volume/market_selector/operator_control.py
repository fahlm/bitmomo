from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path


@dataclass(frozen=True)
class OperatorControlState:
    execution_enabled: bool
    killed: bool
    reason: str


class OperatorControl:
    """External, file-backed operator gate that defaults to execution OFF.

    The enable file must contain the exact configured token. A kill file always
    overrides enablement. This module owns no wallet or exchange connection and is
    safe to inspect/change independently of the trading process.
    """

    DEFAULT_ENABLE_TOKEN = "ENABLE_BITMOMO_CANARY_V0"

    def __init__(
        self,
        *,
        enable_path: str | Path,
        kill_path: str | Path,
        enable_token: str = DEFAULT_ENABLE_TOKEN,
    ):
        if not enable_token.strip():
            raise ValueError("enable_token must be non-empty")
        self.enable_path = Path(enable_path)
        self.kill_path = Path(kill_path)
        self.enable_token = enable_token

    def read(self) -> OperatorControlState:
        if self.kill_path.exists():
            return OperatorControlState(False, True, "kill_switch_present")

        if not self.enable_path.exists():
            return OperatorControlState(False, False, "enable_file_missing")

        try:
            value = self.enable_path.read_text(encoding="utf-8").strip()
        except OSError:
            return OperatorControlState(False, False, "enable_file_unreadable")

        if value != self.enable_token:
            return OperatorControlState(False, False, "enable_token_mismatch")

        return OperatorControlState(True, False, "explicitly_enabled")
