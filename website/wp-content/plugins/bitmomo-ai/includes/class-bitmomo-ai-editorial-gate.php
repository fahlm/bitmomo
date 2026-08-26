<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Editorial_Gate {
    const MAX_PUBLISH_AGE_MINUTES = 180;
    const EXPIRY_WARNING_MINUTES = 60;

    public static function register() {
        add_filter('wp_insert_post_data', [__CLASS__, 'guard_publish'], 99, 2);
        add_action('admin_notices', [__CLASS__, 'admin_notice']);
    }

    public static function status($post_id, $submitted_approval = null) {
        $quality_status = (string) get_post_meta($post_id, '_bm_quality_gate_status', true);
        $quality_passed = in_array($quality_status, ['passed', 'degraded'], true);
        $generated = strtotime((string) get_post_meta($post_id, '_bm_generated_at', true));
        $timestamp_valid = $generated && $generated <= time() + (5 * MINUTE_IN_SECONDS);
        $age_minutes = $timestamp_valid ? max(0, (int) floor((time() - $generated) / 60)) : null;
        $fresh = $timestamp_valid && $age_minutes <= self::MAX_PUBLISH_AGE_MINUTES;
        $expires_in_minutes = $fresh ? self::MAX_PUBLISH_AGE_MINUTES - $age_minutes : 0;
        $near_expiry = $fresh && $expires_in_minutes <= self::EXPIRY_WARNING_MINUTES;
        $editor_approved = $submitted_approval === null
            ? get_post_meta($post_id, '_bm_editor_approved', true) === 'yes'
            : (bool) $submitted_approval;
        $auto_eligible = get_post_meta($post_id, '_bm_auto_publish_eligible', true) === 'yes';
        $approved = $editor_approved || $auto_eligible;
        $reasons = [];
        if (!$quality_passed) $reasons[] = 'Quality gate belum lulus.';
        if (!$timestamp_valid) {
            $reasons[] = 'Waktu pembuatan analisis tidak ditemukan atau tidak valid.';
        } elseif (!$fresh) {
            $reasons[] = sprintf('Data analisis sudah berusia %d menit; batas publikasi %d menit.', $age_minutes, self::MAX_PUBLISH_AGE_MINUTES);
        }
        if (!$approved) $reasons[] = 'Analisis belum mendapat izin auto-publish atau persetujuan editor.';
        return [
            'ready' => $quality_passed && $fresh && $approved,
            'quality_passed' => $quality_passed,
            'quality_status' => $quality_status,
            'fresh' => $fresh,
            'age_minutes' => $age_minutes,
            'expires_in_minutes' => $expires_in_minutes,
            'near_expiry' => $near_expiry,
            'approved' => $approved,
            'editor_approved' => $editor_approved,
            'auto_eligible' => $auto_eligible,
            'reasons' => $reasons,
        ];
    }

    public static function guard_publish($data, $postarr) {
        if (($data['post_type'] ?? '') !== Bitmomo_AI_Content_Types::SIGNAL) return $data;
        if (!in_array($data['post_status'] ?? '', ['publish', 'future'], true)) return $data;
        $post_id = absint($postarr['ID'] ?? 0);
        if ($post_id && get_post_status($post_id) === 'publish') return $data;

        $submitted_approval = null;
        if (isset($_POST[Bitmomo_AI_Content_Types::NONCE]) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[Bitmomo_AI_Content_Types::NONCE])), Bitmomo_AI_Content_Types::NONCE)) {
            $submitted_approval = isset($_POST['bm_editor_approved']) && sanitize_text_field(wp_unslash($_POST['bm_editor_approved'])) === 'yes';
        }
        $status = self::status($post_id, $submitted_approval);
        if (($data['post_status'] ?? '') === 'future') {
            $status['ready'] = false;
            $status['reasons'][] = 'Penjadwalan otomatis dinonaktifkan; analisis harus diterbitkan setelah pemeriksaan editor.';
        }
        if ($status['ready']) return $data;

        $data['post_status'] = 'draft';
        $user_id = get_current_user_id();
        if ($user_id) set_transient('bitmomo_ai_editorial_block_' . $user_id, $status['reasons'], MINUTE_IN_SECONDS);
        return $data;
    }

    public static function admin_notice() {
        $user_id = get_current_user_id();
        if (!$user_id) return;
        $key = 'bitmomo_ai_editorial_block_' . $user_id;
        $reasons = get_transient($key);
        if (!$reasons) return;
        delete_transient($key);
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Publikasi ditahan oleh Bitmomo AI.', 'bitmomo-ai') . '</strong> ' . esc_html(implode(' ', (array) $reasons)) . '</p></div>';
    }
}
