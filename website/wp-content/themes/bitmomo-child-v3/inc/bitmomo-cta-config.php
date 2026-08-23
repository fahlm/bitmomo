<?php
/**
 * Central affiliate / CTA configuration and outbound click tracking.
 *
 * Edit the URL, label, and disclosure text below to change or add
 * partner links -- no template file needs to change when a link changes.
 *
 * @package Bitmomo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns one CTA definition by key, or null if it doesn't exist.
 */
function bitmomo_get_cta( $key ) {
    $ctas = [
        'btc_intelligence' => [
            'label'        => 'Explore Trading Platform',
            // TODO: replace with the real affiliate/referral URL once available.
            'url'          => 'https://example.com/ref/bitmomo',
            'utm_source'   => 'bitmomo',
            'utm_medium'   => 'btc_intelligence_card',
            'utm_campaign' => 'btc_daily',
            'disclosure'   => 'Bitmomo dapat menerima komisi jika Anda mendaftar melalui tautan ini. Ini bukan nasihat finansial -- selalu lakukan riset Anda sendiri.',
            'rel'          => 'sponsored nofollow noopener',
        ],
    ];

    return $ctas[ $key ] ?? null;
}

/**
 * Builds the final outbound URL for a CTA, with UTM parameters attached.
 */
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
