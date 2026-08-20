<?php
/**
 * Plugin Name: Bitmomo AI
 * Description: Editorial foundation for AI Market Insight and Bitcoin Signal.
 * Version: 0.1.0
 * Author: Bitmomo
 * Text Domain: bitmomo-ai
 */

if (!defined('ABSPATH')) exit;

define('BITMOMO_AI_VERSION', '0.1.0');
define('BITMOMO_AI_FILE', __FILE__);
define('BITMOMO_AI_DIR', plugin_dir_path(__FILE__));
define('BITMOMO_AI_URL', plugin_dir_url(__FILE__));

require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-content-types.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-shortcodes.php';

final class Bitmomo_AI_Plugin {
    public static function boot() {
        add_action('init', ['Bitmomo_AI_Content_Types', 'register']);
        add_action('add_meta_boxes', ['Bitmomo_AI_Content_Types', 'add_meta_boxes']);
        add_action('save_post', ['Bitmomo_AI_Content_Types', 'save_meta']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        Bitmomo_AI_Shortcodes::register();
    }

    public static function enqueue_assets() {
        if (!is_singular() && !is_front_page() && !is_home()) return;
        wp_enqueue_style('bitmomo-ai', BITMOMO_AI_URL . 'assets/css/frontend.css', [], BITMOMO_AI_VERSION);
    }
}

Bitmomo_AI_Plugin::boot();

register_activation_hook(__FILE__, function () {
    Bitmomo_AI_Content_Types::register();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
