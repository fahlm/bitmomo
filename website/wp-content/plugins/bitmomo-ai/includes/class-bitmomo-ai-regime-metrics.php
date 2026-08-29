<?php
if (!defined('ABSPATH')) exit;

/** Pure closed-candle derivations required by Market Regime V1. */
final class Bitmomo_AI_Regime_Metrics {
    public static function from_candles(array $h1, array $d1, $structure_state) {
        if (count($h1) < 25 || count($d1) < 31) return null;

        $vol_now = self::range_percentile($d1);
        $prior_daily = array_slice($d1, 0, -1);
        if (count($prior_daily) < 30) return null;

        return [
            'return_1d' => self::return_pct($h1, 24),
            'return_7d' => self::return_pct($d1, 7),
            'return_30d' => self::return_pct($d1, 30),
            'volatility_percentile' => round($vol_now, 2),
            'volatility_change' => round($vol_now - self::range_percentile($prior_daily), 2),
            'volume_percentile' => round(self::volume_percentile($d1), 2),
            'range_position_pct' => round(self::range_position($d1, 30), 2),
            'structure_state' => self::normalize_structure($structure_state),
        ];
    }

    private static function return_pct(array $rows, $periods) {
        $end = (float) $rows[count($rows) - 1][4];
        $start = (float) $rows[count($rows) - 1 - $periods][4];
        return $start > 0 ? round((($end - $start) / $start) * 100, 6) : 0.0;
    }

    private static function range_percentile(array $rows) {
        $window = array_slice($rows, -252);
        $values = array_map(function ($row) {
            $close = (float) $row[4];
            return $close > 0 ? (((float) $row[2] - (float) $row[3]) / $close) * 100 : 0.0;
        }, $window);
        return self::percentile_last($values);
    }

    private static function volume_percentile(array $rows) {
        $values = array_map(function ($row) { return (float) $row[5]; }, array_slice($rows, -252));
        return self::percentile_last($values);
    }

    private static function percentile_last(array $values) {
        if (!$values) return 50.0;
        $current = end($values);
        $below = count(array_filter($values, function ($value) use ($current) { return $value <= $current; }));
        return 100 * $below / count($values);
    }

    private static function range_position(array $rows, $days) {
        $window = array_slice($rows, -$days);
        $high = max(array_map(function ($row) { return (float) $row[2]; }, $window));
        $low = min(array_map(function ($row) { return (float) $row[3]; }, $window));
        $close = (float) $window[count($window) - 1][4];
        return $high > $low ? max(0, min(100, (($close - $low) / ($high - $low)) * 100)) : 50.0;
    }

    private static function normalize_structure($state) {
        if ('breakout_up' === $state) return 'breakout_up';
        if ('breakout_down' === $state) return 'breakdown';
        if ('range' === $state) return 'range';
        return 'unknown';
    }
}
