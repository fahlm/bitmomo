import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const themeDir = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const WCAG_AA_NORMAL_TEXT = 4.5;

function fail(message) {
  console.error(`::error title=UI architecture contract::${message}`);
  process.exitCode = 1;
}
function read(relative) { return fs.readFileSync(path.join(root, relative), 'utf8'); }
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
function requireContrast(label, foreground, background, minimum = WCAG_AA_NORMAL_TEXT) {
  const ratio = contrastRatio(foreground, background);
  if (ratio < minimum) fail(`${label} contrast ${ratio.toFixed(2)}:1 is below ${minimum}:1 (${foreground} on ${background})`);
}

const requiredThemeFiles = [
  'style.css',
  'functions.php',
  'front-page.php',
  'page.php',
  'single.php',
  'category.php',
  'header.php',
  'footer.php',
  'data/runtime-fingerprint.json',
  'inc/trait-bitmomo-assets.php',
  'inc/trait-bitmomo-images.php',
  'inc/trait-bitmomo-frontend.php',
  'assets/css/foundation.css',
  'assets/css/navigation-footer.css',
  'assets/css/public-surfaces.css',
  'assets/css/home.css',
  'assets/css/research.css',
  'assets/css/about.css',
  'template-parts/home-authority.php',
  'template-parts/research-hub.php',
  'template-parts/about-authority.php',
  'assets/js/bitmomo-frontend.js',
];
for (const relative of requiredThemeFiles) {
  if (!fs.existsSync(path.join(themeDir, relative))) fail(`required canonical UI source is missing: ${relative}`);
}

/* Visual CSS must never be emitted from PHP. Dynamic style attributes used for
   CSS custom-property data visualization are allowed, but <style> blocks and
   wp_add_inline_style are not. */
for (const file of walk(themeDir).filter((entry) => entry.endsWith('.php'))) {
  const source = fs.readFileSync(file, 'utf8');
  if (source.includes('wp_add_inline_style(')) fail(`wp_add_inline_style returned: ${path.relative(root, file)}`);
  if (/<style(?:\s|>)/i.test(source)) fail(`inline <style> block returned: ${path.relative(root, file)}`);
}

const functionsPhp = read('website/wp-content/themes/bitmomo-child-v3/functions.php');
for (const marker of ['bitmomo_public_snapshot_contract','bitmomo-snapshot-contract','bitmomo_prevent_homepage_snapshot_cache','bitmomo_snapshot_contract']) {
  if (!functionsPhp.includes(marker)) fail(`P0 public snapshot guard is missing marker: ${marker}`);
}

const assetTrait = read('website/wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-assets.php');
for (const canonicalAsset of ['foundation.css','navigation-footer.css','public-surfaces.css','home.css','research.css','about.css']) {
  if (!assetTrait.includes(canonicalAsset)) fail(`canonical stylesheet is not owned by asset graph: ${canonicalAsset}`);
}
for (const legacyEnqueue of ["wp_enqueue_style('bitmomo-child'", 'home-opportunity.css\',', 'public-readability.css\',']) {
  if (assetTrait.includes(legacyEnqueue)) fail(`legacy stylesheet returned to public cascade: ${legacyEnqueue}`);
}
if (!assetTrait.includes('No speculative external preconnects')) fail('performance contract must reject speculative external preconnects');

const header = read('website/wp-content/themes/bitmomo-child-v3/header.php');
if (/<link\s+rel=["']stylesheet/i.test(header)) fail('header.php must not manually inject stylesheets outside WordPress dependency management');
if (!header.includes('wp_head()')) fail('header.php lost wp_head()');

const runtime = JSON.parse(read('config/production-runtime.json'));
for (const legacyRuntimePath of ['custom.css','assets/css/home-opportunity.css','assets/css/public-readability.css']) {
  if (!runtime.exclude.includes(legacyRuntimePath)) fail(`legacy CSS debt must be excluded from production runtime: ${legacyRuntimePath}`);
}
const themeRuntime = runtime.components.find((component) => component.name === 'bitmomo-child-v3');
for (const requiredRuntimePath of ['data/runtime-fingerprint.json','assets/css/foundation.css','assets/css/home.css','assets/css/research.css','assets/css/about.css','template-parts/research-hub.php']) {
  if (!themeRuntime?.required?.includes(requiredRuntimePath)) fail(`production runtime does not require canonical frontend asset: ${requiredRuntimePath}`);
}

/* Push browser audits must prove byte-level runtime identity. A manually bumped
   version string is not sufficient evidence that source and deployed runtime
   match. The fingerprint contract hashes the named public files themselves. */
const runtimeFingerprint = JSON.parse(read('website/wp-content/themes/bitmomo-child-v3/data/runtime-fingerprint.json'));
const fingerprintFiles = new Set(runtimeFingerprint.files || []);
for (const requiredFingerprintPath of [
  'style.css',
  'functions.php',
  'data/runtime-fingerprint.json',
  'inc/template-functions.php',
  'inc/trait-bitmomo-assets.php',
  'inc/trait-bitmomo-images.php',
  'front-page.php',
  'single.php',
  'category.php',
  'assets/css/foundation.css',
  'assets/css/navigation-footer.css',
  'assets/css/home.css',
  'assets/css/research.css',
  'assets/js/bitmomo-frontend.js',
]) {
  if (!fingerprintFiles.has(requiredFingerprintPath)) fail(`public runtime fingerprint omits launch-critical file: ${requiredFingerprintPath}`);
}
for (const fingerprintPath of fingerprintFiles) {
  if (!fs.existsSync(path.join(themeDir, fingerprintPath))) fail(`public runtime fingerprint references missing file: ${fingerprintPath}`);
}

const templateFunctions = read('website/wp-content/themes/bitmomo-child-v3/inc/template-functions.php');
for (const marker of ['bitmomo_public_runtime_fingerprint','runtime-fingerprint.json','hash_init(\'sha256\')','bitmomo_runtime_fingerprint']) {
  if (!templateFunctions.includes(marker)) fail(`runtime fingerprint implementation is missing marker: ${marker}`);
}
const browserWorkflow = read('.github/workflows/ui-browser-safety.yml');
if (!browserWorkflow.includes('bitmomo_runtime_fingerprint')) fail('browser parity gate must use the byte-level runtime fingerprint endpoint');
if (browserWorkflow.includes('Source theme version:') || browserWorkflow.includes('Production theme version:')) fail('browser parity gate regressed to human-maintained version comparison');
if (!browserWorkflow.includes('source_fingerprint') || !browserWorkflow.includes('runtime_fingerprint')) fail('browser parity gate must compare source and runtime fingerprints explicitly');

const styleCss = read('website/wp-content/themes/bitmomo-child-v3/style.css');
if (/custom styles are enqueued[\s\S]*custom\.css/i.test(styleCss)) fail('theme metadata still documents legacy custom.css as canonical');
if (!/Version:\s*5\.0\.0/.test(styleCss)) fail('theme metadata must identify frontend architecture v5 runtime');

const foundationCss = read('website/wp-content/themes/bitmomo-child-v3/assets/css/foundation.css');
for (const token of ['--bm-bg:', '--bm-text:', '--bm-text-muted:', '--bm-text-subtle:', '--bm-teal:', '--bm-action:', '--bm-container:', '--bm-reading:']) {
  if (!foundationCss.includes(token)) fail(`foundation token missing: ${token}`);
}
if (!foundationCss.includes('@media (prefers-reduced-motion: reduce)')) fail('foundation must honor prefers-reduced-motion');
if (!foundationCss.includes('min-width: 320px')) fail('foundation must explicitly support 320px minimum viewport');

const frontPage = read('website/wp-content/themes/bitmomo-child-v3/front-page.php');
const homeOrder = [
  "template-parts/home', 'hero'",
  "template-parts/home', 'authority'",
  'template-parts/how-it-works',
  'template-parts/research',
  "template-parts/ai', 'lab'",
  'template-parts/whitelist',
].map((marker) => frontPage.indexOf(marker));
if (homeOrder.some((index) => index < 0) || homeOrder.some((value, index) => index > 0 && value <= homeOrder[index - 1])) {
  fail('homepage hierarchy must progress product utility → authority → method → research proof → AI Lab → conversion');
}
if (/template-parts\/btc-intelligence[^\n]*card/.test(frontPage)) fail('homepage must not restore duplicate BTC Intelligence card');
if (/template-parts\/newsletter/.test(frontPage)) fail('homepage must not restore standalone newsletter conversion');

const homeCss = read('website/wp-content/themes/bitmomo-child-v3/assets/css/home.css');
for (const marker of ['.bm-hero-layout', '.bm-direction-summary', '.bm-authority__pillars', '.bm-authority__principles', '.bm-research-list', '.bm-ai-lab-themes', '@media (max-width: 680px)']) {
  if (!homeCss.includes(marker)) fail(`homepage system lost responsive primitive: ${marker}`);
}
if (!/grid-template-columns:\s*minmax\(0,\s*1\.05fr\)/.test(homeCss)) fail('homepage hero columns must remain shrinkable via minmax(0, …)');

const authority = read('website/wp-content/themes/bitmomo-child-v3/template-parts/home-authority.php');
for (const phrase of ['CRYPTO MARKET INTELLIGENCE','AI SYSTEMS RESEARCH','Evidence before narrative','Thesis + invalidation','Fail closed','Accountability']) {
  if (!authority.includes(phrase)) fail(`homepage authority layer lost research principle: ${phrase}`);
}

const aiLab = read('website/wp-content/themes/bitmomo-child-v3/template-parts/ai-lab.php');
for (const phrase of ['Kami tidak sekadar memakai AI','Decentralized AI','Agent Systems','AI Evaluation']) {
  if (!aiLab.includes(phrase)) fail(`AI Lab authority regressed: ${phrase}`);
}
if (!/<p>[^<]{25,}<\/p>/.test(aiLab)) fail('AI Lab themes must carry substantive descriptions, not decorative labels only');

const category = read('website/wp-content/themes/bitmomo-child-v3/category.php');
const researchHub = read('website/wp-content/themes/bitmomo-child-v3/template-parts/research-hub.php');
if (!category.includes("get_template_part( 'template-parts/research', 'hub' )")) fail('Riset category must route to the dedicated Research Hub');
for (const marker of ['Crypto Market Research','AI Systems Research','FEATURED RESEARCH','RESEARCH STANDARD','Market Research','AI Lab','Seluruh publikasi']) {
  if (!researchHub.includes(marker)) fail(`Research Hub lost institutional layer: ${marker}`);
}
if (!researchHub.includes("get_term_by( 'slug', 'ai-lab', 'post_tag' )") || !researchHub.includes('tag__not_in') || !researchHub.includes('tag__in')) {
  fail('Research Hub must separate Market Research and AI Lab from WordPress taxonomy, not hard-coded fake content');
}

const researchCss = read('website/wp-content/themes/bitmomo-child-v3/assets/css/research.css');
for (const marker of ['.bm-research-hub__hero', '.bm-research-disciplines', '.bm-research-featured__card', '.bm-research-principles__grid', '.bm-research-streams', '.bm-article-body', '@media (max-width: 680px)']) {
  if (!researchCss.includes(marker)) fail(`Research responsive system lost primitive: ${marker}`);
}

const single = read('website/wp-content/themes/bitmomo-child-v3/single.php');
for (const marker of ['CRYPTO MARKET RESEARCH','AI SYSTEMS RESEARCH','bm_read_minutes','Standar Bitmomo Research','bm-article-tags']) {
  if (!single.includes(marker)) fail(`article publication surface lost research metadata/standard: ${marker}`);
}

const pageTemplate = read('website/wp-content/themes/bitmomo-child-v3/page.php');
if (!templateFunctions.includes('bitmomo_normalize_public_page_body_headings')) fail('ordinary-page H1 normalizer is missing');
if (!pageTemplate.includes('bitmomo_normalize_public_page_body_headings( $bm_rendered_content )')) fail('ordinary page.php must use canonical heading normalizer');
if (!pageTemplate.includes("get_template_part( 'template-parts/about', 'authority' )")) fail('About page must carry institutional authority layer');

const frontendTrait = read('website/wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-frontend.php');
const modalMethod = frontendTrait.match(/public function render_mailpoet_modal\(\)[\s\S]*?\n\s*}/)?.[0] ?? '';
if (!modalMethod.includes('return;') || /mailpoet_form|do_shortcode/.test(modalMethod)) fail('legacy newsletter modal must remain a no-op; footer owns subscription');
const inlineCssMethod = frontendTrait.match(/public function inline_img_fallback_css\(\)[\s\S]*?\n\s*}/)?.[0] ?? '';
if (!inlineCssMethod.includes('return;') || /<style/.test(inlineCssMethod)) fail('PHP inline image CSS compatibility method must remain a no-op');

const frontendJs = read('website/wp-content/themes/bitmomo-child-v3/assets/js/bitmomo-frontend.js');
for (const marker of ['inert', 'aria-hidden', 'aria-expanded', 'Escape']) {
  if (!frontendJs.includes(marker)) fail(`mobile navigation accessibility behavior lost: ${marker}`);
}
for (const legacyMarker of ['bmreg-trend', 'bmreg-price-line', 'Progressive-enhancement only']) {
  if (frontendJs.includes(legacyMarker)) fail(`legacy duplicate regime chart renderer returned: ${legacyMarker}`);
}

const btcPage = read('website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-page.php');
const renderPage = btcPage.match(/public function render_page[\s\S]*?return ob_get_clean\(\);/)?.[0] ?? '';
for (const requiredCall of ['render_hero()', 'render_current_snapshot()', 'render_historical_regime()', 'render_track_record()', 'render_methodology()', 'render_pro_cta()']) {
  if (!renderPage.includes(requiredCall)) fail(`BTC Intelligence simplified hierarchy lost ${requiredCall}`);
}
for (const removedCall of ['render_how_it_works()', 'render_five_axes()', 'render_how_to_read()', 'render_confidence_evaluation()', 'render_expected_range_performance()', 'render_regime_performance()', 'render_data_quality()']) {
  if (renderPage.includes(removedCall)) fail(`BTC Intelligence explanation wall returned via ${removedCall}`);
}

const btcCss = read('website/wp-content/plugins/bitmomo-btc-intelligence/assets/css/bitmomo-btc-intelligence.css');
if (!btcCss.includes('--bmi-text-subtle:var(--bm-text-subtle-readable,#8294ae)')) fail('BTC Intelligence must consume shared readable subtle-text token');
for (const legacyLowContrast of ['color:#667993', 'color:#71839f', 'color:#6f829c']) {
  if (btcCss.includes(legacyLowContrast)) fail(`BTC Intelligence reintroduced known sub-AA micro-text: ${legacyLowContrast}`);
}

requireContrast('foundation muted text', '#b7c6d3', '#081420');
requireContrast('foundation subtle text', '#8fa3b8', '#081420');
requireContrast('footer subtle text', '#8fa3b8', '#07111b');
requireContrast('commercial CTA text', '#111923', '#f4ad32');
requireContrast('BTC subtle fallback', '#8294ae', '#111d2f');

if (!process.exitCode) console.log('PASS root-cause UI architecture + research authority + runtime parity contract');
