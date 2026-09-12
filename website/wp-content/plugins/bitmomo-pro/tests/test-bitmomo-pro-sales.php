<?php
/**
 * Standalone executable contract test for [bitmomo_pro_sales].
 *
 * Loads the real plugin against the minimal WordPress stub, following the
 * same convention as the other Bitmomo Pro deterministic tests.
 *
 * Run: php tests/test-bitmomo-pro-sales.php
 */

define( 'ABSPATH', '/tmp/' );

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../bitmomo-pro.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

update_option( 'bitmomo_pro_checkout_url', '' );
$html   = Bitmomo_Pro_Sales::instance()->render_sales( array() );
$source = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-sales.php' );

// ==========================================================================
// BASIC RENDER / NO MOCK DATA
// ==========================================================================

check( 'RENDER: sales page produces non-empty output', is_string( $html ) && strlen( $html ) > 1000 );
check( 'RENDER: root wrapper is present', false !== strpos( $html, '<div class="bm-pro-sales">' ) );
check( 'PLACEHOLDERS: no "XX%" placeholder confidence value', false === strpos( $html, 'XX%' ) );
check( 'PLACEHOLDERS: no "$XX,XXX" placeholder price value', false === strpos( $html, '$XX,XXX' ) );
check( 'PLACEHOLDERS: no literal "(placeholder)" text', false === strpos( $html, '(placeholder)' ) );
check( 'PLACEHOLDERS: no Decision View mock preview section', false === strpos( $html, 'Contoh Tampilan Decision View' ) );
check( 'PRICE ESTIMATE: no "349.000" future-price estimate', false === strpos( $html, '349.000' ) );
check( 'PRICE ESTIMATE: no "349000" future-price estimate', false === strpos( $html, '349000' ) );

// ==========================================================================
// CTA CONTRACT — one hero jump plus one canonical whitelist submit surface.
// There must not be a second bottom-of-page conversion block.
// ==========================================================================

check( 'CTA: Founding whitelist label is present', false !== strpos( $html, 'GABUNG FOUNDING WHITELIST' ) );
check( 'CTA: old bare "GABUNG WHITELIST" string is absent', 0 === preg_match( '/GABUNG WHITELIST/', preg_replace( '/GABUNG FOUNDING WHITELIST/', '', $html ) ) );
check( 'CTA: whitelist label appears exactly twice (hero jump + canonical form submit)', 2 === substr_count( $html, 'GABUNG FOUNDING WHITELIST' ) );
check( 'CTA: final CTA markup is absent from rendered output', false === strpos( $html, 'bm-pro-sales__final-cta' ) );
check( 'CTA: obsolete final CTA headline is absent from rendered output', false === strpos( $html, 'Bergabung sebelum 11 AI Analysts dan Watchtower dirilis.' ) );
check( 'CTA: renderer source no longer defines or calls render_final_cta()', false === strpos( $source, 'render_final_cta' ) );

// ==========================================================================
// HERO / FOUNDING FACTS
// ==========================================================================

check( 'HERO: eyebrow matches locked copy', false !== strpos( $html, 'FOUNDING MEMBERSHIP — BITMOMO PRO' ) );
check( 'HERO: headline matches locked copy verbatim', false !== strpos( $html, 'Pahami BTC dalam konteks, bukan sekadar dari potongan data.' ) );
check( 'HERO: supporting product copy present', false !== strpos( $html, 'Decision View hari ini. 11 AI Analysts dan Watchtower segera hadir.' ) );
check( 'HERO: Founding Price is prominent', false !== strpos( $html, 'Founding Price Rp149.000/bulan' ) );
check( 'HERO: annual price is visible', false !== strpos( $html, 'Rp1.490.000' ) );
check( 'HERO: 149 Founding Members fact visible', false !== strpos( $html, '149' ) && false !== strpos( $html, 'Founding Members' ) );
check( 'HERO: Batch pertama fact visible', false !== strpos( $html, 'Batch pertama' ) );
check( 'HERO: annual price is secondary, not a fourth equal-weight stat box', false === strpos( $html, '<div><strong>Rp1.490.000</strong>' ) );
check( 'HERO: hero-facts row contains exactly three boxes', 3 === substr_count( substr( $html, strpos( $html, 'bm-pro-sales__hero-facts' ), 700 ), '<div><strong>' ) );
check( 'HERO: pricing appears before FAQ', strpos( $html, 'Founding Price Rp149.000/bulan' ) < strpos( $html, 'Pertanyaan Sebelum Bergabung' ) );

// ==========================================================================
// DATA CLAIM HONESTY — lock the exact capability-safe wording and reject the
// superseded continuous order-book/on-chain ingestion claim.
// ==========================================================================

$canonical_data_copy = 'Harga, struktur pasar, funding/basis, positioning derivatives, momentum, dan volatilitas BTC diproses dari data pasar yang tersedia.';
$obsolete_data_copy  = 'Harga, order book, funding, positioning, dan sinyal on-chain BTC dikumpulkan secara berkelanjutan.';

check( 'DATA: canonical capability-safe copy is rendered verbatim', false !== strpos( $html, $canonical_data_copy ) );
check( 'DATA: obsolete continuous order-book/on-chain claim is absent', false === strpos( $html, $obsolete_data_copy ) );
check( 'DATA: sales output does not claim order-book ingestion', false === stripos( $html, 'order book' ) );
check( 'DATA: sales output does not claim on-chain signal ingestion', false === stripos( $html, 'sinyal on-chain' ) );
check( 'DATA: renderer source contains the canonical copy', false !== strpos( $source, $canonical_data_copy ) );
check( 'DATA: renderer source does not contain the obsolete copy', false === strpos( $source, $obsolete_data_copy ) );

// ==========================================================================
// FUTURE CAPABILITY STATUS LANGUAGE
// ==========================================================================

check( 'STATUS: Altcoin Intelligence marked SEGERA HADIR', false !== strpos( $html, 'ALTCOIN INTELLIGENCE — SEGERA HADIR' ) );
check( 'STATUS: Daily Alpha Discovery marked SEGERA HADIR', false !== strpos( $html, 'DAILY ALPHA DISCOVERY — SEGERA HADIR' ) );
check( 'STATUS: 11 AI Analysts marked SEGERA HADIR', false !== strpos( $html, '11 AI ANALYSTS — SEGERA HADIR' ) );
check( 'STATUS: Watchtower marked SEGERA HADIR', false !== strpos( $html, 'WATCHTOWER — SEGERA HADIR' ) );
check( 'STATUS: old "IN DEVELOPMENT" wording is gone', false === strpos( $html, 'IN DEVELOPMENT' ) );
check( 'STATUS: no "24/7" claim', false === stripos( $html, '24/7' ) );
check( 'STATUS: no "real-time" claim', false === stripos( $html, 'real-time' ) && false === stripos( $html, 'real time' ) );
check( 'STATUS: analyst/watchtower signature line present', false !== strpos( $html, '11 AI Analysts membangun thesis. Watchtower menjaganya tetap relevan.' ) );
check( 'STATUS: Watchtower retains "bukan news feed" framing', false !== strpos( $html, 'bukan news feed' ) );

// ==========================================================================
// CORE SECTION ORDER — conversion ends at the founding climax; no duplicated
// final CTA after Accountability + FAQ.
// ==========================================================================

$section_markers = array(
	'Pahami BTC dalam konteks, bukan sekadar dari potongan data.',
	'Data ada di mana-mana. Konteks yang jarang.',
	'AI adalah bagian dari sistem. Konteks adalah fondasinya.',
	'Bagaimana Bitmomo mengubah data menjadi intelligence',
	'11 perspektif spesialis, satu kerangka intelligence yang sama.',
	'Bukan soal apa yang bergerak. Soal apakah sesuatu benar-benar berubah.',
	'YANG SUDAH TERSEDIA SEKARANG',
	'Bergabung sebelum Bitmomo Pro mencapai bentuk penuhnya.',
	'Setiap analisis harus bisa dipertanggungjawabkan.',
	'Pertanyaan Sebelum Bergabung',
);
$positions = array();
foreach ( $section_markers as $i => $marker ) {
	$pos         = strpos( $html, $marker );
	$positions[] = $pos;
	check( sprintf( 'SECTION %d: "%s" is present', $i + 1, $marker ), false !== $pos );
}
$in_order = true;
for ( $i = 1; $i < count( $positions ); $i++ ) {
	if ( false === $positions[ $i - 1 ] || false === $positions[ $i ] || $positions[ $i ] < $positions[ $i - 1 ] ) {
		$in_order = false;
		break;
	}
}
check( 'SECTION ORDER: all 10 core sections appear in the required order', $in_order );

// ==========================================================================
// SIX-STAGE FLOW
// ==========================================================================

foreach ( array( 'DATA', 'CONTEXT', 'INTELLIGENCE', 'THESIS', 'MONITORING', 'ACCOUNTABILITY' ) as $stage ) {
	check( "FLOW: stage \"$stage\" is present", false !== strpos( $html, ">$stage<" ) );
}

// ==========================================================================
// FOUNDING ECONOMICS / ACCOUNTABILITY
// ==========================================================================

check( 'ECONOMICS: founder-approved no-number framing present', false !== strpos( $html, 'Rp149.000/bulan adalah Founding Price. Harga membership baru akan berubah seiring pengembangan fitur dan teknologi Bitmomo Pro. Founding Members yang menjaga membership tetap aktif dapat mempertahankan Founding Price selamanya.' ) );
check( 'ECONOMICS: standalone-product boundary line preserved verbatim', false !== strpos( $html, 'Founding benefit berlaku untuk fitur baru yang ditambahkan ke Bitmomo Pro. Produk standalone Bitmomo di masa depan dapat memiliki pricing tersendiri.' ) );
check( 'ECONOMICS: HARI INI / SEGERA HADIR / KE DEPAN progression present', false !== strpos( $html, 'HARI INI' ) && false !== strpos( $html, 'SEGERA HADIR' ) && false !== strpos( $html, 'KE DEPAN' ) );
check( 'ECONOMICS: Founding Whitelist stage present', false !== strpos( $html, 'Tahap saat ini: Founding Whitelist' ) );
check( 'ACCOUNTABILITY: no invented accuracy percentage', 0 === preg_match( '/\d+%\s*akurat/i', $html ) );
check( 'ACCOUNTABILITY: links to live /btc-intelligence/ methodology page', false !== strpos( $html, 'href="' . home_url( '/btc-intelligence/' ) . '"' ) );
check( 'ACCOUNTABILITY: deferred methodology note is absent', false === strpos( $html, 'Metodologi & track record lengkap segera tersedia sebagai halaman terpisah.' ) );

// ==========================================================================
// FAQ
// ==========================================================================

check( 'FAQ: heading is "Pertanyaan Sebelum Bergabung"', false !== strpos( $html, 'Pertanyaan Sebelum Bergabung' ) );
check( 'FAQ: pro_question_ids() returns exactly 10 topics', 10 === count( Bitmomo_Pro_Help_Center::pro_question_ids() ) );
foreach ( array( 'sinyal-buy-atau-sell', 'apa-itu-bitmomo-pro', 'free-vs-pro', 'apa-itu-11-ai-analysts', 'apa-itu-watchtower', 'apakah-analysts-tersedia', 'apa-itu-founding-membership', 'apakah-founding-price-tetap', 'berhenti-dan-bergabung-kembali', 'founders-mendapat-analysts-watchtower' ) as $expected_id ) {
	check( "FAQ: \"$expected_id\" is selected", in_array( $expected_id, Bitmomo_Pro_Help_Center::pro_question_ids(), true ) );
	check( "FAQ: \"$expected_id\" is rendered", false !== strpos( $html, 'id="' . sanitize_title( $expected_id ) . '"' ) );
}

// ==========================================================================
// WHITELIST FUNCTIONAL BEHAVIOR
// ==========================================================================

reset_test_sales_state();
$whitelist = Bitmomo_Pro_Whitelist::instance();
$result    = $whitelist->submit_entry(
	array(
		'email'   => 'salespagecheck@example.com',
		'consent' => true,
		'source'  => 'pro_page',
	)
);
check( 'WHITELIST: submit_entry() still works', true === $result['ok'] && 'created' === $result['status'] );
check( 'WHITELIST: exactly one record is created', 1 === $whitelist->count_total() );

function reset_test_sales_state() {
	$GLOBALS['__wp_stub_posts']      = array();
	$GLOBALS['__wp_stub_postmeta']   = array();
	$GLOBALS['__wp_stub_mail_log']   = array();
	$GLOBALS['__wp_stub_options']    = array();
	$GLOBALS['__wp_stub_action_log'] = array();
}

// ==========================================================================
// PROTECTED PRO EXPERIENCE UNAFFECTED
// ==========================================================================

check( 'PROTECTED DASHBOARD: shortcode class still loads', class_exists( 'Bitmomo_Pro_Shortcodes' ) && method_exists( 'Bitmomo_Pro_Shortcodes', 'render_dashboard' ) );
check( 'PROTECTED DASHBOARD: sales CTA copy did not leak into protected dashboard source', false === strpos( file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-shortcodes.php' ), 'GABUNG FOUNDING WHITELIST' ) );

// ==========================================================================
// MOBILE / COLOR STATIC CONTRACTS
// ==========================================================================

$css = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-sales.css' );
check( 'MOBILE: page container uses max-width (fluid)', false !== strpos( $css, 'max-width: 720px' ) );
check( 'MOBILE: no fixed pixel widths above 360px force horizontal scroll', 0 === preg_match( '/(?<!max-)(?<!min-)\bwidth:\s*(\d+)px/', $css, $m ) || ( isset( $m[1] ) && (int) $m[1] <= 360 ) );
check( 'MOBILE: flow and progression are single-column by default', preg_match( '/\.bm-pro-sales__flow-stages\s*\{[^}]*grid-template-columns:\s*1fr/', $css ) && preg_match( '/\.bm-pro-sales__progression\s*\{[^}]*grid-template-columns:\s*1fr/', $css ) );
check( 'MOBILE: analyst role pills wrap', false !== strpos( $css, 'flex-wrap: wrap' ) );
check( 'COLOR: orange commercial token is defined', false !== strpos( $css, '--bms-orange: #f4ad32' ) );
check( 'COLOR: primary CTA uses orange', (bool) preg_match( '/\.bm-pro-sales__cta\s*\{[^}]*background:\s*var\(\s*--bms-orange\s*\)/s', $css ) );
check( 'COLOR: whitelist submit is orange in climax panel', false !== strpos( $css, '.bm-pro-sales__climax .bm-wl__submit {' ) && (bool) preg_match( '/\.bm-pro-sales__climax \.bm-wl__submit\s*\{[^}]*background:\s*var\(\s*--bms-orange\s*\)/s', $css ) );
check( 'COLOR: status badges remain teal', (bool) preg_match( '/\.bm-pro-sales__status-badge\s*\{[^}]*border:\s*1px solid var\(\s*--bms-teal\s*\)/s', $css ) );

// ==========================================================================
// REPORT
// ==========================================================================

$pass_count = 0;
foreach ( $results as $r ) {
	printf( "[%s] %s\n", $r['pass'] ? 'PASS' : 'FAIL', $r['label'] );
	if ( $r['pass'] ) {
		$pass_count++;
	}
}
$total = count( $results );
printf( "\n%d/%d passed.\n", $pass_count, $total );

exit( $pass_count === $total ? 0 : 1 );
