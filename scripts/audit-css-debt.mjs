import fs from 'node:fs';
import path from 'node:path';

const cssPath = path.join(process.cwd(), 'website/wp-content/themes/bitmomo-child-v3/custom.css');
const raw = fs.readFileSync(cssPath, 'utf8');
const css = raw.replace(/\/\*[\s\S]*?\*\//g, '');

const selectorCounts = new Map();
const mediaCounts = new Map();
let ruleBlocks = 0;

const stack = [];
let boundary = 0;

function currentInsideKeyframes() {
  return stack.some((entry) => entry.type === 'keyframes');
}

for (let i = 0; i < css.length; i += 1) {
  const char = css[i];

  if (char === '{') {
    const header = css.slice(boundary, i).trim();
    let entry = { type: 'rule', header };

    if (/^@media\b/i.test(header)) {
      entry = { type: 'media', header };
      mediaCounts.set(header, (mediaCounts.get(header) || 0) + 1);
    } else if (/^@(?:-\w+-)?keyframes\b/i.test(header)) {
      entry = { type: 'keyframes', header };
    } else if (/^@/i.test(header)) {
      entry = { type: 'atrule', header };
    } else if (header && !currentInsideKeyframes()) {
      ruleBlocks += 1;
      for (const selector of header.split(',')) {
        const normalized = selector.trim().replace(/\s+/g, ' ');
        if (!normalized || /^\d+%$/.test(normalized) || /^(from|to)$/.test(normalized)) continue;
        selectorCounts.set(normalized, (selectorCounts.get(normalized) || 0) + 1);
      }
    }

    stack.push(entry);
    boundary = i + 1;
    continue;
  }

  if (char === '}') {
    stack.pop();
    boundary = i + 1;
  }
}

const duplicateSelectors = [...selectorCounts.entries()]
  .filter(([, count]) => count > 1)
  .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));

const mediaQueries = [...mediaCounts.entries()]
  .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));

const breakpointValues = new Map();
for (const [query, count] of mediaQueries) {
  for (const match of query.matchAll(/(?:min|max)-width\s*:\s*(\d+)px/gi)) {
    const width = Number(match[1]);
    breakpointValues.set(width, (breakpointValues.get(width) || 0) + count);
  }
}

const debtMarkers = {
  approved: (raw.match(/APPROVED/gi) || []).length,
  prParity: (raw.match(/PR\d+\s+VISUAL\s+PARITY/gi) || []).length,
  fixSections: (raw.match(/FIX\s*\(/gi) || []).length,
};

const report = {
  file: path.relative(process.cwd(), cssPath),
  bytes: Buffer.byteLength(raw, 'utf8'),
  lines: raw.split('\n').length,
  ruleBlocks,
  uniqueSelectors: selectorCounts.size,
  duplicateSelectorCount: duplicateSelectors.length,
  duplicateDefinitionCount: duplicateSelectors.reduce((sum, [, count]) => sum + (count - 1), 0),
  topDuplicateSelectors: duplicateSelectors.slice(0, 30).map(([selector, count]) => ({ selector, count })),
  mediaQueries: mediaQueries.map(([query, count]) => ({ query, count })),
  breakpointWidths: [...breakpointValues.entries()].sort((a, b) => a[0] - b[0]).map(([width, count]) => ({ width, count })),
  debtMarkers,
};

console.log(JSON.stringify(report, null, 2));
