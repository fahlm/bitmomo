<?php
/**
 * Minimal stub so the domain classes' `if ( ! defined( 'ABSPATH' ) ) exit;`
 * guard passes when run standalone via `php tests/test-*.php` — not a WP
 * test framework. Bitmomo_Regime_Taxonomy / Config / Input / Classifier
 * call no other WordPress function, by design (see each class's
 * docblock), so nothing else is stubbed here.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
