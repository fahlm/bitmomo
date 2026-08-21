<?php
/**
 * Plugin Name: Bitmomo AI
 * Description: Editorial foundation for AI Market Insight and Bitcoin Signal.
 * Version: 1.0.24
 * Author: Bitmomo
 * Text Domain: bitmomo-ai
 */

if (!defined('ABSPATH')) exit;

define('BITMOMO_AI_VERSION', '1.0.24');
define('BITMOMO_AI_FILE', __FILE__);
define('BITMOMO_AI_DIR', plugin_dir_path(__FILE__));
define('BITMOMO_AI_URL', plugin_dir_url(__FILE__));

require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-content-types.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-shortcodes.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-signal-engine.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-quality-gate.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-performance.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-editorial-gate.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-admin-notices.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-report.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-webhook.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-binance.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-scheduler.php';

final class Bitmomo_AI_Plugin {
    public static function boot() {
        add_action('init', ['Bitmomo_AI_Content_Types', 'register']);
        add_action('add_meta_boxes', ['Bitmomo_AI_Content_Types', 'add_meta_boxes']);
        add_action('save_post', ['Bitmomo_AI_Content_Types', 'save_meta']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        Bitmomo_AI_Webhook::register();
        Bitmomo_AI_Scheduler::register();
        Bitmomo_AI_Shortcodes::register();
        Bitmomo_AI_Editorial_Gate::register();
        Bitmomo_AI_Admin_Notices::register();
    }

    public static function enqueue_assets() {
        if (!is_singular() && !is_front_page() && !is_home()) return;
        wp_enqueue_style('bitmomo-ai', BITMOMO_AI_URL . 'assets/css/frontend.css', [], BITMOMO_AI_VERSION);
        wp_enqueue_style('bitmomo-ai-dashboard-layout', BITMOMO_AI_URL . 'assets/css/dashboard-layout.css', ['bitmomo-ai'], BITMOMO_AI_VERSION);
        wp_enqueue_style('bitmomo-ai-audience-dashboard', BITMOMO_AI_URL . 'assets/css/audience-dashboard.css', ['bitmomo-ai-dashboard-layout'], BITMOMO_AI_VERSION);
        wp_enqueue_style('bitmomo-ai-brief-dashboard', BITMOMO_AI_URL . 'assets/css/brief-dashboard.css', ['bitmomo-ai-audience-dashboard'], BITMOMO_AI_VERSION);
    }

    public static function enqueue_admin_assets($hook_suffix) {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) return;
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== Bitmomo_AI_Content_Types::SIGNAL) return;
        wp_enqueue_style('bitmomo-ai-admin', BITMOMO_AI_URL . 'assets/css/admin.css', [], BITMOMO_AI_VERSION);
    }
}

Bitmomo_AI_Plugin::boot();

register_activation_hook(__FILE__, function () {
    Bitmomo_AI_Content_Types::register();
    Bitmomo_AI_Scheduler::schedule();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    Bitmomo_AI_Scheduler::unschedule();
    flush_rewrite_rules();
});
