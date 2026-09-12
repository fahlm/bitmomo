import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const themeDir = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES = 50300;

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

const requiredFiles = [
  'functions.php',
  'custom.css',
  'assets/css/home-opportunity.css',
  'assets/js/bitmomo-frontend.js',
];

for (const relative of requiredFiles) {
  if (!fs.existsSync(path.join(themeDir, relative))) {
    fail(`required UI source is missing: ${relative}`);
  }
}

const phpFiles = walk(themeDir).filter((file) => file.endsWith('.php'));
for (const file of phpFiles) {
  const source = fs.readFileSync(file, 'utf8');
  if (source.includes('wp_add_inline_style(')) {
    fail(`visual CSS must live in CSS assets, not wp_add_inline_style(): ${path.relative(root, file)}`);
  }
}

const functionsPhp = fs.readFileSync(path.join(themeDir, 'functions.php'), 'utf8');
const p0Markers = [
  'bitmomo_public_snapshot_contract',
  'bitmomo-snapshot-contract',
  'bitmomo_prevent_homepage_snapshot_cache',
  'bitmomo_snapshot_contract',
];
for (const marker of p0Markers) {
  if (!functionsPhp.includes(marker)) {
    fail(`P0 public snapshot guard is missing marker: ${marker}`);
  }
}

const opportunityCss = fs.readFileSync(path.join(themeDir, 'assets/css/home-opportunity.css'), 'utf8');
if (!opportunityCss.includes('.bm-hero-opportunity') || !opportunityCss.includes('.bm-btc-opportunity')) {
  fail('homepage Opportunity CSS does not contain the required hero/card selectors');
}

const customCssPath = path.join(themeDir, 'custom.css');
const customCssBytes = fs.statSync(customCssPath).size;
if (customCssBytes > LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES) {
  fail(
    `custom.css grew beyond the frozen legacy debt ceiling ` +
    `(${customCssBytes} > ${LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES} bytes); ` +
    'put new visual work in explicit component/page assets and reduce the monolith instead of adding overrides'
  );
}

if (!process.exitCode) {
  console.log(
    `PASS UI architecture contract; custom.css=${customCssBytes}/${LEGACY_CUSTOM_CSS_DEBT_CEILING_BYTES} bytes (frozen debt ceiling)`
  );
}
