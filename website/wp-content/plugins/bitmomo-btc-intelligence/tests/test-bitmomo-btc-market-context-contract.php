<?php
$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/bitmomo-btc-intelligence.php' );
$context = file_get_contents( $root . '/includes/class-bitmomo-btc-intelligence-market-context.php' );
$js = file_get_contents( $root . '/assets/js/market-context-explorer.js' );
$css = file_get_contents( $root . '/assets/css/market-context-explorer.css' );

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
	'Only the agreed V1 comparison universe is defined',
	false !== strpos( $context, "'btc'" ) && false !== strpos( $context, "'eth'" ) &&
	false !== strpos( $context, "'sol'" ) && false !== strpos( $context, "'gold'" ) &&
	false === strpos( $context, "'doge'" )
);
market_context_check(
	'All agreed ranges are part of the public contract',
	false !== strpos( $context, "'7d'" ) && false !== strpos( $context, "'30d'" ) &&
	false !== strpos( $context, "'90d'" ) && false !== strpos( $context, "'ytd'" ) &&
	false !== strpos( $context, "'1y'" )
);
market_context_check(
	'Cross-asset comparison explicitly uses indexed-100 normalization',
	false !== strpos( $context, "'normalization'  => 'indexed_100'" ) &&
	false !== strpos( $js, '* 100' ) && false !== strpos( $js, 'Start = 100' )
);
market_context_check(
	'Crypto comparison uses the public Binance market-data host',
	false !== strpos( $context, "https://data-api.binance.vision" ) &&
	false !== strpos( $context, "/api/v3/klines" ) &&
	false !== strpos( $context, "'BTCUSDT'" ) && false !== strpos( $context, "'ETHUSDT'" ) && false !== strpos( $context, "'SOLUSDT'" )
);
market_context_check(
	'Gold is fail-closed behind an explicit provider key and never proxied with a crypto token',
	false !== strpos( $context, 'BITMOMO_ALPHA_VANTAGE_API_KEY' ) &&
	false !== strpos( $context, 'bitmomo_market_context_alpha_vantage_api_key' ) &&
	false !== strpos( $context, 'GOLD_SILVER_HISTORY' ) &&
	false !== strpos( $context, "'provider_not_configured'" ) &&
	false === stripos( $context, 'PAXG' )
);
market_context_check(
	'Provider cadence is explicit instead of implying every series is realtime',
	false !== strpos( $context, "'cadence'     => 'daily close'" ) &&
	false !== strpos( $context, "'cadence'     => 'daily'" ) &&
	false !== strpos( $js, 'series.cadence' )
);
market_context_check(
	'Binance series excludes a still-open daily candle before calling data a close',
	false !== strpos( $context, '$close_ms > $now_ms' ) &&
	false !== strpos( $context, "'insufficient_closed_history'" )
);
market_context_check(
	'Provider failures are cached to avoid outage amplification',
	false !== strpos( $context, 'CACHE_FAILURE_CRYPTO' ) &&
	false !== strpos( $context, 'CACHE_FAILURE_GOLD' ) &&
	false !== strpos( $context, 'cache_unavailable' )
);
market_context_check(
	'Explorer consumes canonical public thesis history and public Decision Ledger markers',
	false !== strpos( $context, 'Bitmomo_Public_Intelligence_Adapter::history()' ) &&
	false !== strpos( $context, 'Bitmomo_Btc_Intelligence_Accountability::decision_ledger( 20 )' ) &&
	false !== strpos( $context, "'type'         => 'state'" ) &&
	false !== strpos( $context, "'type'       => 'decision'" )
);
market_context_check(
	'First in-range observation is not mislabeled as a thesis transition without prior history',
	false !== strpos( $context, '$changed = null !== $previous && (' ) &&
	false === strpos( $context, '$changed = null === $previous ||' )
);
market_context_check(
	'Current Decision View enhancement consumes public-safe market state without recomputing engine logic',
	false !== strpos( $context, 'Bitmomo_Public_Intelligence_Adapter::snapshot()' ) &&
	false !== strpos( $context, "'market_state'" ) &&
	false === strpos( $context, 'Bitmomo_AI_Signal_Engine' )
);
market_context_check(
	'Protected Pro values are never queried by the public explorer',
	false !== strpos( $context, "'expected_range'" ) && false !== strpos( $context, "'available' => false" ) &&
	false === strpos( $context, 'Bitmomo_Pro_Briefs' ) && false === strpos( $context, 'Bitmomo_Pro_Performance' ) &&
	false === strpos( $context, '_bitmomo_pro_')
);
market_context_check(
	'Range changes invalidate stale payload and comparison controls before the new request resolves',
	false !== strpos( $js, 'state.payload = null;') &&
	false !== strpos( $js, "state.active = new Set(['btc']);") &&
	false !== strpos( $js, 'explorer.compareGroup.replaceChildren();')
);
market_context_check(
	'Late or mismatched range responses cannot overwrite the latest range',
	false !== strpos( $js, 'requestSeq: 0') &&
	false !== strpos( $js, 'const requestId = ++state.requestSeq;') &&
	false !== strpos( $js, 'requestId !== state.requestSeq || range !== state.range') &&
	false !== strpos( $js, 'payload.range !== range')
);
market_context_check(
	'Comparison is capped to three active series and actual source values remain visible in tooltip',
	false !== strpos( $js, 'MAX_ACTIVE_SERIES = 3' ) &&
	false !== strpos( $js, 'formatActual(point.value, series.unit)' ) &&
	false !== strpos( $js, 'Maksimal ${MAX_ACTIVE_SERIES} aset sekaligus' )
);
market_context_check(
	'Explorer progressively enhances instead of replacing the server-rendered Decision View',
	false !== strpos( $js, "document.querySelector('.bm-bi')" ) &&
	false !== strpos( $js, "snapshot.insertAdjacentElement('afterend', explorer.section)" ) &&
	false !== strpos( $js, 'Decision View di atas tetap menggunakan data canonical Bitmomo' )
);
market_context_check(
	'Enhanced section rail exposes Market Context without changing the server fallback rail',
	false !== strpos( $js, "link.href = '#market-context'" ) &&
	false !== strpos( $js, "link.textContent = 'Context'" )
);
market_context_check(
	'Enhanced decision flow places the Pro next-step before methodology',
	false !== strpos( $js, "root.querySelector('.bm-bi__methodology')" ) &&
	false !== strpos( $js, "root.querySelector('.bm-bi__pro-cta')" ) &&
	false !== strpos( $js, 'methodology.before(proNextStep)')
);
market_context_check(
	'Pointer-only chart hover layer does not create a meaningless keyboard tab stop',
	false === strpos( $js, "class: 'bm-mc__hit-area', tabindex: '0'" ) &&
	false === strpos( $js, "hit.addEventListener('focus'") &&
	false === strpos( $js, "hit.addEventListener('blur'")
);
market_context_check(
	'Mobile controls retain at least 44px target geometry and chart overflow stays contained',
	false !== strpos( $css, 'min-height:44px' ) &&
	false !== strpos( $css, '.bm-mc__chart{position:relative' ) && false !== strpos( $css, 'overflow:hidden' )
);
market_context_check(
	'Visual hierarchy promotes What Changed ahead of supporting Why content',
	false !== strpos( $css, '.bm-bi__brief-panel:last-child{order:-1' ) &&
	false !== strpos( $css, 'box-shadow:inset 2px 0 0 var(--bmi-teal)' )
);
market_context_check(
	'Explorer aligns to the canonical public shell and includes reduced-motion safety',
	false !== strpos( $css, 'max-width:var(--bm-shell-width,1180px)' ) &&
	false !== strpos( $css, '@media (prefers-reduced-motion:reduce)' )
);

printf( "\n%d/%d passed.\n", $pass, $pass + $fail );
exit( 0 === $fail ? 0 : 1 );