import fs from 'node:fs';
import path from 'node:path';

const repoRoot = process.cwd();
const checks = [];
function read(file) { return fs.readFileSync(path.join(repoRoot, file), 'utf8'); }
function check(label, condition) { checks.push({ label, pass: Boolean(condition) }); }
function withoutCommentLines(source) {
  return source.split('\n').filter((line) => !/^\s*(?:\/\*|\*|\/\/)/.test(line)).join('\n');
}

const frontPage = read('website/wp-content/themes/bitmomo-child-v3/front-page.php');
const themeFunctions = read('website/wp-content/themes/bitmomo-child-v3/functions.php');
const header = read('website/wp-content/themes/bitmomo-child-v3/header.php');
const publicSurfacesCss = read('website/wp-content/themes/bitmomo-child-v3/assets/css/public-surfaces.css');
const homeConversionCss = read('website/wp-content/themes/bitmomo-child-v3/assets/css/home-conversion.css');
const assetsTrait = read('website/wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-assets.php');
const homeHero = read('website/wp-content/themes/bitmomo-child-v3/template-parts/home-hero.php');
const homeWhitelist = read('website/wp-content/themes/bitmomo-child-v3/template-parts/whitelist.php');
const howItWorks = read('website/wp-content/themes/bitmomo-child-v3/template-parts/how-it-works.php');
const research = read('website/wp-content/themes/bitmomo-child-v3/template-parts/research.php');
const frontendTrait = read('website/wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-frontend.php');
const retentionJs = read('website/wp-content/themes/bitmomo-child-v3/assets/js/bitmomo-retention.js');
const retentionOutput = withoutCommentLines(retentionJs);
const publicAdapter = read('website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-public-intelligence-adapter.php');
const btcIntelligencePlugin = read('website/wp-content/plugins/bitmomo-btc-intelligence/bitmomo-btc-intelligence.php');
const btcIntelligencePage = read('website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-page.php');
const btcPageOutput = withoutCommentLines(btcIntelligencePage);
const proSales = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php');
const proSalesOutput = withoutCommentLines(proSales);
const proHelp = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-help-center.php');
const proWhitelist = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php');
const proWhitelistJs = read('website/wp-content/plugins/bitmomo-pro/assets/js/bitmomo-pro-whitelist.js');

const heroIndex = frontPage.indexOf("template-parts/home', 'hero'");
const proConversionIndex = frontPage.indexOf("template-parts/whitelist");
check(
  'Homepage has one current-intelligence surface before one unified Pro conversion surface',
  heroIndex > -1 && proConversionIndex > -1 && heroIndex < proConversionIndex
    && !/template-parts\/btc-intelligence[^\n]*card/.test(frontPage)
    && !/template-parts\/newsletter/.test(frontPage)
    && !/template-parts\/pro[^\n]*teaser/.test(frontPage)
);
check('Homepage does not let AI Lab compete with the launch funnel', !/template-parts\/ai[^\n]*lab/.test(frontPage));
check(
  'Homepage Pro conversion remains provider-neutral and reaches /pro/ when checkout is unavailable',
  /home_url\(\s*'\/pro\/'\s*\)/.test(homeHero)
    && /bitmomo_pro_get_checkout_url\(\)/.test(homeWhitelist)
    && /Bitmomo_Pro_Whitelist::instance\(\)->render_widget/.test(homeWhitelist)
    && /home_url\(\s*'\/pro\/'\s*\)/.test(homeWhitelist)
);
check(
  'Homepage whitelist is an integrated conversion surface instead of a nested standalone card',
  /Bitmomo_Pro_Sales::PRICE_LABEL/.test(homeWhitelist)
    && /bm-wl-unified__facts/.test(homeWhitelist)
    && /bm-wl-unified__form-head/.test(homeWhitelist)
    && !/bm-wl-unified__features/.test(homeWhitelist)
    && /home-conversion\.css/.test(assetsTrait)
    && /\.bm-wl-home \.bm-wl-unified__form \.bm-wl__panel/.test(homeConversionCss)
    && /#bm-wl-form-panel > \.bm-wl__price/.test(homeConversionCss)
);
check('Primary nav exposes /pro/ as the public Pro destination', /home_url\(\s*'\/pro\/'\s*\)/.test(header));

check(
  'Homepage current reading consumes only the public-safe snapshot adapter',
  /Bitmomo_Public_Intelligence_Adapter::snapshot\(\)/.test(homeHero)
    && !/Bitmomo_Public_Intelligence_Adapter::history\(\)/.test(homeHero)
    && !/Bitmomo_AI_Intelligence::free_projection\(\)/.test(homeHero)
    && !/Bitmomo_Regime_State_Store|Bitmomo_Pro_/.test(homeHero)
);
check(
  'Homepage exposes only direction, confidence, one reason and update time',
  />ARAH</.test(homeHero) && />KEYAKINAN</.test(homeHero) && />ALASAN UTAMA</.test(homeHero) && />DIPERBARUI</.test(homeHero)
    && !/>OPPORTUNITY</.test(homeHero) && !/>STATE</.test(homeHero)
    && !/market_state|certainty|Bitmomo_Public_Intelligence_Adapter::history/.test(homeHero)
    && !/provenance|Sumber data/.test(homeHero)
);
check(
  'Homepage explanation uses visitor language instead of engine vocabulary',
  /Baca pasar/.test(howItWorks) && /Ringkas konteks/.test(howItWorks) && /Pahami langkah berikutnya/.test(howItWorks)
    && !/quality gate|logic deterministik|classifier|axis|funding\/basis/i.test(howItWorks)
);
check(
  'Synthetic HTML contract mirrors visible public facts only',
  /'schema'\s*=>\s*2/.test(themeFunctions)
    && /'directional_bias'/.test(themeFunctions) && /'confidence'/.test(themeFunctions)
    && !/'market_state'\s*=>/.test(themeFunctions) && !/'direction_strength'\s*=>/.test(themeFunctions)
    && !/'opportunity_state'\s*=>/.test(themeFunctions) && !/'opportunity_status'\s*=>/.test(themeFunctions)
);
check(
  'Public adapter remains an allowlisted source rather than raw engine output',
  /final class Bitmomo_Public_Intelligence_Adapter/.test(publicAdapter)
    && /public_drivers/.test(publicAdapter) && /metric_map/.test(publicAdapter)
    && !/return\s+\$projection\s*;/.test(publicAdapter)
);

check(
  'BTC Intelligence reads only public adapters, never private stores or Pro internals',
  /Bitmomo_Public_Intelligence_Adapter::snapshot\(\)/.test(btcIntelligencePage)
    && /Bitmomo_Public_Intelligence_Adapter::history\(\)/.test(btcIntelligencePage)
    && /Bitmomo_Public_Intelligence_Adapter::evaluation_summary\(\)/.test(btcIntelligencePage)
    && !/Bitmomo_AI_Scorecard::|Bitmomo_Regime_State_Store::|Bitmomo_Pro_[A-Za-z]+::/.test(btcIntelligencePage)
    && !/do_shortcode_tag|Bitmomo_Btc_Opportunity_UI/.test(btcIntelligencePlugin)
);
check(
  'BTC Intelligence public UI excludes engine and QA kitchen metrics',
  !/activity percentile|60m range|OI 24H|>FUNDING<|>BASIS<|Market State|certainty|confidence_buckets|expected_range_evaluation|regime_performance|stale_rate_pct|settlement_completeness/i.test(btcPageOutput)
    && !/engine-v\d|classifier-v\d|legacy-window-v\d/.test(btcPageOutput)
);
check(
  'BTC Intelligence keeps only concise trust provenance',
  /Sumber data: %s/.test(btcIntelligencePage) && /bm-bi__provenance/.test(btcIntelligencePage)
    && !/Binance public market data|Bybit derivatives fallback/.test(btcIntelligencePage)
);
check('BTC Intelligence track record states the exact +24h evaluation rule', /tepat \+24 jam/.test(btcIntelligencePage));
check('BTC Intelligence keeps methodology secondary and one Pro action', /<details class="bm-bi__details"/.test(btcIntelligencePage) && /Lihat Bitmomo Pro/.test(btcIntelligencePage));

check('/pro sales page is public and does not read entitlement state', /add_shortcode\(\s*'bitmomo_pro_sales'/.test(proSales) && !/bitmomo_user_has_pro_access|get_current_user_id|Bitmomo_Pro_Briefs::get_current_brief_for_display/.test(proSales));
check('/pro sales page renders one whitelist/purchase CTA path from canonical checkout URL', /bitmomo_pro_get_checkout_url\(\)/.test(proSales) && /Bitmomo_Pro_Whitelist::instance\(\)->render_widget/.test(proSales));
check('/pro sales copy keeps future capabilities clearly not-live', /SEGERA HADIR/.test(proSalesOutput) && /Belum tersedia hari ini/.test(proHelp) && !/24\/7|real-time|real time/.test(proSalesOutput));
check(
  '/pro DATA flow is limited to currently supported market inputs',
  /Harga, struktur pasar, funding\/basis, positioning derivatives, momentum, dan volatilitas BTC diproses dari data pasar yang tersedia\./.test(proSalesOutput)
    && !/order book|sinyal on-chain BTC dikumpulkan secara berkelanjutan/i.test(proSalesOutput)
);
check('/pro removes the duplicate final conversion block structurally, not with CSS', !/render_final_cta\s*\(/.test(proSalesOutput) && !/bm-pro-sales__final-cta/.test(publicSurfacesCss));
check('/pro pricing terms match M2 founding package', /Rp149\.000/.test(proSales) && /Rp1\.490\.000/.test(proSales) && /const SEAT_CAP\s*=\s*149/.test(proSales) && /const BATCH_ONE\s*=\s*25/.test(proSales));
check('/pro avoids removed placeholder preview values', !/XX%|\$XX,XXX|\(placeholder\)|Contoh Tampilan Decision View/.test(proSales));
check('/pro avoids old public 7-day refund promise', !/7\s*(hari|day)|refund 7|7-day/i.test(proSales + proHelp));
check('/pro avoids fabricated accuracy percentage', !/\d+%\s*akurat/i.test(proSales + proHelp));
check('Whitelist says joining does not guarantee a seat', /Masuk whitelist tidak menjamin tempat/.test(proWhitelist));
check('Whitelist submit JS can survive LiteSpeed-localization issues via data attributes', /data-ajax-url/.test(proWhitelist) && /data-nonce/.test(proWhitelist) && /bitmomoProWhitelist/.test(proWhitelistJs));
check(
  'Cold-audience browser telemetry covers the whitelist funnel without binding to an analytics provider',
  /pro_cta_click/.test(proWhitelistJs) && /whitelist_view/.test(proWhitelistJs) && /whitelist_submit/.test(proWhitelistJs)
    && /whitelist_created/.test(proWhitelistJs) && /whitelist_duplicate/.test(proWhitelistJs) && /whitelist_error/.test(proWhitelistJs)
    && /CustomEvent\('bitmomo:analytics'/.test(proWhitelistJs) && /Array\.isArray\(window\.dataLayer\)/.test(proWhitelistJs)
    && !/gtag\(|google-analytics|googletagmanager/.test(proWhitelistJs)
);
check(
  'Cold-audience telemetry context is acquisition-only and excludes submitted identity fields',
  /event_version:\s*1/.test(proWhitelistJs) && /source:\s*fieldValue\('source'\)/.test(proWhitelistJs)
    && /utm_source:\s*fieldValue\('utm_source'\)/.test(proWhitelistJs) && /utm_medium:\s*fieldValue\('utm_medium'\)/.test(proWhitelistJs)
    && /utm_campaign:\s*fieldValue\('utm_campaign'\)/.test(proWhitelistJs) && /page_path:\s*window\.location\.pathname/.test(proWhitelistJs)
    && !/payload\.email|payload\.first_name|payload\.whatsapp|payload\.post_id|payload\.record_token/.test(proWhitelistJs)
);
check(
  'Launch-critical SEO titles remain product-led',
  /Bitmomo — BTC Market Intelligence/.test(themeFunctions) && /Bitmomo Pro — BTC Market Intelligence/.test(themeFunctions)
    && /BTC Intelligence — Bitmomo/.test(themeFunctions) && /rank_math\/frontend\/title/.test(themeFunctions) && /pre_get_document_title/.test(themeFunctions)
);
check(
  'Launch-critical SEO descriptions use visitor language rather than engine terms',
  /Bitmomo merangkum arah BTC, tingkat keyakinan analisis, dan alasan utamanya/.test(themeFunctions)
    && /kapan pandangan pasar perlu berubah/.test(themeFunctions)
    && /Lihat kondisi BTC saat ini, alasan utama, konteks 30 hari, dan track record/.test(themeFunctions)
    && !/Opportunity, Directional Bias, Confidence, Market State/.test(themeFunctions)
);
check('Product surfaces suppress the legacy newsletter modal', /is_front_page\(\)\s*\|\|\s*is_page\(\['pro', 'btc-intelligence'\]\)/.test(frontendTrait));
check(
  'Homepage Research stays BTC-first, concise, and excludes AI Lab posts from fallback',
  /array\( 'bitcoin', 'makro', 'market-structure' \)/.test(research)
    && /wp_trim_words\( get_the_excerpt\(\), 14/.test(research)
    && /get_term_by\( 'slug', 'ai-lab', 'post_tag' \)/.test(research)
    && /tag__not_in/.test(research) && !/Baca selengkapnya/.test(research)
);
check('BTC Intelligence loads repeat-use telemetry only on the product page', /bitmomo_enqueue_btc_retention_telemetry/.test(themeFunctions) && /is_page\('btc-intelligence'\)/.test(themeFunctions) && /bitmomo-retention\.js/.test(themeFunctions));
check(
  'BTC repeat-use telemetry measures only useful product signals',
  /btc_intelligence_view/.test(retentionJs) && /btc_intelligence_return_visit/.test(retentionJs)
    && /btc_methodology_expand/.test(retentionJs) && /btc_pro_interest/.test(retentionJs)
    && !/btc_history_interaction|market_state_30d|proof_section/.test(retentionJs)
    && /CustomEvent\('bitmomo:analytics'/.test(retentionJs) && /Array\.isArray\(window\.dataLayer\)/.test(retentionJs)
    && !/gtag\(|google-analytics|googletagmanager/.test(retentionJs)
);
check(
  'BTC repeat-use telemetry stores only a local timestamp and does not create a persistent identity',
  /localStorage\.getItem\(VISIT_KEY\)/.test(retentionJs) && /localStorage\.setItem\(VISIT_KEY, String\(now\)\)/.test(retentionJs)
    && /MIN_RETURN_MS/.test(retentionJs) && !/Math\.random|randomUUID|document\.cookie|user_id|client_id|device_id/.test(retentionOutput)
);

let pass = 0;
for (const result of checks) {
  if (result.pass) pass++;
  console.log(`[${result.pass ? 'PASS' : 'FAIL'}] ${result.label}`);
}
console.log(`${pass}/${checks.length} M2 launch-surface checks passed`);
process.exit(pass === checks.length ? 0 : 1);
