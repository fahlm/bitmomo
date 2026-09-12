<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Signal_Engine {
    public static function evaluate(array $data) {
        $direction = self::direction_score($data['direction']);
        $carry = self::carry_score($data['carry']);
        $structure = self::structure_score($data['structure']);
        $crowding = self::crowding_score($data['crowding']);
        $directional_score = (int) round(($direction * 0.35) + ($carry * 0.15) + ($structure * 0.30) + ($crowding * 0.20));
        $bias = $directional_score >= 20 ? 'bullish' : ($directional_score <= -20 ? 'bearish' : 'neutral');

        // Confidence is evidence coherence, not raw evidence activity and not a
        // probability of success. The previous formula summed absolute axis
        // magnitudes and called that "agreement", which could rate two strong
        // opposing axes as highly confident even while they cancelled in the
        // actual directional score. Keep magnitude as one input, but require
        // the weighted net evidence to remain coherent as well.
        $evidence_magnitude = (abs($direction) * 0.35)
            + (abs($carry) * 0.15)
            + (abs($structure) * 0.30)
            + (abs($crowding) * 0.20);
        $net_magnitude = abs($directional_score);
        $confirmation = self::confirmation_score($data['direction']);
        $volatility_penalty = ($data['volatility']['regime'] ?? '') === 'extreme' ? 15 : 0;
        $confidence = (int) round(min(90, max(25,
            25 + ($evidence_magnitude * 0.35) + ($net_magnitude * 0.40) + $confirmation - $volatility_penalty
        )));

        $volatility = self::volatility_score($data['volatility']);
        $risk = self::risk_levels($data, $bias);

        return [
            'bias' => $bias,
            'direction_strength' => self::direction_strength($directional_score),
            'confidence' => $confidence,
            'score' => $directional_score,
            'quality' => $data['quality'] ?? ['status' => 'unknown'],
            'risk' => $risk,
            'axes' => [
                'volatility' => array_merge($data['volatility'], $volatility),
                'direction' => array_merge($data['direction'], ['score' => $direction, 'status' => self::score_status($direction), 'reason' => self::direction_reason($data['direction'])]),
                'carry' => array_merge($data['carry'], ['score' => $carry, 'status' => self::score_status($carry), 'reason' => self::carry_reason($data['carry'])]),
                'structure' => array_merge($data['structure'], ['score' => $structure, 'status' => self::score_status($structure), 'reason' => self::structure_reason($data['structure'])]),
                'crowding' => array_merge($data['crowding'], ['score' => $crowding, 'status' => self::score_status($crowding), 'reason' => self::crowding_reason($data['crowding'])]),
            ],
        ];
    }

    private static function direction_score(array $axis) {
        $adx = (float) ($axis['adx'] ?? 0);
        if ($adx < 18) return 0;
        $sign = ($axis['plus_di'] ?? 0) > ($axis['minus_di'] ?? 0) ? 1 : -1;
        return $sign * (int) round(min(100, max(25, ($adx - 10) * 4)));
    }

    private static function confirmation_score(array $axis) {
        $primary = self::normalise_bias($axis['bias_4h'] ?? 'neutral');
        $tactical = self::normalise_bias($axis['bias_1h'] ?? 'neutral');
        $regime = self::normalise_bias($axis['bias_1d'] ?? 'neutral');
        if ($primary === 0) return 0;
        $score = $tactical === $primary ? 5 : ($tactical === -$primary ? -5 : 0);
        return $score + ($regime === $primary ? 5 : ($regime === -$primary ? -10 : 0));
    }

    private static function normalise_bias($bias) {
        if ($bias === 'bullish') return 1;
        if ($bias === 'bearish') return -1;
        return 0;
    }

    private static function carry_score(array $axis) {
        $funding = (float) ($axis['funding_rate'] ?? 0);
        $basis = (float) ($axis['basis_pct'] ?? 0);
        if ($funding >= 0.0005 && $basis > 0.25) return -75;
        if ($funding >= 0.00025 || $basis > 0.15) return -35;
        if ($funding <= -0.0005 && $basis < -0.25) return 75;
        if ($funding <= -0.00025 || $basis < -0.15) return 35;
        return 0;
    }

    private static function structure_score(array $axis) {
        $state = $axis['state'] ?? 'range';
        return $state === 'breakout_up' ? 100 : ($state === 'breakout_down' ? -100 : ($state === 'hh_hl' ? 60 : ($state === 'lh_ll' ? -60 : 0)));
    }

    private static function crowding_score(array $axis) {
        $oi = (float) ($axis['oi_change_24h_pct'] ?? 0);
        $price = (float) ($axis['price_change_24h_pct'] ?? 0);
        $long_short = isset($axis['global_long_short_ratio']) ? (float) $axis['global_long_short_ratio'] : null;
        $taker = isset($axis['taker_buy_sell_ratio']) ? (float) $axis['taker_buy_sell_ratio'] : null;
        $score = 0;
        if ($oi >= 2) $score += $price > 0 ? 35 : ($price < 0 ? -35 : 0);
        if ($long_short !== null && $long_short >= 1.25) $score -= 25;
        elseif ($long_short !== null && $long_short <= 0.80) $score += 25;
        if ($taker !== null && $taker >= 1.10) $score += 20;
        elseif ($taker !== null && $taker <= 0.90) $score -= 20;
        return max(-100, min(100, $score));
    }

    private static function volatility_score(array $axis) {
        $percentile = (float) ($axis['atr_percentile_1d'] ?? 50);
        $score = (int) round(max(0, min(100, $percentile)));
        $regime = $axis['regime'] ?? 'normal';
        return ['score' => $score, 'status' => $regime, 'reason' => sprintf('ATR regime %s; 4H ATR %.2f%% and Bollinger width %.2f%%.', $regime, (float) ($axis['atr_pct_4h'] ?? 0), (float) ($axis['bb_width_pct_4h'] ?? 0))];
    }

    private static function score_status($score) {
        return self::direction_strength($score);
    }

    /** Canonical five-state direction derived only from the aggregate score. */
    public static function direction_strength($score) {
        $score = max(-100, min(100, (float) $score));
        if ($score >= 60) return 'strong_bullish';
        if ($score >= 20) return 'bullish';
        if ($score <= -60) return 'strong_bearish';
        if ($score <= -20) return 'bearish';
        return 'neutral';
    }

    private static function direction_reason(array $axis) {
        return sprintf('4H ADX %.1f; +DI %.1f versus -DI %.1f; 1H/4H/1D bias %s/%s/%s.', (float) ($axis['adx'] ?? 0), (float) ($axis['plus_di'] ?? 0), (float) ($axis['minus_di'] ?? 0), $axis['bias_1h'] ?? 'neutral', $axis['bias_4h'] ?? 'neutral', $axis['bias_1d'] ?? 'neutral');
    }

    private static function carry_reason(array $axis) {
        if (($axis['data_status'] ?? '') === 'unavailable') return 'Funding and futures basis are temporarily unavailable; carry is excluded from the conclusion.';
        return sprintf('Funding %.4f%%; mark-index basis %.3f%%.', 100 * (float) ($axis['funding_rate'] ?? 0), (float) ($axis['basis_pct'] ?? 0));
    }

    private static function structure_reason(array $axis) {
        return sprintf('4H structure %s; daily structure %s; range %.2f–%.2f.', $axis['state'] ?? 'range', $axis['state_1d'] ?? 'range', (float) ($axis['last_swing_low'] ?? 0), (float) ($axis['last_swing_high'] ?? 0));
    }

    private static function crowding_reason(array $axis) {
        if (!isset($axis['oi_change_24h_pct']) && !isset($axis['global_long_short_ratio']) && !isset($axis['taker_buy_sell_ratio'])) {
            return sprintf('Derivatives positioning is temporarily unavailable; spot price changed %.2f%% over 24H.', (float) ($axis['price_change_24h_pct'] ?? 0));
        }
        $ls = isset($axis['global_long_short_ratio']) ? number_format((float) $axis['global_long_short_ratio'], 2) : 'N/A';
        $taker = isset($axis['taker_buy_sell_ratio']) ? number_format((float) $axis['taker_buy_sell_ratio'], 2) : 'N/A';
        return sprintf('24H OI %.2f%%; price %.2f%%; accounts L/S %s; taker buy/sell %s.', (float) ($axis['oi_change_24h_pct'] ?? 0), (float) ($axis['price_change_24h_pct'] ?? 0), $ls, $taker);
    }

    private static function risk_levels(array $data, $bias) {
        $price = (float) ($data['close'] ?? 0);
        $atr = $price * ((float) ($data['volatility']['atr_pct_1h'] ?? $data['volatility']['atr_pct_4h'] ?? 0) / 100);
        $support = (float) ($data['structure']['operational_support_1h'] ?? $data['structure']['near_support'] ?? 0);
        $resistance = (float) ($data['structure']['operational_resistance_1h'] ?? $data['structure']['near_resistance'] ?? 0);
        $zone_buffer = $atr * 0.18;
        $invalidation = $bias === 'bullish' ? max(0, $support - ($atr * 0.55)) : ($bias === 'bearish' ? $resistance + ($atr * 0.55) : 0);
        $distance = ($price > 0 && $invalidation > 0) ? abs($price - $invalidation) / $price * 100 : 0;
        return [
            'support' => $support,
            'resistance' => $resistance,
            'support_zone_low' => max(0, $support - $zone_buffer),
            'support_zone_high' => $support + $zone_buffer,
            'resistance_zone_low' => max(0, $resistance - $zone_buffer),
            'resistance_zone_high' => $resistance + $zone_buffer,
            'timeframe' => '1H',
            'source' => (string) ($data['structure']['operational_source'] ?? '1h_operational'),
            'resistance_status' => (string) ($data['structure']['resistance_status_1h'] ?? 'unknown'),
            'support_status' => (string) ($data['structure']['support_status_1h'] ?? 'unknown'),
            'recent_high' => (float) ($data['structure']['recent_high_1h'] ?? 0),
            'recent_low' => (float) ($data['structure']['recent_low_1h'] ?? 0),
            'confirmation_rule' => 'two_closed_1h_candles',
            'invalidation' => $invalidation,
            'invalidation_distance_pct' => round($distance, 2),
            'warning' => $distance > 5 ? 'invalidation_too_far' : ($invalidation > 0 ? 'ok' : 'neutral_bias'),
        ];
    }
}
