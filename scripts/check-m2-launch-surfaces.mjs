import fs from 'node:fs';
import path from 'node:path';

const repoRoot = process.cwd();
const checks = [];
function read(file) { return fs.readFileSync(path.join(repoRoot, file), 'utf8'); }
function check(label, condition) { checks.push({ label, pass: Boolean(condition) }); }
function withoutCommentLines(source) {
  return source.split('\n').filter((line) => !/^\s*(?:\/\*|\*|\/\/)/.test(line)).join('\n');
}

const theme = 'website/wp-content/themes/bitmomo-child-v3/';
const frontPage = read(theme + 'front-page.php');
const themeFunctions = read(theme + 'functions.php');
const header = read(theme + 'header.php');
const homeHero = read(theme + 'template-parts/home-hero.php');
const homeAuthority = read(theme + 'template-parts/home-authority.php');
const homeWhitelist = read(theme + 'template-parts/whitelist.php');
const homeResearch = read(theme + 'template-parts/research.php');
const aiLab = read(theme + 'template-parts/ai-lab.php');
const researchHub = read(theme + 'template-parts/research-hub.php');
const category = read(theme + 'category.php');
const frontendTrait = read(theme + 'inc/trait-bitmomo-frontend.php');
const assetTrait = read(theme + 'inc/trait-bitmomo-assets.php');
const retentionJs = read(theme + 'assets/js/bitmomo-retention.js');
const retentionOutput = withoutCommentLines(retentionJs);
const publicAdapter = read('website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-public-intelligence-adapter.php');
const btcIntelligencePlugin = read('website/wp-content/plugins/bitmomo-btc-intelligence/bitmomo-btc-intelligence.php');
const proSales = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php');
const proSalesOutput = withoutCommentLines(proSales);
const proHelp = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-help-center.php');
const proWhitelist = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php');
const proWhitelistJs = read('website/wp-content/plugins/bitmomo-pro/assets/js/bitmomo-pro-whitelist.js');

const heroIndex = frontPage.indexOf("template-parts/home', 'hero'");
const authorityIndex = frontPage.indexOf("template-parts/home', 'authority'");
const researchIndex = frontPage.indexOf('template-parts/research');
const proConversionIndex = frontPage.indexOf('template-parts/whitelist');
check(
  'Homepage progresses utility → authority → research proof → conversion without duplicate BTC/newsletter surfaces',
  heroIndex > -1 && authorityIndex > heroIndex && researchIndex > authorityIndex && proConversionIndex > researchIndex &&
    !/template-parts\/btc-intelligence[^\n]*card/.test(frontPage) &&
    !/template-parts\/newsletter/.test(frontPage) &&
    !/template-parts\/pro[^\n]*teaser/.test(frontPage)
);
check(
  'Homepage establishes market + AI research authority before asking cold users to convert',
  /CRYPTO MARKET INTELLIGENCE/.test(homeAuthority) && /AI SYSTEMS RESEARCH/.test(homeAuthority) &&
    /Evidence before narrative/.test(homeAuthority) && /Accountability/.test(homeAuthority)
);
check(
  'AI Lab carries substantive research positioning rather than decorative topic labels',
  /Kami tidak sekadar memakai AI/.test(aiLab) && /Decentralized AI/.test(aiLab) && /Agent Systems/.test(aiLab) && /AI Evaluation/.test(aiLab)
);
check(
  'Riset route owns a dedicated institutional Research Hub',
  /template-parts\/research', 'hub/.test(category) && /Crypto Market Research/.test(researchHub) && /AI Systems Research/.test(researchHub) &&
    /FEATURED RESEARCH/.test(researchHub) && /RESEARCH STANDARD/.test(researchHub) && /Seluruh publikasi/.test(researchHub)
);

check(
  'Homepage Pro conversion remains provider-neutral and reaches /pro/ when checkout is unavailable',
  /home_url\(\s*'\/pro\/'\s*\)/.test(homeHero) && /bitmomo_pro_get_checkout_url\(\)/.test(homeWhitelist) &&
    /Bitmomo_Pro_Whitelist::instance\(\)->render_widget/.test(homeWhitelist) && /home_url\(\s*'\/pro\/'\s*\)/.test(homeWhitelist)
);
check('Primary nav exposes BTC Intelligence, Research, About and one canonical /pro/ destination',
  /BTC Intelligence/.test(header) && /Research/.test(header) && /Tentang/.test(header) && /BITMOMO PRO/.test(header) &&
  (header.match(/home_url\(\s*'\/pro\/'\s*\)/g) || []).length === 1
);

check(
  'Homepage current state and 30D history consume one public-safe intelligence adapter',
  /Bitmomo_Public_Intelligence_Adapter::snapshot\(\)/.test(homeHero) && /Bitmomo_Public_Intelligence_Adapter::history\(\)/.test(homeHero) &&
    !/Bitmomo_AI_Intelligence::free_projection\(\)/.test(homeHero) && !/Bitmomo_Regime_State_Store/.test(homeHero) && !/Bitmomo_Pro_/.test(homeHero)
);
check(
  'Homepage intelligence fails closed instead of fabricating unavailable state',
  /is_array\(\s*\$bm_snapshot\s*\)/.test(homeHero) && /array\(\s*'fresh',\s*'delayed'\s*\)/.test(homeHero) &&
    /Belum tersedia/.test(homeHero) && /Riwayat belum tersedia/.test(homeHero)
);
check(
  'Homepage keeps Opportunity separate from directional Bias',
  />OPPORTUNITY</.test(homeHero) && />BIAS</.test(homeHero) && /\$bm_opportunity_state/.test(homeHero) && /\$bm_latest_direction/.test(homeHero) &&
    !/Risk-On|Risk-Off|RISK-ON|RISK-OFF/.test(homeHero)
);
check(
  'Public adapter exposes provenance and market-state certainty as one public contract',
  /'provenance'\s*=>\s*\[/.test(publicAdapter) && /'source'\s*=>\s*\$public_source/.test(publicAdapter) &&
    /'as_of'\s*=>\s*\$as_of/.test(publicAdapter) && /'timezone'\s*=>\s*self::PUBLIC_DISPLAY_TIMEZONE/.test(publicAdapter) && /'market_state_certainty'/.test(publicAdapter)
);
check(
  'Homepage renders compact canonical provenance',
  /\$bm_snapshot\['provenance'\]\['source'\]/.test(homeHero) && />DATA</.test(homeHero) && /WIB/.test(homeHero) && /Riwayat &amp; track record/.test(homeHero)
);
check(
  'Public BTC Intelligence renders provenance from the adapter without hardcoded providers',
  /surface_context\(\)/.test(btcIntelligencePlugin) && /\$surface\['provenance'\]/.test(btcIntelligencePlugin) &&
    /bm-bi__snapshot-provenance/.test(btcIntelligencePlugin) && /SOURCE/.test(btcIntelligencePlugin) && /AS OF/.test(btcIntelligencePlugin) &&
    !/Binance public market data|Bybit derivatives fallback/.test(homeHero + btcIntelligencePlugin)
);

check('/pro sales page is public and does not read entitlement state',
  /add_shortcode\(\s*'bitmomo_pro_sales'/.test(proSales) && !/bitmomo_user_has_pro_access|get_current_user_id|Bitmomo_Pro_Briefs::get_current_brief_for_display/.test(proSales)
);
check('/pro sales page renders one whitelist/purchase CTA path from canonical checkout URL',
  /bitmomo_pro_get_checkout_url\(\)/.test(proSales) && /Bitmomo_Pro_Whitelist::instance\(\)->render_widget/.test(proSales)
);
check('/pro future capabilities remain clearly not-live',
  /SEGERA HADIR/.test(proSalesOutput) && /Belum tersedia hari ini/.test(proHelp) && !/24\/7|real-time|real time/.test(proSalesOutput)
);
check('/pro DATA flow is limited to currently supported market inputs',
  /Harga, struktur pasar, funding\/basis, positioning derivatives, momentum, dan volatilitas BTC diproses dari data pasar yang tersedia\./.test(proSalesOutput) &&
  !/order book|sinyal on-chain BTC dikumpulkan secara berkelanjutan/i.test(proSalesOutput)
);
check('/pro pricing terms match founding package',
  /Rp149\.000/.test(proSales) && /Rp1\.490\.000/.test(proSales) && /const SEAT_CAP\s*=\s*149/.test(proSales) && /const BATCH_ONE\s*=\s*25/.test(proSales)
);
check('/pro avoids fabricated accuracy, placeholders and obsolete refund promises',
  !/XX%|\$XX,XXX|\(placeholder\)|Contoh Tampilan Decision View/.test(proSales) &&
  !/7\s*(hari|day)|refund 7|7-day/i.test(proSales + proHelp) && !/\d+%\s*akurat/i.test(proSales + proHelp)
);
check('Whitelist says joining does not guarantee a seat', /Masuk whitelist tidak menjamin tempat/.test(proWhitelist));
check('Whitelist submit JS survives localization issues via data attributes',
  /data-ajax-url/.test(proWhitelist) && /data-nonce/.test(proWhitelist) && /bitmomoProWhitelist/.test(proWhitelistJs)
);

check(
  'Cold-audience whitelist telemetry is provider-neutral and excludes identity fields',
  /pro_cta_click/.test(proWhitelistJs) && /whitelist_view/.test(proWhitelistJs) && /whitelist_submit/.test(proWhitelistJs) &&
    /whitelist_created/.test(proWhitelistJs) && /CustomEvent\('bitmomo:analytics'/.test(proWhitelistJs) &&
    !/gtag\(|google-analytics|googletagmanager/.test(proWhitelistJs) &&
    !/payload\.email|payload\.first_name|payload\.whatsapp|payload\.post_id|payload\.record_token/.test(proWhitelistJs)
);
check(
  'Legacy newsletter modal is globally disabled; footer is the only retention subscription surface',
  /public function render_mailpoet_modal\(\)[\s\S]*?return;/.test(frontendTrait) && !/do_shortcode|mailpoet_form/.test(frontendTrait.match(/public function render_mailpoet_modal\(\)[\s\S]*?\n\s*}/)?.[0] || '')
);
check(
  'Legacy CSS debt is not part of the canonical asset graph',
  /foundation\.css/.test(assetTrait) && /home\.css/.test(assetTrait) && /research\.css/.test(assetTrait) &&
    !/wp_enqueue_style\([^\n]*bitmomo-child/.test(assetTrait) && !/home-opportunity\.css/.test(withoutCommentLines(assetTrait)) && !/public-readability\.css/.test(withoutCommentLines(assetTrait))
);
check(
  'Homepage Research remains market-first and taxonomy-driven',
  /array\( 'bitcoin', 'makro', 'market-structure' \)/.test(homeResearch) && /get_term_by\( 'slug', 'ai-lab', 'post_tag' \)/.test(homeResearch) && /tag__not_in/.test(homeResearch)
);

check(
  'Launch-critical SEO remains product-led rather than legacy media positioning',
  /Bitmomo — BTC Market Intelligence/.test(themeFunctions) && /Bitmomo Pro — BTC Market Intelligence/.test(themeFunctions) &&
    /BTC Intelligence — Bitmomo/.test(themeFunctions) && /rank_math\/frontend\/title/.test(themeFunctions)
);
check(
  'BTC repeat-use telemetry covers views, meaningful returns, history interaction and Pro intent without persistent identity',
  /btc_intelligence_view/.test(retentionJs) && /btc_intelligence_return_visit/.test(retentionJs) && /btc_history_interaction/.test(retentionJs) && /btc_pro_interest/.test(retentionJs) &&
    /localStorage\.getItem\(VISIT_KEY\)/.test(retentionJs) && /localStorage\.setItem\(VISIT_KEY, String\(now\)\)/.test(retentionJs) &&
    !/Math\.random|randomUUID|document\.cookie|user_id|client_id|device_id/.test(retentionOutput)
);

let pass = 0;
for (const result of checks) {
  if (result.pass) pass++;
  console.log(`[${result.pass ? 'PASS' : 'FAIL'}] ${result.label}`);
}
console.log(`${pass}/${checks.length} M2 launch-surface checks passed`);
process.exit(pass === checks.length ? 0 : 1);
