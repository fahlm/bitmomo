<?php
if (!defined('ABSPATH')) exit;

/**
 * Derives deterministic customer-facing market factors for the FREE BTC
 * Intelligence surface. This class is presentation-only: it consumes the
 * existing five-axis evaluation and does not change scoring or thresholds.
 */
final class Bitmomo_AI_Key_Drivers {

    const MAX_DRIVERS = 6;

    public static function derive(array $evaluation) {
        $axes = is_array($evaluation['axes'] ?? null) ? $evaluation['axes'] : [];
        $candidates = self::build_candidates($axes);
        $ranked = self::rank_and_cap($candidates, self::MAX_DRIVERS);

        if (empty($ranked)) {
            return [self::calm_market_text()];
        }

        return array_values(array_map(static function ($candidate) {
            return $candidate['text'];
        }, $ranked));
    }

    public static function rank_and_cap(array $candidates, $max) {
        $max = max(1, (int) $max);
        $indexed = [];
        foreach (array_values($candidates) as $i => $candidate) {
            $candidate['__order'] = $i;
            $indexed[] = $candidate;
        }

        usort($indexed, static function ($a, $b) {
            if ($a['materiality'] === $b['materiality']) {
                return $a['__order'] <=> $b['__order'];
            }
            return $b['materiality'] <=> $a['materiality'];
        });

        return array_slice($indexed, 0, $max);
    }

    private static function build_candidates(array $axes) {
        $candidates = [];

        $direction = is_array($axes['direction'] ?? null) ? $axes['direction'] : [];
        $structure = is_array($axes['structure'] ?? null) ? $axes['structure'] : [];
        $carry = is_array($axes['carry'] ?? null) ? $axes['carry'] : [];
        $crowding = is_array($axes['crowding'] ?? null) ? $axes['crowding'] : [];
        $volatility = is_array($axes['volatility'] ?? null) ? $axes['volatility'] : [];

        $direction_score = (int) ($direction['score'] ?? 0);
        $structure_score = (int) ($structure['score'] ?? 0);
        $direction_material = self::is_material($direction['status'] ?? 'neutral');
        $structure_material = self::is_material($structure['status'] ?? 'neutral');

        if ($direction_material && $structure_material && self::same_sign($direction_score, $structure_score)) {
            $candidates[] = self::combined_direction_structure_candidate($direction_score, $structure_score);
        } else {
            if ($direction_material) {
                $candidates[] = self::direction_candidate($direction_score);
            }
            if ($structure_material) {
                $candidates[] = self::structure_candidate($structure_score, (string) ($structure['state'] ?? 'range'));
            }
        }

        $carry_unavailable = 'unavailable' === (string) ($carry['data_status'] ?? '');
        if (!$carry_unavailable && self::is_material($carry['status'] ?? 'neutral')) {
            $candidates[] = self::carry_candidate($carry);
        }

        if (self::is_material($crowding['status'] ?? 'neutral')) {
            $candidates[] = self::crowding_candidate($crowding);
        }

        $regime = (string) ($volatility['status'] ?? 'normal');
        if ('normal' !== $regime && '' !== $regime) {
            $volatility_candidate = self::volatility_candidate($regime);
            if (null !== $volatility_candidate) {
                $candidates[] = $volatility_candidate;
            }
        }

        return $candidates;
    }

    private static function is_material($status) {
        return 'neutral' !== (string) $status;
    }

    private static function same_sign($a, $b) {
        return ($a > 0 && $b > 0) || ($a < 0 && $b < 0);
    }

    private static function combined_direction_structure_candidate($direction_score, $structure_score) {
        $bullish = $direction_score > 0;
        $magnitude = max(abs($direction_score), abs($structure_score));
        $text = $bullish
            ? __('Momentum dan struktur harga sama-sama mengonfirmasi bias bullish.', 'bitmomo-ai')
            : __('Momentum dan struktur harga sama-sama mengonfirmasi bias bearish.', 'bitmomo-ai');
        return ['key' => 'direction_structure', 'materiality' => $magnitude, 'text' => $text];
    }

    private static function direction_candidate($score) {
        $abs = abs($score);
        if ($score > 0) {
            $text = $abs >= 60
                ? __('Momentum harga menunjukkan dorongan bullish yang kuat.', 'bitmomo-ai')
                : __('Momentum harga menunjukkan penguatan dengan bias bullish.', 'bitmomo-ai');
        } else {
            $text = $abs >= 60
                ? __('Momentum harga menunjukkan tekanan bearish yang kuat.', 'bitmomo-ai')
                : __('Momentum harga menunjukkan pelemahan dengan bias bearish.', 'bitmomo-ai');
        }
        return ['key' => 'direction', 'materiality' => $abs, 'text' => $text];
    }

    private static function structure_candidate($score, $state) {
        $abs = abs($score);
        switch ($state) {
            case 'breakout_up':
                $text = __('Struktur harga mencatat breakout di atas level teknikal utama.', 'bitmomo-ai');
                break;
            case 'hh_hl':
                $text = __('Struktur harga jangka pendek tetap konstruktif dengan pola higher high dan higher low.', 'bitmomo-ai');
                break;
            case 'breakout_down':
                $text = __('Struktur harga mencatat breakdown di bawah level teknikal utama.', 'bitmomo-ai');
                break;
            case 'lh_ll':
                $text = __('Struktur harga jangka pendek tetap lemah dengan pola lower high dan lower low.', 'bitmomo-ai');
                break;
            default:
                $text = $score > 0
                    ? __('Struktur harga saat ini menunjukkan bias bullish.', 'bitmomo-ai')
                    : __('Struktur harga saat ini menunjukkan bias bearish.', 'bitmomo-ai');
        }
        return ['key' => 'structure', 'materiality' => $abs, 'text' => $text];
    }

    private static function carry_candidate(array $carry) {
        $score = (int) ($carry['score'] ?? 0);
        $abs = abs($score);
        $funding = (float) ($carry['funding_rate'] ?? 0);
        $basis = (float) ($carry['basis_pct'] ?? 0);
        $funding_long_elevated = $funding >= 0.00025;
        $funding_short_elevated = $funding <= -0.00025;
        $basis_long_elevated = $basis > 0.15;
        $basis_short_elevated = $basis < -0.15;

        if ($score < 0) {
            if ($funding_long_elevated && $basis_long_elevated) {
                $text = __('Futures BTC diperdagangkan dengan premi terhadap spot, disertai biaya funding long yang meningkat.', 'bitmomo-ai');
            } elseif ($basis_long_elevated) {
                $text = __('Futures BTC diperdagangkan dengan premi terhadap harga spot.', 'bitmomo-ai');
            } else {
                $text = __('Biaya funding untuk posisi long berada di atas kondisi normal.', 'bitmomo-ai');
            }
        } elseif ($score > 0) {
            if ($funding_short_elevated && $basis_short_elevated) {
                $text = __('Futures BTC diperdagangkan dengan diskon terhadap spot, disertai biaya funding short yang meningkat.', 'bitmomo-ai');
            } elseif ($basis_short_elevated) {
                $text = __('Futures BTC diperdagangkan dengan diskon terhadap harga spot.', 'bitmomo-ai');
            } else {
                $text = __('Biaya funding untuk posisi short berada di atas kondisi normal.', 'bitmomo-ai');
            }
        } else {
            $text = __('Pricing futures BTC belum menunjukkan tekanan yang material.', 'bitmomo-ai');
        }

        return ['key' => 'carry', 'materiality' => $abs, 'text' => $text];
    }

    private static function crowding_candidate(array $crowding) {
        $score = (int) ($crowding['score'] ?? 0);
        $abs = abs($score);
        $oi = (float) ($crowding['oi_change_24h_pct'] ?? 0);
        $price = (float) ($crowding['price_change_24h_pct'] ?? 0);
        $long_short = isset($crowding['global_long_short_ratio']) ? (float) $crowding['global_long_short_ratio'] : null;
        $taker = isset($crowding['taker_buy_sell_ratio']) ? (float) $crowding['taker_buy_sell_ratio'] : null;

        $facts = [];
        if ($oi >= 2 && 0.0 !== $price) {
            $facts[] = [
                'standalone' => __('Open interest futures BTC meningkat, menunjukkan ekspansi posisi terbuka di pasar derivatif.', 'bitmomo-ai'),
                'lead' => __('Open interest futures BTC meningkat', 'bitmomo-ai'),
                'join' => __('open interest menunjukkan ekspansi posisi terbuka', 'bitmomo-ai'),
            ];
        }
        if (null !== $taker && $taker >= 1.10) {
            $facts[] = [
                'standalone' => __('Taker flow futures menunjukkan tekanan beli yang lebih kuat daripada tekanan jual.', 'bitmomo-ai'),
                'lead' => __('Taker flow futures menunjukkan tekanan beli yang lebih kuat', 'bitmomo-ai'),
                'join' => __('tekanan beli taker lebih kuat daripada tekanan jual', 'bitmomo-ai'),
            ];
        } elseif (null !== $taker && $taker <= 0.90) {
            $facts[] = [
                'standalone' => __('Taker flow futures menunjukkan tekanan jual yang lebih kuat daripada tekanan beli.', 'bitmomo-ai'),
                'lead' => __('Taker flow futures menunjukkan tekanan jual yang lebih kuat', 'bitmomo-ai'),
                'join' => __('tekanan jual taker lebih kuat daripada tekanan beli', 'bitmomo-ai'),
            ];
        }
        if (null !== $long_short && $long_short >= 1.25) {
            $facts[] = [
                'standalone' => __('Rasio akun long/short menunjukkan proporsi akun lebih condong ke posisi long.', 'bitmomo-ai'),
                'lead' => __('Rasio akun long/short lebih condong ke posisi long', 'bitmomo-ai'),
                'join' => __('rasio akun long/short lebih condong ke posisi long', 'bitmomo-ai'),
            ];
        } elseif (null !== $long_short && $long_short <= 0.80) {
            $facts[] = [
                'standalone' => __('Rasio akun long/short menunjukkan proporsi akun lebih condong ke posisi short.', 'bitmomo-ai'),
                'lead' => __('Rasio akun long/short lebih condong ke posisi short', 'bitmomo-ai'),
                'join' => __('rasio akun long/short lebih condong ke posisi short', 'bitmomo-ai'),
            ];
        }

        if (empty($facts)) {
            $text = __('Positioning futures BTC belum menunjukkan perubahan yang material.', 'bitmomo-ai');
        } elseif (1 === count($facts)) {
            $text = $facts[0]['standalone'];
        } else {
            $text = sprintf(__('%s, sementara %s.', 'bitmomo-ai'), $facts[0]['lead'], $facts[1]['join']);
        }

        return ['key' => 'crowding', 'materiality' => $abs, 'text' => $text];
    }

    private static function volatility_candidate($regime) {
        switch ($regime) {
            case 'extreme':
                return ['key' => 'volatility', 'materiality' => 90, 'text' => __('Volatilitas BTC berada pada level sangat tinggi dengan amplitudo pergerakan jauh di atas kondisi normal.', 'bitmomo-ai')];
            case 'high':
                return ['key' => 'volatility', 'materiality' => 55, 'text' => __('Volatilitas BTC meningkat dengan amplitudo pergerakan di atas kondisi normal.', 'bitmomo-ai')];
            case 'low':
                return ['key' => 'volatility', 'materiality' => 45, 'text' => __('Volatilitas BTC berada pada level rendah dengan amplitudo pergerakan yang terbatas.', 'bitmomo-ai')];
            default:
                return null;
        }
    }

    private static function calm_market_text() {
        return __('Kondisi pasar relatif seimbang dan belum menunjukkan faktor dominan.', 'bitmomo-ai');
    }
}
