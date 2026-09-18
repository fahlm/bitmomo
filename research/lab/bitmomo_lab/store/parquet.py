"""Deterministic canonical-store writer and content hashing.

``content_sha256`` identifies a dataset by its *values*, independent of Parquet
encoder details: fixed-width columns hash their little-endian value buffers
(nulls hashed via an explicit null mask), strings hash their UTF-8 values.
The Parquet file hash is recorded too, but the content hash is authoritative.

The canonical store only accepts research-truth (``strict``) tables. Tables that
carry production-parity fields are refused structurally.
"""

from __future__ import annotations

import hashlib
import pathlib
import sys

import pyarrow as pa
import pyarrow.compute as pc
import pyarrow.parquet as pq

from bitmomo_lab.contract import PARITY_ONLY_FIELDS, require_observation_fields

STORE_MODE_KEY = b"bitmomo_lab.mode"
STRICT = b"strict"
ROW_GROUP_SIZE = 131_072

assert sys.byteorder == "little", "content hashing assumes a little-endian host"


class ParityContaminationError(ValueError):
    """A production-parity artifact was offered to the canonical research store."""


def _fixed_width_bytes(array: pa.Array) -> bytes:
    """Little-endian value bytes of a fixed-width array, honoring its slice offset."""
    width = array.type.bit_width // 8
    data = array.buffers()[1].to_pybytes()
    return data[array.offset * width:(array.offset + len(array)) * width]


def _column_digest(column: pa.ChunkedArray) -> bytes:
    h = hashlib.sha256()
    dtype = column.type
    h.update(str(dtype).encode())
    array = column.combine_chunks() if column.num_chunks != 1 else column.chunk(0)
    h.update(_fixed_width_bytes(pc.is_null(array).cast(pa.int8())) if array.null_count else b"no-nulls")
    if pa.types.is_timestamp(dtype):
        array = array.cast(pa.int64())
        dtype = pa.int64()
    if pa.types.is_integer(dtype) or pa.types.is_floating(dtype):
        filled = pc.fill_null(array, pa.scalar(0, dtype)) if array.null_count else array
        h.update(_fixed_width_bytes(filled))
    elif pa.types.is_string(dtype) or pa.types.is_large_string(dtype):
        for value in array.to_pylist():
            h.update(b"\x00" if value is None else b"\x01" + value.encode() + b"\x1f")
    else:
        raise TypeError(f"no canonical hash for column type {dtype}")
    return h.digest()


def content_sha256(table: pa.Table) -> str:
    h = hashlib.sha256()
    h.update(str(table.num_rows).encode())
    for name in table.column_names:
        h.update(name.encode() + b"\x1e")
        h.update(_column_digest(table[name]))
    return h.hexdigest()


def file_sha256(path: pathlib.Path) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as handle:
        for block in iter(lambda: handle.read(1 << 20), b""):
            h.update(block)
    return h.hexdigest()


def guard_strict(table: pa.Table) -> None:
    """Refuse anything that is not research truth."""
    leaked = PARITY_ONLY_FIELDS.intersection(table.column_names)
    if leaked:
        raise ParityContaminationError(f"parity-only fields cannot enter the canonical store: {sorted(leaked)}")
    metadata = table.schema.metadata or {}
    if metadata.get(STORE_MODE_KEY, STRICT) != STRICT:
        raise ParityContaminationError(f"table is marked {metadata[STORE_MODE_KEY]!r}, not strict")
    require_observation_fields(table)


def write_dataset(table: pa.Table, path: pathlib.Path) -> tuple[str, str]:
    """Write a canonical dataset deterministically. Returns (content_sha256, file_sha256)."""
    guard_strict(table)
    table = table.replace_schema_metadata({STORE_MODE_KEY: STRICT})
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_name(path.name + ".partial")
    pq.write_table(
        table,
        tmp,
        compression="zstd",
        compression_level=3,
        row_group_size=ROW_GROUP_SIZE,
        use_dictionary=True,
        write_statistics=True,
    )
    tmp.replace(path)
    return content_sha256(table), file_sha256(path)


def read_dataset(path: pathlib.Path) -> pa.Table:
    table = pq.read_table(path, partitioning=None)
    guard_strict(table)
    return table
