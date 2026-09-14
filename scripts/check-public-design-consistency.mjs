import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const theme = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const pro = path.join(root, 'website/wp-content/plugins/bitmomo-pro');
const btc = path.join(root, 'website/wp-content/plugins/bitmomo-btc-intelligence');
const failures = [];

function read(base, relative) {
  return fs.readFileSync(path.join(base, relative), 'utf8');
}
function check(label, condition) {
  if (!condition) failures.push(label);
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
}

const design = read(theme, 'assets/css/design-system.css');
const publicCss = read(theme, 'assets/css/public-surfaces.css');
const readability = read(theme, 'assets/css/public-readability.css');
const nav = read(theme, 'assets/css/navigation-footer.css');
const article = read(theme, 'assets/css/article-reading.css');
const about = read(theme, 'assets/css/about.css');
const home = read(theme, 'assets/css/home.css');
const conversion = read(theme, 'assets/css/home-conversion.css');
const research = read(theme, 'assets/css/research.css');
const assets = read(theme, 'inc/trait-bitmomo-assets.php');
const account = read(pro, 'assets/css/bitmomo-pro-account.css');
const whitelist = read(pro, 'assets/css/bitmomo-pro-whitelist.css');
const help = read(pro, 'assets/css/bitmomo-pro-help.css');
const btcCss = read(btc, 'assets/css/bitmomo-btc-intelligence.css');

check(
  'One canonical design foundation defines graphite, steel-blue, commercial amber and shared geometry',
  /--bm-bg:\s*#0b1118/.test(design) &&
  /--bm-text:\s*#f1f4f6/.test(design) &&
  /--bm-accent:\s*#6c8ebf/.test(design) &&
  /--bm-accent-hover:\s*#88a6cf/.test(design) &&
  /--bm-action:\s*#f4ad32/.test(design) &&
  /--bm-action-hover:\s*#ffc15a/.test(design) &&
  /--bm-font-sans:/.test(design) &&
  /--bm-shell-width:\s*1180px/.test(design) &&
  /--bm-reading-width:\s*720px/.test(design) &&
  /--bm-article-wide:\s*920px/.test(design) &&
  /--bm-touch-target-mobile:\s*44px/.test(design)
);

check(
  'Legacy compatibility aliases resolve only to the canonical design foundation',
  /--bg:\s*var\(--bm-bg\)/.test(design) &&
  /--bg-2:\s*var\(--bm-bg-elevated\)/.test(design) &&
  /--ink:\s*var\(--bm-text\)/.test(design) &&
  /--muted:\s*var\(--bm-text-muted\)/.test(design) &&
  /--teal:\s*var\(--bm-accent\)/.test(design) &&
  /--stroke:\s*var\(--bm-border\)/.test(design) &&
  /--cta:\s*var\(--bm-action\)/.test(design) &&
  /--cta-hover:\s*var\(--bm-action-hover\)/.test(design) &&
  /--bm-teal:\s*var\(--bm-accent\)/.test(design) &&
  /--bm-teal-hover:\s*var\(--bm-accent-hover\)/.test(design) &&
  /--bm-muted:\s*var\(--bm-text-muted\)/.test(design) &&
  /--bm-public-max:\s*var\(--bm-shell-width\)/.test(design) &&
  /--bm-reading-max:\s*var\(--bm-reading-wide\)/.test(design)
);

check(
  'Generic public surfaces no longer redefine the design foundation',
  !/:root\s*\{/.test(publicCss) &&
  !/--bm-(?:bg|text|accent|action|public-max|reading-max)\s*:/.test(publicCss)
);

const forbiddenGenericOwners = [
  '.bm-footer', '.bm-header', '.bm-wl-home', '.bm-pro-sales', '.bm-help', '.bm-article-body', '.bm-article-title'
];
check(
  'Generic public CSS cannot own named chrome, homepage, product, help or article surfaces',
  forbiddenGenericOwners.every((selector) => !publicCss.includes(selector))
);

check(
  'The canonical public grid uses the same shell and responsive gutters as chrome',
  /\.bm-container[\s\S]*?var\(--bm-shell-width\)/.test(publicCss) &&
  /var\(--bm-shell-gutter\)/.test(publicCss) &&
  /var\(--bm-shell-gutter-tablet\)/.test(publicCss) &&
  /var\(--bm-shell-gutter-mobile\)/.test(publicCss) &&
  /var\(--bm-shell-width\)/.test(nav)
);

check(
  'Generic public CSS does not globally hide horizontal overflow',
  !/html\s*,\s*\n?body[\s\S]{0,120}overflow-x:\s*hidden/.test(publicCss)
);

check(
  'Generic public visual semantics contain no historical cyan/teal literals',
  !/rgba\(\s*38\s*,\s*208\s*,\s*198/i.test(publicCss + about) &&
  !/rgba\(\s*109\s*,\s*224\s*,\s*217/i.test(publicCss + about) &&
  !/#26d0c6|#6de0d9/i.test(publicCss + about)
);

check(
  'Public typography is deterministic and does not depend on a locally installed Inter font',
  /font-family:\s*var\(--bm-font-sans\)/.test(design) &&
  /font-family:\s*var\(--bm-font-sans\)/.test(article) &&
  !/font-family\s*:[^;]*\bInter\b/i.test(article + publicCss + about + nav)
);

check(
  'Article reading geometry consumes canonical widths instead of duplicating pixel constants',
  /var\(--bm-reading-width\)/.test(article) &&
  /var\(--bm-article-wide\)/.test(article) &&
  !/--bm-article-reading\s*:\s*720px/.test(article)
);

check(
  'About consumes canonical product accent and reserves amber for Founding commercial action',
  /var\(--bm-accent\)/.test(about) &&
  /rgba\(var\(--bm-accent-rgb\)/.test(about) &&
  /\.bm-about-button--primary[\s\S]*?background:\s*var\(--bm-action\)/.test(about) &&
  /var\(--bm-action-hover\)/.test(about) &&
  /var\(--bm-font-sans\)/.test(about) &&
  !/var\(--(?:ink|muted|teal|stroke|cta)\)/.test(about)
);

check(
  'Generic navigation/action buttons use the product accent rather than commercial amber',
  /\.bm-public-button[\s\S]*?background:\s*var\(--bm-accent\)/.test(publicCss) &&
  !/\.bm-public-button[\s\S]{0,500}background:\s*var\(--bm-action\)/.test(publicCss)
);

check(
  'Product bridge maps BTC Intelligence and Pro semantic variables to the canonical foundation',
  /\.bm-bi[\s\S]*?--bmi-text:\s*var\(--bm-text\)/.test(readability) &&
  /--bmi-teal:\s*var\(--bm-accent\)/.test(readability) &&
  /\.bm-pro-sales[\s\S]*?--bms-text:\s*var\(--bm-text\)/.test(readability) &&
  /--bms-teal:\s*var\(--bm-accent\)/.test(readability) &&
  /--bms-orange:\s*var\(--bm-action\)/.test(readability) &&
  /--bms-orange-dark:\s*var\(--bm-action-hover\)/.test(readability)
);

check(
  'BTC Intelligence owner consumes the institutional palette and cannot reintroduce neon crypto-dashboard literals',
  /--bmi-accent:var\(--bm-accent,#6c8ebf\)/.test(btcCss) &&
  /--bmi-accent-rgb:var\(--bm-accent-rgb,108,142,191\)/.test(btcCss) &&
  /--bmi-action:var\(--bm-action,#f4ad32\)/.test(btcCss) &&
  /--bmi-action-hover:var\(--bm-action-hover,#ffc15a\)/.test(btcCss) &&
  /--bmi-bull:var\(--bm-positive,#63a98a\)/.test(btcCss) &&
  /--bmi-bear:var\(--bm-negative,#c97b77\)/.test(btcCss) &&
  /max-width:var\(--bm-product-width,1080px\)/.test(btcCss) &&
  /var\(--bm-touch-target-mobile,44px\)/.test(btcCss) &&
  !/#2dd4bf|45\s*,\s*212\s*,\s*191|--bmi-teal|#34d399|#f87171|#e69a18/i.test(btcCss)
);

check(
  'Whitelist and account surfaces use orange only for primary commercial action',
  /\.bm-wl__submit[\s\S]*?background:\s*var\(--bm-action\)/.test(whitelist) &&
  /\.bm-pro-account__cta[\s\S]*?background:\s*var\(--bm-action\)/.test(account) &&
  !/#1fae7a|#24c98d/i.test(account)
);

check(
  'Help, account and whitelist consume canonical font, focus/touch and text semantics',
  /font-family:\s*var\(--bm-font-sans\)/.test(help) &&
  /var\(--bm-touch-target-mobile\)/.test(help) &&
  /font-family:\s*var\(--bm-font-sans\)/.test(account) &&
  /var\(--bm-touch-target-mobile\)/.test(account) &&
  /var\(--bm-focus-ring\)/.test(whitelist) &&
  /var\(--bm-touch-target-mobile\)/.test(whitelist)
);

check(
  'Homepage remains isolated to home owners and does not fall back to the retired opportunity layer',
  /home\.css/.test(assets) && /home-conversion\.css/.test(assets) &&
  !/home-opportunity\.css/.test(assets) &&
  home.includes('.bm-home') && conversion.includes('.bm-wl-home')
);

check(
  'Named CSS layers load in deterministic order from foundation to surface owner',
  assets.indexOf('design-system.css') > -1 &&
  assets.indexOf('public-readability.css') > assets.indexOf('design-system.css') &&
  assets.indexOf('public-surfaces.css') > assets.indexOf('public-readability.css') &&
  assets.indexOf('navigation-footer.css') > assets.indexOf('public-surfaces.css') &&
  assets.indexOf('article-reading.css') > assets.indexOf('navigation-footer.css') &&
  assets.indexOf('home.css') > assets.indexOf('navigation-footer.css')
);

const researchUsesCanonicalOrMappedTokens =
  /var\(--bm-/.test(research) ||
  /var\(--(?:bg|bg-2|ink|muted|teal|stroke|cta|cta-hover)\)/.test(research);
check(
  'Research remains a named surface and inherits the shared foundation without its own root token block',
  !/:root\s*\{/.test(research) &&
  /\.bm-research-hub/.test(research) &&
  researchUsesCanonicalOrMappedTokens
);

if (failures.length) {
  console.error(`Sitewide design consistency contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('PASS sitewide institutional design consistency contract.');
