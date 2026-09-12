import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const theme = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const failures = [];

function read(relative) {
  return fs.readFileSync(path.join(theme, relative), 'utf8');
}
function check(label, condition) {
  if (!condition) failures.push(label);
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
}
function occurrences(source, pattern) {
  return (source.match(pattern) || []).length;
}

const header = read('header.php');
const footer = read('footer.php');
const helpers = read('inc/template-functions.php');
const frontend = read('inc/trait-bitmomo-frontend.php');
const content = read('inc/trait-bitmomo-content.php');
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
  'Primary navigation keeps the concise launch IA',
  /BTC Intelligence/.test(header) && /Riset/.test(header) && /Tentang/.test(header) && /Masuk/.test(header) && /BITMOMO PRO/.test(header)
);
check(
  'Primary navigation exposes non-color active-page semantics',
  header.includes('aria-current') && header.includes('page') && navCss.includes('a[aria-current="page"]')
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
  'Newsletter has exactly one permanent footer surface and no standalone newsletter template',
  /id="newsletter"/.test(footer) &&
    /mailpoet_form/.test(footer) &&
    !/template-parts\/newsletter/.test(frontPage) &&
    !fs.existsSync(path.join(theme, 'template-parts/newsletter.php'))
);
check(
  'Legacy newsletter modal is structurally disabled rather than page-by-page suppressed',
  /public function render_mailpoet_modal\(\)[\s\S]*?return;/.test(frontend) &&
    !/bm-subscribe-modal|bm-subscribe-dialog/.test(frontend + js)
);
check(
  'Legacy subscribe routes and menu links resolve to the footer newsletter anchor',
  content.includes("home_url('/#newsletter')") &&
    /strcasecmp\(\$path\s*,\s*'subscribe'\)\s*===\s*0/.test(content) &&
    /str_replace\(\s*'js-open-subscribe'\s*,\s*''/.test(content)
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
  'Navigation/footer stylesheet is loaded after the public surface stylesheet',
  header.indexOf('public-surfaces.css') > -1 &&
    header.indexOf('navigation-footer.css') > header.indexOf('public-surfaces.css')
);

if (failures.length) {
  console.error(`Navigation/footer contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('PASS navigation/footer contract.');
