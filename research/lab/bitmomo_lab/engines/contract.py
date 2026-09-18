"""Engine plug-in contract and the strict / production-parity split.

No engine is implemented in Wave 1 M0. This module fixes the boundary that
later ports (``opportunity-v1-py`` in M2; ``signal-engine-v1-py``,
``regime-v1-py`` in Wave 1.5) must plug into:

* Engines only ever see a ``Snapshot``: PIT views of their inputs at cutoff T,
  built exclusively through ``PITFrame.as_of`` and re-checked by ``assert_pit``.
* ``Mode.STRICT`` (research truth): a missing input stays missing. Asking for a
  fallback value in strict mode raises.
* ``Mode.PRODUCTION_PARITY``: an engine may reproduce legacy PHP behavior such
  as ``?? 0``, but only through ``InputResolver.parity_fill``, which records an
  ``ImputationFlag`` for every substitution.
* Parity outputs carry parity-only columns and are refused by the canonical
  store writer (``store.parquet.guard_strict``), so compatibility semantics can
  never contaminate stored research data.
"""

from __future__ import annotations

import abc
import dataclasses
import datetime as dt
import enum
from typing import Any, Mapping

import pyarrow as pa

from bitmomo_lab.engines.registry import EngineSpec
from bitmomo_lab.store.pit import PITFrame, assert_pit


class Mode(str, enum.Enum):
    STRICT = "strict"
    PRODUCTION_PARITY = "production_parity"


class StrictModeViolation(RuntimeError):
    """A compatibility fill was requested while running in strict mode."""


@dataclasses.dataclass(frozen=True)
class ImputationFlag:
    field: str
    original_value: Any
    effective_value: Any
    rule: str  # e.g. "php: (float) ($axis['funding_rate'] ?? 0)"
    production_compatibility_fill: bool = True


@dataclasses.dataclass(frozen=True)
class MissingInput:
    field: str
    reason: str


class InputResolver:
    """The only sanctioned way for an engine to handle a missing input."""

    def __init__(self, mode: Mode) -> None:
        self.mode = mode
        self.flags: list[ImputationFlag] = []
        self.missing: list[MissingInput] = []

    def value(self, field: str, value: Any, reason: str = "not available at cutoff") -> Any:
        """Return the value; record it as missing when it is None. Never fills."""
        if value is None:
            self.missing.append(MissingInput(field, reason))
        return value

    def parity_fill(self, field: str, value: Any, legacy_default: Any, rule: str) -> Any:
        """Reproduce a legacy production fallback, with provenance. Parity mode only."""
        if value is not None:
            return value
        if self.mode is not Mode.PRODUCTION_PARITY:
            raise StrictModeViolation(f"refusing to fill {field!r} in strict mode ({rule})")
        self.flags.append(ImputationFlag(field, None, legacy_default, rule))
        self.missing.append(MissingInput(field, "filled for production parity"))
        return legacy_default


@dataclasses.dataclass(frozen=True)
class Snapshot:
    """Inputs an engine may consume at one decision cutoff."""

    cutoff: dt.datetime
    inputs: Mapping[str, pa.Table]

    @classmethod
    def build(cls, cutoff: dt.datetime, sources: Mapping[str, PITFrame]) -> "Snapshot":
        tables = {name: frame.as_of(cutoff) for name, frame in sources.items()}
        for table in tables.values():
            assert_pit(table, cutoff)
        return cls(cutoff, tables)


@dataclasses.dataclass(frozen=True)
class EngineResult:
    engine: str
    engine_version: str
    mode: Mode
    cutoff: dt.datetime
    output: Mapping[str, Any]
    missing_inputs: tuple[MissingInput, ...]
    imputation_flags: tuple[ImputationFlag, ...]

    def __post_init__(self) -> None:
        if self.mode is Mode.STRICT and self.imputation_flags:
            raise StrictModeViolation("strict results cannot carry imputation flags")


class Engine(abc.ABC):
    """An engine sees only a Snapshot (PIT views at the cutoff) and a resolver."""

    spec: EngineSpec

    @abc.abstractmethod
    def compute(self, snapshot: Snapshot, resolver: InputResolver) -> Mapping[str, Any]: ...

    def run(self, snapshot: Snapshot, mode: Mode = Mode.STRICT) -> EngineResult:
        missing = [name for name in self.spec.required_inputs if name not in snapshot.inputs]
        if missing:
            raise KeyError(f"{self.spec.engine_id}: snapshot lacks required inputs {missing}")
        resolver = InputResolver(mode)
        output = self.compute(snapshot, resolver)
        return EngineResult(
            self.spec.engine_id, self.spec.engine_version, mode, snapshot.cutoff, output,
            tuple(resolver.missing), tuple(resolver.flags),
        )
