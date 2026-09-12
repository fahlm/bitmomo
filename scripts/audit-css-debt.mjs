import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const cssRoot = path.join(root, 'website/wp-content/themes/bitmomo-child-v3/assets/css');

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });
}

function analyze(file) {
  const raw = fs.readFileSync(file, 'utf8');
  const source = raw.replace(/\/\*[\s\S]*?\*\//g, '');
  const breakpoints = [...source.matchAll(/@media\s*\([^)]*(?:min|max)-width\s*:\s*(\d+)px/gi)].map((match) => Number(match[1]));
  const importantCount = (source.match(/!important\b/g) || []).length;
  const staticHexCount = (source.match(/#[0-9a-f]{6}\b/gi) || []).length;
  const variableReferences = (source.match(/var\(--bm-[^)]+\)/g) || []).length;
  return {
    file: path.relative(root, file),
    bytes: Buffer.byteLength(raw, 'utf8'),
    lines: raw.split('\n').length,
    breakpoints: [...new Set(breakpoints)].sort((a, b) => a - b),
    importantCount,
    staticHexCount,
    variableReferences,
  };
}

const files = walk(cssRoot).filter((file) => file.endsWith('.css')).sort();
const report = files.map(analyze);
const totals = report.reduce((acc, item) => {
  acc.bytes += item.bytes;
  acc.lines += item.lines;
  acc.importantCount += item.importantCount;
  acc.staticHexCount += item.staticHexCount;
  acc.variableReferences += item.variableReferences;
  for (const width of item.breakpoints) acc.breakpoints.add(width);
  return acc;
}, { bytes: 0, lines: 0, importantCount: 0, staticHexCount: 0, variableReferences: 0, breakpoints: new Set() });

console.log(JSON.stringify({
  architecture: 'tokens -> foundation -> components -> domain components -> page owner',
  files: report,
  totals: {
    fileCount: report.length,
    bytes: totals.bytes,
    lines: totals.lines,
    importantCount: totals.importantCount,
    staticHexCount: totals.staticHexCount,
    variableReferences: totals.variableReferences,
    breakpointWidths: [...totals.breakpoints].sort((a, b) => a - b),
  },
}, null, 2));
