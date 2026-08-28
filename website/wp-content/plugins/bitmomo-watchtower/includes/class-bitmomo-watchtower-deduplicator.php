<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deduplication / clustering for Product B (WATCHTOWER PR 2).
 *
 * Pure PHP, no WordPress dependency, no I/O — takes a batch of scored
 * event candidates (each the merge of a Bitmomo_Watchtower_Event_Input
 * validated input with its Bitmomo_Watchtower_Materiality_Engine result)
 * and groups near-duplicate candidates into clusters, so that N reports
 * of the same underlying event become ONE cluster, not N separate
 * alerts. This is what "no N-articles-to-N-alerts" (spec B-series)
 * means in practice — clustering happens here, BEFORE any alert
 * decision (WATCHTOWER PR 3) is ever made.
 *
 * Clustering rule: two candidates cluster together when they share the
 * same `event_type` AND occur within Bitmomo_Watchtower_Config::CLUSTER_WINDOW_MINUTES
 * of the cluster's current last_seen timestamp — deliberately not an
 * NLP/similarity match (no LLM anywhere in this engine), a simple,
 * fully deterministic, fully testable rule. The window is ROLLING: it
 * re-measures from whichever event most recently joined the cluster, not
 * from the cluster's first event — see Bitmomo_Watchtower_Config's
 * docblock for why that's intentional.
 *
 * Never drops or invents events: every input candidate ends up inside
 * exactly one cluster's `members` list — sum of every cluster's
 * `member_count` always equals the number of input candidates.
 */
class Bitmomo_Watchtower_Deduplicator {

	/**
	 * @param array    $candidates      Array of scored candidates. Each expected to carry at
	 *                                   least `event_type`, `occurred_at` (parseable by strtotime),
	 *                                   `score`, `band`; `domain`, `source_id`, `headline` are
	 *                                   used if present.
	 * @param int|null $window_minutes  Overrides Bitmomo_Watchtower_Config::CLUSTER_WINDOW_MINUTES for this call.
	 *
	 * @return array[] Clusters, ordered by first_seen ascending (the order clusters were opened in,
	 *                  which is chronological because candidates are processed in time order).
	 */
	public function cluster( array $candidates, $window_minutes = null ) {
		$window_minutes = null === $window_minutes ? Bitmomo_Watchtower_Config::CLUSTER_WINDOW_MINUTES : (int) $window_minutes;
		$window_seconds = $window_minutes * 60;

		// Deterministic stable sort by occurred_at ascending, tie-broken
		// by original input index. PHP 7.4's usort() is not guaranteed
		// stable (guaranteed only from PHP 8.0+), so the original index
		// is carried explicitly rather than relied on implicitly — same
		// convention as Bitmomo_Regime_Classifier's rank_regimes().
		$decorated = array();
		foreach ( array_values( $candidates ) as $i => $candidate ) {
			$decorated[] = array(
				'i'         => $i,
				'ts'        => strtotime( (string) $candidate['occurred_at'] ),
				'candidate' => $candidate,
			);
		}
		usort(
			$decorated,
			function ( $a, $b ) {
				if ( $a['ts'] === $b['ts'] ) {
					return $a['i'] <=> $b['i'];
				}
				return $a['ts'] <=> $b['ts'];
			}
		);

		$clusters = array();
		foreach ( $decorated as $entry ) {
			$candidate      = $entry['candidate'];
			$ts             = $entry['ts'];
			$matched_index = $this->find_cluster( $clusters, $candidate, $ts, $window_seconds );

			if ( null === $matched_index ) {
				$clusters[] = $this->open_cluster( $candidate, $ts );
				continue;
			}

			$clusters[ $matched_index ] = $this->add_to_cluster( $clusters[ $matched_index ], $candidate, $ts );
		}

		return $clusters;
	}

	/**
	 * First matching open cluster, in cluster-creation order (i.e. the
	 * earliest-opened cluster of the same event_type still within the
	 * window wins) — a fixed, deterministic rule rather than "closest
	 * match."
	 */
	private function find_cluster( $clusters, $candidate, $ts, $window_seconds ) {
		foreach ( $clusters as $index => $cluster ) {
			if ( $cluster['event_type'] !== $candidate['event_type'] ) {
				continue;
			}
			if ( ( $ts - $cluster['last_seen_ts'] ) <= $window_seconds ) {
				return $index;
			}
		}
		return null;
	}

	private function open_cluster( $candidate, $ts ) {
		$summary = $this->member_summary( $candidate );
		return array(
			'cluster_key'   => $candidate['event_type'] . '@' . gmdate( 'Y-m-d\TH:i:s\Z', $ts ),
			'event_type'    => $candidate['event_type'],
			'domain'        => isset( $candidate['domain'] ) ? $candidate['domain'] : null,
			'first_seen'    => (string) $candidate['occurred_at'],
			'first_seen_ts' => $ts,
			'last_seen'     => (string) $candidate['occurred_at'],
			'last_seen_ts'  => $ts,
			'member_count'  => 1,
			'members'       => array( $summary ),
			'representative' => $summary,
			'highest_score' => isset( $candidate['score'] ) ? $candidate['score'] : 0,
			'highest_band'  => isset( $candidate['band'] ) ? $candidate['band'] : null,
		);
	}

	/**
	 * Adds a candidate to an already-open cluster. The representative
	 * (and highest_score/highest_band) only changes on a STRICTLY higher
	 * score than the current representative — so among equal scores, the
	 * earliest-processed (i.e. earliest-occurring, given the sort above)
	 * member stays representative. Deterministic, not "last one wins."
	 */
	private function add_to_cluster( $cluster, $candidate, $ts ) {
		$cluster['last_seen']    = (string) $candidate['occurred_at'];
		$cluster['last_seen_ts'] = $ts;
		$cluster['member_count']++;
		$cluster['members'][] = $this->member_summary( $candidate );

		$score = isset( $candidate['score'] ) ? $candidate['score'] : 0;
		if ( $score > $cluster['highest_score'] ) {
			$cluster['highest_score']  = $score;
			$cluster['highest_band']   = isset( $candidate['band'] ) ? $candidate['band'] : null;
			$cluster['representative'] = $this->member_summary( $candidate );
		}

		return $cluster;
	}

	private function member_summary( $candidate ) {
		return array(
			'source_id'   => isset( $candidate['source_id'] ) ? $candidate['source_id'] : '',
			'headline'    => isset( $candidate['headline'] ) ? $candidate['headline'] : '',
			'occurred_at' => isset( $candidate['occurred_at'] ) ? (string) $candidate['occurred_at'] : '',
			'score'       => isset( $candidate['score'] ) ? $candidate['score'] : 0,
			'band'        => isset( $candidate['band'] ) ? $candidate['band'] : null,
		);
	}
}
