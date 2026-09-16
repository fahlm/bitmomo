import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const failures = [];
const check = (label, condition) => {
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
  if (!condition) failures.push(label);
};

const frontend = read('website/wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-frontend.php');
const proMain = read('website/wp-content/plugins/bitmomo-pro/bitmomo-pro.php');
const whitelist = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php');

check('Non-production indexing fails closed at environment boundary',
  frontend.includes('function bitmomo_public_indexing_enabled()') &&
  frontend.includes("'production' === wp_get_environment_type()") &&
  frontend.includes('BITMOMO_PUBLIC_INDEXING_ENABLED') &&
  frontend.includes("header('X-Robots-Tag: noindex, nofollow', true)") &&
  frontend.includes("add_filter('wp_robots', 'bitmomo_environment_robots_guard', 999)") &&
  frontend.includes("add_filter('rank_math/frontend/robots', 'bitmomo_environment_rank_math_robots_guard', 999)") &&
  frontend.includes("add_filter('wp_sitemaps_enabled', 'bitmomo_environment_sitemaps_enabled', 999)")
);

check('Public WordPress user enumeration is removed while privileged admin access remains possible',
  frontend.includes("current_user_can('list_users')") &&
  frontend.includes("strpos((string) $route, '/wp/v2/users')") &&
  frontend.includes("add_filter('rest_endpoints', 'bitmomo_restrict_public_user_rest_routes', 999)")
);

check('Help and legal document titles have canonical Bitmomo identity',
  frontend.includes("if (is_page('help')) return 'Help Center — Bitmomo';") &&
  frontend.includes("if (is_page('kebijakan-privasi')) return 'Kebijakan Privasi — Bitmomo';") &&
  frontend.includes("if (is_page('disclaimer')) return 'Disclaimer — Bitmomo';")
);

check('Whitelist conversion surfaces cannot serve cached WordPress nonces',
  whitelist.includes('wp_create_nonce( self::NONCE_ACTION )') &&
  proMain.includes('function bitmomo_pro_prevent_whitelist_page_cache()') &&
  proMain.includes("has_shortcode( $content, 'bitmomo_pro_sales' )") &&
  proMain.includes("has_shortcode( $content, 'bitmomo_pro_whitelist' )") &&
  proMain.includes("define( 'DONOTCACHEPAGE', true )") &&
  proMain.includes("do_action( 'litespeed_control_set_nocache', 'Bitmomo whitelist nonce safety' )")
);

if (failures.length) {
  console.error(`Launch audit hardening contract failed with ${failures.length} issue(s):`);
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('PASS launch audit hardening contract.');
