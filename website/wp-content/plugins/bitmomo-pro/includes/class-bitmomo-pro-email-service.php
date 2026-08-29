<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bitmomo Pro email service — the ONLY place in this plugin that calls
 * wp_mail(). Two responsibilities: a founder-triggered welcome/access
 * email, and a founder/editor-triggered daily brief notification email.
 * Nothing here sends automatically — the founder stays in control during
 * Founding Beta, per the task's explicit instruction.
 *
 * Transport is plain wp_mail() today. Recipient selection, content
 * building, and the admin trigger points are all isolated in this one
 * class specifically so a future MailPoet integration can replace only
 * the transport (the actual wp_mail() calls) without touching how
 * recipients are chosen or what the emails say. MailPoet itself is
 * explicitly NOT integrated in this PR.
 */
class Bitmomo_Pro_Email_Service {

	const META_WELCOME_SENT_AT = 'bitmomo_pro_welcome_sent_at';

	const META_DAILY_SENT_AT         = '_bitmomo_pro_daily_email_sent_at';
	const META_DAILY_RECIPIENT_COUNT = '_bitmomo_pro_daily_email_recipient_count';
	const META_DAILY_SUCCESS_COUNT   = '_bitmomo_pro_daily_email_success_count';
	const META_DAILY_FAILURE_COUNT   = '_bitmomo_pro_daily_email_failure_count';
	const META_DAILY_TEST_MODE       = '_bitmomo_pro_daily_email_test_mode';

	const WELCOME_NONCE_ACTION = 'bitmomo_pro_send_welcome_email';
	const WELCOME_NONCE_FIELD  = 'bitmomo_pro_welcome_nonce';
	const WELCOME_ACTION       = 'bitmomo_pro_admin_send_welcome_email';

	const DAILY_NONCE_ACTION = 'bitmomo_pro_send_daily_email';
	const DAILY_NONCE_FIELD  = 'bitmomo_pro_daily_email_nonce';
	const DAILY_ACTION       = 'bitmomo_pro_admin_send_daily_email';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'show_user_profile', array( $this, 'render_welcome_email_section' ) );
		add_action( 'edit_user_profile', array( $this, 'render_welcome_email_section' ) );
		add_action( 'admin_post_' . self::WELCOME_ACTION, array( $this, 'handle_send_welcome_email' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_daily_email_meta_box' ) );
		add_action( 'admin_post_' . self::DAILY_ACTION, array( $this, 'handle_send_daily_email' ) );

		add_action( 'admin_notices', array( $this, 'render_result_notices' ) );
	}

	// ==================================================================
	// WELCOME EMAIL
	// ==================================================================

	private function build_welcome_email( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		$expires_at = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, true );
		$dashboard_url = function_exists( 'bitmomo_pro_get_dashboard_url' ) ? bitmomo_pro_get_dashboard_url() : '';
		$support_email = apply_filters( 'bitmomo_pro_support_email', get_option( 'admin_email' ) );

		$lines   = array();
		$lines[] = sprintf( __( 'Halo %s,', 'bitmomo-pro' ), $user->display_name );
		$lines[] = '';
		$lines[] = __( 'Akses Bitmomo Pro kamu sudah aktif.', 'bitmomo-pro' );
		$lines[] = '';

		if ( ! empty( $dashboard_url ) ) {
			$lines[] = __( 'Dashboard Pro:', 'bitmomo-pro' );
			$lines[] = $dashboard_url;
			$lines[] = '';
		}

		if ( ! empty( $expires_at ) ) {
			/* translators: %s: expiry date (YYYY-MM-DD) */
			$lines[] = sprintf( __( 'Masa akses saat ini berlaku sampai: %s', 'bitmomo-pro' ), $expires_at );
			$lines[] = '';
		}

		$lines[] = __( 'Cara masuk:', 'bitmomo-pro' );
		$lines[] = __( 'Gunakan akun WordPress kamu di alamat email ini. Jika kamu belum pernah mengatur password, atur lewat link resmi berikut:', 'bitmomo-pro' );
		$lines[] = wp_lostpassword_url();
		$lines[] = '';

		$lines[] = __( 'Ini adalah bagian dari Bitmomo Pro Founding Beta — sebagian proses (termasuk aktivasi dan pembatalan) masih dilakukan manual selama kami menyempurnakan produk di masa beta.', 'bitmomo-pro' );
		$lines[] = '';
		/* translators: %s: support email address */
		$lines[] = sprintf( __( 'Butuh bantuan atau ingin membatalkan perpanjangan? Balas email ini atau hubungi %s. Akses tetap aktif sampai akhir periode yang sudah dibayar.', 'bitmomo-pro' ), $support_email );
		$lines[] = '';
		$lines[] = __( 'Salam,', 'bitmomo-pro' );
		$lines[] = __( 'Tim Bitmomo', 'bitmomo-pro' );

		return array(
			'to'      => $user->user_email,
			'subject' => __( 'Bitmomo Pro aktif — mulai gunakan sekarang', 'bitmomo-pro' ),
			'body'    => implode( "\n", $lines ),
		);
	}

	/**
	 * Founder-triggered only. Does not check "test" source — a deliberate
	 * manual send (including to a test account) is always allowed; it is
	 * only automatic/bulk sends that must exclude test/internal accounts
	 * (see get_daily_recipients()).
	 */
	public function send_welcome_email( $user_id ) {
		if ( ! bitmomo_user_has_pro_access( $user_id ) ) {
			return array( 'sent' => false, 'error' => __( 'User does not have active Bitmomo Pro access.', 'bitmomo-pro' ) );
		}

		$email = $this->build_welcome_email( $user_id );
		if ( null === $email ) {
			return array( 'sent' => false, 'error' => __( 'User not found.', 'bitmomo-pro' ) );
		}

		$sent = wp_mail( $email['to'], $email['subject'], $email['body'] );
		if ( $sent ) {
			update_user_meta( $user_id, self::META_WELCOME_SENT_AT, current_time( 'mysql' ) );
		}

		return array( 'sent' => (bool) $sent );
	}

	public function render_welcome_email_section( $user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$has_access = bitmomo_user_has_pro_access( $user->ID );
		$last_sent  = get_user_meta( $user->ID, self::META_WELCOME_SENT_AT, true );

		echo '<h2>' . esc_html__( 'Bitmomo Pro — Email', 'bitmomo-pro' ) . '</h2>';

		if ( ! $has_access ) {
			echo '<p>' . esc_html__( 'Welcome email hanya bisa dikirim untuk akun dengan akses Bitmomo Pro aktif.', 'bitmomo-pro' ) . '</p>';
			return;
		}

		if ( $last_sent ) {
			printf( '<p>%s <strong>%s</strong></p>', esc_html__( 'Welcome email terakhir dikirim:', 'bitmomo-pro' ), esc_html( $last_sent ) );
		} else {
			echo '<p>' . esc_html__( 'Belum pernah dikirim.', 'bitmomo-pro' ) . '</p>';
		}

		wp_nonce_field( self::WELCOME_NONCE_ACTION, self::WELCOME_NONCE_FIELD );
		echo '<input type="hidden" name="user_id" value="' . esc_attr( $user->ID ) . '" />';
		printf(
			'<button type="submit" class="button button-secondary" name="action" value="%1$s" formaction="%2$s" formmethod="post">%3$s</button>',
			esc_attr( self::WELCOME_ACTION ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_html( $last_sent ? __( 'Kirim ulang welcome email', 'bitmomo-pro' ) : __( 'Kirim welcome email', 'bitmomo-pro' ) )
		);
	}

	public function handle_send_welcome_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bitmomo-pro' ) );
		}
		if ( ! isset( $_POST[ self::WELCOME_NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::WELCOME_NONCE_FIELD ] ) ), self::WELCOME_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bitmomo-pro' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$result  = $user_id ? $this->send_welcome_email( $user_id ) : array( 'sent' => false, 'error' => 'missing user_id' );

		set_transient( 'bitmomo_pro_welcome_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'bitmomo_pro_welcome_result', '1', admin_url( 'user-edit.php?user_id=' . $user_id ) ) );
		exit;
	}

	// ==================================================================
	// DAILY BRIEF EMAIL
	// ==================================================================

	/**
	 * Active, non-expired, valid-email users only. Excludes 'test'-source
	 * accounts by default so internal testing never hits real beta
	 * recipients; pass $test_mode = true to invert that (send ONLY to
	 * test/internal accounts, for pre-send QA).
	 *
	 * @return WP_User[]
	 */
	public function get_daily_recipients( $test_mode = false ) {
		$users = get_users(
			array(
				'meta_key'   => Bitmomo_Pro_Entitlements::META_STATUS,
				'meta_value' => 'active',
			)
		);

		$recipients = array();
		foreach ( $users as $user ) {
			if ( 'active' !== Bitmomo_Pro_Entitlement_Service::instance()->get_status( $user->ID ) ) {
				continue; // Expired despite status meta still saying 'active'.
			}
			if ( ! is_email( $user->user_email ) ) {
				continue;
			}

			$source    = get_user_meta( $user->ID, Bitmomo_Pro_Entitlements::META_SOURCE, true );
			$is_test   = ( Bitmomo_Pro_Entitlement_Service::TYPE_TEST === $source );

			if ( $test_mode && ! $is_test ) {
				continue;
			}
			if ( ! $test_mode && $is_test ) {
				continue;
			}

			$recipients[] = $user;
		}

		return $recipients;
	}

	private function build_daily_brief_email( $post_id ) {
		$market_state = get_post_meta( $post_id, '_bitmomo_pro_market_state', true );
		$confidence   = get_post_meta( $post_id, '_bitmomo_pro_confidence', true );
		$what_changed = get_post_meta( $post_id, '_bitmomo_pro_what_changed', true );
		$timestamp    = get_post_meta( $post_id, '_bitmomo_pro_data_timestamp', true );

		$state_label = array(
			'bullish' => __( 'Bullish', 'bitmomo-pro' ),
			'neutral' => __( 'Neutral', 'bitmomo-pro' ),
			'bearish' => __( 'Bearish', 'bitmomo-pro' ),
		);

		$excerpt = $what_changed;
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $excerpt ) > 160 ) {
			$excerpt = mb_substr( $excerpt, 0, 157 ) . '...';
		} elseif ( strlen( $excerpt ) > 160 ) {
			$excerpt = substr( $excerpt, 0, 157 ) . '...';
		}

		$dashboard_url = function_exists( 'bitmomo_pro_get_dashboard_url' ) ? bitmomo_pro_get_dashboard_url() : '';

		$lines   = array();
		$lines[] = __( 'Brief Bitmomo Pro Hari Ini:', 'bitmomo-pro' );
		$lines[] = '';
		/* translators: %s: market state label */
		$lines[] = sprintf( __( 'Market State: %s', 'bitmomo-pro' ), isset( $state_label[ $market_state ] ) ? $state_label[ $market_state ] : $market_state );
		if ( '' !== $confidence ) {
			/* translators: %s: confidence percentage */
			$lines[] = sprintf( __( 'Confidence: %s%%', 'bitmomo-pro' ), $confidence );
		}
		if ( ! empty( $excerpt ) ) {
			$lines[] = '';
			$lines[] = __( 'What Changed:', 'bitmomo-pro' );
			$lines[] = $excerpt;
		}
		$lines[] = '';
		if ( ! empty( $dashboard_url ) ) {
			$lines[] = __( 'Buka analisis lengkap di dashboard Pro:', 'bitmomo-pro' );
			$lines[] = $dashboard_url;
			$lines[] = '';
		}
		if ( ! empty( $timestamp ) ) {
			/* translators: %s: data timestamp */
			$lines[] = sprintf( __( 'Update data: %s', 'bitmomo-pro' ), $timestamp );
		}

		return array(
			'subject' => __( 'Brief Bitmomo Pro Hari Ini', 'bitmomo-pro' ),
			'body'    => implode( "\n", $lines ),
		);
	}

	/**
	 * Sends the daily brief notification for $post_id. Refuses a second
	 * real send unless $force_resend is explicitly true (duplicate
	 * protection). A partial failure never marks the whole thing "unsent"
	 * — success/failure counts are recorded separately and no automatic
	 * retry is attempted.
	 */
	public function send_daily_brief_email( $post_id, $test_mode = false, $force_resend = false ) {
		$already_sent = get_post_meta( $post_id, self::META_DAILY_SENT_AT, true );
		if ( $already_sent && ! $force_resend ) {
			return array(
				'sent'             => false,
				'already_sent'     => true,
				'sent_at'          => $already_sent,
				'recipient_count'  => (int) get_post_meta( $post_id, self::META_DAILY_RECIPIENT_COUNT, true ),
				'success_count'    => (int) get_post_meta( $post_id, self::META_DAILY_SUCCESS_COUNT, true ),
				'failure_count'    => (int) get_post_meta( $post_id, self::META_DAILY_FAILURE_COUNT, true ),
			);
		}

		$email      = $this->build_daily_brief_email( $post_id );
		$recipients = $this->get_daily_recipients( $test_mode );

		$success = 0;
		$failure = 0;
		foreach ( $recipients as $user ) {
			// Individual sends — never a shared To:/CC: list, recipients
			// never see each other.
			$ok = wp_mail( $user->user_email, $email['subject'], $email['body'] );
			if ( $ok ) {
				$success++;
			} else {
				$failure++;
			}
		}

		update_post_meta( $post_id, self::META_DAILY_SENT_AT, current_time( 'mysql' ) );
		update_post_meta( $post_id, self::META_DAILY_RECIPIENT_COUNT, count( $recipients ) );
		update_post_meta( $post_id, self::META_DAILY_SUCCESS_COUNT, $success );
		update_post_meta( $post_id, self::META_DAILY_FAILURE_COUNT, $failure );
		update_post_meta( $post_id, self::META_DAILY_TEST_MODE, $test_mode ? '1' : '' );

		return array(
			'sent'            => true,
			'already_sent'    => false,
			'recipient_count' => count( $recipients ),
			'success_count'   => $success,
			'failure_count'   => $failure,
		);
	}

	public function add_daily_email_meta_box() {
		add_meta_box(
			'bitmomo_pro_daily_email',
			__( 'Kirim Email Harian', 'bitmomo-pro' ),
			array( $this, 'render_daily_email_meta_box' ),
			Bitmomo_Pro_Briefs::POST_TYPE,
			'side',
			'default'
		);
	}

	public function render_daily_email_meta_box( $post ) {
		if ( 'publish' !== $post->post_status ) {
			echo '<p class="description">' . esc_html__( 'Publikasikan brief ini dulu (dan pastikan lolos Status Rilis) sebelum mengirim email.', 'bitmomo-pro' ) . '</p>';
			return;
		}

		$current = Bitmomo_Pro_Briefs::get_current_brief_for_display();
		$is_current = ( null !== $current['brief'] && isset( $current['brief']['id'] ) && (int) $current['brief']['id'] === (int) $post->ID );

		if ( ! $is_current ) {
			echo '<p class="description">' . esc_html__( 'Brief ini bukan brief aktif saat ini (lihat Status Rilis) — tidak bisa dikirim sebagai email harian.', 'bitmomo-pro' ) . '</p>';
			return;
		}

		$already_sent = get_post_meta( $post->ID, self::META_DAILY_SENT_AT, true );
		if ( $already_sent ) {
			printf(
				'<p>%s <strong>%s</strong><br />%s</p>',
				esc_html__( 'Sudah dikirim:', 'bitmomo-pro' ),
				esc_html( $already_sent ),
				esc_html(
					sprintf(
						/* translators: 1: recipient count, 2: success count, 3: failure count */
						__( '%1$d penerima — %2$d sukses, %3$d gagal.', 'bitmomo-pro' ),
						(int) get_post_meta( $post->ID, self::META_DAILY_RECIPIENT_COUNT, true ),
						(int) get_post_meta( $post->ID, self::META_DAILY_SUCCESS_COUNT, true ),
						(int) get_post_meta( $post->ID, self::META_DAILY_FAILURE_COUNT, true )
					)
				)
			);
		}

		$real_count = count( $this->get_daily_recipients( false ) );
		$test_count = count( $this->get_daily_recipients( true ) );

		wp_nonce_field( self::DAILY_NONCE_ACTION, self::DAILY_NONCE_FIELD );
		echo '<input type="hidden" name="post_id" value="' . esc_attr( $post->ID ) . '" />';

		printf( '<p style="font-size:12px;">%s</p>', esc_html( sprintf( __( 'Penerima real saat ini: %1$d. Akun test/internal: %2$d.', 'bitmomo-pro' ), $real_count, $test_count ) ) );

		echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="test_mode" value="1" /> ' . esc_html__( 'Test mode (kirim hanya ke akun test/internal)', 'bitmomo-pro' ) . '</label>';

		if ( $already_sent ) {
			echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="force_resend" value="1" required /> ' . esc_html__( 'Ya, kirim ulang meskipun sudah pernah dikirim', 'bitmomo-pro' ) . '</label>';
		}

		printf(
			'<button type="submit" class="button button-primary" name="action" value="%1$s" formaction="%2$s" formmethod="post">%3$s</button>',
			esc_attr( self::DAILY_ACTION ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_html__( 'Kirim email harian', 'bitmomo-pro' )
		);
	}

	public function handle_send_daily_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bitmomo-pro' ) );
		}
		if ( ! isset( $_POST[ self::DAILY_NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::DAILY_NONCE_FIELD ] ) ), self::DAILY_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bitmomo-pro' ) );
		}

		$post_id      = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$test_mode    = ! empty( $_POST['test_mode'] );
		$force_resend = ! empty( $_POST['force_resend'] );

		$result = $post_id ? $this->send_daily_brief_email( $post_id, $test_mode, $force_resend ) : array( 'sent' => false );

		set_transient( 'bitmomo_pro_daily_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'bitmomo_pro_daily_result', '1', admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) );
		exit;
	}

	// ==================================================================
	// SHARED NOTICES
	// ==================================================================

	public function render_result_notices() {
		if ( isset( $_GET['bitmomo_pro_welcome_result'] ) ) {
			$result = get_transient( 'bitmomo_pro_welcome_result_' . get_current_user_id() );
			if ( is_array( $result ) ) {
				delete_transient( 'bitmomo_pro_welcome_result_' . get_current_user_id() );
				$class = ! empty( $result['sent'] ) ? 'notice-success' : 'notice-error';
				$msg   = ! empty( $result['sent'] ) ? __( 'Welcome email terkirim.', 'bitmomo-pro' ) : __( 'Welcome email gagal terkirim.', 'bitmomo-pro' );
				echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}

		if ( isset( $_GET['bitmomo_pro_daily_result'] ) ) {
			$result = get_transient( 'bitmomo_pro_daily_result_' . get_current_user_id() );
			if ( is_array( $result ) ) {
				delete_transient( 'bitmomo_pro_daily_result_' . get_current_user_id() );
				if ( ! empty( $result['already_sent'] ) ) {
					echo '<div class="notice notice-warning"><p>' . esc_html__( 'Belum dikirim ulang — brief ini sudah pernah dikirim. Centang "Kirim ulang" jika memang ingin mengirim lagi.', 'bitmomo-pro' ) . '</p></div>';
				} elseif ( ! empty( $result['sent'] ) ) {
					printf(
						'<div class="notice notice-success"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: 1: recipient count, 2: success count, 3: failure count */
								__( 'Email harian terkirim ke %1$d penerima — %2$d sukses, %3$d gagal.', 'bitmomo-pro' ),
								$result['recipient_count'],
								$result['success_count'],
								$result['failure_count']
							)
						)
					);
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Gagal mengirim email harian.', 'bitmomo-pro' ) . '</p></div>';
				}
			}
		}
	}
}
