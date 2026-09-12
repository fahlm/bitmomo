import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const theme = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const failures = [];
const read = (file) => fs.readFileSync(path.join(theme, file), 'utf8');
const check = (label, condition) => {
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
  if (!condition) failures.push(label);
};

const assets = read('inc/trait-bitmomo-assets.php');
const header = read('header.php');
const foundation = read('assets/css/foundation.css');
const home = read('assets/css/home.css');
const research = read('assets/css/research.css');
const runtime = JSON.parse(fs.readFileSync(path.join(root, 'config/production-runtime.json'), 'utf8'));

check('Legacy custom.css is not enqueued', !/wp_enqueue_style\([^\n]*bitmomo-child/.test(assets));
check('Canonical stylesheet graph starts from foundation', /'bitmomo-foundation'/.test(assets) && /'bitmomo-navigation-footer'/.test(assets) && /'bitmomo-public-surfaces'/.test(assets));
check('Route styles are conditional', /is_front_page\(\)/.test(assets) && /is_single\(\) \|\| is_category\('riset'\)/.test(assets) && /is_page\('tentang-kami'\)/.test(assets));
check('Header does not inject stylesheets manually', !/<link\s+rel=["']stylesheet/i.test(header));
check('Foundation owns 320px support and reduced motion', /min-width:\s*320px/.test(foundation) && /prefers-reduced-motion/.test(foundation));
check('Homepage hero and authority grids use shrinkable tracks', /minmax\(0,\s*1\.05fr\)/.test(home) && /bm-authority__pillars/.test(home));
check('Research hub and article system share one route stylesheet', /bm-research-hub__hero/.test(research) && /bm-article-body/.test(research));
for (const legacy of ['custom.css','assets/css/home-opportunity.css','assets/css/public-readability.css']) {
  check(`Production runtime excludes ${legacy}`, runtime.exclude.includes(legacy));
}

if (failures.length) {
  console.error(`Frontend foundation contract failed with ${failures.length} issue(s).`);
  process.exit(1);
}
console.log('PASS frontend foundation ownership contract.');
