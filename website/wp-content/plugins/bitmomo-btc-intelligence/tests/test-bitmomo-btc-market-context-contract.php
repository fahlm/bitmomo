<?php
$root    = dirname( __DIR__ );
$plugin  = file_get_contents( $root . '/bitmomo-btc-intelligence.php' );
$context = file_get_contents( $root . '/includes/class-bitmomo-btc-intelligence-market-context.php' );
$js      = file_get_contents( $root . '/assets/js/market-context-explorer.js' );
$css     = file_get_contents( $root . '/assets/css/market-context-explorer.css' );
$page    = file_get_contents( $root . '/includes/class-bitmomo-btc-intelligence-page.php' );
$maincss = file_get_contents( $root . '/assets/css/bitmomo-btc-intelligence.css' );

$pass = 0;
$fail = 0;
function market_context_check( $label, $condition ) {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "[PASS] {$label}\n";
	} else {
		$fail++;
		echo "[FAIL] {$label}\n";
	}
}

market_context_check(
	'Market Context is loaded as a public BTC Intelligence boundary',
	false !== strpos( $plugin, 'class-bitmomo-btc-intelligence-market-context.php' ) &&
	false !== strpos( $plugin, 'Bitmomo_Btc_Intelligence_Market_Context::init()' )
);
market_context_check(
	'BTC asset version is bumped for deterministic cache invalidation',
	false !== strpos( $plugin, "BITMOMO_BTC_INTELLIGENCE_VERSION', '0.3.8'" )
);
market_context_check(
	'Market Context assets use file-hash cache invalidation instead of the static plugin version',
	false !== strpos( $context, 'private static function asset_version' ) &&
	false !== strpos( $context, "hash_file( 'sha256'" ) &&
	false !== strpos( $context, 'self::asset_version( $css_asset )' ) &&
	false !== strpos( $context, 'self::asset_version( $js_asset )' )
);
market_context_check(
	'Only the agreed V1 comparison universe and ranges are public',
	false !== strpos( $context, "'btc'" ) && false !== strpos( $context, "'eth'" ) &&
	false !== strpos( $context, "'sol'" ) && false !== strpos( $context, "'gold'" ) &&
	false === strpos( $context, "'doge'" ) &&
	false !== strpos( $context, "'7d'" ) && false !== strpos( $context, "'30d'" ) &&
	false !== strpos( $context, "'90d'" ) && false !== strpos( $context, "'ytd'" ) &&
	false !== strpos( $context, "'1y'" )
);
market_context_check(
	'Cross-asset comparison explicitly uses indexed-100 normalization',
	false !== strpos( $context, "'normalization'  => 'indexed_100'" ) &&
	false !== strpos( $js, '* 100' ) && false !== strpos( $js, 'Awal = 100' )
);
market_context_check(
	'Market Context visitor copy is natural Indonesian and avoids literal translation artifacts',
	false !== strpos( $js, 'Pahami BTC dalam konteks pasar yang lebih luas.' ) &&
	false !== strpos( $js, 'kapan tesis pasar berubah.' ) &&
	false === stripos( $js, 'sendirian' ) &&
	false === stripos( $js, 'thesis' )
);
market_context_check(
	'Gold is fail-closed behind an explicit provider key and never proxied with a crypto token',
	false !== strpos( $context, 'BITMOMO_ALPHA_VANTAGE_API_KEY' ) &&
	false !== strpos( $context, 'GOLD_SILVER_HISTORY' ) &&
	false !== strpos( $context, "'provider_not_configured'" ) &&
	false === stripos( $context, 'PAXG' )
);
market_context_check(
	'Binance series exposes only closed daily candles',
	false !== strpos( $context, '$close_ms > $now_ms' ) &&
	false !== strpos( $context, "'cadence'     => 'daily close'" )
);
market_context_check(
	'Provider failure is cached and protected data is never queried',
	false !== strpos( $context, 'CACHE_FAILURE_CRYPTO' ) &&
	false !== strpos( $context, 'CACHE_FAILURE_GOLD' ) &&
	false !== strpos( $context, 'cache_unavailable' ) &&
	false === strpos( $context, 'Bitmomo_Pro_Briefs' ) &&
	false === strpos( $context, 'Bitmomo_Pro_Performance' ) &&
	false === strpos( $context, '_bitmomo_pro_' )
);
market_context_check(
	'Market Context boundary remains read-only',
	0 === preg_match( '/update_post_meta|delete_post_meta|wp_update_post|wp_insert_post|wp_delete_post/', $context )
);
market_context_check(
	'First available observation is never mislabeled as a thesis transition',
	false !== strpos( $context, 'null !== $previous' ) &&
	false === strpos( $context, '$changed = null === $previous ||' )
);
market_context_check(
	'Failed range requests clear stale payload and comparison controls before fail-closed UI',
	false !== strpos( $js, 'state.payload = null' ) &&
	false !== strpos( $js, 'explorer.compareGroup.replaceChildren()' )
);
market_context_check(
	'Late range responses cannot overwrite a newer selected range',
	false !== strpos( $js, 'range !== state.range' )
);
market_context_check(
	'Comparison is capped to three active series and actual source values remain visible',
	false !== strpos( $js, 'MAX_ACTIVE_SERIES = 3' ) &&
	false !== strpos( $js, 'formatActual(point.value, series.unit)' )
);
market_context_check(
	'Pointer hover layer creates no meaningless keyboard tab stop',
	false === strpos( $js, "class: 'bm-mc__hit-area', tabindex: '0'" ) &&
	false === strpos( $js, "hit.addEventListener('focus'" )
);
market_context_check(
	'Canonical BTC shell is server-stable rather than dependent on the enhancement class',
	false !== strpos( $maincss, 'max-width:var(--bm-shell-width,1180px)' ) ||
	false !== strpos( $css, '.bm-bi{max-width:var(--bm-shell-width,1180px)' )
);
market_context_check(
	'Public explorer never becomes the only source of the canonical Decision View',
	false !== strpos( $js, "document.querySelector('.bm-bi')" ) &&
	false !== strpos( $js, "snapshot.insertAdjacentElement('afterend', explorer.section)" ) &&
	false !== strpos( $page, 'render_current_snapshot()' )
);
market_context_check(
	'Market Context uses the shared design foundation, non-color line patterns, and reduced-motion safety',
	false !== strpos( $css, 'var(--bm-shell-width,1180px)' ) &&
	false !== strpos( $css, 'stroke-dasharray:8 3' ) &&
	false !== strpos( $css, 'stroke-dasharray:3 3' ) &&
	false !== strpos( $css, '@media (prefers-reduced-motion:reduce)' )
);

printf( "\n%d/%d passed.\n", $pass, $pass + $fail );
exit( 0 === $fail ? 0 : 1 );
