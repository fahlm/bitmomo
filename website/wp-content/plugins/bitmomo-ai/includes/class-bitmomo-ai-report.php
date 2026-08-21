<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Report {
    public static function summary(array $data, array $evaluation) {
        $bias = $evaluation['bias'] ?? 'neutral';
        $labels = ['bullish' => 'bullish', 'bearish' => 'bearish', 'neutral' => 'netral'];
        $structure = $evaluation['axes']['structure'] ?? [];
        $risk = $evaluation['risk'] ?? [];
        $invalidation = (float) ($risk['invalidation'] ?? 0);
        $risk_warning = ($risk['warning'] ?? '') === 'invalidation_too_far' ? ' Jaraknya lebih dari 5% dari harga saat ini; editor wajib meninjau ulang.' : '';

        return [
            'headline' => sprintf('Bias Bitcoin %s dengan keyakinan %d%%.', $labels[$bias] ?? 'netral', (int) ($evaluation['confidence'] ?? 0)),
            'context' => sprintf('Analisis memakai 4H sebagai timeframe utama, 1H sebagai konfirmasi taktis, dan 1D sebagai penjaga rezim. Harga snapshot BTCUSDT: %s.', number_format_i18n((float) ($data['close'] ?? 0), 2)),
            'bull_case' => sprintf('Skenario bullish menguat jika harga bertahan di atas swing high 4H %s dengan arah dan open interest yang ikut mengonfirmasi.', number_format_i18n((float) ($structure['last_swing_high'] ?? 0), 2)),
            'bear_case' => sprintf('Skenario bearish menguat jika harga menembus swing low 4H %s dan tekanan jual taker meningkat.', number_format_i18n((float) ($structure['last_swing_low'] ?? 0), 2)),
            'invalidation' => $invalidation > 0 ? sprintf('Level invalidasi bias utama: %s (jarak %.2f%%).%s', number_format_i18n($invalidation, 2), (float) ($risk['invalidation_distance_pct'] ?? 0), $risk_warning) : 'Bias masih netral; gunakan batas swing 4H sebagai pemicu, bukan sebagai sinyal masuk otomatis.',
        ];
    }

    public static function content(array $data, array $evaluation) {
        $bias = $evaluation['bias'] ?? 'neutral';
        $quality = $evaluation['quality'] ?? [];
        $risk = $evaluation['risk'] ?? [];
        $direction = (int) ($evaluation['axes']['direction']['score'] ?? 0);
        $crowding = (int) ($evaluation['axes']['crowding']['score'] ?? 0);
        $volatility = (int) ($evaluation['axes']['volatility']['score'] ?? 0);
        $resistance_status = (string) ($risk['resistance_status'] ?? 'unknown');
        $support_status = (string) ($risk['support_status'] ?? 'unknown');
        $zone = number_format_i18n((float) ($risk['resistance_zone_low'] ?? 0), 0) . '–' . number_format_i18n((float) ($risk['resistance_zone_high'] ?? 0), 0);
        $support = number_format_i18n((float) ($risk['support_zone_low'] ?? 0), 0) . '–' . number_format_i18n((float) ($risk['support_zone_high'] ?? 0), 0);
        $invalidation = $bias === 'neutral' ? 'Belum terbentuk' : number_format_i18n((float) ($risk['invalidation'] ?? 0), 0);
        $volatility_context = $volatility >= 75 ? 'volatilitas sangat tinggi' : ($volatility >= 40 ? 'volatilitas meningkat' : 'volatilitas relatif rendah');
        $headline = $bias === 'bullish'
            ? ($resistance_status === 'rejected_breakout' ? 'Arah Bitcoin masih naik, tetapi kenaikan di atas resistance belum bertahan.' : sprintf('Bitcoin masih cenderung naik dengan %s.', $volatility_context))
            : ($bias === 'bearish' ? sprintf('Bitcoin masih cenderung turun dengan %s.', $volatility_context) : sprintf('Arah Bitcoin belum cukup kuat dan %s.', $volatility_context));
        $positioning = $crowding >= 20 ? 'Minat pasar meningkat, tetapi belum berlebihan.' : ($crowding <= -20 ? 'Posisi pasar cenderung ke arah turun, tetapi belum berlebihan.' : 'Minat pasar masih seimbang.');
        $attention = $volatility >= 75
            ? 'Fluktuasi saat ini sangat tinggi. Hati-hati jika ingin mengejar pergerakan harga.'
            : ($volatility >= 40 ? 'Fluktuasi sedang meningkat. Reaksi harga di support dan resistance menjadi penting.' : 'Fluktuasi relatif rendah, tetapi arah tetap perlu dikonfirmasi oleh penutupan harga.');
        $zone_context = $resistance_status === 'rejected_breakout'
            ? sprintf('Harga sempat berada di atas resistance %s, lalu kembali turun.', $zone)
            : sprintf('Tren naik menguat jika harga menembus resistance %s dan bertahan di atasnya.', $zone);
        $scenario_up = sprintf('Jika dua penutupan harga per jam berada di atas %s, arah naik menjadi lebih kuat.', $zone);
        $scenario_down = sprintf('Jika harga jatuh di bawah %s, arah naik mulai melemah dan risiko koreksi membesar.', $invalidation);
        $invalidation_heading = 'Bias naik melemah di bawah:';
        if ($bias === 'bearish') {
            $scenario_up = sprintf('Jika harga kembali bertahan di atas %s, tekanan turun mulai melemah.', $zone);
            $scenario_down = sprintf('Jika dua penutupan harga per jam berada di bawah %s, arah turun menjadi lebih kuat.', $support);
            $invalidation_heading = 'Bias turun melemah di atas:';
            $zone_context = $support_status === 'rejected_breakdown'
                ? sprintf('Harga sempat berada di bawah support %s, lalu kembali naik.', $support)
                : sprintf('Tren turun menguat jika harga menembus support %s dan bertahan di bawahnya.', $support);
        } elseif ($bias === 'neutral') {
            $scenario_up = sprintf('Jika dua penutupan harga per jam berada di atas %s, peluang arah naik menjadi lebih kuat.', $zone);
            $scenario_down = sprintf('Jika dua penutupan harga per jam berada di bawah %s, peluang arah turun menjadi lebih kuat.', $support);
            $invalidation_heading = 'Arah utama:';
            $zone_context = sprintf('Arah berikutnya akan lebih jelas setelah harga bertahan di luar support %s atau resistance %s.', $support, $zone);
        }
        $data_label = ($quality['status'] ?? '') === 'complete' ? 'lengkap' : 'sebagian';
        $updated = wp_date('d F Y, H:i T', strtotime($data['timestamp'] ?? 'now'), new DateTimeZone('Asia/Jakarta'));

        return sprintf(
            "<!-- wp:heading --><h2>Kesimpulan Hari Ini</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p><strong>%s</strong></p><!-- /wp:paragraph -->\n<!-- wp:heading {\"level\":3} --><h3>Yang Perlu Diperhatikan</h3><!-- /wp:heading -->\n<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->\n<!-- wp:heading {\"level\":3} --><h3>Faktor Utama Hari Ini</h3><!-- /wp:heading -->\n<!-- wp:list --><ul><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ul><!-- /wp:list -->\n<!-- wp:heading {\"level\":3} --><h3>Level Teknikal Utama</h3><!-- /wp:heading -->\n<!-- wp:list --><ul><li><strong>Support:</strong> %s</li><li><strong>Resistance:</strong> %s</li><li><strong>%s</strong> %s</li></ul><!-- /wp:list -->\n<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->\n<!-- wp:heading {\"level\":3} --><h3>Dua Kemungkinan</h3><!-- /wp:heading -->\n<!-- wp:list --><ul><li>%s</li><li>%s</li></ul><!-- /wp:list -->\n<!-- wp:paragraph --><p><small>Diperbarui %s · data %s. Arah dinilai dari penutupan harga per jam di Binance.</small></p><!-- /wp:paragraph -->\n<!-- wp:paragraph --><p><em>Informasi ini bersifat edukasi, bukan ajakan membeli atau menjual.</em></p><!-- /wp:paragraph -->",
            esc_html($headline),
            esc_html($attention),
            esc_html($direction >= 20 ? 'Tekanan beli masih dominan.' : ($direction <= -20 ? 'Tekanan jual masih dominan.' : 'Tekanan beli dan jual masih seimbang.')),
            esc_html($positioning),
            esc_html($volatility >= 75 ? 'Volatilitas sangat tinggi; risiko koreksi ikut meningkat.' : ($volatility >= 40 ? 'Volatilitas meningkat; perubahan harga dapat berlangsung lebih cepat.' : 'Volatilitas relatif rendah.')),
            esc_html('Harga acuan saat analisis: ' . number_format_i18n((float) ($data['close'] ?? 0), 0) . '.'),
            esc_html($support), esc_html($zone), esc_html($invalidation_heading), esc_html($invalidation), esc_html($zone_context),
            esc_html($scenario_up), esc_html($scenario_down), esc_html($updated), esc_html($data_label)
        );
    }
}
