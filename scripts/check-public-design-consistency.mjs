import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const theme = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const pro = path.join(root, 'website/wp-content/plugins/bitmomo-pro');
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

check(
  'One canonical design foundation defines brand, typography, geometry and interaction tokens',
  /--bm-bg:\s*#0c1c2a/.test(design) &&
  /--bm-text:\s*#edf4f6/.test(design) &&
  /--bm-teal:\s*#26d0c6/.test(design) &&
  /--bm-action:\s*#f4ad32/.test(design) &&
  /--bm-action-hover:\s*#ffc15a/.test(design) &&
  /--bm-font-sans:/.test(design) &&
  /--bm-shell-width:\s*1180px/.test(design) &&
  /--bm-reading-width:\s*720px/.test(design) &&
  /--bm-article-wide:\s*920px/.test(design) &&
  /--bm-touch-target-mobile:\s*44px/.test(design)
);

check(
  'Compatibility variables are aliases to canonical values rather than competing literals',
  /--bm-accent:\s*var\(--bm-teal\)/.test(design) &&
  /--bm-muted:\s*var\(--bm-text-muted\)/.test(design) &&
  /--bm-public-max:\s*var\(--bm-shell-width\)/.test(design) &&
  /--bm-reading-max:\s*var\(--bm-reading-wide\)/.test(design) &&
  /--cta-hover:\s*var\(--bm-action-hover\)/.test(design)
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
  'About uses canonical brand semantics rather than legacy palette variables',
  /var\(--bm-teal\)/.test(about) &&
  /var\(--bm-action\)/.test(about) &&
  /var\(--bm-action-hover\)/.test(about) &&
  /var\(--bm-font-sans\)/.test(about) &&
  !/var\(--(?:ink|muted|teal|stroke|cta)\)/.test(about)
);

check(
  'Product bridge maps BTC Intelligence and Pro semantic variables to the canonical foundation',
  /\.bm-bi[\s\S]*?--bmi-text:\s*var\(--bm-text\)/.test(readability) &&
  /--bmi-teal:\s*var\(--bm-teal\)/.test(readability) &&
  /\.bm-pro-sales[\s\S]*?--bms-text:\s*var\(--bm-text\)/.test(readability) &&
  /--bms-orange:\s*var\(--bm-action\)/.test(readability) &&
  /--bms-orange-dark:\s*var\(--bm-action-hover\)/.test(readability)
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

check(
  'Research remains a named surface and inherits the shared foundation without its own root token block',
  !/:root\s*\{/.test(research) &&
  /\.bm-research-hub/.test(research) &&
  (/var\(--bm-/.test(research) || /var\(--(?:bg|ink|muted|teal|stroke)\)/.test(research))
);

if (failures.length) {
  console.error(`Sitewide design consistency contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('PASS sitewide institutional design consistency contract.');
