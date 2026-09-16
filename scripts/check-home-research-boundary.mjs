import fs from 'node:fs';

const file = 'website/wp-content/themes/bitmomo-child-v3/template-parts/research.php';
const src = fs.readFileSync(file, 'utf8');
const checks = [];
const check = (label, pass) => checks.push({ label, pass: Boolean(pass) });

check(
  'Homepage explicitly detects audited Research Taxonomy V3 activation',
  /bitmomo_research_taxonomy_is_active\(\)/.test(src) && /\$bm_v3_active/.test(src)
);
check(
  'Active V3 path queries the canonical Research helper instead of legacy category-tag inference',
  /if\s*\(\s*\$bm_v3_active[\s\S]*?bitmomo_research_query_args\(/.test(src)
);
check(
  'Homepage remains Market Research only through the canonical final classification boundary',
  /bitmomo_post_is_market_research\(\s*get_the_ID\(\)\s*\)/.test(src)
);
check(
  'V3 candidate query is bounded and final homepage inventory is capped at three publications',
  /'posts_per_page'\s*=>\s*12/.test(src)
    && /count\(\s*\$bm_research_items\s*\)\s*<\s*3/.test(src)
);
check(
  'Legacy bridge is retained only behind the inactive migration path',
  /elseif\s*\(\s*\$bm_riset_term\s*\)[\s\S]*?bitmomo_market_research_taxonomy_slugs\(\)/.test(src)
);
check(
  'Legacy bridge still requires Riset plus explicit market category or tag taxonomy',
  /get_category_by_slug\(\s*'riset'\s*\)/.test(src)
    && /'cat'\s*=>\s*\(int\)\s*\$bm_riset_term->term_id/.test(src)
    && /'taxonomy'\s*=>\s*'category'/.test(src)
    && /'taxonomy'\s*=>\s*'post_tag'/.test(src)
    && /'terms'\s*=>\s*\$bm_market_slugs/.test(src)
);
check(
  'Legacy bridge still excludes AI Lab tagged posts',
  /get_term_by\(\s*'slug'\s*,\s*'ai-lab'/.test(src) && /tag__not_in/.test(src)
);
check(
  'Homepage has no generic Riset fallback that bypasses qualification',
  !/\$bm_fallback_args/.test(src)
    && !/if\s*\(\s*!\s*\$bm_research_items\s*&&\s*\$bm_riset_term\s*\)/.test(src)
);
check(
  'Homepage hides Research when no qualified Market Research exists',
  /if\s*\(\s*!\s*\$bm_research_items\s*\)\s*\{[\s\S]*?return;/.test(src)
);
check(
  'Migration-era homepage keeps BTC-first legacy priority only inside the compatibility path',
  /array\(\s*'bitcoin'\s*,\s*'makro'\s*,\s*'market-structure'\s*\)/.test(src)
);

let passed = 0;
for (const result of checks) {
  if (result.pass) passed++;
  console.log(`[${result.pass ? 'PASS' : 'FAIL'}] ${result.label}`);
}
console.log(`${passed}/${checks.length} homepage research-boundary checks passed`);
process.exit(passed === checks.length ? 0 : 1);
