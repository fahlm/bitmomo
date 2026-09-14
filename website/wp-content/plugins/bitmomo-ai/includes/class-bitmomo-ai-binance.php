<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Binance {
    const BASE = 'https://fapi.binance.com';
    const SPOT_BASE = 'https://data-api.binance.vision';
    const BYBIT_BASE = 'https://api.bybit.com';

    public static function snapshot() {
        $h1_meta = $h4_meta = $d1_meta = [];
        $h1 = self::market_klines('1h', 120, $h1_meta);
        $h4 = self::market_klines('4h', 320, $h4_meta);
        $d1 = self::market_klines('1d', 320, $d1_meta);
        $funding = self::get('/fapi/v1/fundingRate', ['symbol' => 'BTCUSDT', 'limit' => 21]);
        $premium = self::get('/fapi/v1/premiumIndex', ['symbol' => 'BTCUSDT']);
        $oi = self::get('/futures/data/openInterestHist', ['symbol' => 'BTCUSDT', 'period' => '1h', 'limit' => 30]);
        $long_short = self::get('/futures/data/globalLongShortAccountRatio', ['symbol' => 'BTCUSDT', 'period' => '1h', 'limit' => 30]);
        $taker = self::get('/futures/data/takerlongshortRatio', ['symbol' => 'BTCUSDT', 'period' => '1h', 'limit' => 30]);
        $primary = compact('funding', 'premium', 'oi', 'long_short');
        $derivatives_source = 'Binance USD-M';
        $fallback_attempted = false;
        $fallback_error = null;

        if (is_wp_error($funding) || is_wp_error($premium) || is_wp_error($oi) || is_wp_error($long_short)) {
            $fallback_attempted = true;
            $fallback = self::bybit_derivatives();
            if (!is_wp_error($fallback)) {
                if (is_wp_error($funding)) $funding = $fallback['funding'];
                if (is_wp_error($premium)) $premium = $fallback['premium'];
                if (is_wp_error($oi)) $oi = $fallback['oi'];
                if (is_wp_error($long_short)) $long_short = $fallback['long_short'];
                $derivatives_source = 'Bybit linear perpetual fallback';
            } else {
                $fallback_error = $fallback;
            }
        }

        $funding_ok = !is_wp_error($funding) && is_array($funding) && count($funding) >= 2;
        $premium_ok = !is_wp_error($premium) && is_array($premium);
        // A 24H change from hourly observations requires 25 points (24 intervals).
        $oi_ok = !is_wp_error($oi) && is_array($oi) && count($oi) >= 25;
        if (!$funding_ok) $funding = [];
        if (!$premium_ok) $premium = [];
        if (!$oi_ok) $oi = [];
        $long_short_ok = !is_wp_error($long_short) && is_array($long_short) && count($long_short) >= 2;
        $taker_ok = !is_wp_error($taker) && is_array($taker) && count($taker) >= 2;
        $diagnostics = [
            self::market_diagnostic('candles_1h', $h1, $h1_meta),
            self::market_diagnostic('candles_4h', $h4, $h4_meta),
            self::market_diagnostic('candles_1d', $d1, $d1_meta),
            self::input_diagnostic('funding', $funding, $funding_ok, $primary['funding'], $fallback_attempted, $fallback_error, 'fundingTime'),
            self::input_diagnostic('premium_index', $premium, $premium_ok, $primary['premium'], $fallback_attempted, $fallback_error, 'time'),
            self::input_diagnostic('open_interest', $oi, $oi_ok, $primary['oi'], $fallback_attempted, $fallback_error, 'timestamp'),
            self::input_diagnostic('global_long_short_ratio', $long_short, $long_short_ok, $primary['long_short'], $fallback_attempted, $fallback_error, 'timestamp'),
            self::input_diagnostic('taker_buy_sell_ratio', $taker, $taker_ok, $taker, false, null, 'timestamp'),
        ];
        foreach ([$h1, $h4, $d1] as $response) {
            if (is_wp_error($response)) return self::error_with_diagnostics($response, $diagnostics);
        }
        if (!$long_short_ok) $long_short = [];
        if (!$taker_ok) $taker = [];
        $h1 = self::closed_candles($h1);
        $h4 = self::closed_candles($h4);
        $d1 = self::closed_candles($d1);
        if (count($h1) < 60 || count($h4) < 60 || count($d1) < 60) {
            return new WP_Error('binance_incomplete', __('Binance returned incomplete market data.', 'bitmomo-ai'), ['source_diagnostics' => $diagnostics]);
        }

        $direction_1h = self::direction($h1);
        $direction_4h = self::direction($h4);
        $direction_1d = self::direction($d1);
        $structure_4h = self::structure($h4);
        $structure_1d = self::structure($d1);
        $operational_1h = self::operational_levels($h1);
        $last_1h = end($h1);
        $first_24h = $h1[max(0, count($h1) - 25)];
        $outcome_rows = array_slice($h1, -24);
        $outcome_high_24h = max(array_map(function ($row) { return (float) $row[2]; }, $outcome_rows));
        $outcome_low_24h = min(array_map(function ($row) { return (float) $row[3]; }, $outcome_rows));
        $close = (float) $last_1h[4];
        $price_24h = (float) $first_24h[4];
        $mark = (float) ($premium['markPrice'] ?? $close);
        $index = (float) ($premium['indexPrice'] ?? $close);
        $funding_values = array_map(function ($row) { return (float) ($row['fundingRate'] ?? 0); }, $funding);
        $oi_values = array_map(function ($row) { return (float) ($row['sumOpenInterestValue'] ?? 0); }, $oi);
        $oi_change_24h = $oi_ok ? self::percent_change_over_periods($oi_values, 24) : null;
        $long_short_last = $long_short_ok ? end($long_short) : [];
        $taker_last = $taker_ok ? end($taker) : [];
        $last_close_time = isset($last_1h[6]) ? (int) floor(((int) $last_1h[6]) / 1000) : time();
        $data_age_minutes = max(0, (int) floor((time() - $last_close_time) / 60));
        $completeness = 60 + ($funding_ok ? 10 : 0) + ($premium_ok ? 10 : 0) + ($oi_ok ? 10 : 0) + ($long_short_ok ? 5 : 0) + ($taker_ok ? 5 : 0);
        $regime_metrics = Bitmomo_AI_Regime_Metrics::from_candles($h1, $d1, $structure_4h['state']);

        return [
            'symbol' => 'BTCUSDT',
            'exchange' => 'BINANCE',
            'timeframe' => '4H',
            'timestamp' => gmdate('c'),
            'close' => $close,
            'outcome_window' => [
                'high_24h' => $outcome_high_24h,
                'low_24h' => $outcome_low_24h,
                'closed_candles' => count($outcome_rows),
            ],
            'quality' => [
                'status' => $data_age_minutes <= 90 ? ($completeness === 100 ? 'complete' : 'partial') : 'stale',
                'completeness_pct' => $completeness,
                'data_age_minutes' => $data_age_minutes,
                'last_closed_candle' => gmdate('c', $last_close_time),
                'source' => 'Binance public market data + ' . $derivatives_source,
                'optional_missing' => array_values(array_filter([$funding_ok ? '' : 'funding', $premium_ok ? '' : 'premium_index', $oi_ok ? '' : 'open_interest', $long_short_ok ? '' : 'global_long_short_ratio', $taker_ok ? '' : 'taker_buy_sell_ratio'])),
            ],
            'source_diagnostics' => $diagnostics,
            'volatility' => [
                'atr_pct_4h' => round(self::atr_pct($h4), 6),
                'atr_pct_1h' => round(self::atr_pct($h1), 6),
                'atr_percentile_1d' => round(self::range_percentile($d1), 2),
                'bb_width_pct_4h' => round(self::bollinger_width_pct($h4), 6),
                'regime' => self::volatility_regime($d1),
            ],
            'direction' => [
                'plus_di' => round($direction_4h['plus_di'], 4),
                'minus_di' => round($direction_4h['minus_di'], 4),
                'adx' => round($direction_4h['adx'], 4),
                'bias_1h' => $direction_1h['bias'],
                'bias_4h' => $direction_4h['bias'],
                'bias_1d' => $direction_1d['bias'],
            ],
            'carry' => [
                'funding_rate' => $funding_ok ? (float) end($funding_values) : null,
                'funding_7d' => $funding_ok ? array_sum($funding_values) / count($funding_values) : null,
                'basis_pct' => $premium_ok && $index > 0 ? (($mark - $index) / $index) * 100 : null,
                'data_status' => ($funding_ok && $premium_ok) ? ($derivatives_source === 'Binance USD-M' ? 'binance_public' : 'bybit_public') : 'unavailable',
            ],
            'structure' => [
                'last_swing_high' => $structure_4h['high'],
                'last_swing_low' => $structure_4h['low'],
                'state' => $structure_4h['state'],
                'state_1d' => $structure_1d['state'],
                'near_resistance' => $structure_4h['near_high'],
                'near_support' => $structure_4h['near_low'],
                'operational_resistance_1h' => $operational_1h['resistance'],
                'operational_support_1h' => $operational_1h['support'],
                'operational_source' => $operational_1h['source'],
                'resistance_status_1h' => $operational_1h['resistance_status'],
                'support_status_1h' => $operational_1h['support_status'],
                'recent_high_1h' => $operational_1h['recent_high'],
                'recent_low_1h' => $operational_1h['recent_low'],
            ],
            'crowding' => [
                'oi_change_24h_pct' => $oi_change_24h,
                'oi_zscore' => $oi_ok ? self::zscore_last($oi_values) : null,
                'price_change_24h_pct' => $price_24h > 0 ? (($close - $price_24h) / $price_24h) * 100 : 0,
                'global_long_short_ratio' => $long_short_ok ? (float) ($long_short_last['longShortRatio'] ?? 1) : null,
                'taker_buy_sell_ratio' => $taker_ok ? (float) ($taker_last['buySellRatio'] ?? 1) : null,
                'data_status' => ($oi_ok && $long_short_ok) ? ($taker_ok ? 'complete' : 'partial') : 'unavailable',
            ],
            'regime_metrics' => $regime_metrics,
        ];
    }

    private static function market_klines($interval, $limit, &$meta = null) {
        $params = ['symbol' => 'BTCUSDT', 'interval' => $interval, 'limit' => $limit];
        $futures = self::get('/fapi/v1/klines', $params);
        if (!is_wp_error($futures)) {
            $meta = ['provider' => 'binance_usdm', 'fallback_attempted' => false, 'fallback_success' => false];
            return $futures;
        }
        $spot = self::get_from_base(self::SPOT_BASE, '/api/v3/klines', $params, 'spot_');
        $meta = [
            'provider' => is_wp_error($spot) ? 'binance_usdm' : 'binance_spot',
            'fallback_attempted' => true,
            'fallback_success' => !is_wp_error($spot),
            'primary_error' => self::safe_error($futures),
            'fallback_error' => is_wp_error($spot) ? self::safe_error($spot) : [],
        ];
        return $spot;
    }

    private static function market_diagnostic($name, $response, array $meta) {
        $rows = is_array($response) ? self::closed_candles($response) : [];
        $success = count($rows) >= 60;
        $last = $rows ? end($rows) : [];
        $observed = isset($last[6]) ? (int) floor(((int) $last[6]) / 1000) : 0;
        return self::diagnostic_row($name, (string) ($meta['provider'] ?? 'binance_usdm'), $success, $observed, $meta, is_wp_error($response) ? $response : null, $success ? '' : 'incomplete');
    }

    private static function input_diagnostic($name, $response, $success, $primary, $fallback_attempted, $fallback_error, $timestamp_key) {
        $used_fallback = is_wp_error($primary) && $success;
        $provider = $used_fallback ? 'bybit_linear' : 'binance_usdm';
        $observed = self::observed_at($response, $timestamp_key);
        $meta = [
            'fallback_attempted' => is_wp_error($primary) && $fallback_attempted,
            'fallback_success' => $used_fallback,
            'primary_error' => is_wp_error($primary) ? self::safe_error($primary) : [],
            'fallback_error' => $fallback_error instanceof WP_Error ? self::safe_error($fallback_error) : [],
        ];
        $error = is_wp_error($response) ? $response : (!$success && is_wp_error($primary) ? $primary : null);
        return self::diagnostic_row($name, $provider, $success, $observed, $meta, $error, $success ? '' : 'incomplete');
    }

    private static function diagnostic_row($name, $provider, $success, $observed, array $meta, $error = null, $default_error = '') {
        $age = $observed > 0 ? max(0, (int) floor((time() - $observed) / 60)) : null;
        $failure = $error instanceof WP_Error ? self::safe_error($error) : [];
        return [
            'requested_input' => sanitize_key($name),
            'provider' => sanitize_key($provider),
            'success' => (bool) $success,
            'observed_at' => $observed > 0 ? gmdate('c', $observed) : '',
            'age_minutes' => $age,
            'freshness' => !$success ? 'unavailable' : (null === $age ? 'unknown' : ($age <= 360 ? 'fresh' : 'stale')),
            'fallback_attempted' => !empty($meta['fallback_attempted']),
            'fallback_success' => !empty($meta['fallback_success']),
            'error_category' => (string) ($failure['category'] ?? $default_error),
            'provider_status' => $failure['provider_status'] ?? null,
            'primary_error' => (array) ($meta['primary_error'] ?? []),
            'fallback_error' => (array) ($meta['fallback_error'] ?? []),
        ];
    }

    private static function observed_at($response, $key) {
        if (!is_array($response) || !$response) return 0;
        $row = array_key_exists(0, $response) ? end($response) : $response;
        $value = is_array($row) ? (int) ($row[$key] ?? 0) : 0;
        return $value > 20000000000 ? (int) floor($value / 1000) : $value;
    }

    private static function safe_error(WP_Error $error) {
        $data = $error->get_error_data();
        $status = is_array($data) && isset($data['status']) && is_scalar($data['status']) ? (int) $data['status'] : null;
        $code = sanitize_key((string) $error->get_error_code());
        $category = strpos($code, 'http') !== false ? 'http' : (strpos($code, 'json') !== false || strpos($code, 'payload') !== false ? 'invalid_payload' : 'transport');
        return ['category' => $category, 'code' => $code, 'provider_status' => $status];
    }

    private static function error_with_diagnostics(WP_Error $error, array $diagnostics) {
        return new WP_Error($error->get_error_code(), $error->get_error_message(), ['source_diagnostics' => $diagnostics]);
    }

    private static function bybit_derivatives() {
        $funding_raw = self::bybit_get('/v5/market/funding/history', ['category' => 'linear', 'symbol' => 'BTCUSDT', 'limit' => 21]);
        $ticker_raw = self::bybit_get('/v5/market/tickers', ['category' => 'linear', 'symbol' => 'BTCUSDT']);
        $oi_raw = self::bybit_get('/v5/market/open-interest', ['category' => 'linear', 'symbol' => 'BTCUSDT', 'intervalTime' => '1h', 'limit' => 30]);
        $ratio_raw = self::bybit_get('/v5/market/account-ratio', ['category' => 'linear', 'symbol' => 'BTCUSDT', 'period' => '1h', 'limit' => 30]);
        foreach ([$funding_raw, $ticker_raw, $oi_raw, $ratio_raw] as $response) if (is_wp_error($response)) return $response;

        $funding = array_map(function ($row) { return ['fundingRate' => (float) ($row['fundingRate'] ?? 0), 'fundingTime' => (int) ($row['fundingRateTimestamp'] ?? 0)]; }, $funding_raw);
        usort($funding, function ($a, $b) { return $a['fundingTime'] <=> $b['fundingTime']; });
        $ticker = reset($ticker_raw);
        $premium = ['markPrice' => (float) ($ticker['markPrice'] ?? 0), 'indexPrice' => (float) ($ticker['indexPrice'] ?? 0), 'time' => (int) ($ticker['_response_time'] ?? 0)];
        $oi = array_map(function ($row) { return ['sumOpenInterestValue' => (float) ($row['openInterest'] ?? 0), 'timestamp' => (int) ($row['timestamp'] ?? 0)]; }, $oi_raw);
        usort($oi, function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });
        $long_short = array_map(function ($row) {
            $sell = (float) ($row['sellRatio'] ?? 0);
            return ['longShortRatio' => $sell > 0 ? (float) ($row['buyRatio'] ?? 0) / $sell : 1, 'timestamp' => (int) ($row['timestamp'] ?? 0)];
        }, $ratio_raw);
        usort($long_short, function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });
        return ['funding' => $funding, 'premium' => $premium, 'oi' => $oi, 'long_short' => $long_short];
    }

    private static function bybit_get($path, array $params) {
        $response = self::get_from_base(self::BYBIT_BASE, $path, $params, 'bybit_');
        if (is_wp_error($response)) return $response;
        if ((int) ($response['retCode'] ?? -1) !== 0 || !isset($response['result']['list']) || !is_array($response['result']['list'])) {
            return new WP_Error('bybit_payload', __('Bybit returned invalid derivatives data.', 'bitmomo-ai'));
        }
        return self::attach_response_time($response['result']['list'], $response['time'] ?? 0);
    }

    private static function attach_response_time(array $rows, $timestamp) {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) return $rows;
        return array_map(function ($row) use ($timestamp) {
            if (is_array($row) && !isset($row['_response_time'])) $row['_response_time'] = $timestamp;
            return $row;
        }, $rows);
    }

    private static function get($path, array $params) {
        return self::get_from_base(self::BASE, $path, $params);
    }

    private static function get_from_base($base, $path, array $params, $cache_prefix = '') {
        $url = add_query_arg($params, $base . $path);
        $cache_key = 'bitmomo_ai_binance_' . $cache_prefix . md5($url);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
        $last_error = new WP_Error('binance_unknown', __('Binance market-data request failed.', 'bitmomo-ai'));
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = wp_safe_remote_get($url, ['timeout' => 12, 'redirection' => 2, 'user-agent' => 'Bitmomo-AI/' . BITMOMO_AI_VERSION]);
            if (is_wp_error($response)) {
                $last_error = $response;
            } elseif (wp_remote_retrieve_response_code($response) !== 200) {
                $last_error = new WP_Error('binance_http', __('Binance market-data request failed.', 'bitmomo-ai'), ['status' => (int) wp_remote_retrieve_response_code($response)]);
            } else {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (is_array($data)) {
                    set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
                    set_transient($cache_key . '_stale', $data, 6 * HOUR_IN_SECONDS);
                    return $data;
                }
                $last_error = new WP_Error('binance_json', __('Binance returned invalid JSON.', 'bitmomo-ai'));
            }
            if ($attempt === 1) usleep(350000);
        }
        return self::stale_or_error($cache_key, $last_error);
    }

    private static function stale_or_error($cache_key, WP_Error $error) {
        $stale = get_transient($cache_key . '_stale');
        return $stale !== false ? $stale : $error;
    }

    private static function closed_candles(array $rows) {
        $now_ms = (int) floor(microtime(true) * 1000);
        return array_values(array_filter($rows, function ($row) use ($now_ms) {
            return isset($row[6]) && (int) $row[6] < $now_ms;
        }));
    }

    private static function direction(array $rows) {
        $plus = $minus = $tr = [];
        for ($i = 1, $n = count($rows); $i < $n; $i++) {
            $up = (float) $rows[$i][2] - (float) $rows[$i - 1][2];
            $down = (float) $rows[$i - 1][3] - (float) $rows[$i][3];
            $plus[] = ($up > $down && $up > 0) ? $up : 0;
            $minus[] = ($down > $up && $down > 0) ? $down : 0;
            $tr[] = max((float) $rows[$i][2] - (float) $rows[$i][3], abs((float) $rows[$i][2] - (float) $rows[$i - 1][4]), abs((float) $rows[$i][3] - (float) $rows[$i - 1][4]));
        }
        $length = 14;
        $dx = [];
        for ($i = $length - 1, $n = count($tr); $i < $n; $i++) {
            $tr_sum = array_sum(array_slice($tr, $i - $length + 1, $length));
            if ($tr_sum <= 0) continue;
            $p = 100 * array_sum(array_slice($plus, $i - $length + 1, $length)) / $tr_sum;
            $m = 100 * array_sum(array_slice($minus, $i - $length + 1, $length)) / $tr_sum;
            $dx[] = ($p + $m) > 0 ? 100 * abs($p - $m) / ($p + $m) : 0;
        }
        $tr_sum = array_sum(array_slice($tr, -$length));
        $p = $tr_sum > 0 ? 100 * array_sum(array_slice($plus, -$length)) / $tr_sum : 0;
        $m = $tr_sum > 0 ? 100 * array_sum(array_slice($minus, -$length)) / $tr_sum : 0;
        $adx = count($dx) ? array_sum(array_slice($dx, -$length)) / min($length, count($dx)) : 0;
        return ['plus_di' => $p, 'minus_di' => $m, 'adx' => $adx, 'bias' => $adx < 20 ? 'neutral' : ($p > $m ? 'bullish' : 'bearish')];
    }

    private static function structure(array $rows) {
        $lookback = 20;
        $last = end($rows);
        $prior = array_slice($rows, -($lookback + 1), $lookback);
        $high = max(array_map(function ($row) { return (float) $row[2]; }, $prior));
        $low = min(array_map(function ($row) { return (float) $row[3]; }, $prior));
        $close = (float) $last[4];
        $ema20 = self::ema(array_map(function ($row) { return (float) $row[4]; }, $rows), 20);
        $ema50 = self::ema(array_map(function ($row) { return (float) $row[4]; }, $rows), 50);
        $state = $close > $high ? 'breakout_up' : ($close < $low ? 'breakout_down' : ($ema20 > $ema50 && $close > $ema20 ? 'hh_hl' : ($ema20 < $ema50 && $close < $ema20 ? 'lh_ll' : 'range')));
        $near = array_slice($rows, -6, 5);
        $near_high = max(array_map(function ($row) { return (float) $row[2]; }, $near));
        $near_low = min(array_map(function ($row) { return (float) $row[3]; }, $near));
        return ['high' => $high, 'low' => $low, 'near_high' => $near_high, 'near_low' => $near_low, 'state' => $state];
    }

    private static function ema(array $values, $length) {
        $ema = reset($values);
        $alpha = 2 / ($length + 1);
        foreach ($values as $value) $ema = ($value * $alpha) + ($ema * (1 - $alpha));
        return $ema;
    }

    private static function operational_levels(array $rows) {
        $window = array_slice($rows, -49);
        $last = end($window);
        $price = (float) $last[4];
        $atr = $price * (self::atr_pct($rows) / 100);
        $highs = $lows = [];
        for ($i = 2, $n = count($window) - 2; $i < $n; $i++) {
            $high = (float) $window[$i][2];
            $low = (float) $window[$i][3];
            if ($high > (float) $window[$i - 1][2] && $high > (float) $window[$i - 2][2] && $high >= (float) $window[$i + 1][2] && $high >= (float) $window[$i + 2][2]) $highs[] = $high;
            if ($low < (float) $window[$i - 1][3] && $low < (float) $window[$i - 2][3] && $low <= (float) $window[$i + 1][3] && $low <= (float) $window[$i + 2][3]) $lows[] = $low;
        }
        $supports = array_values(array_filter($lows, function ($value) use ($price) { return $value < $price; }));
        $resistances = array_values(array_filter($highs, function ($value) use ($price) { return $value > $price; }));
        $support = $supports ? max($supports) : max(0, $price - $atr);
        $resistance = $resistances ? min($resistances) : $price + $atr;
        $max_distance = max($atr * 1.75, $price * 0.0125);
        if (($price - $support) > $max_distance) $support = max(0, $price - $atr);
        if (($resistance - $price) > $max_distance) $resistance = $price + $atr;
        $buffer = $atr * 0.18;
        $recent = array_slice($window, -6);
        $recent_high = max(array_map(function ($row) { return (float) $row[2]; }, $recent));
        $recent_low = min(array_map(function ($row) { return (float) $row[3]; }, $recent));
        $last_two = array_slice($window, -2);
        $two_closes_above = count($last_two) === 2 && (float) $last_two[0][4] > ($resistance + $buffer) && (float) $last_two[1][4] > ($resistance + $buffer);
        $two_closes_below = count($last_two) === 2 && (float) $last_two[0][4] < ($support - $buffer) && (float) $last_two[1][4] < ($support - $buffer);
        $resistance_status = $two_closes_above ? 'confirmed_breakout' : ($recent_high > ($resistance + $buffer) && $price < $resistance ? 'rejected_breakout' : ($price > ($resistance + $buffer) ? 'awaiting_confirmation' : ($recent_high >= ($resistance - $buffer) ? 'testing' : 'untested')));
        $support_status = $two_closes_below ? 'confirmed_breakdown' : ($recent_low < ($support - $buffer) && $price > $support ? 'rejected_breakdown' : ($price < ($support - $buffer) ? 'awaiting_confirmation' : ($recent_low <= ($support + $buffer) ? 'testing' : 'untested')));
        return ['support' => $support, 'resistance' => $resistance, 'source' => 'confirmed_1h_pivots_atr_guard', 'resistance_status' => $resistance_status, 'support_status' => $support_status, 'recent_high' => $recent_high, 'recent_low' => $recent_low];
    }

    private static function atr_pct(array $rows) {
        $tr = [];
        for ($i = 1, $n = count($rows); $i < $n; $i++) $tr[] = max((float) $rows[$i][2] - (float) $rows[$i][3], abs((float) $rows[$i][2] - (float) $rows[$i - 1][4]), abs((float) $rows[$i][3] - (float) $rows[$i - 1][4]));
        $last = end($rows);
        return ((float) $last[4]) > 0 ? (array_sum(array_slice($tr, -14)) / 14) / (float) $last[4] * 100 : 0;
    }

    private static function bollinger_width_pct(array $rows) {
        $closes = array_map(function ($row) { return (float) $row[4]; }, array_slice($rows, -20));
        if (count($closes) < 20) return 0;
        $mean = array_sum($closes) / count($closes);
        if ($mean <= 0) return 0;
        $variance = array_sum(array_map(function ($value) use ($mean) { return pow($value - $mean, 2); }, $closes)) / count($closes);
        return (4 * sqrt($variance) / $mean) * 100;
    }

    private static function range_percentile(array $rows) {
        $ranges = array_map(function ($row) { $close = (float) $row[4]; return $close > 0 ? ((float) $row[2] - (float) $row[3]) / $close * 100 : 0; }, array_slice($rows, -252));
        $current = end($ranges);
        $below = count(array_filter($ranges, function ($value) use ($current) { return $value <= $current; }));
        return count($ranges) ? 100 * $below / count($ranges) : 50;
    }

    private static function volatility_regime(array $rows) {
        $p = self::range_percentile($rows);
        return $p >= 95 ? 'extreme' : ($p >= 75 ? 'high' : ($p <= 25 ? 'low' : 'normal'));
    }

    /** Exact percent change across N intervals requires N+1 observations. */
    public static function percent_change_over_periods(array $values, $periods) {
        $periods = max(1, (int) $periods);
        if (count($values) < $periods + 1) return null;
        $window = array_slice(array_values($values), -($periods + 1));
        $first = (float) reset($window);
        $last = (float) end($window);
        return $first > 0 ? (($last - $first) / $first) * 100 : null;
    }

    private static function zscore_last(array $values) {
        if (count($values) < 2) return 0;
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function ($value) use ($mean) { return pow($value - $mean, 2); }, $values)) / count($values);
        $sd = sqrt($variance);
        return $sd > 0 ? (end($values) - $mean) / $sd : 0;
    }
}
