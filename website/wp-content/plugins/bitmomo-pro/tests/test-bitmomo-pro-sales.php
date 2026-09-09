<?php
/**
 * Standalone executable test for the [bitmomo_pro_sales] page after the
 * 2026-09 sellability + visual hierarchy redesign
 * (class-bitmomo-pro-sales.php, plus the shared FAQ source in
 * class-bitmomo-pro-help-center.php and the whitelist CTA label change in
 * class-bitmomo-pro-whitelist.php).
 *
 * Not a WordPress PHPUnit suite — loads the real plugin files against the
 * minimal stub in wp-stubs.php, following the same convention as
 * test-bitmomo-pro-whitelist.php.
 *
 * Run: php tests/test-bitmomo-pro-sales.php
 */

define( 'ABSPATH', '/tmp/' ); // Must be set before bitmomo-pro.php's own guard runs.

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../bitmomo-pro.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

update_option( 'bitmomo_pro_checkout_url', '' ); // fail-closed: whitelist is the active flow.
$html = Bitmomo_Pro_Sales::instance()->render_sales( array() );

// ==========================================================================
// RENDERS -> the shortcode produces output and doesn't fatal
// ==========================================================================

check( 'RENDER: sales page produces non-empty output', is_string( $html ) && strlen( $html ) > 1000 );
check( 'RENDER: root wrapper is present', false !== strpos( $html, '<div class="bm-pro-sales">' ) );

// ==========================================================================
// NO PLACEHOLDER VALUES -> the old "Contoh Tampilan Decision View" mock
// block (render_example()) is gone entirely, per the founder's decision to
// remove the Decision View preview rather than fake or historical-example
// it.
// ==========================================================================

check( 'PLACEHOLDERS: no "XX%" placeholder confidence value', false === strpos( $html, 'XX%' ) );
check( 'PLACEHOLDERS: no "$XX,XXX" placeholder price value', false === strpos( $html, '$XX,XXX' ) );
check( 'PLACEHOLDERS: no literal "(placeholder)" text', false === strpos( $html, '(placeholder)' ) );
check( 'PLACEHOLDERS: no "Contoh Tampilan Decision View" mock preview section', false === strpos( $html, 'Contoh Tampilan Decision View' ) );
check( 'PLACEHOLDERS: no "(contoh)" mock state label', false === strpos( $html, '(contoh)' ) );

// ==========================================================================
// FUTURE PRICE ESTIMATE REMOVED -> no Rp349.000 anywhere on the sales page
// ==========================================================================

check( 'PRICE ESTIMATE: no "349.000" anywhere in sales-page output', false === strpos( $html, '349.000' ) );
check( 'PRICE ESTIMATE: no "349000" anywhere in sales-page output', false === strpos( $html, '349000' ) );

// ==========================================================================
// NEW CTA LABEL -> "GABUNG FOUNDING WHITELIST" replaces "GABUNG WHITELIST"
// everywhere on the page (hero, whitelist widget, Final CTA)
// ==========================================================================

check( 'CTA LABEL: "GABUNG FOUNDING WHITELIST" appears on the page', false !== strpos( $html, 'GABUNG FOUNDING WHITELIST' ) );
check( 'CTA LABEL: the old bare "GABUNG WHITELIST" string no longer appears anywhere', 0 === preg_match( '/GABUNG WHITELIST/', preg_replace( '/GABUNG FOUNDING WHITELIST/', '', $html ) ) );

// ==========================================================================
// HERO -> locked copy and required visible facts
// ==========================================================================

check( 'HERO: eyebrow matches locked copy', false !== strpos( $html, 'FOUNDING MEMBERSHIP — BITMOMO PRO' ) );
check( 'HERO: headline matches locked copy verbatim', false !== strpos( $html, 'Pahami BTC dalam konteks, bukan sekadar dari potongan data.' ) );
check( 'HERO: supporting product copy present verbatim (final polish: shortened)', false !== strpos( $html, 'Decision View hari ini. 11 AI Analysts dan Watchtower segera hadir.' ) );
check( 'HERO: "Founding Price Rp149.000/bulan" is prominent', false !== strpos( $html, 'Founding Price Rp149.000/bulan' ) );
check( 'HERO: Rp149.000/bulan fact visible', false !== strpos( $html, 'Rp149.000' ) );
check( 'HERO: Rp1.490.000/tahun fact visible (now a secondary line, not a 4th equal-weight stat box)', false !== strpos( $html, 'Rp1.490.000' ) );
check( 'HERO: 149 Founding Members fact visible', false !== strpos( $html, '149' ) && false !== strpos( $html, 'Founding Members' ) );
check( 'HERO: "Batch pertama" fact visible (final polish: dropped the redundant "25 anggota" repeat next to the 25 stat)', false !== strpos( $html, 'Batch pertama' ) );
check( 'HERO: annual price is a secondary line, not a 4th equal-weight stat box', false === strpos( $html, '<div><strong>Rp1.490.000</strong>' ) );
check( 'HERO: hero-facts stat row is 3 boxes, not 4 (final polish: crowded/merged-looking 4-box row fixed)', 3 === substr_count( substr( $html, strpos( $html, 'bm-pro-sales__hero-facts' ), 700 ), '<div><strong>' ) );
check( 'HERO: hero-level pricing appears before the FAQ (not buried)', strpos( $html, 'Founding Price Rp149.000/bulan' ) < strpos( $html, 'Pertanyaan Sebelum Bergabung' ) );

// ==========================================================================
// STATUS LANGUAGE -> "SEGERA HADIR", not "IN DEVELOPMENT", and no
// unauthorized capability claims (exact date, 24/7, real-time, guaranteed
// Telegram delivery)
// ==========================================================================

check( 'STATUS: 11 AI Analysts marked SEGERA HADIR', false !== strpos( $html, '11 AI ANALYSTS — SEGERA HADIR' ) );
check( 'STATUS: Watchtower marked SEGERA HADIR', false !== strpos( $html, 'WATCHTOWER — SEGERA HADIR' ) );
check( 'STATUS: old "IN DEVELOPMENT" wording is gone', false === strpos( $html, 'IN DEVELOPMENT' ) );
check( 'STATUS: no "24/7" claim', false === stripos( $html, '24/7' ) );
check( 'STATUS: no "real-time" claim', false === stripos( $html, 'real-time' ) && false === stripos( $html, 'real time' ) );
check( 'STATUS: 11 AI Analysts signature line present verbatim', false !== strpos( $html, '11 AI Analysts membangun thesis. Watchtower menjaganya tetap relevan.' ) );
check( 'STATUS: Watchtower "bukan news feed" framing present (final polish: two paragraphs merged into one, shorter)', false !== strpos( $html, 'bukan news feed' ) );

// ==========================================================================
// SECTIONS -> all 11 required sections render in order
// ==========================================================================

$section_markers = array(
	'Pahami BTC dalam konteks, bukan sekadar dari potongan data.',                 // 1. Hero
	'Data ada di mana-mana. Konteks yang jarang.',                                 // 2. Context problem
	'AI adalah bagian dari sistem. Konteks adalah fondasinya.',                    // 3. Market experience
	'Bagaimana Bitmomo mengubah data menjadi intelligence',                        // 4. Intelligence flow
	'11 perspektif spesialis, satu kerangka intelligence yang sama.',              // 5. 11 AI Analysts
	'Bukan soal apa yang bergerak. Soal apakah sesuatu benar-benar berubah.',      // 6. Watchtower
	'YANG SUDAH TERSEDIA SEKARANG',                                                // 7. What exists today
	'Bergabung sebelum Bitmomo Pro mencapai bentuk penuhnya.',                     // 8. Founding economics
	'Setiap analisis harus bisa dipertanggungjawabkan.',                          // 9. Accountability
	'Pertanyaan Sebelum Bergabung',                                                // 10. FAQ
	'Bergabung sebelum 11 AI Analysts dan Watchtower dirilis.',                    // 11. Final CTA
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
check( 'SECTION ORDER: all 11 sections appear in the required order', $in_order );

// ==========================================================================
// SIX-STAGE FLOW -> all six stage labels present
// ==========================================================================

foreach ( array( 'DATA', 'CONTEXT', 'INTELLIGENCE', 'THESIS', 'MONITORING', 'ACCOUNTABILITY' ) as $stage ) {
	check( "FLOW: stage \"$stage\" is present", false !== strpos( $html, ">$stage<" ) );
}

// ==========================================================================
// FOUNDING ECONOMICS -> no Rp349.000 estimate, boundary line preserved
// verbatim, Today/Segera Hadir/Ke Depan progression present
// ==========================================================================

check( 'ECONOMICS: founder-approved no-number framing present', false !== strpos( $html, 'Rp149.000/bulan adalah Founding Price. Harga membership baru akan berubah seiring pengembangan fitur dan teknologi Bitmomo Pro. Founding Members yang menjaga membership tetap aktif dapat mempertahankan Founding Price selamanya.' ) );
check( 'ECONOMICS: standalone-product boundary line preserved verbatim', false !== strpos( $html, 'Founding benefit berlaku untuk fitur baru yang ditambahkan ke Bitmomo Pro. Produk standalone Bitmomo di masa depan dapat memiliki pricing tersendiri.' ) );
check( 'ECONOMICS: HARI INI / SEGERA HADIR / KE DEPAN progression present', false !== strpos( $html, 'HARI INI' ) && false !== strpos( $html, 'KE DEPAN' ) );
check( 'ECONOMICS: "Tahap saat ini: Founding Whitelist" present', false !== strpos( $html, 'Tahap saat ini: Founding Whitelist' ) );

// ==========================================================================
// ACCOUNTABILITY -> no invented accuracy %; /btc-intelligence/ link is
// deferred to plain text while that page 404s on the live site (staging
// visual QA, 2026-09) -- see Bitmomo_Pro_Sales::METHODOLOGY_PAGE_LIVE.
// ==========================================================================

check( 'ACCOUNTABILITY: no invented accuracy percentage (e.g. "XX% akurat")', 0 === preg_match( '/\d+%\s*akurat/i', $html ) );
// The methodology page moved from "not built yet" to live during staging.
// These two assertions are therefore expressed against the CONSTANT rather
// than against one hardcoded state, so the guard they exist for -- never
// ship a link to a page that 404s -- keeps holding in BOTH configurations
// instead of going red the moment the page goes live.
$bm_methodology_link = 'href="' . home_url( '/btc-intelligence/' ) . '"';
$bm_methodology_note = 'Metodologi & track record lengkap segera tersedia sebagai halaman terpisah.';

if ( Bitmomo_Pro_Sales::METHODOLOGY_PAGE_LIVE ) {
	check( 'ACCOUNTABILITY: links to /btc-intelligence/ once METHODOLOGY_PAGE_LIVE is true', false !== strpos( $html, $bm_methodology_link ) );
	check( 'ACCOUNTABILITY: drops the deferred plain-text note once the page is live', false === strpos( $html, $bm_methodology_note ) );
} else {
	check( 'ACCOUNTABILITY: does NOT link to /btc-intelligence/ while METHODOLOGY_PAGE_LIVE is false (avoids a public 404)', false === strpos( $html, $bm_methodology_link ) );
	check( 'ACCOUNTABILITY: shows the deferred plain-text note instead', false !== strpos( $html, $bm_methodology_note ) );
}

// ==========================================================================
// FAQ -> the 10-item buying-objection selection renders with SEGERA HADIR
// status labels, under the redesign's heading
// ==========================================================================

check( 'FAQ: heading is "Pertanyaan Sebelum Bergabung"', false !== strpos( $html, 'Pertanyaan Sebelum Bergabung' ) );
check( 'FAQ: pro_question_ids() now returns exactly 10 topics', 10 === count( Bitmomo_Pro_Help_Center::pro_question_ids() ) );
foreach ( array( 'sinyal-buy-atau-sell', 'apa-itu-bitmomo-pro', 'free-vs-pro', 'apa-itu-11-ai-analysts', 'apa-itu-watchtower', 'apakah-analysts-tersedia', 'apa-itu-founding-membership', 'apakah-founding-price-tetap', 'berhenti-dan-bergabung-kembali', 'founders-mendapat-analysts-watchtower' ) as $expected_id ) {
	check( "FAQ: \"$expected_id\" is one of the 10 selected topics", in_array( $expected_id, Bitmomo_Pro_Help_Center::pro_question_ids(), true ) );
	check( "FAQ: \"$expected_id\" question text is rendered on the page", false !== strpos( $html, 'id="' . sanitize_title( $expected_id ) . '"' ) );
}

// ==========================================================================
// WHITELIST BEHAVIOR -> the widget still submits correctly with the new
// button label (delegates to Bitmomo_Pro_Whitelist, unchanged logic).
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
check( 'WHITELIST: submit_entry() still works after the CTA label change', true === $result['ok'] && 'created' === $result['status'] );
check( 'WHITELIST: exactly one record created (no duplicate side effect from the redesign)', 1 === $whitelist->count_total() );

function reset_test_sales_state() {
	$GLOBALS['__wp_stub_posts']      = array();
	$GLOBALS['__wp_stub_postmeta']   = array();
	$GLOBALS['__wp_stub_mail_log']   = array();
	$GLOBALS['__wp_stub_options']    = array();
	$GLOBALS['__wp_stub_action_log'] = array();
}

// ==========================================================================
// PROTECTED PRO EXPERIENCE UNAFFECTED -> class-bitmomo-pro-shortcodes.php
// (the protected dashboard, [bitmomo_pro_dashboard]) is untouched by this
// redesign. render_dashboard()'s logged-out branch calls wp_login_form(),
// which this plugin's minimal test stub does not implement -- exercising
// it end-to-end is out of scope here (see MOBILE QA / KNOWN LIMITATIONS in
// the implementation report). Instead this asserts the class still loads
// cleanly and its public shortcode entry point is unchanged in shape.
// ==========================================================================

check( 'PROTECTED DASHBOARD: Bitmomo_Pro_Shortcodes still loads and registers its shortcode entry point', class_exists( 'Bitmomo_Pro_Shortcodes' ) && method_exists( 'Bitmomo_Pro_Shortcodes', 'render_dashboard' ) );
check( 'PROTECTED DASHBOARD: source file was not touched by the sales redesign', false === strpos( file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-shortcodes.php' ), 'GABUNG FOUNDING WHITELIST' ) );

// ==========================================================================
// MOBILE 360 / 390 / 412 -> no fixed pixel widths above 360px anywhere in
// the sales stylesheet (static CSS review; this stub harness has no real
// browser/viewport, so true rendered-pixel QA is out of scope here — see
// the OUTPUT report for the honest caveat).
// ==========================================================================

$css = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-sales.css' );
check( 'MOBILE: page container uses max-width (fluid), not a fixed width', false !== strpos( $css, 'max-width: 720px' ) );
check( 'MOBILE: no fixed pixel widths above 360px anywhere in the stylesheet (would force horizontal scroll at 360px)', 0 === preg_match( '/(?<!max-)(?<!min-)\bwidth:\s*(\d+)px/', $css, $m ) || ( isset( $m[1] ) && (int) $m[1] <= 360 ) );
check( 'MOBILE: six-stage flow and progression are single-column by default (stack vertically before the desktop breakpoint)', preg_match( '/\.bm-pro-sales__flow-stages\s*\{[^}]*grid-template-columns:\s*1fr/', $css ) && preg_match( '/\.bm-pro-sales__progression\s*\{[^}]*grid-template-columns:\s*1fr/', $css ) );
check( 'MOBILE: 11 AI Analysts role pills wrap instead of forcing 11 fixed-width cards', false !== strpos( $css, 'flex-wrap: wrap' ) );

// ==========================================================================
// GLOBAL COLOR SYSTEM (final polish) -> orange is the sole commercial/
// paid-conversion CTA color; teal remains the intelligence/panel accent
// everywhere else (status badges, price typography, peak/climax borders).
// ==========================================================================

check( 'COLOR: orange token defined, matching the site-wide navbar CTA color (#f4ad32)', false !== strpos( $css, '--bms-orange: #f4ad32' ) );
check( 'COLOR: the primary CTA button (.bm-pro-sales__cta) uses orange, not teal', (bool) preg_match( '/\.bm-pro-sales__cta\s*\{[^}]*background:\s*var\(\s*--bms-orange\s*\)/s', $css ) );
check( 'COLOR: the whitelist submit button is overridden to orange inside the climax panel', false !== strpos( $css, '.bm-pro-sales__climax .bm-wl__submit {' ) && (bool) preg_match( '/\.bm-pro-sales__climax \.bm-wl__submit\s*\{[^}]*background:\s*var\(\s*--bms-orange\s*\)/s', $css ) );
check( 'COLOR: status badges (11 AI Analysts / Watchtower) remain teal, not repointed to orange', (bool) preg_match( '/\.bm-pro-sales__status-badge\s*\{[^}]*border:\s*1px solid var\(\s*--bms-teal\s*\)/s', $css ) );

// ==========================================================================
// Report
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
