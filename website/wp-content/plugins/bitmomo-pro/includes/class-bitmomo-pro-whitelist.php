<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Founding Membership Whitelist — demand capture before Bitmomo Pro checkout
 * is fully open.
 *
 * NOT a seat reservation, NOT a payment record, NOT a second subscriber
 * database. One canonical record per email, stored as a private, non-public
 * `bm_pro_whitelist` post (same pattern as Bitmomo_Pro_Briefs): no public
 * URL, no public query, no REST route, no enumeration surface. Founding cap
 * (149) and the operational batch (25) are read from
 * Bitmomo_Pro_Entitlement_Service's existing constants rather than
 * duplicated here — this class never touches entitlement/founding-cap logic
 * itself, it only quotes the same numbers as static copy.
 *
 * Render surface: Bitmomo_Pro_Sales::render_cta() calls
 * self::instance()->render_widget() exactly when bitmomo_pro_get_checkout_url()
 * is empty (checkout not yet configured) — see that method, which already
 * decides real-CTA-vs-not and is not duplicated here. Once a checkout URL is
 * configured, the Sales page automatically stops calling this class and
 * shows the real purchase CTA instead; no template rewrite needed for that
 * transition.
 *
 * submit_entry() is the pure, directly-testable core (same convention as
 * Bitmomo_Pro_Activation::activate_member()) — no superglobals, no output.
 * handle_ajax_submit() is the thin $_POST/nonce/rate-limit wrapper around it.
 *
 * MailPoet: NOT integrated in this slice. MailPoet's plugin code is not
 * present in this repository so its List/Subscribers API surface cannot be
 * safely audited here, and the existing Bitmomo_Pro_Email_Service docblock
 * already establishes "MailPoet is explicitly NOT integrated" as this
 * codebase's convention for the same reason. Instead, 'bitmomo_pro_whitelist_created'
 * fires on every new signup so a future, properly-audited MailPoet (or any
 * ESP) integration can hook in without touching this class.
 */
class Bitmomo_Pro_Whitelist {

	const POST_TYPE = 'bm_pro_whitelist';

	// Postmeta keys (all private/non-public via the CPT's own visibility).
	const META_EMAIL            = '_bm_whitelist_email';
	const META_EMAIL_NORMALIZED = '_bm_whitelist_email_normalized';
	const META_FIRST_NAME       = '_bm_whitelist_first_name';
	const META_CREATED_AT       = '_bm_whitelist_created_at';
	const META_CONSENT_AT       = '_bm_whitelist_consent_at';
	const META_SOURCE           = '_bm_whitelist_source';
	const META_LANDING_PAGE     = '_bm_whitelist_landing_page';
	const META_UTM_SOURCE       = '_bm_whitelist_utm_source';
	const META_UTM_MEDIUM       = '_bm_whitelist_utm_medium';
	const META_UTM_CAMPAIGN     = '_bm_whitelist_utm_campaign';
	const META_REFERRER         = '_bm_whitelist_referrer';
	const META_STATUS           = '_bm_whitelist_status';
	const META_VALIDATION_CLASS = '_bm_whitelist_validation_class';

	// WhatsApp is a NOTIFICATION CHANNEL ONLY, on the same canonical record —
	// never a second subscriber database, never required. Consent for this
	// channel is captured separately from META_CONSENT_AT (email consent):
	// it is set only by the act of successfully submitting the WhatsApp step
	// itself, never inherited from the original email signup.
	const META_WHATSAPP_NUMBER     = '_bm_whitelist_whatsapp_number';
	const META_WHATSAPP_CONSENT_AT = '_bm_whitelist_whatsapp_consent_at';

	const STATUS_WAITING   = 'waiting';
	const STATUS_INVITED   = 'invited';
	const STATUS_CONVERTED = 'converted';
	const STATUSES         = array( self::STATUS_WAITING, self::STATUS_INVITED, self::STATUS_CONVERTED );

	const VALIDATION_CLASSES = array( 'cold', 'warm', 'test', 'internal', 'unclassified' );
	const DEFAULT_VALIDATION_CLASS = 'unclassified';

	const NONCE_ACTION = 'bitmomo_pro_whitelist_submit';
	const AJAX_ACTION   = 'bitmomo_pro_whitelist_submit';

	// Separate AJAX action/nonce for the optional second-step WhatsApp
	// opt-in, so it never shares state with the email-submit step above.
	const NONCE_ACTION_WHATSAPP = 'bitmomo_pro_whitelist_whatsapp_submit';
	const AJAX_ACTION_WHATSAPP  = 'bitmomo_pro_whitelist_whatsapp_submit';

	const RATE_LIMIT_MAX    = 5;   // attempts
	const RATE_LIMIT_WINDOW = 600; // seconds (10 minutes)

	const META_LIMIT = 190; // generous cap for free-text meta (UTM/referrer/landing page), avoids unbounded storage.

	private static $instance = null;
	private $did_render_view_event = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_shortcode( 'bitmomo_pro_whitelist', array( $this, 'shortcode' ) );

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_ajax_submit' ) );

		add_action( 'wp_ajax_' . self::AJAX_ACTION_WHATSAPP, array( $this, 'handle_ajax_whatsapp_submit' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION_WHATSAPP, array( $this, 'handle_ajax_whatsapp_submit' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_filter( 'litespeed_optimize_js_excludes', array( $this, 'exclude_whitelist_js' ) );
		add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'exclude_whitelist_js' ) );
		add_filter( 'litespeed_optm_gm_js_exc', array( $this, 'exclude_whitelist_js' ) );

		// Converted-state: listens to the EXISTING entitlement lifecycle hook
		// (Bitmomo_Pro_Entitlement_Service::grant_access()) rather than
		// touching entitlement/activation code. A clean callable
		// (mark_converted_by_email()) also exists for a future payment
		// webhook to call directly once one exists.
		add_action( 'bitmomo_pro_activated', array( $this, 'on_pro_activated' ), 10, 2 );

		// Admin
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		add_filter( 'views_edit-' . self::POST_TYPE, array( $this, 'admin_status_views' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_admin_summary' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_admin_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_admin_meta_box' ) );
	}

	// ==================================================================
	// POST TYPE (private — no public URL, no REST, no enumeration surface)
	// ==================================================================

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Pro Whitelist', 'bitmomo-pro' ),
					'singular_name' => __( 'Whitelist Entry', 'bitmomo-pro' ),
					'all_items'     => __( 'Founding Whitelist', 'bitmomo-pro' ),
					'menu_name'     => __( 'Founding Whitelist', 'bitmomo-pro' ),
					'search_items'  => __( 'Search whitelist entries', 'bitmomo-pro' ),
					'not_found'     => __( 'No whitelist entries yet.', 'bitmomo-pro' ),
				),
				'public'              => false,
				'show_ui'             => true,
				// Nests under the existing "Bitmomo Pro Briefs" admin menu
				// instead of adding a new top-level menu item.
				'show_in_menu'        => 'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
				'menu_icon'           => 'dashicons-groups',
			)
		);
	}

	// ==================================================================
	// LOOKUP / DEDUPLICATION
	// ==================================================================

	public static function normalize_email( $email ) {
		return strtolower( trim( (string) sanitize_email( (string) $email ) ) );
	}

	/**
	 * Normalizes any of the input shapes a visitor might type — '0812...',
	 * '812...', '+62812...', '62812...' — down to a bare 62-prefixed digit
	 * string ('62812...', no '+', no spaces/dots/dashes). Does not validate
	 * length/shape; see is_valid_whatsapp_number() for that. Returns '' for
	 * empty input; an unrecognized prefix is returned as-is (digits only)
	 * so it still fails validation rather than being silently coerced.
	 */
	public static function normalize_whatsapp_number( $raw ) {
		$digits = preg_replace( '/[^0-9]/', '', (string) $raw );

		if ( '' === $digits ) {
			return '';
		}

		if ( 0 === strpos( $digits, '0' ) ) {
			return '62' . substr( $digits, 1 );
		}

		if ( 0 === strpos( $digits, '62' ) ) {
			return $digits;
		}

		if ( 0 === strpos( $digits, '8' ) ) {
			return '62' . $digits;
		}

		return $digits;
	}

	/**
	 * @param string $normalized Output of normalize_whatsapp_number().
	 * Indonesian mobile numbers are '62' + '8' + 7-12 more digits (10-15
	 * digits total) — anything shorter/longer or missing the '8' after the
	 * country code is rejected rather than guessed at.
	 */
	public static function is_valid_whatsapp_number( $normalized ) {
		return (bool) preg_match( '/^628[0-9]{7,12}$/', (string) $normalized );
	}

	/**
	 * Exact-match lookup by normalized email. The single canonical dedup
	 * check — every write path (submit_entry, on_pro_activated,
	 * mark_converted_by_email) goes through this instead of a second,
	 * possibly-drifting query.
	 *
	 * @return int 0 if not found.
	 */
	public function find_post_id_by_email( $email ) {
		$normalized = self::normalize_email( $email );
		if ( '' === $normalized ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_key'       => self::META_EMAIL_NORMALIZED,
				'meta_value'     => $normalized,
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	// ==================================================================
	// CORE WRITE PATH — pure, directly testable, no superglobals/output.
	// ==================================================================

	/**
	 * @param array $args {
	 *     @type string $email
	 *     @type string $first_name  Optional.
	 *     @type bool   $consent     Must be truthy or the entry is rejected.
	 *     @type string $source
	 *     @type string $landing_page
	 *     @type string $utm_source
	 *     @type string $utm_medium
	 *     @type string $utm_campaign
	 *     @type string $referrer
	 * }
	 * @return array {
	 *     @type bool        $ok
	 *     @type string|null $error   'invalid_email'|'consent_required'|null
	 *     @type string      $status  'created'|'duplicate'|''
	 *     @type int         $post_id 0 if none created/found.
	 * }
	 */
	public function submit_entry( $args ) {
		$email = self::normalize_email( $args['email'] ?? '' );

		if ( '' === $email || ! is_email( $email ) ) {
			return array( 'ok' => false, 'error' => 'invalid_email', 'status' => '', 'post_id' => 0 );
		}

		if ( empty( $args['consent'] ) ) {
			return array( 'ok' => false, 'error' => 'consent_required', 'status' => '', 'post_id' => 0 );
		}

		$existing_id = $this->find_post_id_by_email( $email );
		if ( $existing_id ) {
			return array( 'ok' => true, 'error' => null, 'status' => 'duplicate', 'post_id' => $existing_id );
		}

		$now = current_time( 'mysql' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $email,
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return array( 'ok' => false, 'error' => 'insert_failed', 'status' => '', 'post_id' => 0 );
		}

		$this->cap_meta( $post_id, self::META_EMAIL, $args['email'] ?? $email, 190 );
		update_post_meta( $post_id, self::META_EMAIL_NORMALIZED, $email );
		$this->cap_meta( $post_id, self::META_FIRST_NAME, $args['first_name'] ?? '', 100 );
		update_post_meta( $post_id, self::META_CREATED_AT, $now );
		update_post_meta( $post_id, self::META_CONSENT_AT, $now );
		$this->cap_meta( $post_id, self::META_SOURCE, $args['source'] ?? 'pro_page', 60 );
		$this->cap_meta( $post_id, self::META_LANDING_PAGE, $args['landing_page'] ?? '', self::META_LIMIT );
		$this->cap_meta( $post_id, self::META_UTM_SOURCE, $args['utm_source'] ?? '', 100 );
		$this->cap_meta( $post_id, self::META_UTM_MEDIUM, $args['utm_medium'] ?? '', 100 );
		$this->cap_meta( $post_id, self::META_UTM_CAMPAIGN, $args['utm_campaign'] ?? '', 100 );
		$this->cap_meta( $post_id, self::META_REFERRER, $args['referrer'] ?? '', self::META_LIMIT );
		update_post_meta( $post_id, self::META_STATUS, self::STATUS_WAITING );
		update_post_meta( $post_id, self::META_VALIDATION_CLASS, self::DEFAULT_VALIDATION_CLASS );

		/**
		 * Fires once per NEW whitelist signup (never for a duplicate
		 * resubmission). Deliberately does not pass the raw email — a
		 * listener that needs it can read the post's own meta by $post_id.
		 * This is the intended MailPoet/ESP integration point; see class
		 * docblock.
		 */
		do_action( 'bitmomo_pro_whitelist_created', $post_id, self::telemetry_context( $args ) );

		// Best-effort confirmation email. Never blocks or fails the signup
		// itself — matches Bitmomo_Pro_Email_Service's existing
		// success/failure-is-tracked-not-fatal pattern for the daily brief
		// email.
		if ( class_exists( 'Bitmomo_Pro_Email_Service' ) ) {
			Bitmomo_Pro_Email_Service::instance()->send_whitelist_confirmation_email( $post_id );
		}

		return array( 'ok' => true, 'error' => null, 'status' => 'created', 'post_id' => $post_id );
	}

	private function cap_meta( $post_id, $meta_key, $value, $max_len ) {
		$value = sanitize_text_field( (string) $value );
		$value = substr( $value, 0, $max_len );
		update_post_meta( $post_id, $meta_key, $value );
	}

	/**
	 * Pure core of the optional WhatsApp opt-in step — same convention as
	 * submit_entry(): no superglobals, no output. Adds/updates the
	 * WhatsApp number on an EXISTING whitelist record; never creates a new
	 * record and never touches META_EMAIL/META_CONSENT_AT (email consent
	 * is a separate thing from WhatsApp consent).
	 *
	 * @param array $args {
	 *     @type int    $post_id          Existing bm_pro_whitelist record.
	 *     @type string $whatsapp_number  Any accepted input shape.
	 * }
	 * @return array {
	 *     @type bool        $ok
	 *     @type string|null $error  'not_found'|'invalid_whatsapp'|null
	 * }
	 */
	public function submit_whatsapp( $args ) {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;

		if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
			return array( 'ok' => false, 'error' => 'not_found' );
		}

		$normalized = self::normalize_whatsapp_number( $args['whatsapp_number'] ?? '' );

		if ( '' === $normalized || ! self::is_valid_whatsapp_number( $normalized ) ) {
			return array( 'ok' => false, 'error' => 'invalid_whatsapp' );
		}

		// Submitting this step IS the WhatsApp consent — a fresh timestamp
		// every time (re-submitting a new/changed number re-consents), and
		// never copied from the email-signup consent timestamp.
		update_post_meta( $post_id, self::META_WHATSAPP_NUMBER, $normalized );
		update_post_meta( $post_id, self::META_WHATSAPP_CONSENT_AT, current_time( 'mysql' ) );

		/**
		 * Fires only on a successful WhatsApp number addition. A
		 * channel-opt-in signal only — never counted as revenue and never
		 * auto-treated as PMF evidence, same spirit as
		 * 'pro_whitelist_success' for the email step.
		 */
		do_action( 'whatsapp_opt_in', array( 'post_id' => $post_id ) );

		return array( 'ok' => true, 'error' => null );
	}

	/**
	 * The per-record scoped nonce action used to authorize the WhatsApp
	 * step against one specific whitelist record, mirroring WordPress's
	 * own id+nonce action-link convention rather than a new lookup-by-email
	 * endpoint. Returned to the client only in that client's OWN
	 * email-submit success response (see handle_ajax_submit()) — never
	 * exposed on any public/enumerable surface.
	 */
	private static function whatsapp_record_action( $post_id ) {
		return 'bm_wl_whatsapp_record_' . (int) $post_id;
	}

	/**
	 * Marks a whitelist record 'converted' by email — called automatically
	 * from on_pro_activated() below, and callable directly by any future
	 * payment-integration webhook once one exists. No-op (returns false) if
	 * no whitelist record exists for that email; a paid conversion is never
	 * blocked on having whitelisted first.
	 */
	public function mark_converted_by_email( $email ) {
		$post_id = $this->find_post_id_by_email( $email );
		if ( ! $post_id ) {
			return false;
		}

		update_post_meta( $post_id, self::META_STATUS, self::STATUS_CONVERTED );
		return true;
	}

	/**
	 * Listener for the existing 'bitmomo_pro_activated' entitlement hook
	 * (fires from Bitmomo_Pro_Entitlement_Service::grant_access(), which
	 * this class never calls or modifies). $user_id is the account that was
	 * just granted access; its email is looked up read-only via
	 * get_userdata() purely to match it against a whitelist record.
	 */
	public function on_pro_activated( $user_id, $context = array() ) {
		if ( ! $user_id || ! function_exists( 'get_userdata' ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}

		$this->mark_converted_by_email( $user->user_email );
	}

	// ==================================================================
	// FRONTEND: widget + shortcode + AJAX wrapper
	// ==================================================================

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'source' => 'shortcode' ), (array) $atts );
		ob_start();
		$this->render_widget( $atts );
		return ob_get_clean();
	}

	public function maybe_enqueue_assets() {
		global $post;
		$has_widget = is_front_page() || is_home() || ( is_a( $post, 'WP_Post' ) && (
			has_shortcode( $post->post_content, 'bitmomo_pro_sales' ) ||
			has_shortcode( $post->post_content, 'bitmomo_pro_whitelist' )
		) );

		if ( ! $has_widget ) {
			return;
		}

		// Only load the widget's own JS/CSS when it will actually be the
		// surface shown — i.e. checkout isn't configured yet. Once a real
		// checkout URL exists these assets simply stop loading, no template
		// change required.
		if ( ! empty( bitmomo_pro_get_checkout_url() ) ) {
			return;
		}

		wp_enqueue_style( 'bitmomo-pro-sales', BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-sales.css', array(), BITMOMO_PRO_VERSION );
		wp_enqueue_style( 'bitmomo-pro-whitelist', BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-whitelist.css', array( 'bitmomo-pro-sales' ), BITMOMO_PRO_VERSION );
		wp_enqueue_script( 'bitmomo-pro-whitelist', BITMOMO_PRO_URL . 'assets/js/bitmomo-pro-whitelist.js', array(), BITMOMO_PRO_VERSION, true );
		wp_localize_script(
			'bitmomo-pro-whitelist',
			'bitmomoProWhitelist',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'action'         => self::AJAX_ACTION,
				'nonce'          => wp_create_nonce( self::NONCE_ACTION ),
				'whatsappAction' => self::AJAX_ACTION_WHATSAPP,
				'whatsappNonce'  => wp_create_nonce( self::NONCE_ACTION_WHATSAPP ),
				'i18n'    => array(
					'invalid_email'     => __( 'Masukkan alamat email yang valid.', 'bitmomo-pro' ),
					'consent_required'  => __( 'Centang persetujuan untuk melanjutkan.', 'bitmomo-pro' ),
					'rate_limited'      => __( 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.', 'bitmomo-pro' ),
					'generic_error'     => __( 'Terjadi kesalahan. Coba lagi.', 'bitmomo-pro' ),
					'duplicate_title'   => __( 'Email ini sudah terdaftar di whitelist.', 'bitmomo-pro' ),
					'invalid_whatsapp'  => __( 'Masukkan nomor WhatsApp yang valid.', 'bitmomo-pro' ),
					'not_found'         => __( 'Sesi sudah tidak berlaku. Muat ulang halaman dan coba lagi.', 'bitmomo-pro' ),
				),
			)
		);
	}

	public function exclude_whitelist_js( $excludes ) {
		$excludes[] = 'bitmomo-pro-whitelist.js';
		return array_unique( $excludes );
	}

	/**
	 * Renders the whitelist conversion unit: FOUNDING MEMBERSHIP price
	 * recap, supporting copy, the low-friction form, and a hidden
	 * success-state container the JS swaps in. No fake urgency, no
	 * countdown, no remaining-seat counter — mirrors the brief's exact copy.
	 */
	public function render_widget( $atts = array() ) {
		$source = isset( $atts['source'] ) ? sanitize_key( (string) $atts['source'] ) : 'pro_page';

		if ( ! $this->did_render_view_event ) {
			$this->did_render_view_event = true;
			/**
			 * Fires once per page render where the whitelist widget is
			 * actually shown (i.e. checkout is unavailable).
			 */
			do_action( 'pro_whitelist_view', array( 'source' => $source ) );
		}

		$landing_page = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : home_url( '/' );
		$utm_source   = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : '';
		$utm_medium   = isset( $_GET['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_medium'] ) ) : '';
		$utm_campaign = isset( $_GET['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_campaign'] ) ) : '';
		$referrer     = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		$founding_cap   = class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP : 149;
		$founding_batch = class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_OPERATIONAL_BATCH : 25;
		?>
		<div class="bm-pro-sales bm-wl" id="bm-pro-whitelist">
			<div class="bm-wl__panel" id="bm-wl-form-panel">
				<p class="bm-wl__eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo-pro' ); ?></p>
				<p class="bm-wl__price"><?php esc_html_e( 'Rp149.000 / bulan', 'bitmomo-pro' ); ?></p>
				<p class="bm-wl__price"><?php esc_html_e( 'Rp1.490.000 / tahun', 'bitmomo-pro' ); ?></p>
				<p class="bm-wl__cap">
					<?php echo esc_html( sprintf( __( '%d Founding Members', 'bitmomo-pro' ), $founding_cap ) ); ?><br>
					<?php echo esc_html( sprintf( __( 'Batch pertama: %d anggota', 'bitmomo-pro' ), $founding_batch ) ); ?>
				</p>
				<p class="bm-wl__sub"><?php esc_html_e( 'Daftar untuk mendapat akses lebih awal saat Bitmomo Pro dibuka. Batch pertama dibatasi 25 anggota.', 'bitmomo-pro' ); ?></p>

				<form id="bm-wl-form" novalidate>
					<?php wp_nonce_field( self::NONCE_ACTION, 'bm_wl_nonce' ); ?>
					<input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>">
					<input type="hidden" name="landing_page" value="<?php echo esc_attr( $landing_page ); ?>">
					<input type="hidden" name="utm_source" value="<?php echo esc_attr( $utm_source ); ?>">
					<input type="hidden" name="utm_medium" value="<?php echo esc_attr( $utm_medium ); ?>">
					<input type="hidden" name="utm_campaign" value="<?php echo esc_attr( $utm_campaign ); ?>">
					<input type="hidden" name="referrer" value="<?php echo esc_attr( $referrer ); ?>">

					<label class="bm-wl__field">
						<span><?php esc_html_e( 'Email', 'bitmomo-pro' ); ?></span>
						<input type="email" name="email" required autocomplete="email" placeholder="nama@email.com">
					</label>
					<label class="bm-wl__field">
						<span><?php esc_html_e( 'Nama depan (opsional)', 'bitmomo-pro' ); ?></span>
						<input type="text" name="first_name" autocomplete="given-name" maxlength="100">
					</label>
					<label class="bm-wl__consent">
						<input type="checkbox" name="consent" value="1" required>
						<span><?php esc_html_e( 'Saya setuju menerima email terkait peluncuran dan akses Bitmomo Pro.', 'bitmomo-pro' ); ?></span>
					</label>

					<p class="bm-wl__error" id="bm-wl-error" hidden></p>

					<button type="submit" class="bm-wl__submit"><?php esc_html_e( 'GABUNG WHITELIST', 'bitmomo-pro' ); ?></button>
				</form>

				<p class="bm-wl__note"><?php esc_html_e( 'Masuk whitelist tidak menjamin tempat. Membership aktif setelah pembayaran berhasil.', 'bitmomo-pro' ); ?></p>
			</div>

			<div class="bm-wl__panel bm-wl__success" id="bm-wl-success-panel" hidden>
				<p class="bm-wl__success-title" id="bm-wl-success-title"><?php esc_html_e( 'Whitelist berhasil. Kamu akan jadi salah satu yang pertama tahu saat akses dibuka.', 'bitmomo-pro' ); ?></p>
				<p><?php esc_html_e( 'Cek email kamu untuk informasi lebih lanjut.', 'bitmomo-pro' ); ?></p>
				<p class="bm-wl__success-small"><?php esc_html_e( 'Whitelist belum menjamin tempat. Akses aktif setelah pembayaran berhasil, selama Batch pertama masih tersedia.', 'bitmomo-pro' ); ?></p>

				<div class="bm-wl-whatsapp" id="bm-wl-whatsapp-step" data-post-id="" data-record-token="" hidden>
					<p class="bm-wl-whatsapp__label"><?php esc_html_e( 'Tambahkan WhatsApp agar tidak melewatkan pemberitahuan saat akses dibuka.', 'bitmomo-pro' ); ?></p>
					<div class="bm-wl-whatsapp__row">
						<span class="bm-wl-whatsapp__prefix" aria-hidden="true">+62</span>
						<input type="tel" class="bm-wl-whatsapp__input" id="bm-wl-whatsapp-number" inputmode="numeric" autocomplete="tel-national" placeholder="812xxxxxxxx" maxlength="15" aria-label="<?php esc_attr_e( 'Nomor WhatsApp', 'bitmomo-pro' ); ?>">
					</div>
					<p class="bm-wl-whatsapp__error" id="bm-wl-whatsapp-error" hidden></p>
					<button type="button" class="bm-wl-whatsapp__submit" id="bm-wl-whatsapp-submit"><?php esc_html_e( 'TAMBAHKAN WHATSAPP', 'bitmomo-pro' ); ?></button>
					<p class="bm-wl-whatsapp__note"><?php esc_html_e( 'Opsional. Nomor hanya digunakan untuk informasi penting terkait Bitmomo Pro.', 'bitmomo-pro' ); ?></p>
				</div>
				<p class="bm-wl-whatsapp__done" id="bm-wl-whatsapp-done" hidden><?php esc_html_e( 'Nomor WhatsApp tersimpan. Terima kasih!', 'bitmomo-pro' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * $_POST/nonce/rate-limit wrapper around submit_entry(). Never echoes
	 * back raw user input in an error response, never reveals anything
	 * about any OTHER record, and never logs the submitted email.
	 */
	public function handle_ajax_submit() {
		check_ajax_referer( self::NONCE_ACTION, 'bm_wl_nonce' );

		if ( $this->is_rate_limited( 'submit' ) ) {
			wp_send_json_error( array( 'error' => 'rate_limited' ), 429 );
		}
		$this->bump_rate_limit( 'submit' );

		do_action( 'pro_whitelist_submit', array(
			'source' => isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '',
		) );

		$args = array(
			'email'        => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'first_name'   => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
			'consent'      => ! empty( $_POST['consent'] ),
			'source'       => isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : 'pro_page',
			'landing_page' => isset( $_POST['landing_page'] ) ? esc_url_raw( wp_unslash( $_POST['landing_page'] ) ) : '',
			'utm_source'   => isset( $_POST['utm_source'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_source'] ) ) : '',
			'utm_medium'   => isset( $_POST['utm_medium'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_medium'] ) ) : '',
			'utm_campaign' => isset( $_POST['utm_campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ) ) : '',
			'referrer'     => isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '',
		);

		$result = $this->submit_entry( $args );

		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'error' => $result['error'] ), 400 );
		}

		/**
		 * Fires whenever a visitor sees the success state (new signup or an
		 * honest "already registered" duplicate) — the UI-outcome signal.
		 * Distinguish real new demand from a repeat visit via 'status'.
		 * Never counted as revenue and never auto-treated as cold PMF
		 * evidence — see class docblock and validation_class default.
		 */
		do_action( 'pro_whitelist_success', array(
			'status'  => $result['status'],
			'post_id' => $result['post_id'],
		) );

		$has_whatsapp = (bool) get_post_meta( $result['post_id'], self::META_WHATSAPP_NUMBER, true );

		wp_send_json_success( array(
			'status'       => $result['status'],
			// post_id + record_token together authorize ONLY the WhatsApp
			// step for THIS record (see whatsapp_record_action()) — the CPT
			// itself stays non-public/non-queryable/no-REST, so this pair
			// is not a new enumeration surface, only a capability handed to
			// the visitor who just proved they hold this email.
			'post_id'      => $result['post_id'],
			'record_token' => wp_create_nonce( self::whatsapp_record_action( $result['post_id'] ) ),
			// Tells the client whether to show the WhatsApp step at all —
			// never re-ask if a number is already on file (new signup or
			// duplicate resubmission alike).
			'has_whatsapp' => $has_whatsapp,
		) );
	}

	/**
	 * $_POST/nonce/rate-limit wrapper around submit_whatsapp() — the
	 * optional second step. Authorizes against ONE specific record via the
	 * post_id + record_token pair returned from handle_ajax_submit(); a
	 * missing/wrong/expired token and a nonexistent post_id produce the
	 * exact same generic error, so this never becomes a way to probe which
	 * post_ids exist.
	 */
	public function handle_ajax_whatsapp_submit() {
		check_ajax_referer( self::NONCE_ACTION_WHATSAPP, 'bm_wl_whatsapp_nonce' );

		if ( $this->is_rate_limited( 'whatsapp' ) ) {
			wp_send_json_error( array( 'error' => 'rate_limited' ), 429 );
		}
		$this->bump_rate_limit( 'whatsapp' );

		$post_id      = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$record_token = isset( $_POST['record_token'] ) ? sanitize_text_field( wp_unslash( $_POST['record_token'] ) ) : '';

		if ( ! $post_id || ! wp_verify_nonce( $record_token, self::whatsapp_record_action( $post_id ) ) ) {
			wp_send_json_error( array( 'error' => 'not_found' ), 400 );
		}

		$result = $this->submit_whatsapp( array(
			'post_id'         => $post_id,
			'whatsapp_number' => isset( $_POST['whatsapp_number'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp_number'] ) ) : '',
		) );

		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'error' => $result['error'] ), 400 );
		}

		wp_send_json_success( array( 'has_whatsapp' => true ) );
	}

	// ==================================================================
	// RATE LIMITING (per-IP, transient-based, no raw IP stored long-term)
	// ==================================================================

	/**
	 * @param string $action Distinct budget per AJAX action ('submit' vs
	 *                        'whatsapp') so a normal 2-step signup (submit
	 *                        email, then add WhatsApp) never risks
	 *                        exhausting a single shared attempt counter.
	 */
	private function rate_limit_key( $action = 'submit' ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return 'bm_wl_rl_' . sanitize_key( $action ) . '_' . md5( $ip );
	}

	private function is_rate_limited( $action = 'submit' ) {
		return (int) get_transient( $this->rate_limit_key( $action ) ) >= self::RATE_LIMIT_MAX;
	}

	private function bump_rate_limit( $action = 'submit' ) {
		$key   = $this->rate_limit_key( $action );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );
	}

	// ==================================================================
	// ADMIN: list-table columns, status filter views, summary counts,
	// editable status/validation_class meta box. Not a CRM — read access
	// plus two editable dropdowns, matching Bitmomo_Pro_Briefs's meta-box
	// pattern and Bitmomo_Pro_Usage's manually-set-classification pattern.
	// ==================================================================

	public function admin_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['bm_wl_first_name'] = __( 'First name', 'bitmomo-pro' );
				$new['bm_wl_source']     = __( 'Source', 'bitmomo-pro' );
				$new['bm_wl_utm']        = __( 'UTM', 'bitmomo-pro' );
				$new['bm_wl_status']     = __( 'Status', 'bitmomo-pro' );
				$new['bm_wl_class']      = __( 'Validation', 'bitmomo-pro' );
			}
		}
		unset( $new['date'] );
		$new['date'] = __( 'Signed up', 'bitmomo-pro' );
		return $new;
	}

	public function render_admin_column( $column, $post_id ) {
		switch ( $column ) {
			case 'bm_wl_first_name':
				echo esc_html( get_post_meta( $post_id, self::META_FIRST_NAME, true ) ?: '—' );
				break;
			case 'bm_wl_source':
				echo esc_html( get_post_meta( $post_id, self::META_SOURCE, true ) ?: '—' );
				break;
			case 'bm_wl_utm':
				$parts = array_filter( array(
					get_post_meta( $post_id, self::META_UTM_SOURCE, true ),
					get_post_meta( $post_id, self::META_UTM_MEDIUM, true ),
					get_post_meta( $post_id, self::META_UTM_CAMPAIGN, true ),
				) );
				echo $parts ? esc_html( implode( ' / ', $parts ) ) : '—';
				break;
			case 'bm_wl_status':
				echo esc_html( ucfirst( get_post_meta( $post_id, self::META_STATUS, true ) ?: self::STATUS_WAITING ) );
				break;
			case 'bm_wl_class':
				echo esc_html( get_post_meta( $post_id, self::META_VALIDATION_CLASS, true ) ?: self::DEFAULT_VALIDATION_CLASS );
				break;
		}
	}

	/**
	 * Adds All/Waiting/Invited/Converted quick-filter links above the list
	 * table (native WP list-table "views" affordance) instead of a custom
	 * admin page.
	 */
	public function admin_status_views( $views ) {
		foreach ( self::STATUSES as $status ) {
			$count = $this->count_by_status( $status );
			$url   = add_query_arg( array( 'post_type' => self::POST_TYPE, 'bm_wl_status' => $status ), admin_url( 'edit.php' ) );
			$views[ 'bm_wl_' . $status ] = sprintf( '<a href="%s">%s (%d)</a>', esc_url( $url ), esc_html( ucfirst( $status ) ), $count );
		}
		return $views;
	}

	public function count_by_status( $status ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_key'       => self::META_STATUS,
				'meta_value'     => $status,
				'fields'         => 'ids',
			)
		);
		return count( $posts );
	}

	public function count_total() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);
		return count( $posts );
	}

	public function counts_by_validation_class() {
		$counts = array_fill_keys( self::VALIDATION_CLASSES, 0 );
		$posts  = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $post_id ) {
			$class = get_post_meta( $post_id, self::META_VALIDATION_CLASS, true );
			if ( ! in_array( $class, self::VALIDATION_CLASSES, true ) ) {
				$class = self::DEFAULT_VALIDATION_CLASS;
			}
			$counts[ $class ]++;
		}
		return $counts;
	}

	/**
	 * Plain-text summary line above the list table — no charts, matching
	 * the brief and the existing Bitmomo_Pro_Usage::render_admin_summary()
	 * style.
	 */
	public function render_admin_summary( $post_type ) {
		if ( self::POST_TYPE !== $post_type || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$by_class = $this->counts_by_validation_class();

		printf(
			'<span style="margin:0 8px;display:inline-block;">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: total, 2: waiting, 3: invited, 4: converted */
					__( 'Whitelist — total: %1$d, waiting: %2$d, invited: %3$d, converted: %4$d', 'bitmomo-pro' ),
					$this->count_total(),
					$this->count_by_status( self::STATUS_WAITING ),
					$this->count_by_status( self::STATUS_INVITED ),
					$this->count_by_status( self::STATUS_CONVERTED )
				)
			)
		);
		printf(
			'<span style="margin:0 8px;display:inline-block;">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: cold, 2: warm, 3: test, 4: internal, 5: unclassified */
					__( 'Validation — cold: %1$d, warm: %2$d, test: %3$d, internal: %4$d, unclassified: %5$d', 'bitmomo-pro' ),
					$by_class['cold'],
					$by_class['warm'],
					$by_class['test'],
					$by_class['internal'],
					$by_class['unclassified']
				)
			)
		);
	}

	public function add_admin_meta_box() {
		add_meta_box(
			'bitmomo_pro_whitelist_fields',
			__( 'Whitelist Entry', 'bitmomo-pro' ),
			array( $this, 'render_admin_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_admin_meta_box( $post ) {
		wp_nonce_field( 'bitmomo_pro_whitelist_admin_save', 'bitmomo_pro_whitelist_admin_nonce' );

		$fields = array(
			__( 'Email', 'bitmomo-pro' )         => get_post_meta( $post->ID, self::META_EMAIL, true ),
			__( 'First name', 'bitmomo-pro' )    => get_post_meta( $post->ID, self::META_FIRST_NAME, true ),
			__( 'Signed up', 'bitmomo-pro' )     => get_post_meta( $post->ID, self::META_CREATED_AT, true ),
			__( 'Consent at', 'bitmomo-pro' )    => get_post_meta( $post->ID, self::META_CONSENT_AT, true ),
			// WhatsApp — never shown anywhere public; only here, behind the
			// same manage_options-gated admin screen as email/first name.
			__( 'WhatsApp number', 'bitmomo-pro' )    => get_post_meta( $post->ID, self::META_WHATSAPP_NUMBER, true ) ? ( '+' . get_post_meta( $post->ID, self::META_WHATSAPP_NUMBER, true ) ) : '',
			__( 'WhatsApp consent at', 'bitmomo-pro' ) => get_post_meta( $post->ID, self::META_WHATSAPP_CONSENT_AT, true ),
			__( 'Source', 'bitmomo-pro' )        => get_post_meta( $post->ID, self::META_SOURCE, true ),
			__( 'Landing page', 'bitmomo-pro' )  => get_post_meta( $post->ID, self::META_LANDING_PAGE, true ),
			__( 'UTM source', 'bitmomo-pro' )    => get_post_meta( $post->ID, self::META_UTM_SOURCE, true ),
			__( 'UTM medium', 'bitmomo-pro' )    => get_post_meta( $post->ID, self::META_UTM_MEDIUM, true ),
			__( 'UTM campaign', 'bitmomo-pro' )  => get_post_meta( $post->ID, self::META_UTM_CAMPAIGN, true ),
			__( 'Referrer', 'bitmomo-pro' )      => get_post_meta( $post->ID, self::META_REFERRER, true ),
		);

		echo '<table class="form-table" role="presentation">';
		foreach ( $fields as $label => $value ) {
			printf( '<tr><th style="width:180px;">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $value ?: '—' ) );
		}

		$status = get_post_meta( $post->ID, self::META_STATUS, true ) ?: self::STATUS_WAITING;
		echo '<tr><th>' . esc_html__( 'Status', 'bitmomo-pro' ) . '</th><td><select name="bm_wl_status">';
		foreach ( self::STATUSES as $s ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $s ), selected( $status, $s, false ) );
		}
		echo '</select></td></tr>';

		$class = get_post_meta( $post->ID, self::META_VALIDATION_CLASS, true ) ?: self::DEFAULT_VALIDATION_CLASS;
		echo '<tr><th>' . esc_html__( 'Validation class', 'bitmomo-pro' ) . '</th><td><select name="bm_wl_validation_class">';
		foreach ( self::VALIDATION_CLASSES as $c ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $c ), selected( $class, $c, false ) );
		}
		echo '</select></td></tr>';
		echo '</table>';
		echo '<p class="description">' . esc_html__( 'Do not automatically treat every signup as cold PMF evidence — classify manually after reviewing.', 'bitmomo-pro' ) . '</p>';
	}

	public function save_admin_meta_box( $post_id ) {
		if ( ! isset( $_POST['bitmomo_pro_whitelist_admin_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bitmomo_pro_whitelist_admin_nonce'] ) ), 'bitmomo_pro_whitelist_admin_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['bm_wl_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['bm_wl_status'] ) );
			if ( in_array( $status, self::STATUSES, true ) ) {
				update_post_meta( $post_id, self::META_STATUS, $status );
			}
		}

		if ( isset( $_POST['bm_wl_validation_class'] ) ) {
			$class = sanitize_key( wp_unslash( $_POST['bm_wl_validation_class'] ) );
			if ( in_array( $class, self::VALIDATION_CLASSES, true ) ) {
				update_post_meta( $post_id, self::META_VALIDATION_CLASS, $class );
			}
		}
	}

	/**
	 * Strips a submit_entry() $args array down to the non-PII subset safe
	 * to pass into the 'bitmomo_pro_whitelist_created' action's
	 * telemetry-style context — source/UTM/landing page only, never email
	 * or first name.
	 */
	private static function telemetry_context( $args ) {
		return array(
			'source'       => $args['source'] ?? '',
			'utm_source'   => $args['utm_source'] ?? '',
			'utm_medium'   => $args['utm_medium'] ?? '',
			'utm_campaign' => $args['utm_campaign'] ?? '',
			'landing_page' => $args['landing_page'] ?? '',
		);
	}
}
