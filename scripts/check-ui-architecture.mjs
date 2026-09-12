import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const themeDir = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const btcPluginDir = path.join(root, 'website/wp-content/plugins/bitmomo-btc-intelligence');
const LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES = 50300;
const WCAG_AA_NORMAL_TEXT = 4.5;

function fail(message) {
  console.error(`::error title=UI architecture contract::${message}`);
  process.exitCode = 1;
}

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });
}

function hexToRgb(hex) {
  const value = hex.replace('#', '');
  return [0, 2, 4].map((offset) => Number.parseInt(value.slice(offset, offset + 2), 16));
}
function relativeLuminance(hex) {
  const channels = hexToRgb(hex).map((channel) => {
    const value = channel / 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}
function contrastRatio(foreground, background) {
  const a = relativeLuminance(foreground);
  const b = relativeLuminance(background);
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}
function alphaBlend(foreground, background, opacity) {
  const fg = hexToRgb(foreground);
  const bg = hexToRgb(background);
  const blended = fg.map((channel, index) => Math.round(opacity * channel + (1 - opacity) * bg[index]));
  return `#${blended.map((channel) => channel.toString(16).padStart(2, '0')).join('')}`;
}
function requireContrast(label, foreground, background, minimum = WCAG_AA_NORMAL_TEXT) {
  const ratio = contrastRatio(foreground, background);
  if (ratio < minimum) fail(`${label} contrast ${ratio.toFixed(2)}:1 is below ${minimum}:1 (${foreground} on ${background})`);
  return ratio;
}

const requiredFiles = [
  'functions.php', 'custom.css', 'front-page.php', 'page.php', 'inc/template-functions.php',
  'assets/css/home-opportunity.css', 'assets/css/public-readability.css', 'assets/css/public-surfaces.css',
  'assets/js/bitmomo-frontend.js',
];
for (const relative of requiredFiles) {
  if (!fs.existsSync(path.join(themeDir, relative))) fail(`required UI source is missing: ${relative}`);
}

const phpFiles = walk(themeDir).filter((file) => file.endsWith('.php'));
for (const file of phpFiles) {
  const source = fs.readFileSync(file, 'utf8');
  if (source.includes('wp_add_inline_style(')) fail(`visual CSS must live in CSS assets, not wp_add_inline_style(): ${path.relative(root, file)}`);
}

const functionsPhp = fs.readFileSync(path.join(themeDir, 'functions.php'), 'utf8');
for (const marker of ['bitmomo_public_snapshot_contract','bitmomo-snapshot-contract','bitmomo_prevent_homepage_snapshot_cache','bitmomo_snapshot_contract']) {
  if (!functionsPhp.includes(marker)) fail(`P0 public snapshot guard is missing marker: ${marker}`);
}

const frontendTrait = fs.readFileSync(path.join(themeDir, 'inc/trait-bitmomo-frontend.php'), 'utf8');
if (!frontendTrait.includes("is_front_page() || is_page(['pro', 'btc-intelligence'])")) {
  fail('legacy newsletter modal must stay suppressed on homepage, Pro and BTC Intelligence launch surfaces');
}

const frontPage = fs.readFileSync(path.join(themeDir, 'front-page.php'), 'utf8');
if (frontPage.includes("get_template_part( 'template-parts/btc-intelligence', 'card' )")) {
  fail('homepage must not render a second BTC Intelligence card below the hero');
}
if (frontPage.includes("get_template_part( 'template-parts/newsletter' )")) {
  fail('homepage whitelist is the launch conversion path; standalone newsletter must not compete with it');
}

const frontendJs = fs.readFileSync(path.join(themeDir, 'assets/js/bitmomo-frontend.js'), 'utf8');
for (const legacyMarker of ['bmreg-trend', 'bmreg-price-line', 'Progressive-enhancement only']) {
  if (frontendJs.includes(legacyMarker)) fail(`legacy duplicate regime chart renderer returned: ${legacyMarker}`);
}

const pageTemplate = fs.readFileSync(path.join(themeDir, 'page.php'), 'utf8');
const templateFunctions = fs.readFileSync(path.join(themeDir, 'inc/template-functions.php'), 'utf8');
if (!templateFunctions.includes('bitmomo_normalize_public_page_body_headings') || !templateFunctions.includes("array('<h2$1>', '</h2>')")) {
  fail('ordinary-page heading normalizer is missing; legacy DB H1 would duplicate the template-owned H1');
}
if (!pageTemplate.includes("apply_filters( 'the_content', $bm_content )") || !pageTemplate.includes('bitmomo_normalize_public_page_body_headings( $bm_rendered_content )')) {
  fail('ordinary page.php must render filtered content through the canonical body-heading normalizer');
}
if (!pageTemplate.includes('if ( $bm_is_product_surface )') || !pageTemplate.includes('<?php the_content(); ?>')) {
  fail('product shortcode pages must retain renderer-owned heading/content pass-through');
}

const opportunityCss = fs.readFileSync(path.join(themeDir, 'assets/css/home-opportunity.css'), 'utf8');
for (const marker of ['.bm-hero-actions', '.bm-direction-summary', '.bm-direction-card .bm-state-chart']) {
  if (!opportunityCss.includes(marker)) fail(`homepage intelligence UI lost a required presentation primitive: ${marker}`);
}
for (const marker of [
  'grid-template-columns:repeat(var(--bm-history-count),minmax(0,1fr))',
  '.bm-direction-card .bm-state-chart .bm-direction-bar.is-bullish span{background:#35cdbb}',
  '.bm-direction-card .bm-state-chart .bm-direction-bar.is-neutral span{background:#8192aa}',
  '.bm-direction-card .bm-state-chart .bm-direction-bar.is-bearish span{background:#ff7b6d}',
]) {
  if (!opportunityCss.includes(marker)) fail(`homepage context chart lost its semantic/readability contract: ${marker}`);
}
if (opportunityCss.includes('color:#71839f')) fail('homepage intelligence reintroduced the known sub-AA #71839f micro-text color');

const readabilityCss = fs.readFileSync(path.join(themeDir, 'assets/css/public-readability.css'), 'utf8');
for (const marker of ['--bm-text-subtle-readable', '.bm-bi', '--bmi-text-muted', '.bm-pro-sales', '--bms-text-muted', '.bm-wl__submit']) {
  if (!readabilityCss.includes(marker)) fail(`public readability contract is missing marker: ${marker}`);
}

const publicSurfacesCss = fs.readFileSync(path.join(themeDir, 'assets/css/public-surfaces.css'), 'utf8');
if (!/\.bm-wl-unified__intro,\s*\.bm-wl-unified__form\s*\{[^}]*min-width:\s*0;[^}]*\}/s.test(publicSurfacesCss)) {
  fail('unified homepage whitelist grid children must be shrinkable to prevent narrow-viewport overflow');
}
if (!/\.bm-wl-unified__form > \.bm-pro-sales\.bm-wl\s*\{[^}]*width:\s*100%;[^}]*max-width:\s*100%\s*!important;[^}]*padding-inline:\s*0;[^}]*\}/s.test(publicSurfacesCss)) {
  fail('homepage whitelist wrapper containment is missing; shared Pro wrapper sizing can escape the unified grid');
}
if (/\.bm-footer-(?:group strong|bottom)[^{]*\{[^}]*color:\s*#71839f/s.test(publicSurfacesCss)) {
  fail('footer reintroduced the known sub-AA #71839f text color');
}

const btcPage = fs.readFileSync(path.join(btcPluginDir, 'includes/class-bitmomo-btc-intelligence-page.php'), 'utf8');
const renderPageMatch = btcPage.match(/public function render_page[\s\S]*?return ob_get_clean\(\);/);
const renderPage = renderPageMatch ? renderPageMatch[0] : '';
for (const requiredCall of ['render_hero()', 'render_current_snapshot()', 'render_historical_regime()', 'render_track_record()', 'render_methodology()', 'render_pro_cta()']) {
  if (!renderPage.includes(requiredCall)) fail(`BTC Intelligence simplified hierarchy lost ${requiredCall}`);
}
for (const removedCall of ['render_how_it_works()', 'render_five_axes()', 'render_how_to_read()', 'render_confidence_evaluation()', 'render_expected_range_performance()', 'render_regime_performance()', 'render_data_quality()']) {
  if (renderPage.includes(removedCall)) fail(`BTC Intelligence explanation wall returned via ${removedCall}`);
}
if (!btcPage.includes('<summary><?php esc_html_e( \'Evaluasi lainnya\'')) fail('secondary BTC proof must remain behind progressive disclosure');
if (!btcPage.includes('<summary><?php esc_html_e( \'Cara kerja & metodologi\'')) fail('methodology must remain behind progressive disclosure');

const btcCss = fs.readFileSync(path.join(btcPluginDir, 'assets/css/bitmomo-btc-intelligence.css'), 'utf8');
if (!btcCss.includes('--bmi-text-subtle:var(--bm-text-subtle-readable,#8294ae)')) {
  fail('BTC Intelligence must inherit the shared readable micro-text token');
}
for (const legacyLowContrast of ['color:#667993', 'color:#71839f', 'color:#6f829c']) {
  if (btcCss.includes(legacyLowContrast)) fail(`BTC Intelligence reintroduced known sub-AA micro-text: ${legacyLowContrast}`);
}

const homepageSubtle = '#8294ae';
requireContrast('homepage subtle label / card', homepageSubtle, '#0f1d2f');
requireContrast('homepage subtle label / page', homepageSubtle, '#0c1c2a');
requireContrast('homepage secondary micro text / card', '#8799b0', '#0f1d2f');
requireContrast('footer subtle text / footer background', '#8294ae', '#0f2233');
requireContrast('BTC subtle text / snapshot card', '#8294ae', '#111d2f');
const productMutedSource = '#a8b7ca';
requireContrast('BTC micro text after 0.75 opacity', alphaBlend(productMutedSource, '#0c1c2a', 0.75), '#0c1c2a');
requireContrast('Pro price terms after 0.72 opacity', alphaBlend(productMutedSource, '#101a2c', 0.72), '#101a2c');
requireContrast('commercial CTA text', '#0b1620', '#f4ad32');

const customCssPath = path.join(themeDir, 'custom.css');
const customCssBytes = fs.statSync(customCssPath).size;
if (customCssBytes > LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES) {
  fail(`custom.css grew beyond the frozen legacy debt ceiling (${customCssBytes} > ${LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES} bytes)`);
}

if (!process.exitCode) console.log(`PASS UI architecture contract; custom.css=${customCssBytes}/${LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES} bytes`);