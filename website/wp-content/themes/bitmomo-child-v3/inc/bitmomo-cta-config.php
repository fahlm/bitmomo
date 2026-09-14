<?php
/**
 * Dormant affiliate / CTA configuration and outbound click tracking.
 *
 * Affiliate CTAs are disabled by default for the Whitelist V1 launch. They
 * become callable only when the environment explicitly enables the capability;
 * this prevents institutional/product surfaces from accidentally reviving a
 * promotional CTA merely because legacy configuration remains in the theme.
 *
 * @package Bitmomo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Whether dormant affiliate CTA definitions may be resolved at runtime. */
function bitmomo_affiliate_ctas_enabled() {
    $enabled = defined( 'BITMOMO_AFFILIATE_CTAS_ENABLED' ) && (bool) BITMOMO_AFFILIATE_CTAS_ENABLED;
    return (bool) apply_filters( 'bitmomo_affiliate_ctas_enabled', $enabled );
}

/**
 * Returns one CTA definition by key, or null when affiliate CTAs are disabled
 * or the requested definition does not exist.
 */
function bitmomo_get_cta( $key ) {
    if ( ! bitmomo_affiliate_ctas_enabled() ) {
        return null;
    }

    $ctas = [
        'btc_intelligence' => [
            'label'        => 'Explore RedotPay Card',
            'url'          => 'https://wap.redotpay.com/en/invite/?referralId=88bh7',
            'utm_source'   => 'bitmomo',
            'utm_medium'   => 'btc_intelligence_card',
            'utm_campaign' => 'btc_daily',
            'disclosure'   => 'Bitmomo dapat menerima komisi jika Anda mendaftar RedotPay melalui tautan referral ini. Ini bukan nasihat finansial -- selalu lakukan riset Anda sendiri.',
            'rel'          => 'sponsored nofollow noopener',
        ],
    ];

    return $ctas[ $key ] ?? null;
}

/** Builds the final outbound URL for an explicitly enabled CTA. */
function bitmomo_get_cta_url( $key ) {
    $cta = bitmomo_get_cta( $key );

    if ( ! $cta || empty( $cta['url'] ) ) {
        return '';
    }

    $params = array_filter( [
        'utm_source'   => $cta['utm_source']   ?? '',
        'utm_medium'   => $cta['utm_medium']   ?? '',
        'utm_campaign' => $cta['utm_campaign'] ?? '',
    ] );

    if ( empty( $params ) ) {
        return $cta['url'];
    }

    $glue = ( false === strpos( $cta['url'], '?' ) ) ? '?' : '&';

    return $cta['url'] . $glue . http_build_query( $params );
}

/* ---------- Outbound click tracking (native WP, no external service) ---------- */

add_action( 'wp_ajax_bitmomo_cta_click', 'bitmomo_handle_cta_click' );
add_action( 'wp_ajax_nopriv_bitmomo_cta_click', 'bitmomo_handle_cta_click' );

function bitmomo_handle_cta_click() {
    check_ajax_referer( 'bitmomo_cta_click', 'nonce' );

    $key = isset( $_POST['cta'] ) ? sanitize_key( wp_unslash( $_POST['cta'] ) ) : '';

    if ( ! $key || ! bitmomo_get_cta( $key ) ) {
        wp_send_json_error( [ 'message' => 'unknown_cta' ], 400 );
    }

    $day    = current_time( 'Y-m-d' );
    $clicks = get_option( 'bitmomo_cta_clicks', [] );

    if ( ! is_array( $clicks ) ) {
        $clicks = [];
    }

    if ( ! isset( $clicks[ $key ] ) || ! is_array( $clicks[ $key ] ) ) {
        $clicks[ $key ] = [];
    }

    $clicks[ $key ][ $day ] = ( $clicks[ $key ][ $day ] ?? 0 ) + 1;

    update_option( 'bitmomo_cta_clicks', $clicks, false );

    wp_send_json_success( [ 'recorded' => true ] );
}
