<?php
if (!defined('ABSPATH')) exit;

/**
 * Derives the customer-facing "Faktor Utama" (Key Drivers) list for the FREE
 * BTC Daily Intelligence surface.
 *
 * Replaces the old single-"primary driver" idea (still preserved separately
 * as Bitmomo_AI_Intelligence::primary_driver() for backward compatibility)
 * with a dynamic, ranked, capped (1-6) list of natural-Indonesian sentences
 * describing WHY BTC's current condition looks the way it does right now.
 *
 * CORE COPY PRINCIPLE: "describe the market, not the indicator." Copy never
 * translates internal axis/score/quant terminology literally — it translates
 * the MEANING of the data into plain Indonesian a retail crypto user
 * understands on first read, without needing to know what an "axis",
 * "carry", "crowding composite", or "pembacaan" even is.
 *
 * Design constraints:
 *  - Deterministic, table-driven copy. No LLM call, no randomness.
 *  - Reads only Bitmomo_AI_Signal_Engine::evaluate()'s existing `axes` output
 *    — no new scoring, no new external data, no scoring-methodology change.
 *  - A factor is "material" using the engine's own score_status() semantics
 *    (its own definition of "not neutral"), never an invented threshold.
 *  - direction and structure are the only two axes that share a common
 *    bullish/bearish sign convention, so they are the only pair ever merged
 *    into one line — and only when both are material AND same-signed, to
 *    avoid two lines saying the same thing. When they conflict, both are
 *    kept (conflicting evidence must not be hidden).
 *  - carry uses a CONTRARIAN sign convention (funding/basis crowded long ->
 *    negative score) and crowding is a mixed composite — neither is ever
 *    merged with direction/structure. Both carry_candidate() and
 *    crowding_candidate() receive the FULL axis array (not just the final
 *    score) and re-inspect the same underlying fields/thresholds
 *    carry_score()/crowding_score() already use — never crowding_score()
 *    or carry_score() themselves — purely so the copy can describe exactly
 *    which observable condition(s) actually contributed, instead of
 *    over-translating an aggregate score into a claim those fields alone
 *    cannot prove. A futures contract always has a long AND a short
 *    counterparty, so funding/basis (carry) never imply a trader head-count
 *    or a "dominant side"; they describe the futures market's own PRICING
 *    (funding = cost transferred between sides, basis = futures-vs-spot
 *    premium/discount). Crowding's OI-change and taker-ratio facts are
 *    trend-confirming while its account long/short ratio is contrarian, so
 *    they are reported as separate observed facts (at most two, joined
 *    with "sementara") rather than folded into one directional or
 *    "crowded/concentrated" conclusion the composite cannot support.
 *    carry additionally has an explicit fail-closed guard in
 *    build_candidates(): data_status === 'unavailable' unconditionally
 *    suppresses the carry candidate, matching
 *    Bitmomo_AI_Signal_Engine::carry_reason()'s own convention, even if a
 *    malformed/stale payload somehow still carries a material-looking
 *    score/status alongside it.
 *  - volatility has no comparable signed score; its own categorical `regime`
 *    (extreme/high/low/normal, already computed by the engine) drives both
 *    inclusion and a presentation-only materiality rank — not a scoring
 *    change, since 'regime' is already stored, deterministic engine output.
 *  - Current-state / descriptive only. Never forward-looking (no scenario
 *    map, no "what to watch next", no invalidation trigger, no conditional
 *    consequence language such as "jika", "berpotensi", "membuka ruang") —
 *    that stays exclusively Pro content, per
 *    Bitmomo_AI_Intelligence::pro_projection().
 *  - If nothing clears the materiality bar, returns one honest "calm
 *    market" sentence rather than dressing up a weak axis as material.
 */
final class Bitmomo_AI_Key_Drivers {

    const MAX_DRIVERS = 6;

    /**
     * @param array $evaluation Bitmomo_AI_Signal_Engine::evaluate()'s return value.
     * @return string[] 1 to self::MAX_DRIVERS natural-Indonesian sentences, strongest first.
     */
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

    /**
     * Pure selection primitive: sort candidates by materiality (descending,
     * stable on ties by original order) and cap to $max. Kept generic over
     * an arbitrary candidate list (not hardcoded to the 5-axis engine) so it
     * is directly testable in isolation with synthetic >6-candidate input.
     *
     * @param array $candidates Each item: ['key'=>string,'materiality'=>number,'text'=>string].
     * @param int   $max
     * @return array
     */
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

    /**
     * Builds the raw (pre-rank, pre-cap) candidate list from the real
     * 5-axis engine output. Structurally can never return more than 5
     * candidates: direction+structure contribute either one merged entry
     * (material and same-signed) or up to two separate entries (material
     * and conflicting/opposite-signed) — never zero and never more than
     * two — plus carry, crowding, and volatility contributing at most one
     * each. Worst case is 2 + 1 + 1 + 1 = 5, which is below
     * self::MAX_DRIVERS (6); the generic 6-slot display cap exists for
     * forward compatibility with a future axis, not because today's engine
     * can produce a 6th candidate.
     *
     * @return array
     */
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

        // Explicit fail-closed guard: the Signal Engine's own convention is
        // that unavailable carry data (no funding/basis feed) must be
        // excluded from the conclusion — see
        // Bitmomo_AI_Signal_Engine::carry_reason(). carry_score() already
        // returns 0 for an unavailable/all-zero payload, which is_material()
        // would normally catch, but this check makes the exclusion explicit
        // and unconditional: a malformed or stale payload that somehow
        // carries a non-zero/material-looking score or status alongside
        // data_status === 'unavailable' must still never produce a
        // customer-facing carry Key Driver, and produces no fallback
        // sentence in its place — the other candidates are unaffected.
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
            ? __('Momentum dan struktur harga BTC saat ini sama-sama mendukung arah naik.', 'bitmomo-ai')
            : __('Momentum dan struktur harga BTC saat ini sama-sama menunjukkan tekanan ke arah turun.', 'bitmomo-ai');
        return ['key' => 'direction_structure', 'materiality' => $magnitude, 'text' => $text];
    }

    private static function direction_candidate($score) {
        $abs = abs($score);
        if ($score > 0) {
            $text = $abs >= 60
                ? __('Momentum harga BTC saat ini cukup kuat ke arah naik.', 'bitmomo-ai')
                : __('Momentum harga BTC mulai menguat ke arah naik.', 'bitmomo-ai');
        } else {
            $text = $abs >= 60
                ? __('Momentum harga BTC saat ini cukup kuat ke arah turun.', 'bitmomo-ai')
                : __('Momentum harga BTC mulai melemah dan condong ke arah turun.', 'bitmomo-ai');
        }
        return ['key' => 'direction', 'materiality' => $abs, 'text' => $text];
    }

    private static function structure_candidate($score, $state) {
        $abs = abs($score);
        switch ($state) {
            case 'breakout_up':
                $text = __('Struktur harga BTC baru saja menembus level penting ke atas.', 'bitmomo-ai');
                break;
            case 'hh_hl':
                $text = __('Struktur harga BTC jangka pendek masih menunjukkan pola naik.', 'bitmomo-ai');
                break;
            case 'breakout_down':
                $text = __('Struktur harga BTC baru saja menembus level penting ke bawah.', 'bitmomo-ai');
                break;
            case 'lh_ll':
                $text = __('Struktur harga BTC jangka pendek masih menunjukkan pola turun.', 'bitmomo-ai');
                break;
            default:
                // Defensive fallback: score is material but the state string
                // is unrecognised. Describe from the score alone rather than
                // guessing at a state-specific claim the data doesn't support.
                $text = $score > 0
                    ? __('Struktur harga BTC saat ini condong naik.', 'bitmomo-ai')
                    : __('Struktur harga BTC saat ini condong turun.', 'bitmomo-ai');
        }
        return ['key' => 'structure', 'materiality' => $abs, 'text' => $text];
    }

    /**
     * @param array $carry The full axes['carry'] array: score/status plus
     *              the raw funding_rate/basis_pct/data_status fields.
     */
    private static function carry_candidate(array $carry) {
        // A futures contract always has a long AND a short counterparty —
        // funding rate and futures basis describe the market's own PRICING
        // (what longs and shorts pay each other; where futures trade versus
        // spot), never a trader head-count or a "dominant side" claim those
        // two $-notional fields alone cannot prove. Copy re-checks the same
        // thresholds Bitmomo_AI_Signal_Engine::carry_score() already uses
        // (0.00025/0.0005 for funding, 0.15/0.25 for basis) so it can say
        // exactly which condition(s) are actually elevated.
        $score = (int) ($carry['score'] ?? 0);
        $abs = abs($score);
        $funding = (float) ($carry['funding_rate'] ?? 0);
        $basis = (float) ($carry['basis_pct'] ?? 0);
        $funding_long_elevated = $funding >= 0.00025;
        $funding_short_elevated = $funding <= -0.00025;
        $basis_long_elevated = $basis > 0.15;
        $basis_short_elevated = $basis < -0.15;

        if ($score < 0) {
            // Funding/basis positive-elevated = long side paying a premium.
            if ($funding_long_elevated && $basis_long_elevated) {
                $text = __('Pasar futures BTC diperdagangkan di atas harga spot, sementara biaya posisi long juga lebih tinggi dari biasanya.', 'bitmomo-ai');
            } elseif ($basis_long_elevated) {
                $text = __('Harga futures BTC saat ini diperdagangkan lebih tinggi dibandingkan harga spot.', 'bitmomo-ai');
            } else {
                $text = __('Biaya mempertahankan posisi long di pasar futures BTC sedang lebih tinggi dari biasanya.', 'bitmomo-ai');
            }
        } elseif ($score > 0) {
            // Funding/basis negative-elevated = short side paying a premium.
            if ($funding_short_elevated && $basis_short_elevated) {
                $text = __('Pasar futures BTC diperdagangkan di bawah harga spot, sementara biaya posisi short juga lebih tinggi dari biasanya.', 'bitmomo-ai');
            } elseif ($basis_short_elevated) {
                $text = __('Harga futures BTC saat ini diperdagangkan lebih rendah dibandingkan harga spot.', 'bitmomo-ai');
            } else {
                $text = __('Biaya mempertahankan posisi short di pasar futures BTC sedang lebih tinggi dari biasanya.', 'bitmomo-ai');
            }
        } else {
            // Defensive: build_candidates() already gates on is_material(),
            // so a zero/unavailable carry score should never reach here.
            $text = __('Pasar futures BTC belum menunjukkan tekanan harga yang berarti.', 'bitmomo-ai');
        }

        return ['key' => 'carry', 'materiality' => $abs, 'text' => $text];
    }

    /**
     * @param array $crowding The full axes['crowding'] array: score/status
     *              plus the raw oi_change_24h_pct/price_change_24h_pct/
     *              global_long_short_ratio/taker_buy_sell_ratio fields.
     */
    private static function crowding_candidate(array $crowding) {
        // Crowding's composite score mixes OI+price and the taker ratio
        // (trend-confirming) with the account long/short ratio (contrarian),
        // so its sign does not reliably map to "bullish"/"bearish", and a
        // high magnitude does not by itself prove positions are literally
        // "crowded" or that "many traders hold the same position." Copy
        // re-checks the same fields/thresholds
        // Bitmomo_AI_Signal_Engine::crowding_score() already uses and
        // reports only the specific observed fact(s) that actually
        // contributed — at most two, joined with "sementara" — never a
        // blanket concentration/direction conclusion those fields alone
        // cannot prove.
        $score = (int) ($crowding['score'] ?? 0);
        $abs = abs($score);
        $oi = (float) ($crowding['oi_change_24h_pct'] ?? 0);
        $price = (float) ($crowding['price_change_24h_pct'] ?? 0);
        $long_short = isset($crowding['global_long_short_ratio']) ? (float) $crowding['global_long_short_ratio'] : null;
        $taker = isset($crowding['taker_buy_sell_ratio']) ? (float) $crowding['taker_buy_sell_ratio'] : null;

        $facts = [];
        if ($oi >= 2 && 0.0 !== $price) {
            $facts[] = [
                'standalone' => __('Open interest futures BTC sedang meningkat, menunjukkan lebih banyak posisi terbuka di pasar.', 'bitmomo-ai'),
                'lead' => __('Open interest futures BTC sedang meningkat', 'bitmomo-ai'),
                'join' => __('open interest futures BTC sedang meningkat', 'bitmomo-ai'),
            ];
        }
        if (null !== $taker && $taker >= 1.10) {
            $facts[] = [
                'standalone' => __('Aktivitas beli agresif di pasar futures BTC saat ini lebih kuat daripada aktivitas jual.', 'bitmomo-ai'),
                'lead' => __('Aktivitas beli agresif di pasar futures BTC saat ini lebih kuat daripada aktivitas jual', 'bitmomo-ai'),
                'join' => __('aktivitas beli agresif lebih kuat daripada aktivitas jual', 'bitmomo-ai'),
            ];
        } elseif (null !== $taker && $taker <= 0.90) {
            $facts[] = [
                'standalone' => __('Aktivitas jual agresif di pasar futures BTC saat ini lebih kuat daripada aktivitas beli.', 'bitmomo-ai'),
                'lead' => __('Aktivitas jual agresif di pasar futures BTC saat ini lebih kuat daripada aktivitas beli', 'bitmomo-ai'),
                'join' => __('aktivitas jual agresif lebih kuat daripada aktivitas beli', 'bitmomo-ai'),
            ];
        }
        if (null !== $long_short && $long_short >= 1.25) {
            // Explicitly an ACCOUNT proportion, never a position-size claim.
            $facts[] = [
                'standalone' => __('Proporsi akun trader saat ini lebih condong ke posisi long.', 'bitmomo-ai'),
                'lead' => __('Proporsi akun trader saat ini lebih condong ke posisi long', 'bitmomo-ai'),
                'join' => __('proporsi akun trader lebih condong ke posisi long', 'bitmomo-ai'),
            ];
        } elseif (null !== $long_short && $long_short <= 0.80) {
            $facts[] = [
                'standalone' => __('Proporsi akun trader saat ini lebih condong ke posisi short.', 'bitmomo-ai'),
                'lead' => __('Proporsi akun trader saat ini lebih condong ke posisi short', 'bitmomo-ai'),
                'join' => __('proporsi akun trader lebih condong ke posisi short', 'bitmomo-ai'),
            ];
        }

        if (empty($facts)) {
            // Defensive: build_candidates() already gates on is_material(),
            // so an all-zero-contribution composite should never reach here.
            $text = __('Posisi trader di pasar futures BTC belum menunjukkan perubahan yang berarti.', 'bitmomo-ai');
        } elseif (1 === count($facts)) {
            $text = $facts[0]['standalone'];
        } else {
            // Report at most the two highest-priority facts (OI, then
            // taker, then account ratio) as one natural sentence rather
            // than a three-clause run-on.
            $text = sprintf(__('%s, sementara %s.', 'bitmomo-ai'), $facts[0]['lead'], $facts[1]['join']);
        }

        return ['key' => 'crowding', 'materiality' => $abs, 'text' => $text];
    }

    private static function volatility_candidate($regime) {
        // Presentation-only materiality derived from the engine's own
        // already-computed categorical regime — not a new scoring method.
        // Copy describes what the user actually experiences (bigger/quieter
        // price moves) rather than the abstract "market stability" framing.
        switch ($regime) {
            case 'extreme':
                return ['key' => 'volatility', 'materiality' => 90, 'text' => __('Volatilitas BTC saat ini sangat tinggi dan pergerakan harga jauh lebih besar dari biasanya.', 'bitmomo-ai')];
            case 'high':
                return ['key' => 'volatility', 'materiality' => 55, 'text' => __('Volatilitas BTC sedang meningkat dan pergerakan harga menjadi lebih aktif.', 'bitmomo-ai')];
            case 'low':
                return ['key' => 'volatility', 'materiality' => 45, 'text' => __('Volatilitas BTC masih rendah dan pergerakan harga relatif tenang.', 'bitmomo-ai')];
            default:
                // Unrecognised regime string: do not fabricate a volatility claim.
                return null;
        }
    }

    private static function calm_market_text() {
        return __('Pergerakan BTC saat ini relatif tenang dan belum ada faktor yang terlihat dominan.', 'bitmomo-ai');
    }
}
