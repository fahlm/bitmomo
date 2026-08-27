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
 *    hosting/cache behavior, SSL, payment success, and whether Codex's
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

	/**
	 * PLUGIN: is bitmomo-pro itself loaded, and are all the classes this
	 * PR's stack (#27-#36) depends on actually present. A missing class
	 * here means a fatal dependency-ordering problem, not a soft warning.
	 */
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
			'Bitmomo_Pro_Shortcodes',
			'Bitmomo_Pro_Sales',
			'Bitmomo_Pro_Cache',
			'Bitmomo_Pro_Setup',
			'Bitmomo_Pro_Users_List',
			'Bitmomo_Pro_Email_Service',
			'Bitmomo_Pro_Usage',
			'Bitmomo_Pro_Activation',
			'Bitmomo_Pro_Account',
			'Bitmomo_Pro_Daily',
		);

		$missing = array();
		foreach ( $required_classes as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				$missing[] = $class_name;
			}
		}

		$rows[] = array(
			'label'  => __( 'Core service classes loaded (#27-#36)', 'bitmomo-pro' ),
			'status' => empty( $missing ) ? self::STATUS_PASS : self::STATUS_FAIL,
			'detail' => empty( $missing )
				? sprintf(
					/* translators: %d: number of classes checked */
					__( 'All %d expected classes present.', 'bitmomo-pro' ),
					count( $required_classes )
				)
				: sprintf(
					/* translators: %s: comma-separated list of missing class names */
					__( 'Missing: %s', 'bitmomo-pro' ),
					implode( ', ', $missing )
				),
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

	/**
	 * PAGES: existence/published status/shortcode presence for the three
	 * pages Bitmomo_Pro_Setup offers to provision. Read-only — never
	 * creates or modifies a page from this screen.
	 */
	private function target_pages() {
		return array(
			array(
				'path'      => 'pro',
				'label'     => __( '/pro (sales)', 'bitmomo-pro' ),
				'shortcode' => 'bitmomo_pro_sales',
			),
			array(
				'path'      => 'pro-dashboard',
				'label'     => __( '/pro-dashboard', 'bitmomo-pro' ),
				'shortcode' => 'bitmomo_pro_dashboard',
			),
			array(
				'path'      => 'pro/account',
				'label'     => __( '/pro/account', 'bitmomo-pro' ),
				'shortcode' => 'bitmomo_pro_account',
			),
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
					'detail' => __( 'Page does not exist yet. Create it from Bitmomo Pro → Setup.', 'bitmomo-pro' ),
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
					/* translators: 1: post status, 2: yes/no shortcode presence */
					__( 'Status: %1$s. Shortcode [%3$s] present: %2$s.', 'bitmomo-pro' ),
					$page->post_status,
					$has_shortcode ? __( 'yes', 'bitmomo-pro' ) : __( 'no', 'bitmomo-pro' ),
					$target['shortcode']
				),
			);
		}

		return $rows;
	}

	/**
	 * CONFIG: checkout URL, dashboard URL, support email. All three are
	 * allowed to be unconfigured during Founding Beta (the plugin degrades
	 * gracefully — see Bitmomo_Pro_Shortcodes::checkout_cta()) but a
	 * founder needs to see that state plainly rather than guess.
	 */
	private function section_config() {
		$rows = array();

		$checkout_url = function_exists( 'bitmomo_pro_get_checkout_url' ) ? bitmomo_pro_get_checkout_url() : '';
		$rows[]       = array(
			'label'  => __( 'Checkout URL configured', 'bitmomo-pro' ),
			'status' => empty( $checkout_url ) ? self::STATUS_WARN : self::STATUS_PASS,
			'detail' => empty( $checkout_url )
				? __( 'Not set — sales/dashboard CTAs fall back to a manual-activation note (by design, not a bug).', 'bitmomo-pro' )
				: esc_url_raw( $checkout_url ),
		);

		$dashboard_url = function_exists( 'bitmomo_pro_get_dashboard_url' ) ? bitmomo_pro_get_dashboard_url() : '';
		$rows[]        = array(
			'label'  => __( 'Dashboard URL resolves', 'bitmomo-pro' ),
			'status' => empty( $dashboard_url ) ? self::STATUS_WARN : self::STATUS_PASS,
			'detail' => empty( $dashboard_url )
				? __( 'Not set and no published /pro-dashboard page found to auto-detect. Welcome/daily emails will omit the dashboard link.', 'bitmomo-pro' )
				: esc_url_raw( $dashboard_url ),
		);

		$support_email = apply_filters( 'bitmomo_pro_support_email', get_option( 'admin_email' ) );
		$rows[]        = array(
			'label'  => __( 'Support email valid', 'bitmomo-pro' ),
			'status' => is_email( $support_email ) ? self::STATUS_PASS : self::STATUS_FAIL,
			'detail' => is_email( $support_email ) ? (string) $support_email : __( 'admin_email option is missing or not a valid email address.', 'bitmomo-pro' ),
		);

		return $rows;
	}

	/**
	 * PRODUCT: does a current Pro brief exist, does it pass readiness, and
	 * what freshness tier does the display path actually resolve to.
	 * Deliberately reads Bitmomo_Pro_Briefs::get_current_brief_for_display()
	 * — the exact method the dashboard shortcode calls — rather than a
	 * second, parallel readiness check.
	 */
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
			/* translators: 1: post ID, 2: post title */
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
		if ( Bitmomo_Pro_Brief_Readiness::TIER_DELAYED === $tier ) {
			$tier_status = self::STATUS_WARN;
		} elseif ( Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $tier ) {
			$tier_status = self::STATUS_WARN; // Safe state, not a plugin defect — see detail.
		}
		$rows[] = array(
			'label'  => __( 'Freshness tier (as the dashboard would render it)', 'bitmomo-pro' ),
			'status' => $tier_status,
			'detail' => ( Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $tier && null === $result['brief'] )
				? __( 'UNAVAILABLE — this is the intended safe state (stale/invalid brief never silently shown), not a bug. Publish a fresh, complete brief to clear this.', 'bitmomo-pro' )
				: strtoupper( $tier ),
		);

		return $rows;
	}

	/**
	 * MEMBERSHIP: founding seat count, active Pro users, and the
	 * cold-lead activation cross-tab. Reuses the existing entitlement/
	 * usage services' own public counting methods wherever one already
	 * exists (PR #36 integration audit consolidated the underlying
	 * queries there); the cold-active x activated cross-tab below is a
	 * distinct computation no existing method provides, so it is done
	 * here as a single read-only pass.
	 */
	private function section_membership() {
		$rows = array();

		$entitlement_service = Bitmomo_Pro_Entitlement_Service::instance();
		$founding_count       = $entitlement_service->count_active_founding_members();
		$founding_cap         = Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP;

		$rows[] = array(
			'label'  => __( 'Founding Beta seats', 'bitmomo-pro' ),
			'status' => self::STATUS_PASS,
			/* translators: 1: active founding member count, 2: founding seat cap */
			'detail' => sprintf( __( '%1$d / %2$d', 'bitmomo-pro' ), (int) $founding_count, (int) $founding_cap ),
		);

		$usage = Bitmomo_Pro_Usage::instance();
		$by_class = $usage->counts_by_validation_class();
		$active_total = array_sum( $by_class );

		$rows[] = array(
			'label'  => __( 'Active Pro users (all sources)', 'bitmomo-pro' ),
			'status' => self::STATUS_PASS,
			'detail' => (string) (int) $active_total,
		);

		list( $cold_active, $cold_activated ) = $this->cold_activation_crosstab();

		$rows[] = array(
			'label'  => __( 'Cold-lead active users', 'bitmomo-pro' ),
			'status' => self::STATUS_PASS,
			'detail' => (string) $cold_active,
		);

		$rows[] = array(
			'label'  => __( 'Cold-lead users activated (viewed >= 1 current brief)', 'bitmomo-pro' ),
			'status' => self::STATUS_PASS,
			'detail' => $cold_active > 0
				? sprintf(
					/* translators: 1: activated cold count, 2: cold active total */
					__( '%1$d / %2$d', 'bitmomo-pro' ),
					$cold_activated,
					$cold_active
				)
				: __( 'No cold-lead active users yet.', 'bitmomo-pro' ),
		);

		return $rows;
	}

	/**
	 * Single read-only pass over currently-active Pro users, cross-
	 * tabulating validation class ('cold') against Bitmomo_Pro_Usage's own
	 * is_activated() definition. Deliberately the same simple-query style
	 * as Bitmomo_Pro_Entitlement_Service::count_active_founding_members()
	 * — an operational number for the founder, not a cached/optimized
	 * metric.
	 *
	 * @return array{0:int,1:int} array( $cold_active_count, $cold_activated_count )
	 */
	private function cold_activation_crosstab() {
		$users = get_users(
			array(
				'meta_key'   => Bitmomo_Pro_Entitlements::META_STATUS,
				'meta_value' => 'active',
				'fields'     => 'ID',
			)
		);

		$entitlement_service = Bitmomo_Pro_Entitlement_Service::instance();
		$usage                = Bitmomo_Pro_Usage::instance();

		$cold_active    = 0;
		$cold_activated = 0;

		foreach ( $users as $user_id ) {
			if ( 'active' !== $entitlement_service->get_status( $user_id ) ) {
				continue;
			}
			$class = get_user_meta( $user_id, Bitmomo_Pro_Usage::META_VALIDATION_CLASS, true );
			if ( 'cold' !== $class ) {
				continue;
			}
			$cold_active++;
			if ( $usage->is_activated( $user_id ) ) {
				$cold_activated++;
			}
		}

		return array( $cold_active, $cold_activated );
	}

	/**
	 * EMAIL: is the email service loaded, and what is the real send status
	 * (not simulated) of the current-for-display brief's daily email.
	 */
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
			$rows[] = array(
				'label'  => __( 'Current brief daily-email status', 'bitmomo-pro' ),
				'status' => self::STATUS_WARN,
				'detail' => __( 'No current brief to check (see PRODUCT section above).', 'bitmomo-pro' ),
			);
			return $rows;
		}

		$sent_at   = get_post_meta( $brief['id'], Bitmomo_Pro_Email_Service::META_DAILY_SENT_AT, true );
		$test_mode = get_post_meta( $brief['id'], Bitmomo_Pro_Email_Service::META_DAILY_TEST_MODE, true );

		if ( ! $sent_at ) {
			$rows[] = array(
				'label'  => __( 'Current brief daily-email status', 'bitmomo-pro' ),
				'status' => self::STATUS_WARN,
				'detail' => __( 'Not sent yet. Send from the "Kirim Email Harian" box on the brief\'s edit screen.', 'bitmomo-pro' ),
			);
		} else {
			$success = (int) get_post_meta( $brief['id'], Bitmomo_Pro_Email_Service::META_DAILY_SUCCESS_COUNT, true );
			$failure = (int) get_post_meta( $brief['id'], Bitmomo_Pro_Email_Service::META_DAILY_FAILURE_COUNT, true );

			$rows[] = array(
				'label'  => __( 'Current brief daily-email status', 'bitmomo-pro' ),
				'status' => $test_mode ? self::STATUS_WARN : self::STATUS_PASS,
				'detail' => sprintf(
					/* translators: 1: sent-at timestamp, 2: real/test mode, 3: success count, 4: failure count */
					__( 'Sent %1$s (%2$s) — %3$d success, %4$d failure per wp_mail() return value only (see RUNTIME VERIFICATION REQUIRED below for actual inbox delivery).', 'bitmomo-pro' ),
					(string) $sent_at,
					$test_mode ? __( 'test mode', 'bitmomo-pro' ) : __( 'real send', 'bitmomo-pro' ),
					$success,
					$failure
				),
			);
		}

		return $rows;
	}

	// ==================================================================
	// RENDER
	// ==================================================================

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

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
		echo '<p class="description">' . esc_html__( 'A deployment/integration checklist generated from actual plugin and WordPress state — not a product-analytics dashboard, and not automated browser testing. Every PASS below is something this plugin verified itself; nothing here fakes external system health.', 'bitmomo-pro' ) . '</p>';

		$this->render_overall_banner( $overall );

		echo '<h2>' . esc_html__( 'A. Plugin &amp; Product State', 'bitmomo-pro' ) . '</h2>';
		foreach ( $sections as $title => $rows ) {
			$this->render_check_table( $title, $rows );
		}

		$this->render_section_b();
		$this->render_section_c();

		echo '</div>';
	}

	private function render_overall_banner( $overall ) {
		$colors = array(
			'not_ready'     => '#b32d2e',
			'staging_ready' => '#a16207',
		);
		$labels = array(
			'not_ready'     => __( 'NOT READY', 'bitmomo-pro' ),
			'staging_ready' => __( 'STAGING-READY CANDIDATE', 'bitmomo-pro' ),
		);

		printf(
			'<div style="border:2px solid %1$s;background:#fff;padding:14px 18px;max-width:820px;margin:16px 0;">' .
			'<p style="margin:0 0 6px 0;font-size:18px;font-weight:700;color:%1$s;">%2$s: %3$s</p>' .
			'<p style="margin:0;font-size:12px;color:#555;">%4$s</p>' .
			'</div>',
			esc_attr( $colors[ $overall['state'] ] ),
			esc_html__( 'D. Overall Launch Status', 'bitmomo-pro' ),
			esc_html( $labels[ $overall['state'] ] ),
			esc_html__( 'This plugin can never report "PRODUCTION READY" on its own — it cannot see hosting, DNS/SSL, real payment success, or Codex\'s live canonical adapter output. See Section B.', 'bitmomo-pro' )
		);

		if ( 'staging_ready' === $overall['state'] ) {
			echo '<p class="description" style="max-width:820px;">' . esc_html__( 'Minimum criteria met: core classes loaded, bm_pro_brief registered, a current brief exists and passes readiness, and the email service is loaded. This means the product has something real to show and send — it does NOT mean staging/production infrastructure has been verified. Proceed to Section C.', 'bitmomo-pro' ) . '</p>';
		} else {
			echo '<p class="description" style="max-width:820px;"><strong>' . esc_html__( 'Blocking:', 'bitmomo-pro' ) . '</strong> ' . esc_html( implode( ' ', $overall['blocking'] ) ) . '</p>';
		}
	}

	/**
	 * STAGING-READY CANDIDATE requires: no missing core classes, the
	 * bm_pro_brief post type registered, a current brief that exists AND
	 * passes readiness, and the email service loaded. Deliberately does
	 * NOT require checkout URL, dashboard URL, or all three pages
	 * published — those are known, acceptable Founding Beta gaps (see
	 * CONFIG/PAGES rows above), not launch blockers for staging.
	 */
	private function compute_overall_status( $sections ) {
		$blocking = array();

		foreach ( $sections['PLUGIN'] as $row ) {
			if ( self::STATUS_FAIL === $row['status'] ) {
				$blocking[] = $row['label'] . ': ' . $row['detail'];
			}
		}

		$product_ok = false;
		foreach ( $sections['PRODUCT'] as $row ) {
			if ( __( 'Passes release readiness', 'bitmomo-pro' ) === $row['label'] && self::STATUS_PASS === $row['status'] ) {
				$product_ok = true;
			}
			if ( __( 'Current brief exists', 'bitmomo-pro' ) === $row['label'] && self::STATUS_FAIL === $row['status'] ) {
				$blocking[] = __( 'No current brief exists — publish at least one complete Pro brief.', 'bitmomo-pro' );
			}
		}
		if ( ! $product_ok && ! in_array( __( 'No current brief exists — publish at least one complete Pro brief.', 'bitmomo-pro' ), $blocking, true ) ) {
			$blocking[] = __( 'Current brief does not pass release readiness.', 'bitmomo-pro' );
		}

		foreach ( $sections['EMAIL'] as $row ) {
			if ( __( 'Welcome/daily email service loaded', 'bitmomo-pro' ) === $row['label'] && self::STATUS_FAIL === $row['status'] ) {
				$blocking[] = $row['label'] . ': ' . $row['detail'];
			}
		}

		return array(
			'state'    => empty( $blocking ) ? 'staging_ready' : 'not_ready',
			'blocking' => $blocking,
		);
	}

	private function status_badge( $status ) {
		$colors = array(
			self::STATUS_PASS => array( '#1a7f4b', __( 'PASS', 'bitmomo-pro' ) ),
			self::STATUS_WARN => array( '#a16207', __( 'ATTENTION', 'bitmomo-pro' ) ),
			self::STATUS_FAIL => array( '#b32d2e', __( 'FAIL', 'bitmomo-pro' ) ),
		);
		list( $color, $label ) = isset( $colors[ $status ] ) ? $colors[ $status ] : array( '#555', strtoupper( $status ) );

		return sprintf(
			'<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:700;color:#fff;background:%s;">%s</span>',
			esc_attr( $color ),
			esc_html( $label )
		);
	}

	private function render_check_table( $title, $rows ) {
		echo '<h3 style="margin-top:22px;">' . esc_html( $title ) . '</h3>';
		echo '<table class="widefat striped" style="max-width:960px;"><thead><tr><th style="width:320px;">' . esc_html__( 'Check', 'bitmomo-pro' ) . '</th><th style="width:110px;">' . esc_html__( 'Status', 'bitmomo-pro' ) . '</th><th>' . esc_html__( 'Detail', 'bitmomo-pro' ) . '</th></tr></thead><tbody>';

		foreach ( $rows as $row ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $row['label'] ),
				$this->status_badge( $row['status'] ), // Already escaped internally.
				esc_html( $row['detail'] )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * SECTION B — explicit, permanent "cannot verify from here" list. Never
	 * rendered as a table with a fake PASS/FAIL column; deliberately looks
	 * visually different from Section A so it's never mistaken for one.
	 */
	private function render_section_b() {
		$items = array(
			__( 'Actual wp_mail() deliverability — this plugin only knows whether wp_mail() returned true, not whether the message reached an inbox.', 'bitmomo-pro' ),
			__( 'Spam-folder placement / sender domain reputation for welcome and daily emails.', 'bitmomo-pro' ),
			__( 'Whether LiteSpeed Cache actually excludes the Pro dashboard route in production — Bitmomo_Pro_Cache only sends the no-cache signal (headers + DONOTCACHEPAGE), it cannot confirm LiteSpeed honored it.', 'bitmomo-pro' ),
			__( 'Hostinger\'s own hosting-level caching/CDN behavior in front of LiteSpeed.', 'bitmomo-pro' ),
			__( 'Production SSL/DNS/network reachability of the live domain.', 'bitmomo-pro' ),
			__( 'End-to-end payment checkout success — no payment provider is integrated yet (bitmomo_pro_get_checkout_url() is a placeholder destination only).', 'bitmomo-pro' ),
			__( 'Whether Codex\'s canonical intelligence adapter is returning real, current production data through the bitmomo_pro_available_source_payload filter — this plugin can only report whether that filter is registered and non-empty at page-load time (see the Daily Pro screen\'s Canonical Source panel), never whether the data behind it is real, live, or correct.', 'bitmomo-pro' ),
		);

		echo '<h2 style="margin-top:32px;">' . esc_html__( 'B. Cannot Verify From Here — Runtime Verification Required', 'bitmomo-pro' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'These are not failures — they are outside what a WordPress plugin can prove about itself. Every item below must be checked manually or by Codex on staging/production; none of them will ever show a green PASS on this screen.', 'bitmomo-pro' ) . '</p>';
		echo '<ul style="max-width:900px;list-style:disc;margin-left:22px;">';
		foreach ( $items as $item ) {
			printf(
				'<li style="margin-bottom:8px;"><span style="display:inline-block;padding:1px 7px;border-radius:3px;font-size:10px;font-weight:700;color:#fff;background:#555;margin-right:6px;">%s</span> %s</li>',
				esc_html__( 'RUNTIME VERIFICATION REQUIRED', 'bitmomo-pro' ),
				esc_html( $item )
			);
		}
		echo '</ul>';
	}

	/**
	 * SECTION C — static Codex staging verification checklist. Plain
	 * numbered text informed by the current plugin architecture. Not
	 * automated, not a test runner — Codex checks these by hand on
	 * staging.
	 */
	private function render_section_c() {
		$items = array(
			__( 'Publish /pro, /pro-dashboard, and /pro/account (create via Bitmomo Pro → Setup if not already created) and confirm each shortcode renders without a PHP notice/warning.', 'bitmomo-pro' ),
			__( 'Visit /pro-dashboard logged out — confirm the login-gate + [bitmomo_pro_sales]-style CTA renders, and no paid brief content appears anywhere in the page source.', 'bitmomo-pro' ),
			__( 'Log in as a WordPress user with no Bitmomo Pro entitlement — confirm the "access not active" gate renders, again with zero paid content in the page source.', 'bitmomo-pro' ),
			__( 'Log in as a user with active Bitmomo Pro access and a current, ready, fresh (<=3h) brief published — confirm the full decision-view dashboard renders and the "fresh" freshness label shows.', 'bitmomo-pro' ),
			__( 'With that same brief\'s data_timestamp backdated 8-24h, confirm the dashboard still renders (delayed tier) with the "Diperbarui N jam lalu" age label, not a hidden/broken state.', 'bitmomo-pro' ),
			__( 'With that brief\'s data_timestamp backdated past 24h (or data_freshness_status set to "unavailable"), confirm the dashboard shows the safe "belum tersedia" message and NOT stale numbers.', 'bitmomo-pro' ),
			__( 'On the Pro Brief edit screen, submit a brief missing a required field (e.g. blank Base Scenario) and publish — confirm it reverts to draft with the specific missing-field notice, and that the content the editor typed is NOT lost (this is the one case, "Case 10", the standalone test suite explicitly could not exercise without a real database transition).', 'bitmomo-pro' ),
			__( 'Trigger a welcome email (User profile → Bitmomo Pro → Email) to a real test inbox and confirm it actually arrives (not just that wp_mail() returned true) and does not land in spam.', 'bitmomo-pro' ),
			__( 'Send a daily brief email in test mode first (recipients limited to "test"-source accounts), confirm it arrives correctly, then send a real send and spot-check delivery/spam placement for at least one real recipient.', 'bitmomo-pro' ),
			__( 'With DevTools/curl, confirm the /pro-dashboard response carries Cache-Control: private, no-cache, no-store and X-LiteSpeed-Cache-Control: no-cache, and that a second request from a different session does NOT receive a cached copy of the first visitor\'s dashboard.', 'bitmomo-pro' ),
			__( 'Confirm /pro (sales) IS still served from cache normally (it is intentionally left cacheable) — i.e. the no-cache change did not overreach to unrelated pages.', 'bitmomo-pro' ),
			__( 'Once the canonical adapter is wired to bitmomo_pro_available_source_payload, open Daily Pro and confirm "Buat Draft Hari Ini" creates a real draft from a real production record — not a placeholder/sample payload — and that re-clicking with the same source_record_id reopens the same draft instead of duplicating it.', 'bitmomo-pro' ),
			__( 'Confirm wp-admin screens gate correctly by role: Launch Readiness, Setup, and the Activate Pro Member console are invisible/inaccessible to an editor without manage_options, while Daily Pro remains reachable to any edit_posts user.', 'bitmomo-pro' ),
		);

		echo '<h2 style="margin-top:32px;">' . esc_html__( 'C. Codex Staging Verification Checklist', 'bitmomo-pro' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Static reference text, not automated testing. Informed by this plugin\'s current architecture as of PR #36.', 'bitmomo-pro' ) . '</p>';
		echo '<ol style="max-width:900px;margin-left:22px;">';
		foreach ( $items as $item ) {
			echo '<li style="margin-bottom:10px;">' . esc_html( $item ) . '</li>';
		}
		echo '</ol>';
	}
}
