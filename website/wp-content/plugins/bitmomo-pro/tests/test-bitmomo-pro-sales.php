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
$whitelist_source = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-whitelist.php' );
$email_source = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-email-service.php' );

// Integrity and truthfulness.
check( 'RENDER: sales page is non-empty and owns one root surface', is_string( $html ) && strlen( $html ) > 2500 && false !== strpos( $html, '<div class="bm-pro-sales">' ) );
check( 'TRUTH: no placeholder preview values', false === strpos( $html, 'XX%' ) && false === strpos( $html, '$XX,XXX' ) && false === strpos( $html, '(placeholder)' ) );
check( 'TRUTH: no fabricated accuracy claim', 0 === preg_match( '/\d+%\s*akurat/i', $html ) );
check( 'TRUTH: no 24/7 or realtime claim', false === stripos( $html, '24/7' ) && false === stripos( $html, 'real-time' ) && false === stripos( $html, 'real time' ) );
check( 'TRUTH: old seven-day refund promise cannot return', 0 === preg_match( '/7\s*(hari|day)|refund 7|7-day/i', $html . $help ) );
check( 'TRUTH: no static live-state Decision View claim', false === strpos( $html, 'Decision View aktif hari ini' ) && false === strpos( $html, 'Decision View BTC, aktif setiap hari.' ) );

// Above-the-fold cognition.
check( 'HERO: next-scenario value is explicit', false !== strpos( $html, 'Pahami skenario berikutnya — dan kapan tesis BTC berubah.' ) );
check( 'HERO: Decision View deliverables are concrete', false !== strpos( $html, 'Decision View BTC memetakan Expected Range, skenario Base/Bull/Bear, kondisi invalidasi' ) );
check( 'HERO: buy/sell misconception is handled', false !== strpos( $html, 'Bukan sinyal beli/jual' ) );
check( 'HERO: monthly and annual founding economics are visible', false !== strpos( $html, 'Founding Price Rp149.000/bulan' ) && false !== strpos( $html, 'Rp1.490.000/tahun' ) && false !== strpos( $html, 'hemat Rp298.000' ) );
check( 'HERO: cap and first batch are factual without remaining-seat theater', false !== strpos( $html, '149' ) && false !== strpos( $html, 'Founding Members' ) && false !== strpos( $html, 'Batch pertama' ) && false === stripos( $html, 'tersisa' ) );
check( 'HERO: whitelist jump plus canonical form submit are the only same-label conversion actions', 2 === substr_count( $html, 'GABUNG FOUNDING WHITELIST' ) );
check( 'HERO: secondary proof action exists', false !== strpos( $html, '>Lihat contoh Pro<' ) );

// Page navigation and focused information architecture.
foreach ( array( '#pro-product', '#pro-example', '#pro-proof', '#pro-pricing', '#pro-faq' ) as $anchor ) {
	check( 'NAV: rail exposes ' . $anchor, false !== strpos( $html, 'href="' . $anchor . '"' ) );
}
check(
	'FLOW: current product -> real example -> Free/Pro -> accountability -> economics -> FAQ',
	in_order(
		$html,
		array(
			'YANG SUDAH TERSEDIA SEKARANG',
			'PRODUCT PROOF',
			'FREE → PRO',
			'ACCOUNTABILITY',
			'FOUNDING PRICE Rp149.000/bulan',
			'Hal yang perlu jelas sebelum bergabung.'
		)
	)
);
check( 'FLOW: generic problem statement is not rendered', false === strpos( $html, 'Data ada di mana-mana. Konteks yang jarang.' ) );
check( 'FLOW: internal six-stage pipeline is not rendered', false === strpos( $html, 'Bagaimana Bitmomo mengubah data menjadi intelligence' ) );
check( 'FLOW: generic AI/experience section is not rendered', false === strpos( $html, 'AI adalah bagian dari sistem. Konteks adalah fondasinya.' ) );
check( 'FLOW: speculative roadmap is not rendered', false === strpos( $html, 'ROADMAP — SEGERA HADIR' ) && false === strpos( $html, 'Altcoin Intelligence' ) && false === strpos( $html, '11 AI Analysts' ) && false === strpos( $html, 'Watchtower' ) );

// Current product and Free vs Pro distinction.
foreach ( array( 'Expected Range', 'Scenario Map', 'Invalidasi Tesis', 'Full Monitoring', 'Confidence Context' ) as $deliverable ) {
	check( 'CURRENT PRODUCT: ' . $deliverable . ' is visible', false !== strpos( $html, $deliverable ) );
}
check( 'CURRENT PRODUCT: quality boundary is visitor-facing language', false !== strpos( $html, 'Analisis hanya ditampilkan ketika data memenuhi standar kualitas Bitmomo.' ) );
check( 'FREE VS PRO: decision-depth boundary is explicit', false !== strpos( $html, 'Bedanya bukan lebih banyak data. Bedanya adalah kedalaman keputusan.' ) );
check( 'FREE VS PRO: useful Free and deeper Pro framing is explicit', false !== strpos( $html, 'Gratis sudah cukup untuk memahami kondisi, perubahan, makna, dan satu konteks pantauan. Pro menambahkan monitoring lengkap, skenario, level, dan invalidasi' ) );
check( 'FREE VS PRO: comparison grants Free material change, meaning and one watch', false !== strpos( $html, 'What Changed · ringkas' ) && false !== strpos( $html, 'Why It Matters' ) && false !== strpos( $html, '1 konteks What to Watch' ) );
check( 'FREE VS PRO: full monitoring remains Pro-only', false !== strpos( $html, 'Full monitoring / watch set' ) );

// Public proof must be real, delayed, and fail closed.
check( 'PROOF: renderer asks only for one delayed public proof row', false !== strpos( $source, 'Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 )' ) );
check( 'PROOF: no direct current Pro brief store is queried', false === strpos( $source, 'Bitmomo_Pro_Briefs::' ) && false === strpos( $source, 'Bitmomo_Pro_Performance::' ) && false === strpos( $source, '_bitmomo_pro_' ) );
check( 'PROOF: no mock proof is manufactured', false !== strpos( $html, 'Bitmomo tidak membuat harga, confidence, atau hasil contoh untuk mengisi ruang ini.' ) );
check( 'PROOF: delayed archive boundary is visible', false !== strpos( $html, 'ARSIP ≥ 48 JAM' ) );
check( 'PROOF: public ledger/archive remains one click away', false !== strpos( $html, '/btc-intelligence/#pro-archive' ) && false !== strpos( $html, '/btc-intelligence/#decision-ledger' ) );
check( 'PROOF: public labels use final visitor language directly', false !== strpos( $source, 'HASIL +24H' ) && false !== strpos( $source, 'Rentang tercapai:' ) && false === strpos( $source, 'OUTCOME +24H' ) && false === strpos( $source, 'Range hit:' ) );

// Accountability.
check( 'ACCOUNTABILITY: recorded-before-outcome principle is explicit', false !== strpos( $html, 'Dicatat sebelum hasil diketahui' ) );
check( 'ACCOUNTABILITY: no-cherry-picking principle is explicit', false !== strpos( $html, 'Tidak memilih hasil yang bagus saja' ) );
check( 'ACCOUNTABILITY: limitations remain visible', false !== strpos( $html, 'Batas metode tetap terlihat' ) );

// Commercial clarity.
check( 'PRICE: monthly founding price is canonical', false !== strpos( $html, 'Rp149.000' ) );
check( 'PRICE: annual founding price is canonical', false !== strpos( $html, 'Rp1.490.000' ) );
check( 'PRICE: annual savings is explicit', false !== strpos( $html, 'hemat Rp298.000' ) );
check( 'PRICE: cap and first batch remain canonical', Bitmomo_Pro_Sales::SEAT_CAP === 149 && Bitmomo_Pro_Sales::BATCH_ONE === 25 );
check( 'PRICE: features are equal across billing periods', false !== strpos( $html, 'Fitur sama pada paket bulanan dan tahunan.' ) );
check( 'TERMS: cancel-anytime access-through-paid-period is visible', false !== strpos( $html, 'Batalkan kapan saja. Akses tetap aktif sampai akhir periode berlangganan yang sudah dibayar.' ) );
check( 'TERMS: final-payment policy includes access-failure exception', false !== strpos( $html, 'kegagalan pemberian akses dari sisi Bitmomo' ) );
check( 'FOUNDING: price retention boundary remains explicit', false !== strpos( $html, 'Founding Members yang menjaga membership tetap aktif mempertahankan Founding Price selama membership tersebut tetap aktif.' ) && false === strpos( $html, 'Founding Price selamanya' ) );
check( 'FOUNDING: future new-member price remains conditional', false !== strpos( $html, 'Harga untuk member baru dapat berubah' ) );
check( 'FOUNDING: standalone product pricing boundary remains explicit', false !== strpos( $html, 'Produk Bitmomo yang terpisah di masa depan dapat memiliki harga tersendiri.' ) );

// Buyer FAQ answers purchase objections without promoting speculative roadmap.
$buyer_ids = array( 'apa-itu-bitmomo-pro', 'free-vs-pro', 'sinyal-buy-atau-sell', 'harga-bitmomo-pro', 'apa-itu-founding-membership', 'batalkan-kapan-saja', 'kebijakan-refund', 'berhenti-dan-bergabung-kembali' );
foreach ( $buyer_ids as $id ) {
	check( 'FAQ: buyer question rendered: ' . $id, false !== strpos( $html, 'id="' . $id . '"' ) );
}
foreach ( array( 'apa-itu-11-ai-analysts', 'apa-itu-watchtower', 'apakah-analysts-tersedia' ) as $future_id ) {
	check( 'FAQ: roadmap question not promoted: ' . $future_id, false === strpos( $html, 'id="' . $future_id . '"' ) );
}
check( 'FAQ: answers come from canonical Help Center categories()', false !== strpos( $source, 'Bitmomo_Pro_Help_Center::categories()' ) );
check( 'FAQ: full Help Center remains linked', false !== strpos( $html, '/help/' ) );
check( 'HELP: public product name is BTC Intelligence', false !== strpos( $help, "'title' => 'BTC Intelligence'") && false === strpos( $help, "'title' => 'BTC Daily Intelligence'") );

// Whitelist write path and privacy behavior.
reset_test_sales_state();
$whitelist = Bitmomo_Pro_Whitelist::instance();
$result = $whitelist->submit_entry( array( 'email' => 'salespagecheck@example.com', 'consent' => true, 'source' => 'pro_page' ) );
check( 'WHITELIST: canonical submit_entry creates a record', true === $result['ok'] && 'created' === $result['status'] );
check( 'WHITELIST: canonical storage deduplicates into one record', 1 === $whitelist->count_total() );
check( 'WHITELIST: joining does not guarantee a seat', false !== strpos( $html, 'Masuk whitelist tidak menjamin tempat.' ) );
check( 'WHITELIST: consent exposes Privacy link', false !== strpos( $html, '/kebijakan-privasi/' ) );
check( 'WHITELIST: WhatsApp acquisition is fail-closed by default', false === Bitmomo_Pro_Whitelist::whatsapp_opt_in_enabled() && false === strpos( $html, 'Tambahkan WhatsApp agar tidak melewatkan pemberitahuan' ) );
check( 'WHITELIST: dormant WhatsApp capability requires explicit enablement', false !== strpos( $whitelist_source, 'BITMOMO_PRO_WHATSAPP_OPT_IN_ENABLED' ) && false !== strpos( $whitelist_source, 'bitmomo_pro_whatsapp_opt_in_enabled' ) );
check( 'EMAIL: confirmation copy is channel-aware', false !== strpos( $email_source, 'Kami akan mengirim pemberitahuan melalui email ini saat akses dibuka.' ) && false !== strpos( $email_source, 'if ( $whatsapp_enabled )' ) );

function reset_test_sales_state() {
	$GLOBALS['__wp_stub_posts']      = array();
	$GLOBALS['__wp_stub_postmeta']   = array();
	$GLOBALS['__wp_stub_mail_log']   = array();
	$GLOBALS['__wp_stub_options']    = array();
	$GLOBALS['__wp_stub_action_log'] = array();
}

// Protected product implementation must remain decoupled from sales copy.
check( 'PROTECTED: protected dashboard class remains available', class_exists( 'Bitmomo_Pro_Shortcodes' ) && method_exists( 'Bitmomo_Pro_Shortcodes', 'render_dashboard' ) );
check( 'PROTECTED: whitelist CTA copy does not leak into protected dashboard source', false === strpos( file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-shortcodes.php' ), 'GABUNG FOUNDING WHITELIST' ) );
check( 'PUBLIC: sales renderer does not read current user/entitlement state', false === strpos( $source, 'bitmomo_user_has_pro_access' ) && false === strpos( $source, 'get_current_user_id' ) && false === strpos( $source, 'get_current_brief_for_display' ) );

// Institutional visual / performance contract.
check( 'LAYOUT: Pro page uses canonical 1180px shell token', false !== strpos( $css, '--bms-shell:var(--bm-shell-width,1180px)' ) && false === strpos( $css, 'max-width:720px' ) );
check( 'LAYOUT: internal product rail is sticky under canonical header', false !== strpos( $css, '.bm-pro-sales__rail{position:sticky;top:var(--bm-header-height,64px)' ) );
check( 'LAYOUT: mobile rail uses 60px header and 44px targets', false !== strpos( $css, 'top:var(--bm-header-height-mobile,60px)') && false !== strpos( $css, '.bm-pro-sales__rail a{min-height:44px}' ) );
check( 'LAYOUT: commercial CTA has at least 48px target height', false !== strpos( $css, 'min-height:48px' ) );
check( 'LAYOUT: explicit responsive contracts exist', false !== strpos( $css, '@media(max-width:900px)') && false !== strpos( $css, '@media(max-width:768px)') && false !== strpos( $css, '@media(max-width:420px)') );
check( 'ACCESSIBILITY: focus-visible owner exists', false !== strpos( $css, ':focus-visible' ) );
check( 'ACCESSIBILITY: reduced-motion safety exists', false !== strpos( $css, '@media(prefers-reduced-motion:reduce)' ) );
check( 'PERFORMANCE: no third-party font/image/script dependency introduced', 0 === preg_match( '/https?:\/\//', $css ) && 0 === preg_match( '/<script|<img\b/i', $source ) );
check( 'PERFORMANCE: sales CSS uses dedicated asset version', false !== strpos( $source, "SALES_ASSET_VERSION = '2026.09.14-pro-cognition-v2'" ) );

// Trust layer.
check( 'TRUST: Decision Ledger, Help, Privacy and Disclaimer are discoverable', false !== strpos( $html, 'Decision Ledger' ) && false !== strpos( $html, 'Help Center' ) && false !== strpos( $html, 'Kebijakan Privasi' ) && false !== strpos( $html, 'Disclaimer' ) );
check( 'TRUST: support uses configured email instead of hard-coded identity', false !== strpos( $source, 'bitmomo_pro_support_email' ) && false === strpos( $source, 'mailto:hi@bitmomo.id' ) );
check( 'TRUST: sales page does not fabricate a Terms route', false === strpos( $source, '/syarat-layanan/' ) );

$failures = array_values( array_filter( $results, function( $row ) { return ! $row['pass']; } ) );
foreach ( $results as $row ) echo '[' . ( $row['pass'] ? 'PASS' : 'FAIL' ) . '] ' . $row['label'] . "\n";
printf( "\n%d/%d passed.\n", count( $results ) - count( $failures ), count( $results ) );
if ( $failures ) {
	foreach ( $failures as $failure ) fwrite( STDERR, '- ' . $failure['label'] . "\n" );
	exit( 1 );
}
exit( 0 );