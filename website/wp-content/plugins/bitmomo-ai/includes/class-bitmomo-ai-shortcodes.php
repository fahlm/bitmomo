<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Shortcodes {
    public static function register() {
        add_shortcode('bitmomo_market_insights', [__CLASS__, 'insights']);
        add_shortcode('bitmomo_bitcoin_signal', [__CLASS__, 'signal']);
        add_shortcode('bitmomo_ai_dashboard', [__CLASS__, 'dashboard']);
    }

    public static function insights($atts) {
        $atts = shortcode_atts(['limit' => 3], $atts, 'bitmomo_market_insights');
        $query = new WP_Query([
            'post_type' => Bitmomo_AI_Content_Types::INSIGHT,
            'post_status' => 'publish',
            'posts_per_page' => min(12, max(1, absint($atts['limit']))),
            'no_found_rows' => true,
        ]);
        ob_start();
        echo '<section class="bm-ai-list" aria-label="' . esc_attr__('AI Market Insights', 'bitmomo-ai') . '">';
        while ($query->have_posts()) { $query->the_post();
            printf('<article class="bm-ai-card"><h3><a href="%s">%s</a></h3><p>%s</p><time datetime="%s">%s</time></article>', esc_url(get_permalink()), esc_html(get_the_title()), esc_html(get_the_excerpt()), esc_attr(get_the_date('c')), esc_html(get_the_date()));
        }
        echo '</section>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function signal() {
        $posts = get_posts(['post_type' => Bitmomo_AI_Content_Types::SIGNAL, 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true]);
        if (!$posts) return '';
        $post = $posts[0];
        $direction = get_post_meta($post->ID, '_bm_direction', true) ?: 'neutral';
        $confidence = min(100, max(0, absint(get_post_meta($post->ID, '_bm_confidence', true))));
        $timeframe = get_post_meta($post->ID, '_bm_timeframe', true);
        return sprintf('<section class="bm-signal bm-signal--%1$s"><p class="bm-signal-label">%2$s</p><h3><a href="%3$s">%4$s</a></h3><dl><div><dt>%5$s</dt><dd>%6$s</dd></div><div><dt>%7$s</dt><dd>%8$d%%</dd></div><div><dt>%9$s</dt><dd>%10$s</dd></div></dl><p class="bm-signal-disclaimer">%11$s</p></section>', esc_attr($direction), esc_html__('Latest Bitcoin Signal', 'bitmomo-ai'), esc_url(get_permalink($post)), esc_html(get_the_title($post)), esc_html__('Direction', 'bitmomo-ai'), esc_html(ucfirst($direction)), esc_html__('Confidence', 'bitmomo-ai'), $confidence, esc_html__('Timeframe', 'bitmomo-ai'), esc_html($timeframe ?: '—'), esc_html__('Educational information only; not financial advice.', 'bitmomo-ai'));
    }

    public static function dashboard() {
        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        if ($host !== 'seagreen-snail-158456.hostingersite.com' && !current_user_can('manage_options')) return '';
        $preview = get_option('bitmomo_ai_latest_preview', []);
        if (empty($preview['data']) || empty($preview['evaluation'])) return '<p>' . esc_html__('Market preview is not available yet.', 'bitmomo-ai') . '</p>';
        $data = $preview['data'];
        $evaluation = $preview['evaluation'];
        $summary = Bitmomo_AI_Report::summary($data, $evaluation);
        $quality = $evaluation['quality'] ?? [];
        $risk = $evaluation['risk'] ?? [];
        $zones_ready = ($risk['timeframe'] ?? '') === '1H';
        $bias = $evaluation['bias'] ?? 'neutral';
        $volatility = (int) ($evaluation['axes']['volatility']['score'] ?? 0);
        $direction = (int) ($evaluation['axes']['direction']['score'] ?? 0);
        $structure = (int) ($evaluation['axes']['structure']['score'] ?? 0);
        $crowding = (int) ($evaluation['axes']['crowding']['score'] ?? 0);
        $missing = (array) ($quality['optional_missing'] ?? []);
        $positioning_available = !in_array('open_interest', $missing, true) || !in_array('global_long_short_ratio', $missing, true) || !in_array('taker_buy_sell_ratio', $missing, true);
        $risk_high = $volatility >= 75 || ($risk['warning'] ?? '') === 'invalidation_too_far';
        $resistance_status = (string) ($risk['resistance_status'] ?? 'unknown');
        $support_status = (string) ($risk['support_status'] ?? 'unknown');
        $volatility_context = $volatility >= 75 ? 'volatilitas sangat tinggi' : ($volatility >= 40 ? 'volatilitas meningkat' : 'volatilitas relatif rendah');
        $headline = $bias === 'bullish'
            ? ($resistance_status === 'rejected_breakout' ? 'Arah Bitcoin masih naik, tetapi kenaikan di atas resistance belum bertahan.' : sprintf('Bitcoin masih cenderung naik dengan %s.', $volatility_context))
            : ($bias === 'bearish' ? ($support_status === 'rejected_breakdown' ? 'Arah Bitcoin masih turun, tetapi penurunan di bawah support belum bertahan.' : sprintf('Bitcoin masih cenderung turun dengan %s.', $volatility_context)) : sprintf('Arah Bitcoin belum cukup kuat dan %s.', $volatility_context));
        if ($bias === 'bullish') {
            $action = $resistance_status === 'rejected_breakout'
                ? 'Harga sempat berada di atas resistance, lalu ditutup kembali di bawahnya. Tren naik menguat jika dua penutupan harga per jam bertahan di atas resistance.'
                : ($support_status === 'rejected_breakdown'
                    ? 'Harga sempat turun di bawah support, lalu ditutup kembali di atasnya. Bias naik tetap terjaga selama harga bertahan di atas support.'
                    : 'Tren naik menguat jika dua penutupan harga per jam berada di atas resistance.' . ($volatility >= 75 ? ' Karena fluktuasi sangat tinggi, hati-hati jika ingin mengejar pergerakan harga.' : ''));
        } elseif ($bias === 'bearish') {
            $action = $support_status === 'rejected_breakdown'
                ? 'Harga sempat berada di bawah support, lalu ditutup kembali di atasnya. Tren turun menguat jika dua penutupan harga per jam bertahan di bawah support.'
                : ($resistance_status === 'rejected_breakout'
                    ? 'Harga sempat naik di atas resistance, lalu kembali turun. Tekanan jual tetap dominan selama harga bertahan di bawah resistance.'
                    : 'Tren turun menguat jika dua penutupan harga per jam berada di bawah support.' . ($volatility >= 75 ? ' Karena fluktuasi sangat tinggi, hati-hati jika ingin mengejar pergerakan harga.' : ''));
        } else {
            $action = 'Arah berikutnya akan lebih jelas setelah harga keluar dan bertahan di luar area support–resistance.';
        }
        if ($volatility >= 75 && strpos($action, 'Hati-hati jika ingin mengejar pergerakan harga.') === false) {
            $action .= ' Hati-hati jika ingin mengejar pergerakan harga.';
        }
        $support_zone = $zones_ready ? number_format_i18n((float) ($risk['support_zone_low'] ?? 0), 0) . '–' . number_format_i18n((float) ($risk['support_zone_high'] ?? 0), 0) : 'Menunggu data terbaru';
        $resistance_zone = $zones_ready ? number_format_i18n((float) ($risk['resistance_zone_low'] ?? 0), 0) . '–' . number_format_i18n((float) ($risk['resistance_zone_high'] ?? 0), 0) : 'Menunggu data terbaru';
        $invalidation_label = $zones_ready && $bias !== 'neutral' ? number_format_i18n((float) ($risk['invalidation'] ?? 0), 0) : ($zones_ready ? 'Belum terbentuk' : 'Menunggu data terbaru');
        $reasons = [
            $direction >= 60 ? 'Arah jangka pendek masih naik.' : ($direction <= -60 ? 'Arah jangka pendek masih turun.' : 'Arah jangka pendek belum jelas.'),
            $direction >= 20 ? 'Tekanan beli masih dominan.' : ($direction <= -20 ? 'Tekanan jual masih dominan.' : 'Tekanan beli dan jual masih seimbang.'),
            !$positioning_available ? 'Data posisi futures belum tersedia dan tidak digunakan dalam kesimpulan.' : ($crowding >= 20 ? 'Minat pasar meningkat, tetapi belum berlebihan.' : ($crowding <= -20 ? 'Posisi pasar cenderung ke arah turun, tetapi belum berlebihan.' : 'Minat pasar masih seimbang.')),
            $volatility >= 75 ? 'Volatilitas sangat tinggi; risiko koreksi ikut meningkat.' : ($volatility >= 40 ? 'Volatilitas meningkat; perubahan harga dapat berlangsung lebih cepat.' : 'Volatilitas relatif rendah.'),
        ];
        $direction_label = $direction >= 60 ? 'Naik kuat' : ($direction >= 20 ? 'Cenderung naik' : ($direction <= -60 ? 'Turun kuat' : ($direction <= -20 ? 'Cenderung turun' : 'Netral')));
        $activity_label = $volatility >= 75 ? 'Tinggi' : ($volatility >= 40 ? 'Sedang' : 'Rendah');
        $risk_label = $risk_high ? 'Tinggi' : ($volatility >= 40 ? 'Sedang' : 'Rendah');
        $confidence_value = (int) ($evaluation['confidence'] ?? 0);
        $confidence_label = $confidence_value >= 70 ? 'Tinggi' : ($confidence_value >= 45 ? 'Sedang' : 'Rendah');
        $pulse = [
            ['class' => 'direction', 'icon' => '↗', 'label' => 'Arah pasar', 'value' => $direction_label, 'level' => abs($direction) >= 60 ? 3 : (abs($direction) >= 20 ? 2 : 1)],
            ['class' => 'activity', 'icon' => '≈', 'label' => 'Volatilitas', 'value' => $activity_label, 'level' => $volatility >= 75 ? 3 : ($volatility >= 40 ? 2 : 1)],
            ['class' => 'risk', 'icon' => '!', 'label' => 'Risiko koreksi', 'value' => $risk_label, 'level' => $risk_high ? 3 : ($volatility >= 40 ? 2 : 1)],
            ['class' => 'confidence', 'icon' => '✓', 'label' => 'Keyakinan analisis', 'value' => $confidence_label, 'level' => $confidence_value >= 70 ? 3 : ($confidence_value >= 45 ? 2 : 1)],
        ];
        if ($bias === 'bearish') {
            $level_cards = [
                ['label' => 'Resistance terdekat', 'value' => $resistance_zone, 'text' => 'Jika harga kembali di atas resistance ini, tekanan jual mulai berkurang.', 'danger' => false],
                ['label' => 'Support terdekat', 'value' => $support_zone, 'text' => 'Jika harga bertahan di bawah support ini, tren turun semakin kuat.', 'danger' => true],
                ['label' => 'Level risiko', 'value' => $invalidation_label, 'text' => 'Jika naik di atas level ini, bias turun mulai melemah.', 'danger' => false],
            ];
            $scenarios = [
                ['label' => 'Dua penutupan harga per jam berada di bawah ' . $support_zone, 'text' => 'Arah turun menguat dan risiko penurunan berlanjut.', 'danger' => true],
                ['label' => 'Harga naik di atas ' . $invalidation_label, 'text' => 'Arah turun melemah dan peluang pemulihan meningkat.', 'danger' => false],
            ];
        } elseif ($bias === 'neutral') {
            $level_cards = [
                ['label' => 'Support', 'value' => $support_zone, 'text' => 'Harga masih tertahan di atas support ini.', 'danger' => true],
                ['label' => 'Resistance', 'value' => $resistance_zone, 'text' => 'Harga masih tertahan di bawah resistance ini.', 'danger' => false],
                ['label' => 'Status arah', 'value' => $invalidation_label, 'text' => 'Arah akan lebih jelas setelah harga bertahan di luar rentang.', 'danger' => false],
            ];
            $scenarios = [
                ['label' => 'Harga bertahan di atas ' . $resistance_zone, 'text' => 'Peluang arah naik menguat.', 'danger' => false],
                ['label' => 'Harga bertahan di bawah ' . $support_zone, 'text' => 'Peluang arah turun menguat.', 'danger' => true],
            ];
        } else {
            $level_cards = [
                ['label' => 'Support terdekat', 'value' => $support_zone, 'text' => 'Selama harga bertahan di atas support ini, bias naik masih terjaga.', 'danger' => false],
                ['label' => 'Resistance terdekat', 'value' => $resistance_zone, 'text' => $resistance_status === 'rejected_breakout' ? 'Harga sempat berada di atas resistance ini, lalu kembali turun. Dua penutupan per jam di atasnya akan memperkuat tren naik.' : 'Tren naik menguat jika harga menembus resistance ini dan dua penutupan per jam bertahan di atasnya.', 'danger' => $resistance_status === 'rejected_breakout'],
                ['label' => 'Level risiko', 'value' => $invalidation_label, 'text' => 'Jika turun di bawah level ini, risiko koreksi meningkat.', 'danger' => true],
            ];
            $scenarios = [
                ['label' => 'Dua penutupan harga per jam berada di atas ' . $resistance_zone, 'text' => 'Arah naik menguat dan peluang kenaikan berlanjut.', 'danger' => false],
                ['label' => 'Harga turun di bawah ' . $invalidation_label, 'text' => 'Arah naik mulai melemah dan risiko koreksi membesar.', 'danger' => true],
            ];
        }
        ob_start();
        ?>
        <section class="bm-brief bm-brief--<?php echo esc_attr($bias); ?>" aria-label="Analisis Bitcoin Bitmomo">
            <header class="bm-brief__hero"><p class="bm-brief__eyebrow">BITMOMO AI · ANALISIS HARI INI</p><h2><?php echo esc_html($headline); ?></h2><div class="bm-brief__action"><span>Kondisi terbaru</span><strong><?php echo esc_html($action); ?></strong></div></header>
            <section class="bm-pulse" aria-label="Kondisi pasar saat ini"><?php foreach ($pulse as $item) : ?><article class="bm-pulse__item bm-pulse__item--<?php echo esc_attr($item['class']); ?>"><span class="bm-pulse__icon" aria-hidden="true"><?php echo esc_html($item['icon']); ?></span><div><small><?php echo esc_html($item['label']); ?></small><strong><?php echo esc_html($item['value']); ?></strong><span class="bm-pulse__scale" aria-label="Tingkat <?php echo esc_attr($item['level']); ?> dari 3"><?php for ($i = 1; $i <= 3; $i++) echo '<i class="' . ($i <= $item['level'] ? 'is-active' : '') . '"></i>'; ?></span></div></article><?php endforeach; ?></section>
            <div class="bm-brief__grid"><section class="bm-brief__reasons"><p class="bm-brief__section-label">Dasar analisis</p><h3>Faktor utama hari ini</h3><ul><?php foreach ($reasons as $reason) echo '<li>' . esc_html($reason) . '</li>'; ?></ul></section>
                <section class="bm-brief__levels"><p class="bm-brief__section-label">Area penting</p><h3>Level teknikal utama</h3>
                    <?php foreach ($level_cards as $card) : ?><article class="<?php echo $card['danger'] ? 'is-danger' : ''; ?>"><span><?php echo esc_html($card['label']); ?></span><strong><?php echo esc_html($card['value']); ?></strong><p><?php echo esc_html($zones_ready ? $card['text'] : 'Level harga per jam akan muncul setelah koneksi data pulih.'); ?></p></article><?php endforeach; ?>
                </section></div>
            <section class="bm-brief__scenarios"><p class="bm-brief__section-label">Dua kemungkinan</p><?php if ($zones_ready) : ?><div><?php foreach ($scenarios as $scenario) : ?><article class="<?php echo $scenario['danger'] ? 'is-danger' : ''; ?>"><span><?php echo esc_html($scenario['label']); ?></span><strong><?php echo esc_html($scenario['text']); ?></strong></article><?php endforeach; ?></div><?php else : ?><p>Zona dan kemungkinan harian sedang menunggu pembaruan data per jam. Kesimpulan lama tidak digunakan sebagai pengganti.</p><?php endif; ?></section>
            <footer>Diperbarui <?php echo esc_html(wp_date('d M Y, H:i T', strtotime($preview['time'] ?? 'now'))); ?> · data <?php echo esc_html(($quality['status'] ?? '') === 'complete' ? 'lengkap' : 'sebagian'); ?> · arah dinilai dari penutupan harga per jam di Binance. Analisis ini bersifat edukasi, bukan ajakan membeli atau menjual.</footer>
        </section>
        <?php
        return ob_get_clean();
    }
}
