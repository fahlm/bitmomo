import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const themeDir = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const btcPluginDir = path.join(root, 'website/wp-content/plugins/bitmomo-btc-intelligence');
const APPROVED_BREAKPOINTS = new Set([767, 1023]);
const WCAG_AA_NORMAL_TEXT = 4.5;
const failures = [];

function fail(message) {
  failures.push(message);
  console.error(`::error title=UI architecture contract::${message}`);
}
function check(label, condition) {
  if (!condition) fail(label);
  else console.log(`[PASS] ${label}`);
}
function walk(dir) {
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });
}
function read(relative) { return fs.readFileSync(path.join(themeDir, relative), 'utf8'); }
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
  else console.log(`[PASS] ${label} ${ratio.toFixed(2)}:1`);
}
function stripReducedMotionBlock(source) {
  return source.replace(/@media\s*\(prefers-reduced-motion\s*:\s*reduce\)\s*\{[\s\S]*?\n\s*\}\s*\n\s*\}/gi, '');
}

const requiredFiles = [
  'functions.php','front-page.php','page.php','page-tentang-kami.php','single.php','header.php','footer.php',
  'inc/template-functions.php','inc/trait-bitmomo-assets.php','inc/trait-bitmomo-frontend.php',
  'assets/css/tokens.css','assets/css/foundation.css','assets/css/components.css','assets/css/components/research-cards.css',
  'assets/css/pages/home.css','assets/css/pages/research.css','assets/css/pages/article.css','assets/css/pages/about.css','assets/css/pages/public.css',
  'assets/js/bitmomo-frontend.js','template-parts/home-authority.php','template-parts/research-hub.php','template-parts/ai-lab.php',
];
for (const relative of requiredFiles) check(`required canonical UI source exists: ${relative}`, fs.existsSync(path.join(themeDir, relative)));

const forbiddenLegacyFiles = [
  'custom.css','assets/css/home-opportunity.css','assets/css/navigation-footer.css','assets/css/public-readability.css',
  'assets/css/public-surfaces.css','assets/css/home.css','assets/css/research.css',
];
for (const relative of forbiddenLegacyFiles) check(`legacy stylesheet is permanently retired: ${relative}`, !fs.existsSync(path.join(themeDir, relative)));

const phpFiles = walk(themeDir).filter((file) => file.endsWith('.php'));
for (const file of phpFiles) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file);
  if (source.includes('wp_add_inline_style(')) fail(`visual CSS must not use wp_add_inline_style(): ${relative}`);
  if (/<style(?:\s|>)/i.test(source)) fail(`visual CSS must not be emitted in a PHP <style> block: ${relative}`);
  for (const match of source.matchAll(/style\s*=\s*"([^"]*)"/gi)) {
    const value = match[1].trim();
    if (!value.startsWith('--')) fail(`static inline style attribute is forbidden in ${relative}: ${value.slice(0, 80)}`);
  }
}

const cssFiles = walk(path.join(themeDir, 'assets/css')).filter((file) => file.endsWith('.css'));
for (const file of cssFiles) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file);
  const sourceWithoutReducedMotion = stripReducedMotionBlock(source);
  if (/!important\b/.test(sourceWithoutReducedMotion)) fail(`!important is forbidden outside prefers-reduced-motion: ${relative}`);
  if (/overflow-x\s*:\s*(?:hidden|clip)\b/i.test(source)) fail(`overflow masking is forbidden; fix the component instead: ${relative}`);
  for (const match of source.matchAll(/@media\s*\([^)]*(?:min|max)-width\s*:\s*(\d+)px/gi)) {
    const width = Number(match[1]);
    if (!APPROVED_BREAKPOINTS.has(width)) fail(`unapproved responsive breakpoint ${width}px in ${relative}; use 1023/767 plus fluid layout`);
  }
}

const assetLoader = read('inc/trait-bitmomo-assets.php');
for (const marker of [
  'assets/css/tokens.css','assets/css/foundation.css','assets/css/components.css','assets/css/components/research-cards.css',
  'assets/css/pages/home.css','assets/css/pages/research.css','assets/css/pages/article.css','assets/css/pages/about.css','assets/css/pages/public.css',
]) check(`asset graph owns ${marker}`, assetLoader.includes(marker));
for (const marker of forbiddenLegacyFiles) check(`asset graph never references ${marker}`, !assetLoader.includes(`'${marker}'`) && !assetLoader.includes(`"${marker}"`));

const header = read('header.php');
check('header delegates styles to enqueue graph', !/<link[^>]+stylesheet/i.test(header));
check('header exposes keyboard skip navigation to #primary', /bm-skip-link/.test(header) && /href="#primary"/.test(header));

const functionsPhp = read('functions.php');
for (const marker of ['bitmomo_public_snapshot_contract','bitmomo-snapshot-contract','bitmomo_prevent_homepage_snapshot_cache','bitmomo_snapshot_contract']) {
  check(`P0 public snapshot guard remains: ${marker}`, functionsPhp.includes(marker));
}

const frontendTrait = read('inc/trait-bitmomo-frontend.php');
check('legacy newsletter modal remains a structural no-op', /public function render_mailpoet_modal\(\)[\s\S]*?return;/.test(frontendTrait));
check('legacy PHP image-style hook cannot emit visual CSS', /public function inline_img_fallback_css\(\)[\s\S]*?return;/.test(frontendTrait) && !/<style/.test(frontendTrait));

const frontPage = read('front-page.php');
const heroIndex = frontPage.indexOf("template-parts/home', 'hero'");
const authorityIndex = frontPage.indexOf("template-parts/home', 'authority'");
check('homepage owns a real primary landmark', /<main id="primary"/.test(frontPage));
check('homepage places institutional authority immediately behind product utility', heroIndex > -1 && authorityIndex > heroIndex);
check('homepage keeps one Pro conversion surface', (frontPage.match(/template-parts\/whitelist/g) || []).length === 1 && !/template-parts\/pro[^\n]*teaser/.test(frontPage));
check('homepage does not restore standalone newsletter conversion', !/template-parts\/newsletter/.test(frontPage));

const frontendJs = read('assets/js/bitmomo-frontend.js');
check('mobile navigation uses canonical 1023px boundary', /matchMedia\('\(max-width: 1023px\)'\)/.test(frontendJs));
for (const legacyMarker of ['bmreg-trend','bmreg-price-line','Progressive-enhancement only']) check(`legacy regime renderer stays removed: ${legacyMarker}`, !frontendJs.includes(legacyMarker));

const pageTemplate = read('page.php');
const templateFunctions = read('inc/template-functions.php');
check('ordinary page H1 normalization remains centralized', templateFunctions.includes('bitmomo_normalize_public_page_body_headings') && templateFunctions.includes("array('<h2$1>', '</h2>')"));
check('ordinary page content flows through H1 normalizer', pageTemplate.includes("apply_filters( 'the_content', $bm_content )") && pageTemplate.includes('bitmomo_normalize_public_page_body_headings( $bm_rendered_content )'));
check('product shortcode routes retain renderer-owned pass-through', pageTemplate.includes('if ( $bm_is_product_surface )') && pageTemplate.includes('<?php the_content(); ?>'));

const homeCss = read('assets/css/pages/home.css');
for (const marker of ['.bm-hero-layout','.bm-direction-summary','.bm-authority__pillars','.bm-wl-home .bm-wl-unified']) check(`homepage page owner contains ${marker}`, homeCss.includes(marker));
check('homepage chart uses semantic tokens', homeCss.includes('var(--bm-color-bull)') && homeCss.includes('var(--bm-color-bear)') && homeCss.includes('var(--bm-color-neutral)'));

const researchHub = read('template-parts/research-hub.php');
for (const marker of ['Crypto Market Research','AI Systems Research','FEATURED RESEARCH','RESEARCH STANDARD','Market Research','AI Lab','Seluruh publikasi']) check(`Research Hub preserves ${marker}`, researchHub.includes(marker));
check('Research Hub consumes canonical taxonomy helper', researchHub.includes('bitmomo_research_taxonomy()'));
check('Research Hub de-duplicates featured work from both streams', (researchHub.match(/post__not_in/g) || []).length >= 2);

const single = read('single.php');
for (const marker of ['bitmomo_primary_public_category','bitmomo_post_research_lane','bitmomo_research_classification_label','bitmomo_estimated_reading_minutes','Standar Bitmomo Research']) check(`article consumes ${marker}`, single.includes(marker));
check('article template contains no static inline visual styles', !/style\s*=/.test(single));

const about = read('page-tentang-kami.php');
for (const marker of ['ABOUT BITMOMO','Crypto Market Research','AI Systems Research','Evidence sebelum narrative','Fail closed','BTC Intelligence','Bitmomo Pro','Bitmomo Research']) check(`About preserves ${marker}`, about.includes(marker));
check('About owns exactly one H1', (about.match(/<h1\b/g) || []).length === 1);

const btcPage = fs.readFileSync(path.join(btcPluginDir, 'includes/class-bitmomo-btc-intelligence-page.php'), 'utf8');
const renderPageMatch = btcPage.match(/public function render_page[\s\S]*?return ob_get_clean\(\);/);
const renderPage = renderPageMatch ? renderPageMatch[0] : '';
for (const requiredCall of ['render_hero()','render_current_snapshot()','render_historical_regime()','render_track_record()','render_methodology()','render_pro_cta()']) check(`BTC hierarchy retains ${requiredCall}`, renderPage.includes(requiredCall));
for (const removedCall of ['render_how_it_works()','render_five_axes()','render_how_to_read()','render_confidence_evaluation()','render_expected_range_performance()','render_regime_performance()','render_data_quality()']) check(`BTC explanation wall stays removed: ${removedCall}`, !renderPage.includes(removedCall));
check('secondary BTC proof stays behind progressive disclosure', btcPage.includes("<summary><?php esc_html_e( 'Evaluasi lainnya'"));
check('BTC methodology stays behind progressive disclosure', btcPage.includes("<summary><?php esc_html_e( 'Cara kerja & metodologi'"));

const btcCss = fs.readFileSync(path.join(btcPluginDir, 'assets/css/bitmomo-btc-intelligence.css'), 'utf8');
check('BTC Intelligence inherits canonical background', btcCss.includes('--bmi-bg:var(--bm-color-bg'));
check('BTC Intelligence inherits shared readable micro-text token', btcCss.includes('--bmi-text-subtle:var(--bm-text-subtle-readable,#8294ae)'));
for (const legacyLowContrast of ['color:#667993','color:#71839f','color:#6f829c']) check(`BTC avoids sub-AA ${legacyLowContrast}`, !btcCss.includes(legacyLowContrast));

requireContrast('canonical subtle text / deepest page', '#8399aa', '#06101a');
requireContrast('canonical subtle text / surface', '#8399aa', '#112a3b');
requireContrast('canonical secondary text / surface', '#c6d4dc', '#112a3b');
requireContrast('commercial CTA text', '#101820', '#f4ad32');
requireContrast('BTC micro text after 0.75 opacity', alphaBlend('#a8b7ca', '#0c1c2a', 0.75), '#0c1c2a');

if (failures.length) {
  console.error(`UI architecture contract failed with ${failures.length} issue(s).`);
  process.exit(1);
}
console.log(`PASS UI architecture contract; canonical_css_files=${cssFiles.length}; approved_breakpoints=767,1023; legacy_css=0`);
