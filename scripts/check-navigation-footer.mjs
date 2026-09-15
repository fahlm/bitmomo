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
const assets = read('inc/trait-bitmomo-assets.php');
const content = read('inc/trait-bitmomo-content.php');
const js = read('assets/js/bitmomo-frontend.js');
const navCss = read('assets/css/navigation-footer.css');
const designCss = read('assets/css/design-system.css');
const frontPage = read('front-page.php');

check(
  'Header has one canonical Pro destination instead of duplicate Pro navigation',
  occurrences(header, /home_url\(\s*'\/pro\/'\s*\)/g) === 1 && occurrences(header, />BITMOMO PRO</g) === 1 && !/>Bitmomo Pro</.test(header)
);
check(
  'Primary navigation keeps the concise launch IA and resolves account state',
  /BTC Intelligence/.test(header) && /Riset/.test(header) && /Tentang/.test(header) && /Masuk/.test(header) && /Akun/.test(header) && /BITMOMO PRO/.test(header)
);
check(
  'Primary navigation exposes non-color active-page semantics',
  header.includes('aria-current') && header.includes('page') && navCss.includes('a:not(.bm-nav-pro)[aria-current="page"]::after')
);
check(
  'Research active state uses canonical classification instead of historical category membership',
  /bitmomo_post_research_classification/.test(header) && /'market', 'ai-systems'/.test(header) && !/is_single\(\)\s*&&\s*has_category/.test(header)
);
check(
  'Account navigation URL and active state share the same resolved page source',
  /get_page_by_path\(\s*'pro\/account'/.test(header) &&
  /\$bm_account_page \? is_page\( \(int\) \$bm_account_page->ID \)/.test(header) &&
  /\$bm_is_account \? ' aria-current="page"'/.test(header)
);
check(
  'Every public route has a keyboard skip contract',
  /class="bm-skip-link"/.test(header) && /href="#primary"/.test(header) && /id="primary"/.test(frontPage) && /\.bm-skip-link/.test(designCss)
);
check(
  'Mobile menu removes hidden links from keyboard navigation',
  /nav\.setAttribute\('inert', ''\)/.test(js) && /nav\.setAttribute\('aria-hidden', 'true'\)/.test(js) && /nav\.removeAttribute\('inert'\)/.test(js)
);
check(
  'Mobile menu closes predictably and contains keyboard focus while open',
  /navLink/.test(js) && /!event\.target\.closest\('#bm-nav'\)/.test(js) && /event\.key === 'Escape'/.test(js) &&
  /event\.key !== 'Tab'/.test(js) && /menuFocusable\(\)/.test(js) && /window\.addEventListener\('resize'/.test(js)
);
check(
  'Canonical shell owns header and footer geometry',
  /--bm-shell-width:\s*1180px/.test(designCss) && /--bm-header-height:\s*64px/.test(designCss) &&
  /--bm-header-height-mobile:\s*60px/.test(designCss) && /var\(--bm-shell-width/.test(navCss) &&
  occurrences(navCss, /var\(--bm-shell-width/g) >= 3
);
check(
  'Mobile navigation is viewport-bounded instead of using a fragile fixed max-height',
  /100dvh/.test(navCss) && /overflow-y:\s*auto/.test(navCss) && !/max-height:\s*(?:320|390)px/.test(navCss)
);
check(
  'Shared touch tokens own navigation and footer controls',
  /--bm-touch-target:\s*40px/.test(designCss) && /--bm-touch-target-mobile:\s*44px/.test(designCss) &&
  /min-height:\s*48px/.test(navCss) && /var\(--bm-touch-target-mobile/.test(navCss)
);
check(
  'One shared commercial action token owns the Pro header action',
  /--bm-action:\s*#f4ad32/.test(designCss) && /background:\s*var\(--bm-action/.test(navCss) && /--bm-action-hover/.test(designCss)
);
check(
  'Exactly one compact footer newsletter is the free retention surface',
  occurrences(footer, /id="newsletter"/g) === 1 && /mailpoet_form/.test(footer) && /bm-footer-newsletter/.test(footer + navCss) &&
  !/template-parts\/newsletter/.test(frontPage) && !fs.existsSync(path.join(theme, 'template-parts/newsletter.php'))
);
check(
  'Newsletter backend identity is configurable and fails closed',
  /BITMOMO_NEWSLETTER_FORM_ID/.test(footer) && /bitmomo_newsletter_form_id/.test(footer) &&
  /shortcode_exists\(\s*'mailpoet_form'\s*\)/.test(footer) && /if \( \$bm_newsletter_available \)/.test(footer)
);
check(
  'Newsletter is visually secondary and isolated from the commercial action token',
  /\.bm-footer-newsletter\s*\{[\s\S]*?background:\s*var\(--bm-bg-elevated/.test(navCss) &&
  /\.bm-footer-newsletter\s*\{[\s\S]*?border:\s*1px solid var\(--bm-border/.test(navCss) &&
  !/\.bm-footer-newsletter[\s\S]{0,1800}background:\s*var\(--bm-action/.test(navCss)
);
check(
  'Legacy newsletter modal remains structurally disabled',
  /public function render_mailpoet_modal\(\)[\s\S]*?return;/.test(frontend) && !/bm-subscribe-modal|bm-subscribe-dialog/.test(frontend + js)
);
check(
  'Legacy subscribe routes and menu links resolve to the canonical footer newsletter',
  content.includes("home_url('/#newsletter')") && /strcasecmp\(\$path\s*,\s*'subscribe'\)\s*===\s*0/.test(content) &&
  /#subscribe/.test(content) && /#newsletter/.test(content) && /str_replace\(\s*'js-open-subscribe'\s*,\s*''/.test(content)
);
check(
  'Footer IA exposes clear product, research and company destinations without internal jargon',
  /PRODUK/.test(footer) && /RESEARCH/.test(footer) && /BITMOMO/.test(footer) &&
  /BTC Intelligence/.test(footer) && /Bitmomo Pro/.test(footer) && /Methodology & Track Record/.test(footer) &&
  /Market Research/.test(footer) && /AI Research/.test(footer) && /Research Standard/.test(footer) &&
  /Tentang Bitmomo/.test(footer) && /Help Center/.test(footer) && !/>Decision Ledger</.test(footer)
);
check(
  'Footer makes Bitmomo operating principles visible as a restrained trust rail',
  /bm-footer-principles/.test(footer + navCss) && /EVIDENCE FIRST/.test(footer) &&
  /TESTABLE RESEARCH/.test(footer) && /FAIL CLOSED/.test(footer) &&
  /grid-template-columns:\s*repeat\(3,\s*minmax\(0,\s*1fr\)\)/.test(navCss)
);
check(
  'Terms link is fail-closed until a canonical published WordPress page exists',
  /get_page_by_path\(\s*'syarat-layanan'/.test(footer) && /'publish' === \$bm_terms_page->post_status/.test(footer) &&
  /if \( \$bm_terms_url \)/.test(footer) && /Syarat Layanan/.test(footer)
);
check(
  'Footer brand reuses canonical identity and states the intelligence plus AI-research position',
  /bitmomo_render_brand\(\)/.test(footer) && /Market intelligence BTC dan riset AI/.test(footer) &&
  /rekam jejak keputusan/.test(footer) && /Bitmomo\.<\/p>/.test(footer) &&
  /bukan rekomendasi beli\/jual atau nasihat keuangan/.test(footer) && !/bloginfo\(\s*'name'\s*\)/.test(footer)
);
check(
  'Footer grid collapses deterministically from desktop to tablet, mobile and narrow mobile',
  /grid-template-columns:\s*minmax\(300px, 1\.45fr\) repeat\(3/.test(navCss) &&
  /@media \(max-width: 900px\)[\s\S]*?grid-template-columns:\s*repeat\(3/.test(navCss) &&
  /@media \(max-width: 640px\)[\s\S]*?grid-template-columns:\s*repeat\(2/.test(navCss) &&
  /@media \(max-width: 430px\)[\s\S]*?grid-template-columns:\s*1fr/.test(navCss)
);
check(
  'Footer controls meet desktop and mobile touch geometry',
  /\.bm-footer-group a[\s\S]*?var\(--bm-touch-target/.test(navCss) &&
  /\.bm-footer-social a[\s\S]*?var\(--bm-touch-target/.test(navCss) &&
  /\.bm-footer-legal a[\s\S]*?var\(--bm-touch-target/.test(navCss) &&
  /@media \(max-width: 640px\)[\s\S]*?var\(--bm-touch-target-mobile/.test(navCss)
);
check(
  'Footer social and legal navigation stay visually quiet rather than becoming pill walls',
  /\.bm-footer-social a,[\s\S]*?\.bm-footer-legal a[\s\S]*?border:\s*0;/.test(navCss) &&
  /background:\s*transparent;/.test(navCss)
);
check(
  'Public social destinations fail closed and never use hard-coded public fallbacks',
  /BITMOMO_TELEGRAM_URL/.test(helpers) && /BITMOMO_YOUTUBE_URL/.test(helpers) && /BITMOMO_X_URL/.test(helpers) &&
  /wp_http_validate_url/.test(helpers) && /array\('https'\)/.test(helpers) &&
  !/https:\/\/t\.me\//.test(helpers) && !/https:\/\/www\.youtube\.com\//.test(helpers) && !/https:\/\/x\.com\//.test(helpers) &&
  !/bm-footer-social__pending/.test(footer)
);
check(
  'Design foundation and navigation/footer layers have explicit dependency order',
  !header.includes('public-surfaces.css') && assets.indexOf('design-system.css') > -1 && assets.indexOf('public-surfaces.css') > assets.indexOf('design-system.css') && assets.indexOf('navigation-footer.css') > assets.indexOf('public-surfaces.css') && assets.includes("'bitmomo-navigation-footer'") && assets.includes("$public_surface_deps = ['bitmomo-navigation-footer'];")
);

if (failures.length) {
  console.error(`Navigation/footer contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('PASS navigation/footer institutional contract.');
