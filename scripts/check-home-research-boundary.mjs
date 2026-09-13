import fs from 'node:fs';

const file = 'website/wp-content/themes/bitmomo-child-v3/template-parts/research.php';
const src = fs.readFileSync(file, 'utf8');
const checks = [];
const check = (label, pass) => checks.push({ label, pass: Boolean(pass) });

check('Homepage uses canonical market-research taxonomy helper', /bitmomo_market_research_taxonomy_slugs\(\)/.test(src));
check('Homepage requires the broad Riset category as a parent boundary', /get_category_by_slug\(\s*'riset'\s*\)/.test(src) && /'cat'\s*=>\s*\(int\)\s*\$bm_riset_term->term_id/.test(src));
check('Homepage additionally requires explicit market category or tag taxonomy', /'tax_query'\s*=>\s*array\(/.test(src) && /'taxonomy'\s*=>\s*'category'/.test(src) && /'taxonomy'\s*=>\s*'post_tag'/.test(src) && /'terms'\s*=>\s*\$bm_market_slugs/.test(src));
check('Homepage excludes AI Lab tagged posts', /get_term_by\(\s*'slug'\s*,\s*'ai-lab'/.test(src) && /tag__not_in/.test(src));
check('Homepage applies defense-in-depth classification helper', /bitmomo_post_is_market_research\(\s*get_the_ID\(\)\s*\)/.test(src));
check('Homepage has no generic Riset fallback query', !/\$bm_fallback_args/.test(src) && !/if\s*\(\s*!\s*\$bm_research_items\s*&&\s*\$bm_riset_term\s*\)/.test(src));
check('Homepage hides the section when no qualified market research exists', /if\s*\(\s*!\s*\$bm_research_items\s*\)\s*\{[\s\S]*?return;/.test(src));
check('Homepage keeps BTC-first priority taxonomy', /array\(\s*'bitcoin'\s*,\s*'makro'\s*,\s*'market-structure'\s*\)/.test(src));

let passed = 0;
for (const result of checks) {
  if (result.pass) passed++;
  console.log(`[${result.pass ? 'PASS' : 'FAIL'}] ${result.label}`);
}
console.log(`${passed}/${checks.length} homepage research-boundary checks passed`);
process.exit(passed === checks.length ? 0 : 1);
