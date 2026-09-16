import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const theme = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const failures = [];

function read(file) {
  return fs.readFileSync(file, 'utf8');
}

function check(label, condition) {
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
  if (!condition) failures.push(label);
}

function containsAll(text, fragments) {
  return fragments.every((fragment) => text.includes(fragment));
}

function pxValues(text) {
  const values = [];
  for (const match of text.matchAll(/(?:font-size\s*:\s*|font\s*:[^;\n]*?\s)(\d+(?:\.\d+)?)px\b/g)) {
    values.push(Number(match[1]));
  }
  return values;
}

const readability = read(path.join(theme, 'assets/css/public-readability.css'));
const about = read(path.join(theme, 'assets/css/about.css'));
const assets = read(path.join(theme, 'inc/trait-bitmomo-assets.php'));

check(
  'Canonical public micro/dense/support scales are explicit',
  containsAll(readability, [
    '--bm-type-micro: 11px;',
    '--bm-type-dense: 10px;',
    '--bm-type-support: 13px;',
  ])
);

check(
  'Readability bridge is part of the canonical public asset chain',
  containsAll(assets, [
    "'bitmomo-public-readability'",
    "'/assets/css/public-readability.css'",
    "$public_base_deps = ['bitmomo-public-readability'];",
  ])
);

check(
  'Homepage micro labels and support copy are governed by the public readability contract',
  containsAll(readability, [
    '.home .bm-home-reading__metric-label',
    '.home .bm-home-reading__driver > span',
    '.home .bm-home-proof__kicker',
    '.home .bm-home-research__meta',
    '.home .bm-home-proof__item p',
    '.home .bm-wl-home .bm-wl-unified__fact dt',
  ])
);

check(
  'Research Hub metadata and library copy are governed by the public readability contract',
  containsAll(readability, [
    '.bm-research-hub .bm-research-standard-line span',
    '.bm-research-hub .bm-research-meta',
    '.bm-research-hub .bm-research-library__meta span',
    '.bm-research-hub .bm-research-library__copy p',
  ])
);

check(
  'BTC Intelligence dense evidence no longer renders at the former 7.5–9px sizes',
  containsAll(readability, [
    '.bm-bi .bm-bi__ledger-table th',
    '.bm-bi .bm-bi__ledger-table td:first-child small',
    '.bm-bi .bm-bi__verdict',
    '.bm-bi .bm-bi__track-record .bm-bi__proof-metric small',
    '.bm-bi .bm-bi__track-record .bm-bi__section-intro',
  ])
);

check(
  'Market Context controls, tooltips, legends, and marker keys have explicit readable floors',
  containsAll(readability, [
    '.bm-bi .bm-mc__control-label',
    '.bm-bi .bm-mc__axis-label',
    '.bm-bi .bm-mc__tooltip-event',
    '.bm-bi .bm-mc__legend-item small',
    '.bm-bi .bm-mc__marker-key-items > span',
  ])
);

check(
  'Public Pro Decision View and feature explanation have explicit readable floors',
  containsAll(readability, [
    '.bm-pro-sales .bm-pro-sales__deliverables > div > span',
    '.bm-pro-sales .bm-pro-sales__decision-metrics span',
    '.bm-pro-sales .bm-pro-sales__scenario-map span',
    '.bm-pro-sales .bm-pro-sales__range-marker small',
    '.bm-pro-sales .bm-pro-sales__comparison-row.is-head',
  ])
);

check(
  'Institutional footer labels and descriptive text are covered by the same readability floor',
  containsAll(readability, [
    'body .bm-footer-principle > span',
    'body .bm-footer-principle > p',
    'body .bm-footer-legal a',
  ])
);

const tooSmallContractValues = pxValues(readability).filter((value) => value < 10);
check(
  'Canonical readability contract contains no font declaration below 10px',
  tooSmallContractValues.length === 0
);

check(
  'About page owns compact outer rhythm rather than inheriting generic 86/104px page bands',
  /\.bm-public-page:has\(\.bm-about-authority\)\s*\{[\s\S]*?padding-top:\s*clamp\(32px,\s*4vw,\s*52px\);[\s\S]*?padding-bottom:\s*clamp\(48px,\s*6vw,\s*72px\);[\s\S]*?\}/.test(about)
);

check(
  'About sections use bounded rhythm and do not restore the retired 64px shared margin stack',
  containsAll(about, [
    '.bm-about-authority { margin-top: clamp(24px, 3vw, 36px); }',
    '.bm-about-product { margin-top: clamp(36px, 4vw, 48px); }',
    '.bm-about-contact { margin-top: clamp(32px, 3.5vw, 44px); }',
    'padding-top: clamp(22px, 2.8vw, 32px);',
  ]) && !about.includes('margin-top: clamp(30px, 5vw, 64px)')
);

check(
  'About supporting copy is not disclaimer-sized',
  containsAll(about, [
    '.bm-about-authority__grid p',
    'font-size: 14px;',
    'font-size: 13.5px;',
    '.bm-about-product__boundary { font-size: 14px; }',
  ])
);

if (failures.length) {
  console.error(`Public readability/rhythm contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('PASS public readability and vertical-rhythm contract.');
