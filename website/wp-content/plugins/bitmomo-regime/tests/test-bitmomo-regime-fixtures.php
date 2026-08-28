<?php
/**
 * Fixture regression test (Integration Hardening Phase, Task 1).
 *
 * Loads every tests/fixtures/regime-*.json fixture, re-runs the REAL
 * Bitmomo_Regime_Input::validate() + Bitmomo_Regime_Classifier::evaluate()
 * against each fixture's normalized_input, and asserts the live result
 * matches the fixture's "expected" block exactly (except confidence, which
 * is asserted to fall within the fixture's documented confidence_range,
 * per the fixture's own confidence_range_note).
 *
 * Purpose: these fixtures are Codex's canonical, machine-readable examples
 * of what each of the five regime outcomes actually looks like end-to-end.
 * If a future change to Bitmomo_Regime_Classifier or Bitmomo_Regime_Config
 * changes any fixture's outcome, THIS test fails loudly — forcing either
 * the fixture or CLASSIFIER_VERSION to be deliberately updated, rather
 * than silently drifting out of sync with what Codex was told to expect.
 *
 * Run: php tests/test-bitmomo-regime-fixtures.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-regime-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-regime-config.php';
require __DIR__ . '/../includes/class-bitmomo-regime-input.php';
require __DIR__ . '/../includes/class-bitmomo-regime-classifier.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

$classifier    = new Bitmomo_Regime_Classifier();
$fixtures_dir  = __DIR__ . '/fixtures';
$fixture_files = glob( $fixtures_dir . '/regime-*.json' );
sort( $fixture_files );

check( 'At least 5 regime fixtures exist', count( $fixture_files ) >= 5 );

$seen_regimes = array();

foreach ( $fixture_files as $path ) {
	$name    = basename( $path );
	$decoded = json_decode( file_get_contents( $path ), true );

	check( "{$name}: parses as valid JSON", null !== $decoded && JSON_ERROR_NONE === json_last_error() );
	if ( null === $decoded ) {
		continue;
	}

	foreach ( array( 'fixture_id', 'regime_label', 'classifier_version', 'normalized_input', 'expected' ) as $required_key ) {
		check( "{$name}: has required top-level key '{$required_key}'", array_key_exists( $required_key, $decoded ) );
	}

	check(
		"{$name}: classifier_version matches the current engine (regime-v1)",
		Bitmomo_Regime_Config::CLASSIFIER_VERSION === $decoded['classifier_version']
	);

	$validation = Bitmomo_Regime_Input::validate( $decoded['normalized_input'] );
	check( "{$name}: normalized_input passes Bitmomo_Regime_Input::validate()", true === $validation['valid'] );
	if ( ! $validation['valid'] ) {
		continue;
	}

	$result   = $classifier->evaluate( $validation['input'] );
	$expected = $decoded['expected'];

	check( "{$name}: regime matches expected ({$expected['regime']})", $expected['regime'] === $result['regime'] );

	$conf_min = $expected['confidence_range'][0];
	$conf_max = $expected['confidence_range'][1];
	check(
		"{$name}: confidence {$result['confidence']} falls within documented range [{$conf_min}, {$conf_max}]",
		$result['confidence'] >= $conf_min && $result['confidence'] <= $conf_max
	);
	check(
		"{$name}: confidence matches the exact value this fixture was generated with ({$expected['confidence_exact']})",
		$expected['confidence_exact'] === $result['confidence']
	);

	check( "{$name}: evidence matches expected exactly", $expected['evidence'] === $result['evidence'] );
	check( "{$name}: conflicts match expected exactly", $expected['conflicts'] === $result['conflicts'] );
	check( "{$name}: scores match expected exactly", $expected['scores'] === $result['scores'] );
	check( "{$name}: directional_bias passthrough matches expected", $expected['directional_bias'] === $result['directional_bias'] );
	// JSON has no int/float distinction; Bitmomo_Regime_Input::validate()
	// normalizes directional_confidence to float. Compare numerically, not
	// with strict ===, so a fixture value like 40 (decoded as PHP int)
	// correctly matches the classifier's 40.0 (float).
	check(
		"{$name}: directional_confidence passthrough matches expected",
		( null === $expected['directional_confidence'] && null === $result['directional_confidence'] )
		|| ( (float) $expected['directional_confidence'] === $result['directional_confidence'] )
	);

	$seen_regimes[ $decoded['regime_label'] ] = true;
}

foreach ( array( 'accumulation', 'expansion', 'distribution', 'capitulation', 'transition' ) as $regime ) {
	check( "A fixture exists for regime_label '{$regime}'", isset( $seen_regimes[ $regime ] ) );
}

// ---------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------
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
