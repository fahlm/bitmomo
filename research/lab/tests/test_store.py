import pyarrow as pa
import pyarrow.parquet as pq
import pytest

from bitmomo_lab.contract import SCHEMA_VERSION, TS
from bitmomo_lab.store.parquet import ParityContaminationError, content_sha256, write_dataset
from bitmomo_lab.store.pit import IntegrityError, load_verified


def observations(n=5, value_offset=0.0):
    return pa.table({
        "source": pa.array(["s"] * n), "dataset": pa.array(["d"] * n), "symbol": pa.array(["BTCUSDT"] * n),
        "interval": pa.array(["5m"] * n),
        "event_time": pa.array([i * 300_000_000 for i in range(n)], pa.int64()).cast(TS),
        "available_at": pa.array([(i + 1) * 300_000_000 for i in range(n)], pa.int64()).cast(TS),
        "availability_basis": pa.array(["close_boundary"] * n), "schema_version": pa.array([SCHEMA_VERSION] * n),
        "quality": pa.array(["ok"] * n), "raw_ref": pa.array(["f@abc"] * n),
        "close": pa.array([100.0 + i + value_offset for i in range(n)]),
        "maybe": pa.array([None if i % 2 else 1.0 for i in range(n)], pa.float64()),
    })


def test_write_is_byte_deterministic(tmp_path):
    a = write_dataset(observations(), tmp_path / "a.parquet")
    b = write_dataset(observations(), tmp_path / "b.parquet")
    assert a == b
    assert (tmp_path / "a.parquet").read_bytes() == (tmp_path / "b.parquet").read_bytes()


def test_content_hash_ignores_chunking_but_not_values():
    table = observations(10)
    rechunked = pa.concat_tables([table.slice(0, 3), table.slice(3)])
    assert rechunked["close"].num_chunks == 2
    assert content_sha256(table) == content_sha256(rechunked)
    assert content_sha256(table) != content_sha256(observations(10, value_offset=1e-9))


def test_content_hash_distinguishes_null_from_zero():
    table = observations(4)
    zeroed = table.set_column(table.column_names.index("maybe"), "maybe", pa.array([1.0, 0.0, 1.0, 0.0]))
    assert content_sha256(table) != content_sha256(zeroed)


def test_store_refuses_parity_columns(tmp_path):
    table = observations().append_column("imputation_flags", pa.array(["[]"] * 5))
    with pytest.raises(ParityContaminationError):
        write_dataset(table, tmp_path / "x.parquet")
    assert not (tmp_path / "x.parquet").exists()


def test_store_refuses_parity_marked_tables(tmp_path):
    table = observations().replace_schema_metadata({b"bitmomo_lab.mode": b"production_parity"})
    with pytest.raises(ParityContaminationError):
        write_dataset(table, tmp_path / "x.parquet")


def test_store_requires_canonical_fields(tmp_path):
    with pytest.raises(ValueError, match="canonical observation fields"):
        write_dataset(observations().drop_columns(["available_at"]), tmp_path / "x.parquet")


def test_load_verified_rejects_tampered_data(tmp_path):
    path = tmp_path / "d.parquet"
    content, _ = write_dataset(observations(), path)
    assert load_verified(path, content).num_rows == 5
    tampered = pq.read_table(path)
    tampered = tampered.set_column(tampered.column_names.index("close"), "close", pa.array([0.0] * 5))
    pq.write_table(tampered, path)
    with pytest.raises(IntegrityError):
        load_verified(path, content)


def test_content_hash_supports_booleans_with_nulls():
    a = pa.table({"b": pa.array([True, None, False])})
    b = pa.table({"b": pa.array([True, False, False])})
    assert content_sha256(a) == content_sha256(pa.table({"b": pa.array([True, None, False])}))
    assert content_sha256(a) != content_sha256(b)
