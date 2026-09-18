"""Engine identity and a minimal registry.

Just enough structure for Opportunity V1 plus one later engine: an immutable
``EngineSpec`` (what the engine is, what it needs, what it emits) and a dict
keyed by ``engine_id``. No plugin discovery, no dynamic loading.
"""

from __future__ import annotations

import dataclasses
import hashlib
import json
from typing import Any, Mapping


@dataclasses.dataclass(frozen=True)
class EngineSpec:
    engine_id: str  # e.g. "opportunity-v1-py"
    engine_version: str  # version of this Python implementation
    methodology_version: str  # frozen methodology it implements, e.g. "opportunity-v1"
    required_inputs: tuple[str, ...]  # canonical dataset ids read through PITFrame
    required_features: Mapping[str, str]  # feature_id -> feature_version
    min_history: str  # human-readable minimum history, e.g. "4044 x 5m candles"
    output_schema: Mapping[str, str]  # field -> type description
    parameters: Mapping[str, Any]  # deterministic, frozen parameters
    source_ref: str  # the production code this ports, if any

    def identity(self) -> dict:
        return {
            "engine_id": self.engine_id,
            "engine_version": self.engine_version,
            "methodology_version": self.methodology_version,
            "parameters_sha256": parameters_sha256(self.parameters),
            "required_features": dict(sorted(self.required_features.items())),
        }


def parameters_sha256(parameters: Mapping[str, Any]) -> str:
    return hashlib.sha256(json.dumps(parameters, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


_REGISTRY: dict[str, Any] = {}


def register(engine_cls):
    spec: EngineSpec = engine_cls.spec
    if spec.engine_id in _REGISTRY and _REGISTRY[spec.engine_id] is not engine_cls:
        raise ValueError(f"engine id already registered: {spec.engine_id}")
    _REGISTRY[spec.engine_id] = engine_cls
    return engine_cls


def get(engine_id: str):
    # Import side effect registers built-in engines.
    from bitmomo_lab.engines import opportunity_v1  # noqa: F401

    try:
        return _REGISTRY[engine_id]
    except KeyError:
        raise KeyError(f"unknown engine {engine_id!r}; known: {sorted(_REGISTRY)}") from None
