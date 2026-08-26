<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Admin_Notices {
    public static function register() {
        add_action('admin_notices', [__CLASS__, 'render']);
        add_action('admin_notices', [__CLASS__, 'render_automation']);
    }

    public static function latest_status() {
        $last_run = (array) get_option('bitmomo_ai_last_run', []);
        $last_webhook = (array) get_option('bitmomo_ai_last_webhook', []);
        $run_time = strtotime((string) ($last_run['time'] ?? '')) ?: 0;
        $webhook_time = strtotime((string) ($last_webhook['time'] ?? '')) ?: 0;
        $last = $webhook_time > $run_time ? $last_webhook : $last_run;
        $post_id = absint($last['post_id'] ?? 0);
        if (!$post_id || get_post_type($post_id) !== Bitmomo_AI_Content_Types::SIGNAL) {
            return [
                'state' => in_array(($last['status'] ?? ''), ['error', 'blocked'], true) ? 'blocked' : 'empty',
                'post_id' => 0,
                'message' => (string) ($last['message'] ?? ''),
            ];
        }

        if (get_post_status($post_id) === 'publish') {
            return [
                'state' => 'published',
                'post_id' => $post_id,
                'message' => __('Analisis terbaru sudah diterbitkan.', 'bitmomo-ai'),
            ];
        }

        $release = Bitmomo_AI_Editorial_Gate::status($post_id);
        if (!$release['quality_passed']) {
            $state = 'blocked';
            $message = __('Quality gate belum lulus. Analisis tidak boleh dirilis.', 'bitmomo-ai');
        } elseif (!$release['fresh']) {
            $state = 'expired';
            $message = __('Data analisis sudah kedaluwarsa. Buat analisis baru sebelum meninjau kembali.', 'bitmomo-ai');
        } elseif ($release['near_expiry'] && !$release['approved']) {
            $state = 'expiring';
            $message = sprintf(__('Draft menunggu pemeriksaan dan data berlaku %d menit lagi.', 'bitmomo-ai'), $release['expires_in_minutes']);
        } elseif (!$release['approved']) {
            $state = 'review';
            $message = sprintf(__('Draft analisis baru menunggu pemeriksaan editor. Data berlaku %d menit lagi.', 'bitmomo-ai'), $release['expires_in_minutes']);
        } elseif ($release['near_expiry']) {
            $state = 'expiring';
            $message = sprintf(__('Pemeriksaan selesai, tetapi data hanya berlaku %d menit lagi.', 'bitmomo-ai'), $release['expires_in_minutes']);
        } else {
            $state = 'ready';
            $message = __('Semua pemeriksaan selesai. Draft siap dirilis secara manual.', 'bitmomo-ai');
        }

        return [
            'state' => $state,
            'post_id' => $post_id,
            'message' => $message,
            'release' => $release,
        ];
    }

    public static function render() {
        if (!current_user_can('edit_posts')) return;
        $status = self::latest_status();
        if (in_array($status['state'], ['empty', 'published'], true)) return;

        $notice_class = in_array($status['state'], ['blocked', 'expired'], true)
            ? 'notice-error'
            : ($status['state'] === 'ready' ? 'notice-success' : 'notice-warning');
        $actions = [];
        if (!empty($status['post_id'])) {
            $actions[] = '<a href="' . esc_url(get_edit_post_link($status['post_id'])) . '">' . esc_html__('Tinjau draft', 'bitmomo-ai') . '</a>';
        }
        if (current_user_can('manage_options')) {
            $actions[] = '<a href="' . esc_url(admin_url('tools.php?page=bitmomo-ai')) . '">' . esc_html__('Buka diagnostik', 'bitmomo-ai') . '</a>';
        }

        echo '<div class="notice ' . esc_attr($notice_class) . '"><p><strong>' . esc_html__('Bitmomo AI:', 'bitmomo-ai') . '</strong> ' . esc_html($status['message']);
        if ($actions) echo ' ' . wp_kses_post(implode(' · ', $actions));
        echo '</p></div>';
    }

    public static function render_automation() {
        if (!current_user_can('manage_options')) return;
        $health = Bitmomo_AI_Scheduler::automation_health();
        if (!in_array($health['state'], ['not_scheduled', 'overdue', 'failed'], true)) return;
        $class = $health['state'] === 'overdue' ? 'notice-warning' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p><strong>' . esc_html__('Otomasi Bitmomo AI:', 'bitmomo-ai') . '</strong> ' . esc_html($health['message']) . ' <a href="' . esc_url(admin_url('tools.php?page=bitmomo-ai')) . '">' . esc_html__('Periksa diagnostik', 'bitmomo-ai') . '</a></p></div>';
    }
}
