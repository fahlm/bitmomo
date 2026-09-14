<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap compatibility shim.
 *
 * Canonical public copy is owned by the renderers that publish it. The legacy
 * post-render string-replacement layer is intentionally retired; keeping this
 * class and its init() method preserves bootstrap compatibility without
 * mutating sales, Help, whitelist, account, pricing, entitlement, data, or
 * legal output after render.
 */
final class Bitmomo_Pro_Public_Copy {

	public static function init() {
		// Intentionally empty. Public copy must be source-owned.
	}
}
