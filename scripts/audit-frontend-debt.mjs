import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const policy = JSON.parse(fs.readFileSync(path.join(root, 'config/engineering-policy.json'), 'utf8'));
const roots = [
  'website/wp-content/themes/bitmomo-child-v3',
  'website/wp-content/plugins/bitmomo-ai',
  'website/wp-content/plugins/bitmomo-btc-intelligence',
  'website/wp-content/plugins/bitmomo-pro',
  'website/wp-content/plugins/bitmomo-regime',
];

function walk(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const item of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, item.name);
    if (item.isDirectory()) walk(p, out);
    else if (item.isFile() && item.name.endsWith('.css')) out.push(p);
  }
  return out;
}

function duplicateSelectors(raw) {
  const css = raw.replace(/\/\*[\s\S]*?\*\//g, '');
  const counts = new Map();
  for (const match of css.matchAll(/(^|})\s*([^@{}][^{}]*)\{/g)) {
    const header = match[2].trim();
    for (const selector of header.split(',')) {
      const normalized = selector.trim().replace(/\s+/g, ' ');
      if (!normalized || /^(from|to|\d+%)$/.test(normalized)) continue;
      counts.set(normalized, (counts.get(normalized) || 0) + 1);
    }
  }
  return [...counts.values()].reduce((sum, count) => sum + Math.max(0, count - 1), 0);
}

const files = roots.flatMap((rel) => walk(path.join(root, rel))).sort();
const report = files.map((file) => {
  const raw = fs.readFileSync(file, 'utf8');
  return {
    file: path.relative(root, file),
    bytes: Buffer.byteLength(raw, 'utf8'),
    important: (raw.match(/!important\b/gi) || []).length,
    mediaQueries: (raw.match(/@media\b/gi) || []).length,
    duplicateDefinitions: duplicateSelectors(raw),
  };
});

const totals = report.reduce((acc, row) => {
  acc.bytes += row.bytes;
  acc.important += row.important;
  acc.mediaQueries += row.mediaQueries;
  acc.duplicateDefinitions += row.duplicateDefinitions;
  return acc;
}, { bytes: 0, important: 0, mediaQueries: 0, duplicateDefinitions: 0 });

const legacyPath = 'website/wp-content/themes/bitmomo-child-v3/custom.css';
const legacy = report.find((row) => row.file === legacyPath);
const failures = [];
if (legacy && legacy.bytes > policy.legacy_custom_css_max_bytes) {
  failures.push(`${legacyPath} grew to ${legacy.bytes} bytes (ceiling ${policy.legacy_custom_css_max_bytes})`);
}

const payload = {
  cssFiles: report.length,
  totals,
  legacyCustomCss: legacy || null,
  topByBytes: [...report].sort((a, b) => b.bytes - a.bytes).slice(0, 12),
  topByImportant: [...report].filter((row) => row.important > 0).sort((a, b) => b.important - a.important).slice(0, 12),
};

if (process.argv.includes('--json')) console.log(JSON.stringify(payload, null, 2));
else {
  console.log(`CSS files=${payload.cssFiles} bytes=${totals.bytes} !important=${totals.important} media=${totals.mediaQueries} duplicate-definitions=${totals.duplicateDefinitions}`);
  if (legacy) console.log(`legacy custom.css=${legacy.bytes}/${policy.legacy_custom_css_max_bytes} bytes`);
  console.log('Largest CSS files:');
  for (const row of payload.topByBytes) console.log(`- ${row.bytes.toString().padStart(6)} B | !important=${row.important.toString().padStart(3)} | ${row.file}`);
}

if (failures.length) {
  for (const failure of failures) console.error(`FAIL ${failure}`);
  process.exit(1);
}
console.log('PASS frontend debt budget: legacy custom.css is frozen; modular debt remains observable.');
