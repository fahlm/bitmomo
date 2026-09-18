import pytest

from bitmomo_lab import timeutil


def test_detects_millisecond_and_microsecond_epochs():
    assert timeutil.epoch_unit(1735689300000) == "ms"
    assert timeutil.epoch_unit(1735689600000000) == "us"


@pytest.mark.parametrize("value", [173568930000, 17356893000000, 1735689600, 17356896000000000])
def test_rejects_ambiguous_magnitudes(value):
    with pytest.raises(ValueError):
        timeutil.epoch_unit(value)


def test_mixed_units_within_one_file_are_rejected():
    with pytest.raises(ValueError, match="mixed"):
        timeutil.uniform_unit([1735689300000, 1735689600000000])


def test_ms_and_us_normalize_to_the_same_instant():
    assert timeutil.to_us(1735689600000, "ms") == timeutil.to_us(1735689600000000, "us") == 1735689600000000


def test_parse_utc_accepts_archive_and_iso_forms():
    assert timeutil.parse_utc("2020-09-01 00:00:00") == timeutil.parse_utc("2020-09-01T00:00:00Z") == 1598918400000000
