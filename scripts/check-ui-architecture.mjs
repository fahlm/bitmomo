import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const themeDir = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const btcPluginDir = path.join(root, 'website/wp-content/plugins/bitmomo-btc-intelligence');
const proPluginDir = path.join(root, 'website/wp-content/plugins/bitmomo-pro');
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
function read(base, relative) { return fs.readFileSync(path.join(base, relative), 'utf8'); }
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
function requireContrast(label, foreground, background, minimum = WCAG_AA_NORMAL_TEXT) {
  const ratio = contrastRatio(foreground, background);
  if (ratio < minimum) fail(`${label} contrast ${ratio.toFixed(2)}:1 is below ${minimum}:1 (${foreground} on ${background})`);
}

const requiredFiles = [
  'functions.php', 'custom.css', 'front-page.php', 'page.php', 'single.php', 'footer.php',
  'inc/template-functions.php', 'inc/trait-bitmomo-assets.php', 'inc/trait-bitmomo-frontend.php',
  'assets/css/design-system.css', 'assets/css/public-readability.css', 'assets/css/public-surfaces.css',
  'assets/css/navigation-footer.css', 'assets/css/home.css', 'assets/css/home-conversion.css',
  'assets/css/article-reading.css', 'assets/css/research.css', 'assets/css/about.css',
  'assets/js/bitmomo-frontend.js',
];
for (const relative of requiredFiles) {
  if (!fs.existsSync(path.join(themeDir, relative))) fail(`required UI source is missing: ${relative}`);
}

for (const file of walk(themeDir).filter((entry) => entry.endsWith('.php'))) {
  const source = fs.readFileSync(file, 'utf8');
  if (source.includes('wp_add_inline_style(')) fail(`visual CSS must live in CSS assets, not wp_add_inline_style(): ${path.relative(root, file)}`);
  if (/<style\b/i.test(source)) fail(`public theme PHP must not own ad-hoc inline <style> blocks: ${path.relative(root, file)}`);
}

const functionsPhp = read(themeDir, 'functions.php');
for (const marker of ['bitmomo_public_snapshot_contract', 'bitmomo-snapshot-contract', 'bitmomo_prevent_homepage_snapshot_cache', 'bitmomo_snapshot_contract']) {
  if (!functionsPhp.includes(marker)) fail(`P0 public snapshot guard is missing marker: ${marker}`);
}
if (!functionsPhp.includes("'schema' => 2") || functionsPhp.includes("'market_state' =>") || functionsPhp.includes("'opportunity_state' =>")) {
  fail('public snapshot HTML contract must mirror visible public facts only');
}
if (!functionsPhp.includes('bitmomo_should_noindex_public_view') || !functionsPhp.includes('is_search()') || !functionsPhp.includes("is_archive() && !is_category('riset')")) {
  fail('public indexability must be governed by one utility/archive noindex predicate');
}

const frontendTrait = read(themeDir, 'inc/trait-bitmomo-frontend.php');
if (!/public function render_mailpoet_modal\(\)[\s\S]*?return;/.test(frontendTrait) || /bm-subscribe-modal|bm-subscribe-dialog/.test(frontendTrait)) {
  fail('legacy newsletter modal must remain structurally disabled on every public route');
}

const frontPage = read(themeDir, 'front-page.php');
if (!frontPage.includes('<main id="primary"')) fail('homepage must expose canonical #primary skip target');
for (const retired of ["template-parts/btc-intelligence', 'card", "template-parts/newsletter", 'template-parts/ai', 'template-parts/platform']) {
  if (frontPage.includes(retired)) fail(`homepage launch hierarchy reintroduced retired/competing surface: ${retired}`);
}
const heroIndex = frontPage.indexOf("template-parts/home', 'hero'");
const howIndex = frontPage.indexOf("template-parts/how-it-works");
const conversionIndex = frontPage.indexOf("template-parts/whitelist");
const researchIndex = frontPage.indexOf("template-parts/research");
if (!(heroIndex > -1 && howIndex > heroIndex && researchIndex > howIndex && conversionIndex > researchIndex)) {
  fail('homepage hierarchy must remain current intelligence -> evidence/mechanism -> qualified research -> Founding conversion');
}

const frontendJs = read(themeDir, 'assets/js/bitmomo-frontend.js');
if (!frontendJs.includes('menuFocusable()') || !frontendJs.includes("event.key !== 'Tab'") || !frontendJs.includes("event.key === 'Escape'")) {
  fail('mobile navigation keyboard containment/escape contract is incomplete');
}
for (const marker of ['data-bm-event', 'bitmomo:analytics', 'homepage_post_signup_btc_click', 'homepage_post_signup_ledger_click']) {
  if (!frontendJs.includes(marker)) fail(`homepage telemetry/continuation contract missing marker: ${marker}`);
}
if (/payload\.(?:email|first_name|whatsapp|phone|user_id|client_id|device_id)\s*=/.test(frontendJs)) {
  fail('public product analytics must not include PII or persistent identity');
}

const pageTemplate = read(themeDir, 'page.php');
const templateFunctions = read(themeDir, 'inc/template-functions.php');
if (!templateFunctions.includes('bitmomo_normalize_public_page_body_headings') || !templateFunctions.includes("array('<h2$1>', '</h2>')")) {
  fail('ordinary-page heading normalizer is missing; database H1 can duplicate template H1');
}
if (!pageTemplate.includes("apply_filters( 'the_content', $bm_content )") || !pageTemplate.includes('bitmomo_normalize_public_page_body_headings( $bm_rendered_content )')) {
  fail('ordinary page.php must render filtered content through canonical heading normalization');
}
if (!pageTemplate.includes('if ( $bm_is_product_surface )') || !pageTemplate.includes('<?php the_content(); ?>')) {
  fail('product shortcode pages must retain renderer-owned content pass-through');
}

const assetsTrait = read(themeDir, 'inc/trait-bitmomo-assets.php');
const designCss = read(themeDir, 'assets/css/design-system.css');
const readabilityCss = read(themeDir, 'assets/css/public-readability.css');
const publicSurfacesCss = read(themeDir, 'assets/css/public-surfaces.css');
const navCss = read(themeDir, 'assets/css/navigation-footer.css');
const articleCss = read(themeDir, 'assets/css/article-reading.css');
const homeCss = read(themeDir, 'assets/css/home.css');
const homeConversionCss = read(themeDir, 'assets/css/home-conversion.css');

for (const marker of ['.bm-skip-link', 'prefers-reduced-motion: reduce', '--bm-focus-ring', '--bm-touch-target-mobile: 44px', '--bm-font-sans:', '--bm-shell-width: 1180px']) {
  if (!designCss.includes(marker)) fail(`canonical design foundation missing primitive: ${marker}`);
}
if (!/\.bm-header[\s\S]*?var\(--bm-header-surface\)/.test(navCss) || !/\.bm-footer[\s\S]*?var\(--bm-footer-surface\)/.test(navCss)) {
  fail('navigation-footer.css must remain the explicit shared chrome owner');
}
if (/:root\s*\{/.test(publicSurfacesCss)) fail('public-surfaces.css must not redefine global tokens');
for (const forbiddenOwner of ['.bm-footer', '.bm-header', '.bm-wl-home', '.bm-pro-sales', '.bm-help', '.bm-article-body']) {
  if (publicSurfacesCss.includes(forbiddenOwner)) fail(`public-surfaces.css crossed named ownership boundary: ${forbiddenOwner}`);
}
if (!/\.bm-container[\s\S]*?var\(--bm-shell-width\)/.test(publicSurfacesCss)) fail('generic public layer must own the canonical outer alignment grid');
if (/font-family\s*:[^;]*\bInter\b/i.test(articleCss + publicSurfacesCss + navCss)) {
  fail('public rendering must not depend on an unbundled locally installed Inter font');
}
if (!articleCss.includes('var(--bm-reading-width)') || !articleCss.includes('var(--bm-article-wide)')) {
  fail('article reading geometry must consume canonical reading/wide tokens');
}
if (!readabilityCss.includes('--bmi-text: var(--bm-text)') || !readabilityCss.includes('--bms-orange: var(--bm-action)')) {
  fail('product token bridge must resolve BTC Intelligence and Pro to the canonical foundation');
}

const homeHero = read(themeDir, 'template-parts/home-hero.php');
const homeEvidence = read(themeDir, 'template-parts/how-it-works.php');
const homeWhitelist = read(themeDir, 'template-parts/whitelist.php');
for (const marker of ['.bm-home-hero', '.bm-home-reading', '.bm-home-evidence', '.bm-home-ledger', '.bm-home-pro-proof', '.bm-howworks', '.bm-home-research']) {
  if (!homeCss.includes(marker)) fail(`homepage stylesheet lost institutional primitive: ${marker}`);
}
for (const marker of ['>BTC MARKET VIEW<', '>BIAS<', '>CONFIDENCE<', '>REFERENSI BTC<', '>DIPERBARUI<', '>FAKTOR UTAMA<', '<strong>SUMBER DATA</strong>', 'APA YANG TERJADI', 'APA YANG BERUBAH', 'MENGAPA PENTING', 'PANTAU BERIKUTNYA']) {
  if (!homeHero.includes(marker)) fail(`homepage market view lost visitor-facing information: ${marker}`);
}
for (const forbidden of ['>OPPORTUNITY<', '>STATE<', "['market_state']", "['market_state_certainty']", 'Bitmomo_Public_Intelligence_Adapter::history()', '<style', 'Decision View']) {
  if (homeHero.includes(forbidden)) fail(`homepage leaked retired/internal detail: ${forbidden}`);
}
if (!/class="bm-home-hero__primary"[^>]+\/btc-intelligence\//.test(homeHero) || !homeHero.includes('Buka BTC Intelligence')) {
  fail('homepage primary action must open product before asking for commitment');
}
if (!homeHero.includes('/btc-intelligence/#decision-ledger') || !homeHero.includes('Periksa rekam jejak')) {
  fail('homepage must expose the public Decision Ledger proof path before the later Founding conversion surface');
}
for (const marker of ['BUKTI, BUKAN KLAIM', '30D STATE TAPE', 'DECISION LEDGER', 'ARSIP PRO ≥48 JAM', 'EXPECTED RANGE', 'CARA KERJA BITMOMO']) {
  if (!homeEvidence.includes(marker)) fail(`homepage evidence/mechanism surface lost required marker: ${marker}`);
}
if (homeEvidence.indexOf('BUKTI, BUKAN KLAIM') > homeEvidence.indexOf('CARA KERJA BITMOMO')) {
  fail('homepage evidence must precede secondary product-mechanism explanation');
}
if (!homeWhitelist.includes('/btc-intelligence/#decision-ledger') || !homeWhitelist.includes('Tidak ada pembayaran pada tahap whitelist')) {
  fail('homepage whitelist must expose proof and remove payment ambiguity');
}
if (!homeConversionCss.includes('input[name="first_name"]') || !homeConversionCss.includes('.bm-wl__continuation')) {
  fail('homepage conversion owner must support email-first acquisition and post-signup continuation');
}
for (const lowContrast of ['#71839f', '#667993']) {
  if (homeCss.toLowerCase().includes(lowContrast)) fail(`homepage reintroduced known sub-AA micro-text: ${lowContrast}`);
}

const btcPage = read(btcPluginDir, 'includes/class-bitmomo-btc-intelligence-page.php');
const renderPageMatch = btcPage.match(/public function render_page[\s\S]*?return ob_get_clean\(\);/);
const renderPage = renderPageMatch ? renderPageMatch[0] : '';
for (const requiredCall of ['render_hero()', 'render_current_snapshot()', 'render_history()', 'render_decision_ledger()', 'render_track_record()', 'render_delayed_proof()', 'render_methodology()', 'render_pro_cta()']) {
  if (!renderPage.includes(requiredCall)) fail(`BTC Intelligence visitor-first hierarchy lost ${requiredCall}`);
}
for (const removedCall of ['render_how_it_works()', 'render_five_axes()', 'render_how_to_read()', 'render_confidence_evaluation()', 'render_expected_range_performance()', 'render_regime_performance()', 'render_data_quality()', 'render_historical_regime()']) {
  if (renderPage.includes(removedCall)) fail(`BTC Intelligence dashboard/explanation wall returned via ${removedCall}`);
}
for (const forbidden of ['activity percentile', '60m range', 'OI 24H', '>FUNDING<', '>BASIS<', 'Market State', 'confidence_buckets', 'expected_range_evaluation', 'regime_performance', 'stale_rate_pct', 'settlement_completeness_pct']) {
  if (btcPage.includes(forbidden)) fail(`BTC Intelligence renderer leaked engine/QA detail: ${forbidden}`);
}
const methodologyDetails = btcPage.match(/<details class="bm-bi__details"([^>]*)>[\s\S]*?<summary>[\s\S]*?Metodologi & aturan akuntabilitas[\s\S]*?<\/summary>/);
if (!methodologyDetails || /\bopen\b/i.test(methodologyDetails[1] || '')) fail('methodology must remain behind closed progressive disclosure');

const btcCss = read(btcPluginDir, 'assets/css/bitmomo-btc-intelligence.css');
const subtleToken = designCss.match(/--bm-text-subtle:\s*(#[0-9a-f]{6})\s*;/i)?.[1];
if (!subtleToken || !designCss.includes('--bm-text-subtle-readable: var(--bm-text-subtle)') || !btcCss.includes(`--bmi-subtle:var(--bm-text-subtle-readable,${subtleToken})`)) {
  fail('BTC Intelligence must inherit the canonical readable micro-text token with a matching fail-safe fallback');
} else {
  requireContrast('BTC subtle fallback / terminal background', subtleToken, '#090f16');
}
if (!btcCss.includes('--bmi-sticky-header:var(--bm-header-height,64px)') || !btcCss.includes('--bmi-sticky-rail:63px') || !btcCss.includes('--bmi-sticky-header:var(--bm-header-height-mobile,60px)')) {
  fail('BTC rail must consume canonical 64px desktop / 60px mobile header tokens and preserve explicit rail clearance');
}
if (!btcCss.includes('scroll-margin-top:calc(var(--bmi-sticky-header) + var(--bmi-sticky-rail) + 12px)')) {
  fail('BTC anchors must clear both sticky site header and section rail');
}
for (const lowContrast of ['color:#667993', 'color:#71839f', 'color:#6f829c']) {
  if (btcCss.includes(lowContrast)) fail(`BTC Intelligence reintroduced known sub-AA micro-text: ${lowContrast}`);
}

const proAccountCss = read(proPluginDir, 'assets/css/bitmomo-pro-account.css');
const proWhitelistCss = read(proPluginDir, 'assets/css/bitmomo-pro-whitelist.css');
const proHelpCss = read(proPluginDir, 'assets/css/bitmomo-pro-help.css');
if (!proAccountCss.includes('background: var(--bm-action)') || /#1fae7a|#24c98d/i.test(proAccountCss)) {
  fail('Pro account must use canonical commercial action, not a separate green CTA palette');
}
if (!proWhitelistCss.includes('background: var(--bm-action)') || !proWhitelistCss.includes('var(--bm-touch-target-mobile)')) {
  fail('Whitelist must use canonical commercial action and touch geometry');
}
if (!proHelpCss.includes('font-family: var(--bm-font-sans)') || !proHelpCss.includes('var(--bm-touch-target-mobile)')) {
  fail('Help Center must consume canonical typography and touch geometry');
}

requireContrast('canonical muted text / public background', '#9eb0c2', '#0c1c2a');
requireContrast('canonical subtle text / public background', '#8294ae', '#0c1c2a');
requireContrast('canonical secondary text / elevated surface', '#c7d4db', '#0f2434');
requireContrast('canonical link / public background', '#6de0d9', '#0c1c2a');
requireContrast('commercial CTA text', '#0b1620', '#f4ad32');

const customCssPath = path.join(themeDir, 'custom.css');
const customCssBytes = fs.statSync(customCssPath).size;
if (customCssBytes > LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES) {
  fail(`custom.css grew beyond frozen legacy debt ceiling (${customCssBytes} > ${LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES} bytes)`);
}

if (!process.exitCode) {
  console.log(`PASS UI architecture contract; custom.css=${customCssBytes}/${LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES} bytes`);
}