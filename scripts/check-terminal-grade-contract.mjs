import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const root = process.cwd();
const theme = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const pro = path.join(root, 'website/wp-content/plugins/bitmomo-pro');
const failures = [];

function read(file) { return fs.readFileSync(path.join(root, file), 'utf8'); }
function check(label, condition) {
  if (!condition) failures.push(label);
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
}
function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });
}

const header = read('website/wp-content/themes/bitmomo-child-v3/header.php');
const frontPage = read('website/wp-content/themes/bitmomo-child-v3/front-page.php');
const home = read('website/wp-content/themes/bitmomo-child-v3/home.php');
const functions = read('website/wp-content/themes/bitmomo-child-v3/functions.php');
const helpers = read('website/wp-content/themes/bitmomo-child-v3/inc/template-functions.php');
const assets = read('website/wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-assets.php');
const hero = read('website/wp-content/themes/bitmomo-child-v3/template-parts/home-hero.php');
const design = read('website/wp-content/themes/bitmomo-child-v3/assets/css/design-system.css');
const frontendJs = read('website/wp-content/themes/bitmomo-child-v3/assets/js/bitmomo-frontend.js');
const proMain = read('website/wp-content/plugins/bitmomo-pro/bitmomo-pro.php');
const account = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-account.php');
const whitelistJs = read('website/wp-content/plugins/bitmomo-pro/assets/js/bitmomo-pro-whitelist.js');
const runtime = read('config/production-runtime.json');

check('Canonical design-system layer exists and is runtime-required',
  fs.existsSync(path.join(theme, 'assets/css/design-system.css')) &&
  assets.includes('assets/css/design-system.css') &&
  assets.includes("'bitmomo-design-system'") &&
  runtime.includes('"assets/css/design-system.css"')
);

const customCss = path.join(theme, 'custom.css');
let customGitSha = '';
try { customGitSha = execFileSync('git', ['hash-object', customCss], { encoding: 'utf8' }).trim(); } catch {}
check('Legacy custom.css is byte-frozen rather than merely size-capped', customGitSha === '7d27f9a30071f9cbc4c1d8fce7d0cd04c35be176');

const themePhp = walk(theme).filter((file) => file.endsWith('.php'));
const inlineStyleOwners = themePhp.filter((file) => /<style\b/i.test(fs.readFileSync(file, 'utf8')));
check('Theme templates contain no ad-hoc inline <style> ownership', inlineStyleOwners.length === 0);
check('Homepage hero has no private breakpoint/style island', !/<style\b/i.test(hero) && !hero.includes('Decision View'));

check('Every route has one keyboard skip target contract',
  /class="bm-skip-link"[^>]*href="#primary"/.test(header) && /<main id="primary"/.test(frontPage)
);
check('Foundation owns reduced-motion and mobile touch comfort',
  design.includes('prefers-reduced-motion: reduce') && design.includes('44px !important') && design.includes('.bm-skip-link')
);
check('Mobile nav traps keyboard focus while open and still closes on Escape',
  frontendJs.includes("event.key !== 'Tab'") && frontendJs.includes('menuFocusable()') && frontendJs.includes("event.key === 'Escape'")
);

check('Research nav active state consumes canonical classification, never generic category membership',
  header.includes('bitmomo_post_research_classification') &&
  header.includes("array( 'market', 'ai-systems' )") &&
  !header.includes("is_single() && has_category( 'riset' )")
);
check('Signed-in navigation can identify account state instead of always saying Masuk',
  header.includes("is_user_logged_in() ? __( 'Akun'") && header.includes('$bm_account_label')
);

check('Posts index is explicitly neutral and cannot call itself institutional Research',
  home.includes('Arsip Publikasi') && home.includes('intentionally noindex') && !home.includes('BITMOMO RESEARCH')
);
check('One public noindex predicate owns all utility/archive side doors',
  functions.includes('bitmomo_should_noindex_public_view') &&
  functions.includes('bitmomo_is_pro_account_page() || is_search()') &&
  functions.includes("is_home() && !is_front_page()") &&
  functions.includes("is_archive() && !is_category('riset')") &&
  functions.includes("rank_math/frontend/robots")
);

check('Pro runtime detaches its legacy duplicate SEO owner',
  proMain.includes("remove_filter( 'rank_math/frontend/description'") &&
  proMain.includes("remove_action( 'wp_head'") &&
  proMain.includes("BITMOMO_PRO_VERSION', '0.12.7'")
);
check('Theme runtime version changed with public contract', functions.includes("define('BM_VERSION', '4.6')"));

check('Account flow has no pre-checkout dead end and renders human dates',
  account.includes("home_url( '/pro/#bm-pro-whitelist' )") &&
  account.includes('wp_lostpassword_url') &&
  account.includes('wp_date(') &&
  !account.includes('Aktivasi Bitmomo Pro saat ini dilakukan secara manual. Hubungi tim Bitmomo')
);

check('Whitelist exposes accessible async state and field-directed errors',
  whitelistJs.includes("form.setAttribute('aria-busy', 'true')") &&
  whitelistJs.includes("setAttribute('aria-invalid', 'true')") &&
  whitelistJs.includes("setAttribute('role', 'alert')") &&
  whitelistJs.includes('successTitle.focus()') &&
  whitelistJs.includes("waDoneEl.setAttribute('role', 'status')")
);
check('Whitelist browser acquisition context strips query strings from ordinary flow',
  whitelistJs.includes('safeUrlWithoutQuery(document.referrer)') &&
  whitelistJs.includes('window.location.origin + window.location.pathname')
);

check('No speculative third-party preconnect remains without an owned dependency',
  !assets.includes('fonts.gstatic.com') && !assets.includes('cdn.bitmomo.id')
);
check('Heavy static logo fallback is gone from code and runtime tree',
  !helpers.includes('bitmomo-logo.png') && !fs.existsSync(path.join(theme, 'assets/images/bitmomo-logo.png'))
);

const oversizedImages = walk(path.join(theme, 'assets'))
  .filter((file) => /\.(?:png|jpe?g|webp|gif)$/i.test(file))
  .filter((file) => fs.statSync(file).size > 300 * 1024)
  .map((file) => `${path.relative(root, file)}=${fs.statSync(file).size}`);
check('Theme ships no >300KB static image payloads', oversizedImages.length === 0);
if (oversizedImages.length) console.error(oversizedImages.join('\n'));

if (failures.length) {
  console.error(`Terminal-grade public contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('PASS terminal-grade public contract.');
