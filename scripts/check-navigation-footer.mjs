import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const theme = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const failures = [];

function read(relative) { return fs.readFileSync(path.join(theme, relative), 'utf8'); }
function check(label, condition) {
  if (!condition) failures.push(label);
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
}
function occurrences(source, pattern) { return (source.match(pattern) || []).length; }

const header = read('header.php');
const footer = read('footer.php');
const helpers = read('inc/template-functions.php');
const frontend = read('inc/trait-bitmomo-frontend.php');
const content = read('inc/trait-bitmomo-content.php');
const assets = read('inc/trait-bitmomo-assets.php');
const js = read('assets/js/bitmomo-frontend.js');
const navCss = read('assets/css/navigation-footer.css');
const frontPage = read('front-page.php');

check(
  'Header has one canonical Pro destination instead of duplicate Pro navigation',
  occurrences(header, /home_url\(\s*'\/pro\/'\s*\)/g) === 1 &&
    occurrences(header, />BITMOMO PRO</g) === 1 &&
    !/>Bitmomo Pro</.test(header)
);
check(
  'Primary navigation keeps concise product/research/company IA',
  /BTC Intelligence/.test(header) && /Research/.test(header) && /Tentang/.test(header) && /Masuk/.test(header) && /BITMOMO PRO/.test(header)
);
check(
  'Primary navigation exposes non-color active-page semantics',
  /aria-current=\\"page\\"/.test(header) && /a\[aria-current="page"\]/.test(navCss)
);
check(
  'Mobile menu removes hidden links from keyboard navigation',
  /nav\.setAttribute\('inert', ''\)/.test(js) &&
    /nav\.setAttribute\('aria-hidden', 'true'\)/.test(js) &&
    /nav\.removeAttribute\('inert'\)/.test(js)
);
check(
  'Mobile menu closes by link, outside click, Escape and desktop resize',
  /navLink/.test(js) && /!event\.target\.closest\('#bm-nav'\)/.test(js) && /event\.key !== 'Escape'/.test(js) && /window\.addEventListener\('resize'/.test(js)
);
check(
  'Newsletter has exactly one permanent footer surface and no homepage standalone section',
  /id="newsletter"/.test(footer) && /mailpoet_form/.test(footer) && !/template-parts\/newsletter/.test(frontPage)
);
check(
  'Legacy newsletter modal is structurally disabled',
  /public function render_mailpoet_modal\(\)[\s\S]*?return;/.test(frontend) &&
    !/bm-subscribe-modal|bm-subscribe-dialog/.test(frontend + js)
);
check(
  'Legacy subscribe routes and menu links resolve to the footer newsletter anchor',
  /home_url\('\/#newsletter'\)/.test(content) && !/js-open-subscribe/.test(content)
);
check(
  'Header styling is owned by one responsive stylesheet',
  /\.bm-header/.test(navCss) && /\.bm-hamburger/.test(navCss) && /@media \(max-width: 900px\)/.test(navCss)
);
check(
  'Footer newsletter is intentionally compact and responsive',
  /\.bm-footer-connect/.test(navCss) && /\.bm-footer-newsletter/.test(navCss) && /@media \(max-width: 640px\)/.test(navCss)
);
check(
  'Footer exposes canonical Telegram, YouTube and X destinations',
  /https:\/\/t\.me\/bitmomodaily/.test(helpers) &&
    /https:\/\/www\.youtube\.com\/@bitmomoid/.test(helpers) &&
    /https:\/\/x\.com\/bitmomoid/.test(helpers) &&
    /data-social=/.test(footer)
);
check(
  'Stylesheet order is dependency-managed outside header markup',
  !/<link\s+rel=["']stylesheet/i.test(header) &&
    /'bitmomo-foundation'/.test(assets) &&
    /'bitmomo-navigation-footer'/.test(assets) &&
    /\['bitmomo-foundation'\]/.test(assets) &&
    /\['bitmomo-foundation', 'bitmomo-navigation-footer'\]/.test(assets)
);

if (failures.length) {
  console.error(`Navigation/footer contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}
console.log('PASS navigation/footer contract.');
