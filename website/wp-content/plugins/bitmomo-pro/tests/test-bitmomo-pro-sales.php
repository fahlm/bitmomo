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

update_option( 'bitmomo_pro_checkout_url', '' );
$html   = Bitmomo_Pro_Sales::instance()->render_sales( array() );
$source = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-sales.php' );
$css    = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-sales.css' );
$help   = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-help-center.php' );

// Basic integrity / truthfulness.
check( 'RENDER: sales page is non-empty and owns one root surface', is_string( $html ) && strlen( $html ) > 3000 && false !== strpos( $html, '<div class="bm-pro-sales">' ) );
check( 'TRUTH: no placeholder preview values', false === strpos( $html, 'XX%' ) && false === strpos( $html, '$XX,XXX' ) && false === strpos( $html, '(placeholder)' ) );
check( 'TRUTH: no fabricated accuracy claim', 0 === preg_match( '/\d+%\s*akurat/i', $html ) );
check( 'TRUTH: no 24/7 or realtime claim for non-realtime capabilities', false === stripos( $html, '24/7' ) && false === stripos( $html, 'real-time' ) && false === stripos( $html, 'real time' ) );
check( 'TRUTH: old public seven-day refund promise cannot return', 0 === preg_match( '/7\s*(hari|day)|refund 7|7-day/i', $html . $help ) );

// Hero: current product, offer, and first action are understandable immediately.
check( 'HERO: canonical product headline remains present', false !== strpos( $html, 'Pahami BTC dalam konteks, bukan sekadar dari potongan data.' ) );
check( 'HERO: current Decision View is the value proposition', false !== strpos( $html, 'Decision View harian untuk memahami rentang, skenario, invalidation, dan perubahan penting BTC' ) );
check( 'HERO: status boundary says Decision View is active now', false !== strpos( $html, 'Decision View aktif hari ini' ) );
check( 'HERO: buy/sell misconception is handled above the fold', false !== strpos( $html, 'Bukan sinyal buy / sell' ) );
check( 'HERO: current hero does not sell future analyst/watchtower capability', false === strpos( substr( $html, 0, (int) strpos( $html, 'id="pro-product"' ) ), '11 AI Analysts' ) && false === strpos( substr( $html, 0, (int) strpos( $html, 'id="pro-product"' ) ), 'Watchtower' ) );
check( 'HERO: monthly and annual founding economics are visible', false !== strpos( $html, 'Founding Price Rp149.000/bulan' ) && false !== strpos( $html, 'Rp1.490.000/tahun' ) && false !== strpos( $html, 'hemat Rp298.000' ) );
check( 'HERO: founding cap and first batch are visible without fake remaining-seat urgency', false !== strpos( $html, '149' ) && false !== strpos( $html, 'Founding Members' ) && false !== strpos( $html, 'Batch pertama' ) && false === stripos( $html, 'tersisa' ) );
check( 'HERO: exactly one hero purchase/whitelist jump plus canonical form submit', 2 === substr_count( $html, 'GABUNG FOUNDING WHITELIST' ) );
check( 'HERO: secondary proof action exists instead of a competing commercial CTA', false !== strpos( $html, '>Lihat contoh Pro<' ) );

// Page navigation and information architecture.
foreach ( array( '#pro-product', '#pro-example', '#pro-proof', '#pro-pricing', '#pro-faq' ) as $anchor ) {
	check( 'NAV: rail exposes ' . $anchor, false !== strpos( $html, 'href="' . $anchor . '"' ) );
}
check(
	'FLOW: proof-first buying journey is ordered correctly',
	in_order(
		$html,
		array(
			'YANG SUDAH TERSEDIA SEKARANG',
			'PRODUCT PROOF',
			'FREE → PRO',
			'Data ada di mana-mana. Konteks yang jarang.',
			'Bagaimana Bitmomo mengubah data menjadi intelligence',
			'Setiap analisis harus bisa dipertanggungjawabkan.',
			'AI adalah bagian dari sistem. Konteks adalah fondasinya.',
			'FOUNDING PRICE Rp149.000/bulan',
			'Pertanyaan yang seharusnya jelas sebelum Anda membayar.',
			'ROADMAP — SEGERA HADIR'
		)
	)
);
check( 'FLOW: roadmap stays after the buying decision and FAQ', strpos( $html, 'ROADMAP — SEGERA HADIR' ) > strpos( $html, 'id="pro-faq"' ) );

// Current product and Free vs Pro value distinction.
foreach ( array( 'Expected Range', 'Scenario Map', 'Thesis Invalidation', 'What Changed', 'Confidence Explanation' ) as $deliverable ) {
	check( 'CURRENT PRODUCT: ' . $deliverable . ' is visible', false !== strpos( $html, $deliverable ) );
}
check( 'FREE VS PRO: decision boundary is explicit', false !== strpos( $html, 'Bedanya bukan lebih banyak data. Bedanya adalah keputusan apa yang dibantu.' ) );
check( 'FREE VS PRO: free/current and Pro/next framing is explicit', false !== strpos( $html, 'Free menjawab “apa yang terjadi sekarang”. Pro menambahkan scenario planning, invalidation, dan monitoring' ) );

// Public proof must be real, delayed, and fail closed.
check( 'PROOF: renderer asks only for one delayed public proof row', false !== strpos( $source, 'Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 )' ) );
check( 'PROOF: no direct current Pro brief store is queried from sales renderer', false === strpos( $source, 'Bitmomo_Pro_Briefs::' ) && false === strpos( $source, 'Bitmomo_Pro_Performance::' ) && false === strpos( $source, '_bitmomo_pro_' ) );
check( 'PROOF: no mock proof is manufactured when delayed proof is unavailable', false !== strpos( $html, 'Bitmomo tidak membuat mock price, mock confidence, atau contoh hasil palsu' ) );
check( 'PROOF: delayed archive boundary is visibly disclosed', false !== strpos( $html, 'ARSIP ≥ 48 JAM' ) );
check( 'PROOF: public Decision Ledger / Pro archive remains one click away', false !== strpos( $html, '/btc-intelligence/#pro-archive' ) && false !== strpos( $html, '/btc-intelligence/#decision-ledger' ) );

// Capability honesty.
$canonical_data_copy = 'Harga, struktur pasar, funding/basis, positioning derivatives, momentum, dan volatilitas BTC diproses dari data pasar yang tersedia.';
check( 'DATA: supported market-input copy is exact', false !== strpos( $html, $canonical_data_copy ) && false !== strpos( $source, $canonical_data_copy ) );
check( 'DATA: sales renderer does not claim order-book ingestion', false === stripos( $html, 'order book' ) );
check( 'DATA: sales renderer does not claim continuous on-chain ingestion', false === stripos( $html, 'sinyal on-chain' ) );
foreach ( array( 'DATA', 'CONTEXT', 'INTELLIGENCE', 'THESIS', 'MONITORING', 'ACCOUNTABILITY' ) as $stage ) {
	check( 'SYSTEM: ' . $stage . ' stage remains present', false !== strpos( $html, '>' . $stage . '<' ) );
}

// Accountability / expertise.
check( 'ACCOUNTABILITY: recorded-before-outcome principle is explicit', false !== strpos( $html, 'Dicatat sebelum outcome' ) );
check( 'ACCOUNTABILITY: no-cherry-picking principle is explicit', false !== strpos( $html, 'Tidak memilih hasil yang bagus saja' ) );
check( 'ACCOUNTABILITY: sample/methodology limitations remain visible', false !== strpos( $html, 'Metodologi ikut terlihat' ) );
check( 'EXPERTISE: AI is framed as a system tool rather than an oracle', false !== strpos( $html, 'AI sebagai alat untuk membantu compression, consistency, dan monitoring — bukan sebagai oracle.' ) );
check( 'EXPERTISE: About page is linked for authority verification', false !== strpos( $html, '/tentang-kami/' ) );

// Commercial clarity.
check( 'PRICE: monthly founding price is canonical', false !== strpos( $html, 'Rp149.000' ) );
check( 'PRICE: annual founding price is canonical', false !== strpos( $html, 'Rp1.490.000' ) );
check( 'PRICE: annual savings is mathematically explicit', false !== strpos( $html, 'hemat Rp298.000' ) );
check( 'PRICE: founding cap and operational first batch remain canonical', Bitmomo_Pro_Sales::SEAT_CAP === 149 && Bitmomo_Pro_Sales::BATCH_ONE === 25 );
check( 'PRICE: features are stated equal across billing periods', false !== strpos( $html, 'Fitur sama pada paket bulanan dan tahunan.' ) );
check( 'TERMS: cancel-anytime access-through-paid-period is visible before form', false !== strpos( $html, 'Batalkan kapan saja. Akses tetap aktif sampai akhir periode berlangganan yang sudah dibayar.' ) );
check( 'TERMS: final-payment policy includes access-failure exception', false !== strpos( $html, 'kegagalan pemberian akses dari sisi Bitmomo' ) );
check( 'FOUNDING: locked-price boundary remains explicit', false !== strpos( $html, 'Founding Members yang menjaga membership tetap aktif dapat mempertahankan Founding Price selamanya.' ) );
check( 'FOUNDING: standalone future-product pricing boundary remains explicit', false !== strpos( $html, 'Produk standalone Bitmomo di masa depan dapat memiliki pricing tersendiri.' ) );

// Buyer FAQ must answer purchase objections, not let speculative roadmap dominate.
$buyer_ids = array( 'apa-itu-bitmomo-pro', 'free-vs-pro', 'sinyal-buy-atau-sell', 'harga-bitmomo-pro', 'apa-itu-founding-membership', 'batalkan-kapan-saja', 'kebijakan-refund', 'berhenti-dan-bergabung-kembali' );
foreach ( $buyer_ids as $id ) {
	check( 'FAQ: buyer question rendered: ' . $id, false !== strpos( $html, 'id="' . $id . '"' ) );
}
foreach ( array( 'apa-itu-11-ai-analysts', 'apa-itu-watchtower', 'apakah-analysts-tersedia' ) as $future_id ) {
	check( 'FAQ: roadmap question not promoted into buyer FAQ: ' . $future_id, false === strpos( $html, 'id="' . $future_id . '"' ) );
}
check( 'FAQ: answers are sourced from canonical Help Center categories()', false !== strpos( $source, 'Bitmomo_Pro_Help_Center::categories()' ) );
check( 'FAQ: full Help Center remains linked', false !== strpos( $html, '/help/' ) );

// Roadmap is compact and explicitly not live.
check( 'ROADMAP: future capability is collapsed behind native details', false !== strpos( $html, '<section class="bm-pro-sales__roadmap">') && false !== strpos( $html, '<details>' ) );
check( 'ROADMAP: one explicit non-live status boundary exists', 1 === substr_count( $html, 'ROADMAP — SEGERA HADIR' ) );
foreach ( array( 'Altcoin Intelligence', 'Daily Alpha Discovery', '11 AI Analysts', 'Watchtower' ) as $future ) {
	check( 'ROADMAP: ' . $future . ' is retained as future capability', false !== strpos( $html, $future ) );
}
check( 'ROADMAP: no promised release date', false !== strpos( $html, 'tidak memiliki tanggal rilis yang dijanjikan' ) );
check( 'ROADMAP: old standalone future-wall methods remain absent', false === strpos( $source, 'render_altcoin_intelligence' ) && false === strpos( $source, 'render_alpha_discovery' ) && false === strpos( $source, 'render_ai_analysts' ) && false === strpos( $source, 'render_watchtower' ) );

// Whitelist write path and privacy behavior stay unchanged.
reset_test_sales_state();
$whitelist = Bitmomo_Pro_Whitelist::instance();
$result = $whitelist->submit_entry( array( 'email' => 'salespagecheck@example.com', 'consent' => true, 'source' => 'pro_page' ) );
check( 'WHITELIST: canonical submit_entry still creates a record', true === $result['ok'] && 'created' === $result['status'] );
check( 'WHITELIST: canonical storage still deduplicates into one record', 1 === $whitelist->count_total() );
check( 'WHITELIST: joining still explicitly does not guarantee a seat', false !== strpos( $html, 'Masuk whitelist tidak menjamin tempat.' ) );

function reset_test_sales_state() {
	$GLOBALS['__wp_stub_posts']      = array();
	$GLOBALS['__wp_stub_postmeta']   = array();
	$GLOBALS['__wp_stub_mail_log']   = array();
	$GLOBALS['__wp_stub_options']    = array();
	$GLOBALS['__wp_stub_action_log'] = array();
}

// Protected product implementation must not become coupled to sales copy.
check( 'PROTECTED: protected dashboard class remains available', class_exists( 'Bitmomo_Pro_Shortcodes' ) && method_exists( 'Bitmomo_Pro_Shortcodes', 'render_dashboard' ) );
check( 'PROTECTED: public whitelist CTA copy does not leak into protected dashboard source', false === strpos( file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-shortcodes.php' ), 'GABUNG FOUNDING WHITELIST' ) );
check( 'PUBLIC: sales renderer does not read current user/entitlement state', false === strpos( $source, 'bitmomo_user_has_pro_access' ) && false === strpos( $source, 'get_current_user_id' ) && false === strpos( $source, 'get_current_brief_for_display' ) );

// Institutional visual / performance contract.
check( 'LAYOUT: Pro page uses canonical 1180px public shell token', false !== strpos( $css, '--bms-shell:var(--bm-shell-width,1180px)' ) && false === strpos( $css, 'max-width:720px' ) );
check( 'LAYOUT: internal product rail is sticky under canonical site header', false !== strpos( $css, '.bm-pro-sales__rail{position:sticky;top:var(--bm-header-height,64px)' ) );
check( 'LAYOUT: mobile rail uses canonical 60px header and 44px touch targets', false !== strpos( $css, 'top:var(--bm-header-height-mobile,60px)') && false !== strpos( $css, '.bm-pro-sales__rail a{min-height:44px}' ) );
check( 'LAYOUT: commercial CTA has at least 48px target height', false !== strpos( $css, 'min-height:48px' ) );
check( 'LAYOUT: explicit 900/768/420 responsive contracts exist', false !== strpos( $css, '@media(max-width:900px)') && false !== strpos( $css, '@media(max-width:768px)') && false !== strpos( $css, '@media(max-width:420px)') );
check( 'ACCESSIBILITY: focus-visible owner exists', false !== strpos( $css, ':focus-visible' ) );
check( 'ACCESSIBILITY: reduced-motion safety exists', false !== strpos( $css, '@media(prefers-reduced-motion:reduce)' ) );
check( 'PERFORMANCE: no third-party font/image/script dependency introduced by sales surface', false === preg_match( '/https?:\/\//', $css ) && false === preg_match( '/<script|<img\b/i', $source ) );
check( 'PERFORMANCE: sales CSS uses dedicated asset version for deterministic cache busting', false !== strpos( $source, "SALES_ASSET_VERSION = '2026.09.13-pro-conversion-v1'" ) );

// Trust layer must exist but must not fabricate Terms before a canonical page exists.
check( 'TRUST: Decision Ledger, Help, Privacy and Disclaimer are directly discoverable', false !== strpos( $html, 'Decision Ledger' ) && false !== strpos( $html, 'Help Center' ) && false !== strpos( $html, 'Kebijakan Privasi' ) && false !== strpos( $html, 'Disclaimer' ) );
check( 'TRUST: sales page does not fabricate a Syarat Layanan route', false === strpos( $source, '/syarat-layanan/' ) );

$failures = array_values( array_filter( $results, function( $row ) { return ! $row['pass']; } ) );
foreach ( $results as $row ) echo '[' . ( $row['pass'] ? 'PASS' : 'FAIL' ) . '] ' . $row['label'] . "\n";
printf( "\n%d/%d passed.\n", count( $results ) - count( $failures ), count( $results ) );
if ( $failures ) {
	foreach ( $failures as $failure ) fwrite( STDERR, '- ' . $failure['label'] . "\n" );
	exit( 1 );
}
exit( 0 );
