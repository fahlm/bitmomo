<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin diagnostics screen for Product B (WATCHTOWER PR 4).
 *
 * Deliberately a DEBUG/TRUST screen, not an analytics dashboard —
 * mirrors Bitmomo_Regime_Admin_Diagnostics's posture and structure for
 * consistency across the Bitmomo plugin family. Shows exactly what has
 * actually been persisted: the current thesis, recent thesis history,
 * recent alerts (with their transition context), and how many alerts
 * are sitting queued waiting for a Telegram adapter that does not exist
 * yet. `manage_options` gated, read-only, no REST route.
 *
 * This screen intentionally does NOT show materiality scores or
 * clusters for individual event candidates — Bitmomo_Watchtower_Event_Input,
 * Bitmomo_Watchtower_Materiality_Engine, and Bitmomo_Watchtower_Deduplicator
 * are pure, stateless classes with nothing persisted in this plugin to
 * look back on (no event/cluster store exists — see this PR's
 * CODEX_INTEGRATION_CONTRACT.md for why that is a deliberate, documented
 * gap for a future PR/Codex to fill, not an oversight).
 */
class Bitmomo_Watchtower_Admin_Diagnostics {

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
		add_menu_page(
			__( 'Bitmomo Watchtower', 'bitmomo-watchtower' ),
			__( 'Bitmomo Watchtower', 'bitmomo-watchtower' ),
			'manage_options',
			'bitmomo-watchtower-diagnostics',
			array( $this, 'render_page' ),
			'dashicons-visibility',
			81
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$thesis_store = Bitmomo_Watchtower_Thesis_Store::instance();
		$outbox       = Bitmomo_Watchtower_Alert_Outbox::instance();

		$current       = $thesis_store->get_current();
		$history       = $thesis_store->get_history( 10 );
		$recent_alerts = $outbox->get_recent( 10 );
		$queued        = $outbox->get_queued( 50 );

		echo '<div class="wrap"><h1>' . esc_html__( 'Bitmomo Watchtower — Diagnostics', 'bitmomo-watchtower' ) . '</h1>';
		echo '<p>' . esc_html__( 'Debug/trust view of the material-change detection engine\'s current and historical state. Not an analytics dashboard.', 'bitmomo-watchtower' ) . '</p>';

		if ( null === $current ) {
			echo '<p><strong>' . esc_html__( 'No thesis has been recorded yet.', 'bitmomo-watchtower' ) . '</strong> ';
			echo esc_html__( 'Bitmomo_Watchtower_Orchestrator::process() has not been called by the runtime feed yet — this is expected until Codex\'s scheduler is connected. No cron of any kind exists in this plugin.', 'bitmomo-watchtower' ) . '</p>';
		} else {
			$this->render_current_thesis( $current );
		}

		$this->render_thesis_history( $history );
		$this->render_recent_alerts( $recent_alerts );
		$this->render_queue_summary( $queued );
		$this->render_versions();

		echo '</div>';
	}

	private function render_current_thesis( $current ) {
		echo '<h2>' . esc_html__( 'Current Thesis', 'bitmomo-watchtower' ) . '</h2>';
		$rows = array(
			__( 'As of', 'bitmomo-watchtower' )              => $current['as_of'],
			__( 'Regime', 'bitmomo-watchtower' )              => $current['regime'],
			__( 'Directional bias', 'bitmomo-watchtower' )    => $current['directional_bias'],
			__( 'Confidence', 'bitmomo-watchtower' )          => $current['confidence'] . '%',
			__( 'Expected range', 'bitmomo-watchtower' )      => sprintf( '%s – %s', $current['expected_range_low'], $current['expected_range_high'] ),
			__( 'Invalidation', 'bitmomo-watchtower' )        => $current['invalidation'],
			__( 'Major driver', 'bitmomo-watchtower' )        => $current['major_driver'],
			__( 'Source record id', 'bitmomo-watchtower' )    => $current['source_record_id'],
		);
		$this->render_kv_table( $rows );
	}

	private function render_thesis_history( $history ) {
		echo '<h2>' . esc_html__( 'Recent Thesis History', 'bitmomo-watchtower' ) . '</h2>';
		if ( empty( $history ) ) {
			echo '<p>' . esc_html__( 'No thesis history yet.', 'bitmomo-watchtower' ) . '</p>';
			return;
		}
		$rows = array();
		foreach ( $history as $record ) {
			$rows[] = array( $record['as_of'], $record['regime'], $record['directional_bias'], $record['confidence'] . '%' );
		}
		$this->render_table(
			array( __( 'As of', 'bitmomo-watchtower' ), __( 'Regime', 'bitmomo-watchtower' ), __( 'Bias', 'bitmomo-watchtower' ), __( 'Confidence', 'bitmomo-watchtower' ) ),
			$rows
		);
		echo '<p><em>' . esc_html__( 'Append-only: a NO_CHANGE evaluation does not write a new row here (nothing changed); every other transition does.', 'bitmomo-watchtower' ) . '</em></p>';
	}

	private function render_recent_alerts( $alerts ) {
		echo '<h2>' . esc_html__( 'Recent Alerts', 'bitmomo-watchtower' ) . '</h2>';
		if ( empty( $alerts ) ) {
			echo '<p>' . esc_html__( 'No alerts have been enqueued yet.', 'bitmomo-watchtower' ) . '</p>';
			return;
		}
		$rows = array();
		foreach ( $alerts as $alert ) {
			$rows[] = array(
				$alert['created_at'],
				$alert['alert_class'],
				$alert['transition_type'],
				$alert['track'],
				$alert['status'],
				$alert['reason'],
			);
		}
		$this->render_table(
			array(
				__( 'Created', 'bitmomo-watchtower' ),
				__( 'Alert class', 'bitmomo-watchtower' ),
				__( 'Transition', 'bitmomo-watchtower' ),
				__( 'Routed track', 'bitmomo-watchtower' ),
				__( 'Status', 'bitmomo-watchtower' ),
				__( 'Reason', 'bitmomo-watchtower' ),
			),
			$rows
		);
	}

	private function render_queue_summary( $queued ) {
		echo '<h2>' . esc_html__( 'Outbox Queue', 'bitmomo-watchtower' ) . '</h2>';
		$this->render_kv_table(
			array( __( 'Currently queued (never auto-sent)', 'bitmomo-watchtower' ) => count( $queued ) )
		);
		echo '<p><em>' . esc_html__( 'No Telegram credentials or send logic exist anywhere in this plugin. Every queued alert stays queued until a future Telegram adapter (Codex\'s runtime work) drains it via get_queued() and calls mark_status().', 'bitmomo-watchtower' ) . '</em></p>';
	}

	private function render_versions() {
		echo '<h2>' . esc_html__( 'Engine Versions', 'bitmomo-watchtower' ) . '</h2>';
		$this->render_kv_table(
			array( __( 'Materiality engine version', 'bitmomo-watchtower' ) => Bitmomo_Watchtower_Config::MATERIALITY_ENGINE_VERSION )
		);
	}

	private function render_kv_table( $rows ) {
		echo '<table class="widefat striped" style="max-width:800px;"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th style="width:260px;">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_table( $headers, $rows ) {
		echo '<table class="widefat striped" style="max-width:1100px;"><thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $row as $cell ) {
				echo '<td>' . esc_html( (string) $cell ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
