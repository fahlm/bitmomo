<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Bitmomo Pro Launch Readiness" — a founder/admin deployment and
 * integration checklist generated from actual WordPress/plugin state.
 *
 * This is NOT a product-analytics dashboard and NOT automated browser
 * testing. It answers one narrow question: "what can this plugin itself
 * prove about its own installed/configured state right now?" — nothing
 * more. Three deliberate boundaries:
 *
 * 1. Section A only checks things this plugin can actually query from
 *    inside PHP/WordPress (post types, options, entitlement/usage meta,
 *    brief readiness). It never fabricates a PASS.
 * 2. Section B is an explicit, permanent list of things this plugin
 *    cannot verify from inside itself — real-world mail deliverability,
 *    hosting/cache behavior, SSL, payment success, and whether the
 *    canonical adapter is actually returning real production data. Those
 *    always read "RUNTIME VERIFICATION REQUIRED", never a fake PASS.
 * 3. Section D's overall status is only ever "NOT READY" or "STAGING-READY
 *    CANDIDATE" — this plugin cannot see Hostinger, DNS, SSL, or a real
 *    payment provider, so it can never honestly claim "PRODUCTION READY".
 *
 * Founder/admin only (manage_options) — this screen can reveal
 * operational counts (membership numbers, email send status) that are not
 * appropriate for a lower-privileged editor.
 */
class Bitmomo_Pro_Launch_Readiness {

	const STATUS_PASS = 'pass';
	const STATUS_WARN = 'warn';
	const STATUS_FAIL = 'fail';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE,
			__( 'Bitmomo Pro Launch Readiness', 'bitmomo-pro' ),
			__( 'Launch Readiness', 'bitmomo-pro' ),
			'manage_options',
			'bitmomo-pro-launch-readiness',
			array( $this, 'render_page' )
		);
	}

	// ==================================================================
	// SECTION A — plugin/runtime-checkable state
	// ==================================================================

	private function section_plugin() {
		$rows = array();

		$rows[] = array(
			'label'  => __( 'Plugin version', 'bitmomo-pro' ),
			'status' => defined( 'BITMOMO_PRO_VERSION' ) ? self::STATUS_PASS : self::STATUS_FAIL,
			'detail' => defined( 'BITMOMO_PRO_VERSION' ) ? BITMOMO_PRO_VERSION : __( 'BITMOMO_PRO_VERSION not defined.', 'bitmomo-pro' ),
		);

		$required_classes = array(
			'Bitmomo_Pro_Entitlement_Service',
			'Bitmomo_Pro_Entitlements',
			'Bitmomo_Pro_Briefs',
			'Bitmomo_Pro_Brief_Readiness',
			'Bitmomo_Pro_Brief_Prefill',
			'Bitmomo_Pro_Canonical_Adapter',
			'Bitmomo_Pro_Shortcodes',
			'Bitmomo_Pro_Help_Center',
			'Bitmomo_Pro_Sales',
			'Bitmomo_Pro_Cache',
			'Bitmomo_Pro_Setup',
			'Bitmomo_Pro_Users_List',
			'Bitmomo_Pro_Email_Service',
			'Bitmomo_Pro_Usage',
			'Bitmomo_Pro_Activation',
			'Bitmomo_Pro_Account',
			'Bitmomo_Pro_Daily',
			'Bitmomo_Pro_Whitelist',
		);

		$missing = array();
		foreach ( $required_classes as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				$missing[] = $class_name;
			}
		}

		$rows[] = array(
			'label'  => __( 'Core service classes loaded', 'bitmomo-pro' ),
			'status' => empty( $missing ) ? self::STATUS_PASS : self::STATUS_FAIL,
			'detail' => empty( $missing )
				? sprintf( __( 'All %d expected classes present.', 'bitmomo-pro' ), count( $required_classes ) )
				: sprintf( __( 'Missing: %s', 'bitmomo-pro' ), implode( ', ', $missing ) ),
		);

		$rows[] = array(
			'label'  => __( 'bm_pro_brief post type registered', 'bitmomo-pro' ),
			'status' => post_type_exists( Bitmomo_Pro_Briefs::POST_TYPE ) ? self::STATUS_PASS : self::STATUS_FAIL,
			'detail' => post_type_exists( Bitmomo_Pro_Briefs::POST_TYPE )
				? __( 'Registered, private (no public URL/REST route), as intended.', 'bitmomo-pro' )
				: __( 'Not registered — activation hook may not have run.', 'bitmomo-pro' ),
		);

		return $rows;
	}

	/** Public/product routes whose shortcode ownership can be proven here. */
	private function target_pages() {
		return array(
			array( 'path' => 'pro', 'label' => __( '/pro (sales)', 'bitmomo-pro' ), 'shortcode' => 'bitmomo_pro_sales', 'setup' => true ),
			array( 'path' => 'pro-dashboard', 'label' => __( '/pro-dashboard', 'bitmomo-pro' ), 'shortcode' => 'bitmomo_pro_dashboard', 'setup' => true ),
			array( 'path' => 'pro/account', 'label' => __( '/pro/account', 'bitmomo-pro' ), 'shortcode' => 'bitmomo_pro_account', 'setup' => true ),
			array( 'path' => 'help', 'label' => __( '/help', 'bitmomo-pro' ), 'shortcode' => 'bitmomo_help_center', 'setup' => false ),
		);
	}

	private function section_pages() {
		$rows = array();

		foreach ( $this->target_pages() as $target ) {
			$page = get_page_by_path( $target['path'], OBJECT, 'page' );

			if ( ! $page ) {
				$rows[] = array(
					'label'  => $target['label'],
					'status' => self::STATUS_FAIL,
					'detail' => ! empty( $target['setup'] )
						? __( 'Page does not exist yet. Create it from Bitmomo Pro → Setup.', 'bitmomo-pro' )
						: __( 'Required public page does not exist yet.', 'bitmomo-pro' ),
				);
				continue;
			}

			$has_shortcode = has_shortcode( (string) $page->post_content, $target['shortcode'] );
			$is_published  = ( 'publish' === $page->post_status );

			$status = self::STATUS_PASS;
			if ( ! $is_published ) {
				$status = self::STATUS_WARN;
			}
			if ( ! $has_shortcode ) {
				$status = self::STATUS_FAIL;
			}

			$rows[] = array(
				'label'  => $target['label'],
				'status' => $status,
				'detail' => sprintf(
					__( 'Status: %1$s. Shortcode [%3$s] present: %2$s.', 'bitmomo-pro' ),
					$page->post_status,
					$has_shortcode ? __( 'yes', 'bitmomo-pro' ) : __( 'no', 'bitmomo-pro' ),
					$target['shortcode']
				),
			);
		}

		return $rows;
	}

	/** Configuration shown exactly as the public conversion path uses it. */
	private function section_config() {
		$rows = array();
		$checkout_url_raw = function_exists( 'bitmomo_pro_get_checkout_url_raw' ) ? bitmomo_pro_get_checkout_url_raw() : '';
		$checkout_url     = function_exists( 'bitmomo_pro_get_checkout_url' ) ? bitmomo_pro_get_checkout_url() : '';

		if ( '' !== trim( (string) $checkout_url_raw ) && empty( $checkout_url ) ) {
			$rows[] = array(
				'label'  => __( 'Checkout URL configured', 'bitmomo-pro' ),
				'status' => self::STATUS_FAIL,
				'detail' => sprintf( __( 'A value is set but is not a valid http(s) URL. Public conversion therefore fails closed to the Founding Whitelist: %s', 'bitmomo-pro' ), (string) $checkout_url_raw ),
			);
		} else {
			$rows[] = array(
				'label'  => __( 'Checkout URL configured', 'bitmomo-pro' ),
				'status' => empty( $checkout_url ) ? self::STATUS_WARN : self::STATUS_PASS,
				'detail' => empty( $checkout_url )
					? __( 'Not set — Founding Whitelist is the public conversion path. This is the required state for whitelist-only launch.', 'bitmomo-pro' )
					: esc_url_raw( $checkout_url ),
			);
		}

		$dashboard_url = function_exists( 'bitmomo_pro_get_dashboard_url' ) ? bitmomo_pro_get_dashboard_url() : '';
		$rows[]        = array(
			'label'  => __( 'Dashboard URL resolves', 'bitmomo-pro' ),
			'status' => empty( $dashboard_url ) ? self::STATUS_WARN : self::STATUS_PASS,
			'detail' => empty( $dashboard_url )
				? __( 'Not set and no published /pro-dashboard page found to auto-detect. Welcome/daily emails will omit the dashboard link.', 'bitmomo-pro' )
				: esc_url_raw( $dashboard_url ),
		);

		$support_email = sanitize_email( (string) apply_filters( 'bitmomo_pro_support_email', get_option( 'admin_email' ) ) );
		$rows[]        = array(
			'label'  => __( 'Support email valid', 'bitmomo-pro' ),
			'status' => is_email( $support_email ) ? self::STATUS_PASS : self::STATUS_FAIL,
			'detail' => is_email( $support_email ) ? (string) $support_email : __( 'Configured support/admin email is missing or invalid.', 'bitmomo-pro' ),
		);

		$whatsapp_enabled = class_exists( 'Bitmomo_Pro_Whitelist' ) && method_exists( 'Bitmomo_Pro_Whitelist', 'whatsapp_opt_in_enabled' )
			? Bitmomo_Pro_Whitelist::whatsapp_opt_in_enabled()
			: false;
		$rows[] = array(
			'label'  => __( 'Whitelist WhatsApp opt-in', 'bitmomo-pro' ),
			'status' => $whatsapp_enabled ? self::STATUS_WARN : self::STATUS_PASS,
			'detail' => $whatsapp_enabled
				? __( 'Enabled. Do not launch this channel until its notification transport and browser/runtime acceptance are verified.', 'bitmomo-pro' )
				: __( 'Disabled/fail-closed — expected for Whitelist V1.', 'bitmomo-pro' ),
		);

		return $rows;
	}

	private function section_product() {
		$rows  = array();
		$brief = Bitmomo_Pro_Briefs::get_latest_brief();

		if ( null === $brief ) {
			$rows[] = array(
				'label'  => __( 'Current brief exists', 'bitmomo-pro' ),
				'status' => self::STATUS_FAIL,
				'detail' => __( 'No bm_pro_brief has ever been published. The dashboard will correctly show its safe "belum tersedia" state.', 'bitmomo-pro' ),
			);
			return $rows;
		}

		$rows[] = array(
			'label'  => __( 'Current brief exists', 'bitmomo-pro' ),
			'status' => self::STATUS_PASS,
			'detail' => sprintf( __( '#%1$d — %2$s', 'bitmomo-pro' ), (int) $brief['id'], (string) $brief['title'] ),
		);

		$eval = Bitmomo_Pro_Brief_Readiness::instance()->evaluate_post( (int) $brief['id'] );
		$rows[] = array(
			'label'  => __( 'Passes release readiness', 'bitmomo-pro' ),
			'status' => $eval['ready'] ? self::STATUS_PASS : self::STATUS_FAIL,
			'detail' => $eval['ready'] ? __( 'SIAP DIRILIS', 'bitmomo-pro' ) : implode( ' ', $eval['issues'] ),
		);

		$result = Bitmomo_Pro_Briefs::get_current_brief_for_display();
		$tier   = $result['tier'];
		$tier_status = self::STATUS_PASS;
		if ( Bitmomo_Pro_Brief_Readiness::TIER_DELAYED === $tier || Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $tier ) {
			$tier_status = self::STATUS_WARN;
		}
		$rows[] = array(
			'label'  => __( 'Freshness tier (as the dashboard would render it)', 'bitmomo-pro' ),
			'status' => $tier_status,
			'detail' => ( Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $tier && null === $result['brief'] )
				? __( 'UNAVAILABLE — intended safe state. Publish a fresh, complete brief to clear this.', 'bitmomo-pro' )
				: strtoupper( $tier ),
		);

		return $rows;
	}

	private function section_membership() {
		$rows = array();
		$entitlement_service = Bitmomo_Pro_Entitlement_Service::instance();
		$founding_count       = $entitlement_service->count_active_founding_members();
		$founding_cap         = Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP;

		$rows[] = array(
			'label'  => __( 'Founding Beta seats', 'bitmomo-pro' ),
			'status' => self::STATUS_PASS,
			'detail' => sprintf( __( '%1$d / %2$d', 'bitmomo-pro' ), (int) $founding_count, (int) $founding_cap ),
		);

		$usage = Bitmomo_Pro_Usage::instance();
		$by_class = $usage->counts_by_validation_class();
		$active_total = array_sum( $by_class );
		$rows[] = array( 'label' => __( 'Active Pro users (all sources)', 'bitmomo-pro' ), 'status' => self::STATUS_PASS, 'detail' => (string) (int) $active_total );

		list( $cold_active, $cold_activated ) = $this->cold_activation_crosstab();
		$rows[] = array( 'label' => __( 'Cold-lead active users', 'bitmomo-pro' ), 'status' => self::STATUS_PASS, 'detail' => (string) $cold_active );
		$rows[] = array(
			'label'  => __( 'Cold-lead users activated (viewed >= 1 current brief)', 'bitmomo-pro' ),
			'status' => self::STATUS_PASS,
			'detail' => $cold_active > 0 ? sprintf( __( '%1$d / %2$d', 'bitmomo-pro' ), $cold_activated, $cold_active ) : __( 'No cold-lead active users yet.', 'bitmomo-pro' ),
		);
		return $rows;
	}

	private function cold_activation_crosstab() {
		$users = get_users( array( 'meta_key' => Bitmomo_Pro_Entitlements::META_STATUS, 'meta_value' => 'active', 'fields' => 'ID' ) );
		$entitlement_service = Bitmomo_Pro_Entitlement_Service::instance();
		$usage = Bitmomo_Pro_Usage::instance();
		$cold_active = 0;
		$cold_activated = 0;
		foreach ( $users as $user_id ) {
			if ( 'active' !== $entitlement_service->get_status( $user_id ) ) continue;
			if ( 'cold' !== get_user_meta( $user_id, Bitmomo_Pro_Usage::META_VALIDATION_CLASS, true ) ) continue;
			$cold_active++;
			if ( $usage->is_activated( $user_id ) ) $cold_activated++;
		}
		return array( $cold_active, $cold_activated );
	}

	private function section_email() {
		$rows = array();
		$rows[] = array(
			'label'  => __( 'Welcome/daily email service loaded', 'bitmomo-pro' ),
			'status' => class_exists( 'Bitmomo_Pro_Email_Service' ) ? self::STATUS_PASS : self::STATUS_FAIL,
			'detail' => class_exists( 'Bitmomo_Pro_Email_Service' )
				? __( 'Bitmomo_Pro_Email_Service is the only class in this plugin that calls wp_mail().', 'bitmomo-pro' )
				: __( 'Class not found.', 'bitmomo-pro' ),
		);

		$result = Bitmomo_Pro_Briefs::get_current_brief_for_display();
		$brief  = $result['brief'];
		if ( null === $brief || ! isset( $brief['id'] ) ) {
			$rows[] = array( 'label' => __( 'Current brief daily-email status', 'bitmomo-pro' ), 'status' => self::STATUS_WARN, 'detail' => __( 'No current brief to check (see PRODUCT section above).', 'bitmomo-pro' ) );
			return $rows;
		}

		$sent_at = get_post_meta( $brief['id'], Bitmomo_Pro_Email_Service::META_DAILY_SENT_AT, true );
		$test_mode = get_post_meta( $brief['id'], Bitmomo_Pro_Email_Service::META_DAILY_TEST_MODE, true );
		if ( ! $sent_at ) {
			$rows[] = array( 'label' => __( 'Current brief daily-email status', 'bitmomo-pro' ), 'status' => self::STATUS_WARN, 'detail' => __( 'Not sent yet. Send from the "Kirim Email Harian" box on the brief edit screen.', 'bitmomo-pro' ) );
		} else {
			$success = (int) get_post_meta( $brief['id'], Bitmomo_Pro_Email_Service::META_DAILY_SUCCESS_COUNT, true );
			$failure = (int) get_post_meta( $brief['id'], Bitmomo_Pro_Email_Service::META_DAILY_FAILURE_COUNT, true );
			$rows[] = array(
				'label'  => __( 'Current brief daily-email status', 'bitmomo-pro' ),
				'status' => $test_mode ? self::STATUS_WARN : self::STATUS_PASS,
				'detail' => sprintf( __( 'Sent %1$s (%2$s) — %3$d success, %4$d failure per wp_mail() return value only; actual inbox delivery still needs runtime verification.', 'bitmomo-pro' ), (string) $sent_at, $test_mode ? __( 'test mode', 'bitmomo-pro' ) : __( 'real send', 'bitmomo-pro' ), $success, $failure ),
			);
		}
		return $rows;
	}

	// ==================================================================
	// RENDER
	// ==================================================================

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$sections = array(
			'PLUGIN'     => $this->section_plugin(),
			'PAGES'      => $this->section_pages(),
			'CONFIG'     => $this->section_config(),
			'PRODUCT'    => $this->section_product(),
			'MEMBERSHIP' => $this->section_membership(),
			'EMAIL'      => $this->section_email(),
		);
		$overall = $this->compute_overall_status( $sections );

		echo '<div class="wrap"><h1>' . esc_html__( 'Bitmomo Pro — Launch Readiness', 'bitmomo-pro' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Plugin-internal checklist only. It does not replace the canonical Whitelist V1 artifact, staging runtime, browser/accessibility, legal-content, BTC-freshness, Research, or whitelist-flow release gates.', 'bitmomo-pro' ) . '</p>';
		$this->render_overall_banner( $overall );
		echo '<h2>' . esc_html__( 'A. Plugin &amp; Product State', 'bitmomo-pro' ) . '</h2>';
		foreach ( $sections as $title => $rows ) $this->render_check_table( $title, $rows );
		$this->render_section_b();
		$this->render_section_c();
		echo '</div>';
	}

	private function render_overall_banner( $overall ) {
		$colors = array( 'not_ready' => '#b32d2e', 'staging_ready' => '#a16207' );
		$labels = array( 'not_ready' => __( 'NOT READY', 'bitmomo-pro' ), 'staging_ready' => __( 'PLUGIN-INTERNAL STAGING CANDIDATE', 'bitmomo-pro' ) );
		printf(
			'<div style="border:2px solid %1$s;background:#fff;padding:14px 18px;max-width:820px;margin:16px 0;"><p style="margin:0 0 6px 0;font-size:18px;font-weight:700;color:%1$s;">%2$s: %3$s</p><p style="margin:0;font-size:12px;color:#555;">%4$s</p></div>',
			esc_attr( $colors[ $overall['state'] ] ),
			esc_html__( 'D. Overall Launch Status', 'bitmomo-pro' ),
			esc_html( $labels[ $overall['state'] ] ),
			esc_html__( 'This screen can never authorize staging or production on its own. Canonical release acceptance is external to this plugin.', 'bitmomo-pro' )
		);
		if ( 'staging_ready' === $overall['state'] ) {
			echo '<p class="description" style="max-width:820px;">' . esc_html__( 'Core plugin criteria are internally coherent. Continue with the canonical Whitelist V1 release gates; do not treat this banner as deployment approval.', 'bitmomo-pro' ) . '</p>';
		} else {
			echo '<p class="description" style="max-width:820px;"><strong>' . esc_html__( 'Blocking:', 'bitmomo-pro' ) . '</strong> ' . esc_html( implode( ' ', $overall['blocking'] ) ) . '</p>';
		}
	}

	private function compute_overall_status( $sections ) {
		$blocking = array();
		foreach ( $sections['PLUGIN'] as $row ) if ( self::STATUS_FAIL === $row['status'] ) $blocking[] = $row['label'] . ': ' . $row['detail'];

		$product_ok = false;
		foreach ( $sections['PRODUCT'] as $row ) {
			if ( __( 'Passes release readiness', 'bitmomo-pro' ) === $row['label'] && self::STATUS_PASS === $row['status'] ) $product_ok = true;
			if ( __( 'Current brief exists', 'bitmomo-pro' ) === $row['label'] && self::STATUS_FAIL === $row['status'] ) $blocking[] = __( 'No current brief exists — publish at least one complete Pro brief.', 'bitmomo-pro' );
		}
		if ( ! $product_ok && ! in_array( __( 'No current brief exists — publish at least one complete Pro brief.', 'bitmomo-pro' ), $blocking, true ) ) $blocking[] = __( 'Current brief does not pass release readiness.', 'bitmomo-pro' );
		foreach ( $sections['EMAIL'] as $row ) if ( __( 'Welcome/daily email service loaded', 'bitmomo-pro' ) === $row['label'] && self::STATUS_FAIL === $row['status'] ) $blocking[] = $row['label'] . ': ' . $row['detail'];

		return array( 'state' => empty( $blocking ) ? 'staging_ready' : 'not_ready', 'blocking' => $blocking );
	}

	private function status_badge( $status ) {
		$colors = array(
			self::STATUS_PASS => array( '#1a7f4b', __( 'PASS', 'bitmomo-pro' ) ),
			self::STATUS_WARN => array( '#a16207', __( 'ATTENTION', 'bitmomo-pro' ) ),
			self::STATUS_FAIL => array( '#b32d2e', __( 'FAIL', 'bitmomo-pro' ) ),
		);
		list( $color, $label ) = isset( $colors[ $status ] ) ? $colors[ $status ] : array( '#555', strtoupper( $status ) );
		return sprintf( '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:700;color:#fff;background:%s;">%s</span>', esc_attr( $color ), esc_html( $label ) );
	}

	private function render_check_table( $title, $rows ) {
		echo '<h3 style="margin-top:22px;">' . esc_html( $title ) . '</h3>';
		echo '<table class="widefat striped" style="max-width:960px;"><thead><tr><th style="width:320px;">' . esc_html__( 'Check', 'bitmomo-pro' ) . '</th><th style="width:110px;">' . esc_html__( 'Status', 'bitmomo-pro' ) . '</th><th>' . esc_html__( 'Detail', 'bitmomo-pro' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) printf( '<tr><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html( $row['label'] ), $this->status_badge( $row['status'] ), esc_html( $row['detail'] ) );
		echo '</tbody></table>';
	}

	private function render_section_b() {
		$items = array(
			__( 'Actual wp_mail() deliverability — the plugin can capture/generate a message, but inbox delivery and spam placement require a bounded real-recipient preflight.', 'bitmomo-pro' ),
			__( 'Hosting/LiteSpeed/CDN cache behavior and exact first-party asset bytes served to a guest browser.', 'bitmomo-pro' ),
			__( 'Production SSL/DNS/network reachability and security/cache headers.', 'bitmomo-pro' ),
			__( 'End-to-end payment success — checkout remains intentionally disabled for whitelist-only launch.', 'bitmomo-pro' ),
			__( 'Whether the canonical public BTC intelligence snapshot is fresh/current enough for launch; use the canonical staging-readiness age gate.', 'bitmomo-pro' ),
			__( 'Whether Privacy/Disclaimer WordPress content matches canonical release copy and whether qualified Market Research exists in the staging database.', 'bitmomo-pro' ),
			__( 'Responsive, keyboard, 200% zoom, Axe, console, Market Context failure/race, and real whitelist browser-flow acceptance.', 'bitmomo-pro' ),
		);
		echo '<h2 style="margin-top:32px;">' . esc_html__( 'B. Cannot Verify From Here — Runtime Verification Required', 'bitmomo-pro' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'These checks belong to the canonical release acceptance layer. They never become green merely because this plugin screen looks healthy.', 'bitmomo-pro' ) . '</p>';
		echo '<ul style="max-width:900px;list-style:disc;margin-left:22px;">';
		foreach ( $items as $item ) printf( '<li style="margin-bottom:8px;"><span style="display:inline-block;padding:1px 7px;border-radius:3px;font-size:10px;font-weight:700;color:#fff;background:#555;margin-right:6px;">%s</span> %s</li>', esc_html__( 'RUNTIME VERIFICATION REQUIRED', 'bitmomo-pro' ), esc_html( $item ) );
		echo '</ul>';
	}

	private function render_section_c() {
		$items = array(
			__( 'Deploy only the exact artifact from the accepted release/whitelist-v1 candidate; verify commit/tree/artifact hash and guest asset/CDN coherence before visual QA.', 'bitmomo-pro' ),
			__( 'Verify /, /btc-intelligence/, /pro/, /help/, /category/riset/, one qualified Research article, /tentang-kami/, legal pages, /pro/account/, search and 404 across the canonical responsive/browser matrix.', 'bitmomo-pro' ),
			__( 'Verify the public BTC snapshot is available, within the launch age budget, and honestly switches to delayed/unavailable when quality/freshness gates fail.', 'bitmomo-pro' ),
			__( 'Verify Market Context 7D/30D/90D/YTD/1Y, rapid range changes, provider failure, JavaScript-off fallback, and no stale range payload after errors.', 'bitmomo-pro' ),
			__( 'Verify the Founding Whitelist rejects invalid email/missing consent, persists a new record once, deduplicates repeat signup, shows the success state, and generates exactly one confirmation email contract.', 'bitmomo-pro' ),
			__( 'For Whitelist V1, confirm checkout is disabled and WhatsApp opt-in is fail-closed. Do not expose either capability merely because dormant code exists.', 'bitmomo-pro' ),
			__( 'Verify canonical Privacy/Disclaimer content in WordPress and at least two qualified Market Research publications; generic Riset/AI Lab content is not a substitute.', 'bitmomo-pro' ),
			__( 'Verify support contact, lost-password/account path, Help deep links, footer trust links, and newsletter fail-closed behavior without a legacy popup.', 'bitmomo-pro' ),
			__( 'Run keyboard, short-height mobile menu, 200% zoom, Axe serious/critical, console/page-error, fresh-cache and warm-cache acceptance before requesting production authorization.', 'bitmomo-pro' ),
		);
		echo '<h2 style="margin-top:32px;">' . esc_html__( 'C. Canonical Staging Verification Checklist', 'bitmomo-pro' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Operator reference only. The executable scripts and exact release evidence remain authoritative.', 'bitmomo-pro' ) . '</p>';
		echo '<ol style="max-width:900px;margin-left:22px;">';
		foreach ( $items as $item ) echo '<li style="margin-bottom:10px;">' . esc_html( $item ) . '</li>';
		echo '</ol>';
	}
}
