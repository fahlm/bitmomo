<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transport-agnostic Telegram acquisition brief formatter.
 *
 * This class is deliberately presentation-only. It consumes the same
 * public-safe adapter as /btc-intelligence/ and cannot calculate a second
 * market opinion. Telegram transport credentials/sending are intentionally
 * outside this class.
 */
final class Bitmomo_Btc_Telegram_Brief {

	const DEFAULT_CTA_URL = 'https://bitmomo.id/pro/?utm_source=telegram&utm_medium=channel&utm_campaign=founding149_tlw_v1#bm-pro-whitelist';
	const MAX_TEXT_LENGTH = 3500;

	/**
	 * Build one public-safe Telegram brief.
	 *
	 * @param array|null $snapshot Optional fixture/explicit public snapshot.
	 * @param string     $cta_url  Optional attributed Founding CTA.
	 * @return array{ok:bool,reason:string,text:string}
	 */
	public static function build( $snapshot = null, $cta_url = self::DEFAULT_CTA_URL ) {
		if ( null === $snapshot ) {
			if ( ! class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) ) {
				return self::failure( 'adapter_unavailable' );
			}
			$snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
		}

		if ( ! is_array( $snapshot ) || 'fresh' !== (string) ( $snapshot['status'] ?? '' ) ) {
			return self::failure( 'snapshot_not_fresh' );
		}

		$bias       = self::bias_label( $snapshot['directional_bias'] ?? '' );
		$confidence = self::confidence_value( $snapshot['confidence'] ?? null );
		$price      = self::positive_number( $snapshot['btc_reference_price'] ?? null );
		$timestamp  = self::wib_timestamp( $snapshot['freshness']['timestamp_iso'] ?? ( $snapshot['provenance']['as_of'] ?? '' ) );
		$driver     = self::first_driver( $snapshot['key_drivers'] ?? array() );

		$session = is_array( $snapshot['session_intelligence'] ?? null ) ? $snapshot['session_intelligence'] : array();
		$changes = self::public_changes( $session['what_changed'] ?? array() );
		$meaning = self::public_meaning( $session['why_it_matters'] ?? array() );
		$watch   = self::public_watch( $session['what_to_watch'] ?? array() );

		if ( '' === $bias || null === $confidence || null === $price || '' === $timestamp || '' === $driver ) {
			return self::failure( 'required_public_field_missing' );
		}
		if ( empty( $changes ) || empty( $meaning ) || '' === $watch ) {
			return self::failure( 'brief_contract_incomplete' );
		}

		$lines   = array();
		$lines[] = 'BTC DECISION BRIEF';
		$lines[] = $timestamp;
		$lines[] = '';
		$lines[] = sprintf( 'Bias: %1$s · Confidence: %2$d%%', $bias, $confidence );
		$lines[] = 'BTC: $' . number_format( $price, 0, '.', ',' );
		$lines[] = 'Driver utama: ' . $driver;
		$lines[] = '';
		$lines[] = 'APA YANG BERUBAH';
		foreach ( array_slice( $changes, 0, 2 ) as $change ) {
			$lines[] = '• ' . $change;
		}
		$lines[] = '';
		$lines[] = 'MENGAPA PENTING';
		$lines[] = '• ' . $meaning[0];
		$lines[] = '';
		$lines[] = 'PANTAU BERIKUTNYA';
		$lines[] = '• ' . $watch;
		$lines[] = '';
		$lines[] = 'Bukan sinyal beli/jual.';

		$cta_url = self::safe_https_url( $cta_url );
		if ( '' !== $cta_url ) {
			$lines[] = 'Founding 149: ' . $cta_url;
		}

		$text = implode( "\n", $lines );
		if ( strlen( $text ) > self::MAX_TEXT_LENGTH ) {
			return self::failure( 'brief_too_long' );
		}

		return array(
			'ok'     => true,
			'reason' => 'ready',
			'text'   => $text,
		);
	}

	private static function failure( $reason ) {
		return array(
			'ok'     => false,
			'reason' => (string) $reason,
			'text'   => '',
		);
	}

	private static function clean_text( $value, $max = 220 ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( preg_replace( '/\s+/', ' ', strip_tags( $value ) ) );
		$value = trim( $value );
		if ( strlen( $value ) > $max ) {
			$value = substr( $value, 0, $max );
		}
		return $value;
	}

	private static function positive_number( $value ) {
		if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
			return null;
		}
		return (float) $value;
	}

	private static function confidence_value( $confidence ) {
		$value = is_array( $confidence ) ? ( $confidence['value'] ?? null ) : $confidence;
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$value = (int) $value;
		return $value >= 0 && $value <= 100 ? $value : null;
	}

	private static function bias_label( $bias ) {
		$map = array(
			'bearish' => 'Bearish',
			'neutral' => 'Netral',
			'bullish' => 'Bullish',
		);
		$key = strtolower( self::clean_text( $bias, 30 ) );
		return $map[ $key ] ?? '';
	}

	private static function first_driver( $drivers ) {
		foreach ( (array) $drivers as $driver ) {
			$driver = self::clean_text( $driver, 180 );
			if ( '' !== $driver ) {
				return $driver;
			}
		return '';
	}

	private static function public_changes( $changes ) {
		$out = array();
		foreach ( (array) $changes as $change ) {
			if ( ! is_array( $change ) ) {
				continue;
			}
			$field = strtolower( self::clean_text( $change['field'] ?? '', 40 ) );
			if ( 'directional_bias' === $field ) {
				$from = self::bias_label( $change['from'] ?? '' );
				$to   = self::bias_label( $change['to'] ?? '' );
				if ( '' !== $from && '' !== $to && $from !== $to ) {
					$out[] = sprintf( 'Bias berubah dari %1$s menjadi %2$s.', $from, $to );
				}
			} elseif ( 'confidence' === $field && is_numeric( $change['from'] ?? null ) && is_numeric( $change['to'] ?? null ) ) {
				$from = max( 0, min( 100, (int) $change['from'] ) );
				$to   = max( 0, min( 100, (int) $change['to'] ) );
				if ( $from !== $to ) {
					$out[] = sprintf( 'Confidence berubah dari %1$d%% menjadi %2$d%%.', $from, $to );
				}
			}
			if ( count( $out ) >= 2 ) {
				break;
			}
		}
		return $out;
	}

	private static function public_meaning( $reasons ) {
		$allowlist = array(
			'directional_context_changed' => 'Arah dominan pasar berubah.',
			'evidence_strength_changed'    => 'Kekuatan bukti yang mendukung pembacaan berubah.',
			'structure_context_changed'     => 'Konteks struktur harga berubah.',
		);
		$out = array();
		foreach ( (array) $reasons as $reason ) {
			$key = strtolower( self::clean_text( $reason, 60 ) );
			if ( isset( $allowlist[ $key ] ) ) {
				$out[] = $allowlist[ $key ];
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function public_watch( $watches ) {
		$allowlist = array(
			'directional_consistency' => 'Apakah kekuatan arah tetap konsisten.',
			'structure_continuity'    => 'Apakah struktur harga tetap mendukung bias saat ini.',
		);
		foreach ( (array) $watches as $watch ) {
			$key = strtolower( self::clean_text( $watch, 60 ) );
			if ( isset( $allowlist[ $key ] ) ) {
				return $allowlist[ $key ];
			}
		}
		return '';
	}

	private static function wib_timestamp( $value ) {
		$value = self::clean_text( $value, 80 );
		if ( '' === $value ) {
			return '';
		}
		try {
			$date = new DateTimeImmutable( $value );
			$date = $date->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) );
			return $date->format( 'd M Y · H:i' ) . ' WIB';
		} catch ( Exception $e ) {
			return '';
		}
	}

	private static function safe_https_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || 1 !== preg_match( '#^https://[^\s]+$#i', $url ) ) {
			return '';
		}
		return $url;
	}
}
