import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const theme = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const failures = [];

function read(relative) {
  return fs.readFileSync(path.join(theme, relative), 'utf8');
}

function check(label, condition) {
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
  if (!condition) failures.push(label);
}

const design = read('assets/css/design-system.css');
const home = read('assets/css/home.css');
const research = read('assets/css/research.css');
const article = read('assets/css/article-reading.css');
const publicCss = read('assets/css/public-surfaces.css');

check(
  'Canonical foundation exposes horizontal overflow so launch QA cannot be masked by frozen legacy CSS',
  /body\s*\{[\s\S]*?overflow-x:\s*visible\s*;[\s\S]*?\}/.test(design)
);

check(
  'Mobile hamburger bars explicitly defeat frozen legacy !important geometry and color',
  /\.bm-header\s+button\.bm-hamburger\s*>\s*span\s*\{[\s\S]*?width:\s*22px\s*!important[\s\S]*?background-color:\s*currentColor\s*!important[\s\S]*?background-image:\s*none\s*!important[\s\S]*?\}/.test(design)
);

check(
  'Homepage How-It-Works neutralizes retired counter/card presentation',
  /\.bm-howworks-steps\s*\{[\s\S]*?counter-reset:\s*none\s*;[\s\S]*?\}/.test(home) &&
  /\.bm-howworks-steps\s+li\s*\{[\s\S]*?border:\s*0\s*;[\s\S]*?border-top:\s*1px\s+solid[\s\S]*?background:\s*transparent\s*;[\s\S]*?border-radius:\s*0\s*;[\s\S]*?\}/.test(home) &&
  /\.bm-howworks-step-label::before\s*\{[\s\S]*?content:\s*none\s*!important\s*;[\s\S]*?\}/.test(home)
);

check(
  'Research V2 header explicitly owns block geometry instead of inheriting legacy flex layout',
  /\.bm-research-head\s*\{[\s\S]*?display:\s*block\s*;[\s\S]*?width:\s*100%\s*;[\s\S]*?margin:\s*0\s*;[\s\S]*?justify-content:\s*initial\s*;[\s\S]*?flex-wrap:\s*nowrap\s*;[\s\S]*?gap:\s*0\s*;[\s\S]*?\}/.test(research)
);

check(
  'Article V2 neutralizes legacy figure ratio, meta spacing and Related Research title semantics',
  /\.bm-article-meta\s*\{[\s\S]*?margin:\s*22px\s+0\s+0\s*;[\s\S]*?\}/.test(article) &&
  /\.bm-article-figure\s*\{[\s\S]*?aspect-ratio:\s*auto\s*;[\s\S]*?\}/.test(article) &&
  /\.bm-related-head\s+\.bm-section-title\s*\{[\s\S]*?color:\s*var\(--bm-text\)\s*;[\s\S]*?text-align:\s*left\s*;[\s\S]*?text-transform:\s*none\s*;[\s\S]*?\}/.test(article)
);

check(
  'Generic archive cards fully own spacing/chrome instead of inheriting legacy card presentation',
  /\.bm-cards--research\s+\.bm-card\s*\{[\s\S]*?padding:\s*0\s*;[\s\S]*?gap:\s*0\s*;[\s\S]*?box-shadow:\s*none\s*;[\s\S]*?\}/.test(publicCss) &&
  /\.bm-cards--research\s+\.bm-card-art\s*\{[\s\S]*?border-radius:\s*0\s*;[\s\S]*?background:\s*var\(--bm-bg-soft\)\s*;[\s\S]*?\}/.test(publicCss) &&
  /\.bm-cards--research\s+\.bm-card-title\s*\{[\s\S]*?color:\s*var\(--bm-text\)\s*;[\s\S]*?\}/.test(publicCss)
);

check(
  'Generic V2 pagination owns 44px neutral controls instead of inherited legacy teal pills',
  /\.bm-pagination--v2\s+a,[\s\S]*?\.bm-pagination--v2\s+span\s*\{[\s\S]*?min-width:\s*44px\s*;[\s\S]*?height:\s*44px\s*;[\s\S]*?border:\s*1px\s+solid\s+var\(--bm-border\)\s*;[\s\S]*?background:\s*transparent\s*;[\s\S]*?\}/.test(publicCss) &&
  /\.bm-pagination--v2\s+\.current\s*\{[\s\S]*?border-color:\s*var\(--bm-accent\)\s*;[\s\S]*?\}/.test(publicCss)
);

if (failures.length) {
  console.error(`Staged cascade ownership contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('PASS staged legacy cascade ownership contract.');
