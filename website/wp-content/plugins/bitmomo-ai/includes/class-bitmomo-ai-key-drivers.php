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
 *    merged with direction/structure, and crowding's copy stays magnitude-
 *    only (no directional claim its composite score cannot actually support).
 *    carry's copy describes positioning SKEW ("posisi long/short sedang
 *    dominan"), never a literal trader head-count claim, since funding/basis
 *    is a $-notional cost/pricing signal, not an account-count signal.
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

        if (self::is_material($carry['status'] ?? 'neutral')) {
            $candidates[] = self::carry_candidate((int) ($carry['score'] ?? 0));
        }

        if (self::is_material($crowding['status'] ?? 'neutral')) {
            $candidates[] = self::crowding_candidate((int) ($crowding['score'] ?? 0));
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

    private static function carry_candidate($score) {
        // Funding rate and futures basis are a $-notional cost/pricing
        // signal (what longs and shorts pay each other, and where futures
        // trade versus spot) — not a literal trader head-count. Copy
        // describes positioning SKEW/dominance, current-state only, never
        // a forward-looking consequence of that skew unwinding.
        $abs = abs($score);
        if ($score < 0) {
            // Negative carry score = funding/basis crowded long (contrarian).
            $text = $abs >= 75
                ? __('Posisi long sedang sangat dominan di pasar futures BTC.', 'bitmomo-ai')
                : __('Trader futures mulai lebih banyak mengambil posisi long.', 'bitmomo-ai');
        } else {
            // Positive carry score = funding/basis crowded short (contrarian).
            $text = $abs >= 75
                ? __('Posisi short sedang sangat dominan di pasar futures BTC.', 'bitmomo-ai')
                : __('Trader futures mulai lebih banyak mengambil posisi short.', 'bitmomo-ai');
        }
        return ['key' => 'carry', 'materiality' => $abs, 'text' => $text];
    }

    private static function crowding_candidate($score) {
        // Crowding is a mixed composite (OI+price and taker ratio are trend-
        // confirming, long/short ratio is contrarian) so its sign does not
        // reliably map to "bullish"/"bearish" — copy stays magnitude-only,
        // never asserting a direction the composite score cannot support,
        // and never a forward-looking consequence of positions unwinding.
        $abs = abs($score);
        $text = $abs >= 60
            ? __('Posisi trader di pasar futures BTC saat ini cukup padat, dengan banyak pelaku pasar mengambil posisi yang serupa.', 'bitmomo-ai')
            : __('Posisi trader di pasar futures BTC saat ini mulai lebih berat ke satu sisi.', 'bitmomo-ai');
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
