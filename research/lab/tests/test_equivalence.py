import datetime as dt

from bitmomo_lab import build
from bitmomo_lab.sources.registry import get
from bitmomo_lab.validate.equivalence import metrics_vs_klines

from conftest import make_zip, utc

UM = get("binance_um_klines_5m")
METRICS = get("binance_um_metrics_5m")
HEADER = ("create_time,symbol,sum_open_interest,sum_open_interest_value,count_toptrader_long_short_ratio,"
          "sum_toptrader_long_short_ratio,count_long_short_ratio,sum_taker_long_short_vol_ratio")


def test_detects_that_metrics_rows_describe_the_period_starting_at_create_time(tmp_path, fake_archive):
    start = utc(2026, 8, 1)
    klines, metrics = [], [HEADER]
    for i in range(288):
        t = start + dt.timedelta(minutes=5 * i)
        ms = int(t.timestamp() * 1000)
        volume, buy = 100.0, 30.0 + (i % 40)
        close = 50_000 + i
        klines.append(f"{ms},{close - 1},{close + 5},{close - 5},{close},{volume},{ms + 299_999},1,1,{buy},1,0")
        # Taker ratio of THIS period; OI implied price = this period's open.
        metrics.append(f"{t:%Y-%m-%d %H:%M:%S},BTCUSDT,2,{2 * (close - 1)},1,1,1,{buy / (volume - buy)}")
    fake_archive.add(UM.url("BTCUSDT", "daily", "2026-08-01"), make_zip("k.csv", "\n".join(klines)))
    fake_archive.add(METRICS.url("BTCUSDT", "daily", "2026-08-01"), make_zip("m.csv", "\n".join(metrics)))
    data, manifests = tmp_path / "data", tmp_path / "manifests"
    for dataset in ("binance_um_klines_5m", "binance_um_metrics_5m"):
        build.build_dataset(dataset, "BTCUSDT", dt.date(2026, 8, 1), dt.date(2026, 8, 1), data, manifests, fake_archive)

    name = "__BTCUSDT__2026-08-01__2026-08-01.json"
    report = metrics_vs_klines(manifests / f"binance_um_metrics_5m{name}", manifests / f"binance_um_klines_5m{name}", data)
    alignment = report["taker_ratio_alignment"]
    assert alignment["kline_open=T"]["share_rel_err_lt_1e-4"] == 1.0
    assert alignment["kline_open=T-5m"]["share_rel_err_lt_1e-4"] < 0.1
    assert report["oi_implied_price_vs_kline"]["kline_open_price_at_T"]["share_rel_err_lt_1e-4"] == 1.0
