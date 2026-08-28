<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin diagnostics screen for Product A (REGIME PR 3).
 *
 * Deliberately a DEBUG/TRUST screen, not an analytics dashboard — it
 * shows exactly what the spec asks for and nothing more: the current
 * official regime, the raw candidate that produced it, confidence,
 * evidence, conflicting evidence, the previous official state, a
 * history count, and the classifier version. An operator (Codex or the
 * founder) uses this to sanity-check that the engine is behaving, not to
 * analyze market trends.
 *
 * Read-only: this screen never writes state. `manage_options` gated, not
 * public, no REST route — same trust boundary as bitmomo-pro's launch
 * readiness screen (Bitmomo_Pro_Launch_Readiness), which this file's
 * table-rendering style deliberately mirrors for consistency across the
 * Bitmomo plugin family.
 */
class Bitmomo_Regime_Admin_Diagnostics {

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
			__( 'Bitmomo Market Regime', 'bitmomo-regime' ),
			__( 'Bitmomo Regime', 'bitmomo-regime' ),
			'manage_options',
			'bitmomo-regime-diagnostics',
			array( $this, 'render_page' ),
			'dashicons-chart-line',
			80
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$store  = Bitmomo_Regime_State_Store::instance();
		$recent = $store->get_recent( 30 ); // newest-first
		$latest = isset( $recent[0] ) ? $recent[0] : null;
		$prev   = isset( $recent[1] ) ? $recent[1] : null;

		echo '<div class="wrap"><h1>' . esc_html__( 'Bitmomo Market Regime — Diagnostics', 'bitmomo-regime' ) . '</h1>';
		echo '<p>' . esc_html__( 'Debug/trust view of the regime engine\'s current and historical state. Not an analytics dashboard.', 'bitmomo-regime' ) . '</p>';

		if ( null === $latest ) {
			echo '<p><strong>' . esc_html__( 'No regime state has been recorded yet.', 'bitmomo-regime' ) . '</strong> ';
			echo esc_html__( 'evaluate_and_record() has not been called by the runtime feed yet — this is expected until Codex\'s scheduler is connected.', 'bitmomo-regime' ) . '</p>';
			echo '</div>';
			return;
		}

		$this->render_current_state( $latest );
		$this->render_evidence( $latest );
		$this->render_previous_state( $prev );
		$this->render_history_summary( $recent );

		echo '</div>';
	}

	private function render_current_state( $latest ) {
		echo '<h2>' . esc_html__( 'Current Official State', 'bitmomo-regime' ) . '</h2>';
		$rows = array(
			__( 'As of', 'bitmomo-regime' )                => $latest['as_of'],
			__( 'Official regime', 'bitmomo-regime' )      => sprintf( '%s (%s)', Bitmomo_Regime_Taxonomy::regime_label_en( $latest['regime'] ), $latest['regime'] ),
			__( 'Candidate regime', 'bitmomo-regime' )      => sprintf( '%s (%s)', Bitmomo_Regime_Taxonomy::regime_label_en( $latest['candidate_regime'] ), $latest['candidate_regime'] ),
			__( 'Directional bias', 'bitmomo-regime' )      => $latest['directional_bias'],
			__( 'Regime confidence', 'bitmomo-regime' )     => $latest['regime_confidence'] . '%',
			__( 'Directional confidence', 'bitmomo-regime' ) => $latest['directional_confidence'] . '%',
			__( 'Transition strength', 'bitmomo-regime' )   => $latest['transition_strength'],
			__( 'Transition reason', 'bitmomo-regime' )     => $latest['transition_reason'],
			__( 'Pending regime', 'bitmomo-regime' )        => $latest['pending_regime'] ? $latest['pending_regime'] : __( '(none)', 'bitmomo-regime' ),
			__( 'Pending streak', 'bitmomo-regime' )        => (int) $latest['pending_streak'],
			__( 'Classifier version', 'bitmomo-regime' )    => $latest['classifier_version'],
			__( 'Source record id', 'bitmomo-regime' )      => $latest['source_record_id'] ? $latest['source_record_id'] : __( '(none)', 'bitmomo-regime' ),
		);
		$this->render_kv_table( $rows );
	}

	private function render_evidence( $latest ) {
		echo '<h2>' . esc_html__( 'Evidence', 'bitmomo-regime' ) . '</h2>';
		$this->render_list( is_array( $latest['evidence'] ) ? $latest['evidence'] : array(), __( 'No evidence recorded.', 'bitmomo-regime' ) );

		echo '<h2>' . esc_html__( 'Conflicting Evidence', 'bitmomo-regime' ) . '</h2>';
		$this->render_list( is_array( $latest['conflicts'] ) ? $latest['conflicts'] : array(), __( 'No conflicting evidence — this classification was not close to ambiguous.', 'bitmomo-regime' ) );
	}

	private function render_previous_state( $prev ) {
		echo '<h2>' . esc_html__( 'Previous Official State', 'bitmomo-regime' ) . '</h2>';
		if ( null === $prev ) {
			echo '<p>' . esc_html__( 'Only one state record exists so far — no previous state to compare against.', 'bitmomo-regime' ) . '</p>';
			return;
		}
		$rows = array(
			__( 'As of', 'bitmomo-regime' )            => $prev['as_of'],
			__( 'Regime', 'bitmomo-regime' )           => sprintf( '%s (%s)', Bitmomo_Regime_Taxonomy::regime_label_en( $prev['regime'] ), $prev['regime'] ),
			__( 'Directional bias', 'bitmomo-regime' ) => $prev['directional_bias'],
			__( 'Regime confidence', 'bitmomo-regime' ) => $prev['regime_confidence'] . '%',
		);
		$this->render_kv_table( $rows );
	}

	private function render_history_summary( $recent ) {
		echo '<h2>' . esc_html__( 'History Summary', 'bitmomo-regime' ) . '</h2>';
		$rows = array(
			__( 'Records available (of last 30 fetched)', 'bitmomo-regime' ) => count( $recent ),
		);
		$this->render_kv_table( $rows );
		echo '<p><em>' . esc_html__( 'This count reflects only the most recent 30 fetched here, not the plugin\'s total lifetime record count — history is append-only and never overwritten.', 'bitmomo-regime' ) . '</em></p>';
	}

	private function render_kv_table( $rows ) {
		echo '<table class="widefat striped" style="max-width:800px;"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th style="width:260px;">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_list( $items, $empty_message ) {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html( $empty_message ) . '</p>';
			return;
		}
		echo '<ul style="list-style:disc;margin-left:1.5em;">';
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( (string) $item ) . '</li>';
		}
		echo '</ul>';
	}
}
