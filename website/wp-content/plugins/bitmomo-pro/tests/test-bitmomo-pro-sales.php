<?php
/** Standalone executable contract test for the public Bitmomo Pro sales surface. */

define( 'ABSPATH', '/tmp/' );
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $html ) { return (string) $html; }
}
require __DIR__ . '/../bitmomo-pro.php';

$results = array();
function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}
function in_order( $haystack, $markers ) {
	$last = -1;
	foreach ( $markers as $marker ) {
		$position = strpos( $haystack, $marker );
		if ( false === $position || $position < $last ) return false;
		$last = $position;
	}
	return true;
}
function reset_test_sales_state() {
	$GLOBALS['__wp_stub_posts']      = array();
	$GLOBALS['__wp_stub_postmeta']   = array();
	$GLOBALS['__wp_stub_mail_log']   = array();
	$GLOBALS['__wp_stub_options']    = array();
	$GLOBALS['__wp_stub_action_log'] = array();
}

update_option( 'bitmomo_pro_checkout_url', '' );
$html          = Bitmomo_Pro_Sales::instance()->render_sales( array() );
$source        = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-sales.php' );
$dashboard     = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-shortcodes.php' );
$css           = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-sales.css' );
$decision_css  = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-decision-view.css' );
$help          = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-help-center.php' );
$whitelist_src = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-whitelist.php' );
$email_src     = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-email-service.php' );

// Integrity and truthfulness.
check( 'RENDER: sales page is non-empty and owns one decision-first root surface', is_string( $html ) && strlen( $html ) > 2000 && false !== strpos( $html, '<div class="bm-pro-sales bm-pro-sales--decision-first">' ) );
check( 'TRUTH: no placeholder preview values', false === strpos( $html, 'XX%' ) && false === strpos( $html, '$XX,XXX' ) && false === strpos( $html, '(placeholder)' ) );
check( 'TRUTH: no fabricated accuracy claim', 0 === preg_match( '/\d+%\s*akurat/i', $html ) );
check( 'TRUTH: no 24/7 or realtime claim', false === stripos( $html, '24/7' ) && false === stripos( $html, 'real-time' ) && false === stripos( $html, 'real time' ) );
check( 'TRUTH: old seven-day refund promise cannot return', 0 === preg_match( '/7\s*(hari|day)|refund 7|7-day/i', $html . $help ) );
check( 'TRUTH: public sales renderer never reads current protected brief', false === strpos( $source, 'get_current_brief_for_display' ) && false === strpos( $source, 'Bitmomo_Pro_Briefs::' ) );
check( 'TRUTH: no fake remaining-seat counter', false === stripos( $html, 'kursi tersisa' ) && false === stripos( $html, 'remaining seats' ) );

// Ten-second comprehension and action hierarchy.
check( 'HERO: Free vs Pro boundary is explicit immediately', false !== strpos( $html, 'BTC Intelligence Free menjelaskan apa yang terjadi sekarang.' ) && false !== strpos( $html, 'Pro menambahkan Expected Range, Scenario Map, Thesis Invalidation' ) );
check( 'HERO: founding economics are visible without implying checkout is live', false !== strpos( $html, 'Founding Price Rp149.000/bulan' ) && false !== strpos( $html, 'Rp1.490.000/tahun' ) && false !== strpos( $html, 'Checkout belum dibuka.' ) );
check( 'HERO: founding cap is factual', false !== strpos( $html, 'Maksimum 149 Founding Members.' ) );
check( 'HERO: whitelist is primary conversion path while checkout is off', false !== strpos( $html, 'href="#bm-pro-whitelist"' ) && false !== strpos( $html, 'GABUNG FOUNDING WHITELIST' ) );
check( 'HERO: secondary action goes to actual historical proof', false !== strpos( $html, 'href="#pro-example"' ) && false !== strpos( $html, 'Lihat Decision View historis' ) );

// Product proof comes before explanation and offer.
check(
	'FLOW: proof -> Free/Pro boundary -> accountability -> offer -> FAQ',
	in_order(
		$html,
		array(
			'ACTUAL PRODUCT PROOF',
			'FREE → PRO',
			'ACCOUNTABILITY',
			'FOUNDING MEMBERSHIP',
			'Hal yang perlu jelas sebelum bergabung.'
		)
	)
);
check( 'FLOW: legacy five-card feature catalogue is not rendered', false === strpos( $source, 'render_what_exists_today' ) && false === strpos( $html, 'YANG SUDAH TERSEDIA SEKARANG' ) );
check( 'FLOW: sticky in-page product rail is no longer required by renderer', false === strpos( $source, 'render_section_nav' ) );
check( 'FLOW: speculative roadmap is not rendered', false === strpos( $html, 'ROADMAP — SEGERA HADIR' ) && false === strpos( $html, 'Altcoin Intelligence' ) && false === strpos( $html, '11 AI Analysts' ) && false === strpos( $html, 'Watchtower' ) );

// Public proof must be real, delayed, and fail closed.
check( 'PROOF: renderer asks only for one delayed public proof row', false !== strpos( $source, 'Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 )' ) );
check( 'PROOF: no direct Pro post/meta store is queried', false === strpos( $source, 'Bitmomo_Pro_Performance::' ) && false === strpos( $source, '_bitmomo_pro_' ) );
check( 'PROOF: delayed historical boundary is visible before proof', false !== strpos( $html, 'HISTORICAL · DELAYED ≥48H' ) );
check( 'PROOF: fail-closed fallback refuses fake examples', false !== strpos( $html, 'Bitmomo memilih ruang kosong daripada membuat range, skenario, confidence, atau outcome palsu.' ) );
check( 'PROOF: source owns real visual range rail', false !== strpos( $source, 'bm-pro-sales__range-track' ) && false !== strpos( $source, 'range_positions' ) );
check( 'PROOF: source owns Base/Bull/Bear scenario map without probability', false !== strpos( $source, 'bm-pro-sales__scenario-map' ) && false !== strpos( $source, 'BASE · WORKING THESIS' ) && false === stripos( $source, 'probability' ) );
check( 'PROOF: Thesis Invalidation is a dedicated risk boundary', false !== strpos( $source, 'bm-pro-sales__risk-boundary' ) && false !== strpos( $source, 'THESIS INVALIDATION' ) );
check( 'PROOF: no row-ledger/archive link competes with conversion path', false === strpos( $html, '#decision-ledger' ) && false === strpos( $html, '#pro-archive' ) );

// Free versus Pro stays concise and semantic.
foreach ( array( 'Kondisi BTC sekarang', 'What Changed', 'Expected Range', 'Scenario Map Base / Bull / Bear', 'Thesis Invalidation', 'What to Watch', 'Accountability hasil' ) as $dimension ) {
	check( 'FREE VS PRO: dimension visible: ' . $dimension, false !== strpos( $html, $dimension ) );
}
check( 'FREE VS PRO: Free owns one watch context while Pro owns deeper monitoring', false !== strpos( $html, '1 konteks utama' ) && false !== strpos( $html, 'Monitoring lebih lengkap' ) );

// Accountability is visible without percentage theatre.
check( 'ACCOUNTABILITY: recorded-before-outcome principle is explicit', false !== strpos( $html, 'Recorded before outcome' ) );
check( 'ACCOUNTABILITY: settlement is forward-only', false !== strpos( $html, 'Settled outcome' ) && false !== strpos( $html, 'forward-only' ) );
check( 'ACCOUNTABILITY: small/undefined aggregate sample is disclosed', false !== strpos( $html, 'INSUFFICIENT SAMPLE' ) );
check( 'ACCOUNTABILITY: no percentage win-rate is emitted', false === stripos( $html, 'win-rate' ) && false === stripos( $html, 'accuracy:') );

// Commercial clarity.
check( 'PRICE: monthly founding price is canonical', false !== strpos( $html, 'Rp149.000' ) );
check( 'PRICE: annual founding price is canonical', false !== strpos( $html, 'Rp1.490.000' ) );
check( 'PRICE: annual savings is mathematically correct', false !== strpos( $html, 'hemat Rp298.000' ) );
check( 'PRICE: founding cap and first-batch constants remain canonical', Bitmomo_Pro_Sales::SEAT_CAP === 149 && Bitmomo_Pro_Sales::BATCH_ONE === 25 );
check( 'PRICE: features are equal across billing periods', false !== strpos( $html, 'Fitur sama pada paket bulanan dan tahunan.' ) );
check( 'TERMS: cancel-anytime access-through-paid-period is visible', false !== strpos( $html, 'Batalkan kapan saja. Akses tetap aktif sampai akhir periode berlangganan yang sudah dibayar.' ) );
check( 'TERMS: final-payment policy keeps access-failure exception', false !== strpos( $html, 'kegagalan pemberian akses dari sisi Bitmomo' ) );
check( 'FOUNDING: price retention boundary remains explicit', false !== strpos( $html, 'Founding Members yang menjaga membership tetap aktif mempertahankan Founding Price selama membership tersebut tetap aktif.' ) && false === strpos( $html, 'Founding Price selamanya' ) );

// Buyer FAQ stays canonical and avoids roadmap promotion.
$buyer_ids = array( 'apa-itu-bitmomo-pro', 'free-vs-pro', 'sinyal-buy-atau-sell', 'harga-bitmomo-pro', 'apa-itu-founding-membership', 'batalkan-kapan-saja', 'kebijakan-refund', 'berhenti-dan-bergabung-kembali' );
foreach ( $buyer_ids as $id ) {
	check( 'FAQ: buyer question rendered: ' . $id, false !== strpos( $html, 'id="' . $id . '"' ) );
}
foreach ( array( 'apa-itu-11-ai-analysts', 'apa-itu-watchtower', 'apakah-analysts-tersedia' ) as $future_id ) {
	check( 'FAQ: roadmap question not promoted: ' . $future_id, false === strpos( $html, 'id="' . $future_id . '"' ) );
}
check( 'FAQ: answers come from canonical Help Center categories()', false !== strpos( $source, 'Bitmomo_Pro_Help_Center::categories()' ) );
check( 'FAQ: full Help Center remains linked', false !== strpos( $html, '/help/' ) );

// Whitelist write path and privacy behavior remain untouched.
reset_test_sales_state();
$whitelist = Bitmomo_Pro_Whitelist::instance();
$result    = $whitelist->submit_entry( array( 'email' => 'salespagecheck@example.com', 'consent' => true, 'source' => 'pro_page' ) );
check( 'WHITELIST: canonical submit_entry creates a record', true === $result['ok'] && 'created' === $result['status'] );
check( 'WHITELIST: canonical storage deduplicates into one record', 1 === $whitelist->count_total() );
check( 'WHITELIST: WhatsApp acquisition is fail-closed by default', false === Bitmomo_Pro_Whitelist::whatsapp_opt_in_enabled() );
check( 'WHITELIST: dormant WhatsApp capability still requires explicit enablement', false !== strpos( $whitelist_src, 'BITMOMO_PRO_WHATSAPP_OPT_IN_ENABLED' ) && false !== strpos( $whitelist_src, 'bitmomo_pro_whatsapp_opt_in_enabled' ) );
check( 'EMAIL: confirmation copy remains channel-aware', false !== strpos( $email_src, 'Kami akan mengirim pemberitahuan melalui email ini saat akses dibuka.' ) && false !== strpos( $email_src, 'if ( $whatsapp_enabled )' ) );

// Protected product stays server-gated, while checkout-off UX points to whitelist.
check( 'PROTECTED: protected dashboard class remains available', class_exists( 'Bitmomo_Pro_Shortcodes' ) && method_exists( 'Bitmomo_Pro_Shortcodes', 'render_dashboard' ) );
check( 'PROTECTED: public sales renderer reads no user entitlement state', false === strpos( $source, 'bitmomo_user_has_pro_access' ) && false === strpos( $source, 'get_current_user_id' ) );
check( 'PROTECTED: login and entitlement checks remain server-side', false !== strpos( $dashboard, '! is_user_logged_in()' ) && false !== strpos( $dashboard, '! bitmomo_user_has_pro_access( get_current_user_id() )' ) );
check( 'PROTECTED: checkout-off path points to canonical whitelist', false !== strpos( $dashboard, "home_url( '/pro/#bm-pro-whitelist' )" ) );

// Institutional visual/performance contract.
check( 'LAYOUT: public page retains canonical 1180px shell token', false !== strpos( $css, '--bms-shell:var(--bm-shell-width,1180px)' ) );
check( 'LAYOUT: protected Decision View widens to 980px without widening reading copy globally', false !== strpos( $decision_css, 'max-width: 980px' ) );
check( 'LAYOUT: Expected Range has a CSS/HTML rail with no chart framework', false !== strpos( $decision_css, '.bm-pro-sales__range-track') && false !== strpos( $decision_css, '.bm-pro__range-track' ) && false === stripos( $source . $dashboard, 'chart.js' ) );
check( 'MOBILE: primary actions keep at least 48px target height', false !== strpos( $decision_css, 'min-height: 48px' ) );
check( 'MOBILE: responsive Decision View contract exists below tablet width', false !== strpos( $decision_css, '@media (max-width: 760px)' ) && false !== strpos( $decision_css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ) && false !== strpos( $decision_css, 'grid-template-columns: 1fr;' ) );
check( 'ACCESSIBILITY: focus-visible owner exists for the decision-first surfaces', false !== strpos( $decision_css, ':focus-visible' ) );
check( 'PERFORMANCE: no public JS dependency is introduced by sales renderer', false === strpos( $source, 'wp_enqueue_script' ) );
check( 'PERFORMANCE: no external font or third-party UI dependency is introduced', false === stripos( $decision_css, '@import' ) && false === stripos( $decision_css, 'fonts.googleapis.com' ) );

$failed = array_filter( $results, static function ( $result ) { return ! $result['pass']; } );
foreach ( $results as $result ) {
	echo sprintf( "[%s] %s\n", $result['pass'] ? 'PASS' : 'FAIL', $result['label'] );
}
printf( "\n%d/%d passed.\n", count( $results ) - count( $failed ), count( $results ) );
exit( $failed ? 1 : 0 );
