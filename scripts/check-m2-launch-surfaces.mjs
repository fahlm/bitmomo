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
const btcAccountability = read('website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-accountability.php');
const proSales = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php');
const proSalesOutput = withoutCommentLines(proSales);
const proHelp = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-help-center.php');
const proWhitelist = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php');
const proWhitelistJs = read('website/wp-content/plugins/bitmomo-pro/assets/js/bitmomo-pro-whitelist.js');

const heroIndex = frontPage.indexOf("template-parts/home', 'hero'");
const howIndex = frontPage.indexOf("template-parts/how-it-works");
const proConversionIndex = frontPage.indexOf("template-parts/whitelist");
const researchIndex = frontPage.indexOf("template-parts/research");
check(
  'Homepage has one current-intelligence surface before one unified Pro conversion surface',
  heroIndex > -1 && proConversionIndex > -1 && heroIndex < proConversionIndex
    && !/template-parts\/btc-intelligence[^\n]*card/.test(frontPage)
    && !/template-parts\/newsletter/.test(frontPage)
    && !/template-parts\/pro[^\n]*teaser/.test(frontPage)
);
check('Homepage does not let AI Lab compete with the launch funnel', !/template-parts\/ai[^\n]*lab/.test(frontPage));
check(
  'Homepage journey is current intelligence -> evidence -> qualified research -> conversion',
  heroIndex > -1 && howIndex > heroIndex && researchIndex > howIndex && proConversionIndex > researchIndex
    && /home_url\(\s*'\/btc-intelligence\/'\s*\)/.test(homeHero)
    && /\/btc-intelligence\/#decision-ledger/.test(homeHero)
    && /Bitmomo_Public_Intelligence_Adapter::history\(\)/.test(howItWorks)
    && /Bitmomo_Btc_Intelligence_Accountability::decision_ledger\(\s*3\s*\)/.test(howItWorks)
    && /Bitmomo_Btc_Intelligence_Accountability::delayed_proof\(\s*1\s*\)/.test(howItWorks)
    && /ARSIP PRO ≥48 JAM/.test(howItWorks)
    && /EXPECTED RANGE/.test(howItWorks)
    && /bitmomo_pro_get_checkout_url\(\)/.test(homeWhitelist)
    && /Bitmomo_Pro_Whitelist::instance\(\)->render_widget/.test(homeWhitelist)
    && /home_url\(\s*'\/pro\/'\s*\)/.test(homeWhitelist)
    && /homepage_pro_interest/.test(homeWhitelist)
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
  'Homepage market view is concise but finance-grade: bias, confidence, reference, factor, time and safe source only',
  />BTC MARKET VIEW</.test(homeHero) && />BIAS</.test(homeHero) && />CONFIDENCE</.test(homeHero)
    && />REFERENSI BTC</.test(homeHero) && />FAKTOR UTAMA</.test(homeHero) && />DIPERBARUI</.test(homeHero)
    && /<strong>SUMBER DATA<\/strong>/.test(homeHero)
    && /\$bm_drivers\[0\]/.test(homeHero) && /\['provenance'\]\['source'\]/.test(homeHero)
    && /APA YANG TERJADI/.test(homeHero) && /APA YANG BERUBAH/.test(homeHero)
    && /MENGAPA PENTING/.test(homeHero) && /PANTAU BERIKUTNYA/.test(homeHero)
    && !/>OPPORTUNITY</.test(homeHero) && !/>STATE</.test(homeHero)
    && !/\$bm_snapshot\s*\[\s*['"]market_state['"]\s*\]|market_state_certainty|certainty|source_diagnostics|private_note|Bitmomo_Public_Intelligence_Adapter::history/.test(homeHero)
);
check(
  'Homepage change narrative covers canonical structural changes without exposing raw state values',
  /'market_state' === \$bm_field/.test(homeHero)
    && /'structural_state' === \$bm_field/.test(homeHero)
    && /Konteks pasar berubah dibanding brief sebelumnya\./.test(homeHero)
    && /Struktur harga berubah dibanding brief sebelumnya\./.test(homeHero)
    && !/\$bm_change\[['"](?:from|to)['"]\][\s\S]{0,120}(?:market_state|structural_state)/.test(homeHero)
);
check(
  'Homepage evidence precedes mechanism and uses only public accountability boundaries',
  /BUKTI, BUKAN KLAIM/.test(howItWorks)
    && /30D STATE TAPE/.test(howItWorks)
    && /DECISION LEDGER/.test(howItWorks)
    && /ARSIP PRO ≥48 JAM/.test(howItWorks)
    && howItWorks.indexOf('BUKTI, BUKAN KLAIM') < howItWorks.indexOf('HOW BITMOMO WORKS')
    && !/Bitmomo_Pro_Briefs|Bitmomo_AI_Scorecard|Bitmomo_Regime_State_Store/.test(howItWorks)
);
check(
  'Homepage explanation uses the canonical visitor lifecycle instead of engine vocabulary',
  /01 · UNDERSTAND NOW/.test(howItWorks)
    && /02 · MAP WHAT CHANGES/.test(howItWorks)
    && /03 · AUDIT THE RESULT/.test(howItWorks)
    && /Data yang tidak memenuhi standar tidak dipaksakan menjadi analisis/.test(howItWorks)
    && !/quality gate|logic deterministik|classifier|axis|funding\/basis|\bstale\b|\bthesis\b|Data bermasalah ditahan/i.test(howItWorks)
);
check(
  'Synthetic HTML contract mirrors public facts only',
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
  'BTC Intelligence reads public-safe boundaries and renderer-owned copy only',
  /Bitmomo_Public_Intelligence_Adapter::snapshot\(\)/.test(btcIntelligencePage)
    && /Bitmomo_Public_Intelligence_Adapter::history\(\)/.test(btcIntelligencePage)
    && /Bitmomo_Public_Intelligence_Adapter::evaluation_summary\(\)/.test(btcIntelligencePage)
    && /Bitmomo_Btc_Intelligence_Accountability/.test(btcIntelligencePage)
    && !/Bitmomo_AI_Scorecard::|Bitmomo_Regime_State_Store::|Bitmomo_Pro_[A-Za-z]+::/.test(btcIntelligencePage)
    && /TIDAK SESUAI/.test(btcIntelligencePage)
    && /BELUM DINILAI/.test(btcIntelligencePage)
    && !/do_shortcode_tag|bitmomo_btc_intelligence_public_copy|strtr\s*\(/.test(btcIntelligencePlugin)
    && !/Bitmomo_Btc_Opportunity_UI/.test(btcIntelligencePlugin)
);
check(
  'BTC Free Major Brief preserves Now + Change + Meaning + One Watch without exposing arbitrary monitoring text',
  /MAJOR BRIEF/.test(btcIntelligencePage)
    && /APA YANG BERUBAH\?/.test(btcIntelligencePage)
    && /MENGAPA PENTING/.test(btcIntelligencePage)
    && /PANTAU BERIKUTNYA/.test(btcIntelligencePage)
    && /directional_consistency/.test(btcIntelligencePage)
    && /structure_continuity/.test(btcIntelligencePage)
    && /count\( \$lines \) >= 2/.test(btcIntelligencePage)
    && /array_slice\([\s\S]*?key_drivers[\s\S]*?0, 2/.test(btcIntelligencePage)
    && !/monitoring_conditions\s*\]|scenario_contract\s*\]|expected_range\s*\]|invalidation\s*\]/.test(btcPageOutput)
);
check(
  'BTC fast layer states the truthful cadence rather than claiming five-minute canonical evaluations',
  /evaluasi 15 menit dari candle 5 menit/.test(btcIntelligencePage)
    && !/evaluasi(?: canonical)? (?:setiap )?5 menit/i.test(btcPageOutput)
);
check(
  'BTC accountability boundary is read-only and result-neutral',
  /recorded_live/.test(btcAccountability)
    && /window_missed/.test(btcAccountability)
    && /ORIGINAL_META/.test(btcAccountability)
    && !/update_post_meta|delete_post_meta|wp_update_post|wp_insert_post|wp_delete_post/.test(btcAccountability)
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
check('BTC Intelligence track record states the exact +24h evaluation rule', /\+24 jam/.test(btcIntelligencePage));
check('BTC Intelligence keeps methodology secondary and one Pro action', /<details class="bm-bi__details"/.test(btcIntelligencePage) && /Lihat Bitmomo Pro/.test(btcIntelligencePage));
check(
  'BTC Intelligence exposes Decision Ledger and a policy-bound 48h delayed Pro archive',
  /Decision Ledger/.test(btcIntelligencePage)
    && /TERTUNDA ≥ %d JAM/.test(btcIntelligencePage)
    && /const PROOF_DELAY_HOURS\s*=\s*48/.test(btcAccountability)
);

check('/pro sales page is public and does not read entitlement state', /add_shortcode\(\s*'bitmomo_pro_sales'/.test(proSales) && !/bitmomo_user_has_pro_access|get_current_user_id|Bitmomo_Pro_Briefs::get_current_brief_for_display/.test(proSales));
check('/pro sales page renders one whitelist/purchase path from canonical checkout URL', /bitmomo_pro_get_checkout_url\(\)/.test(proSales) && /Bitmomo_Pro_Whitelist::instance\(\)->render_widget/.test(proSales));
check(
  '/pro sales path is current-product first and removes speculative roadmap theatre',
  /Decision View BTC memetakan Expected Range/.test(proSalesOutput)
    && /Analisis Pro aktif tidak ditampilkan pada halaman publik/.test(proSalesOutput)
    && !/ROADMAP — SEGERA HADIR|Altcoin Intelligence|Daily Alpha Discovery|11 AI Analysts|Watchtower/.test(proSalesOutput)
    && !/24\/7|real-time|real time/.test(proSalesOutput)
);
check(
  '/pro sales copy avoids internal pipeline and generic capability language',
  !/Bagaimana Bitmomo mengubah data menjadi intelligence|Harga, struktur pasar, funding\/basis|AI sebagai alat untuk membantu compression/i.test(proSalesOutput)
    && !/order book|sinyal on-chain BTC dikumpulkan secara berkelanjutan/i.test(proSalesOutput)
);
const proRenderMatch = proSales.match(/public function render_sales[\s\S]*?return ob_get_clean\(\);/);
const proRender = proRenderMatch ? proRenderMatch[0] : '';
const todayIndex = proRender.indexOf('render_what_exists_today()');
const exampleIndex = proRender.indexOf('render_product_proof()');
const comparisonIndex = proRender.indexOf('render_free_vs_pro()');
const accountabilityIndex = proRender.indexOf('render_accountability()');
const conversionIndex = proRender.indexOf('render_founding_economics()');
const faqIndex = proRender.indexOf('render_buyer_faq()');
check(
  '/pro tells the proof-first focused buyer journey in canonical order',
  exampleIndex > -1 && todayIndex > exampleIndex && comparisonIndex > todayIndex
    && accountabilityIndex > comparisonIndex && conversionIndex > accountabilityIndex && faqIndex > conversionIndex
);
check(
  '/pro renderer has no legacy generic problem, pipeline, market-experience or roadmap calls',
  !/render_context_problem|render_intelligence_flow|render_market_experience|render_roadmap/.test(proRender)
);
check('/pro removes duplicate final conversion block structurally, not with CSS', !/render_final_cta\s*\(/.test(proSalesOutput) && !/bm-pro-sales__final-cta/.test(publicSurfacesCss));
check('/pro pricing terms match M2 founding package', /Rp149\.000/.test(proSales) && /Rp1\.490\.000/.test(proSales) && /const SEAT_CAP\s*=\s*149/.test(proSales) && /const BATCH_ONE\s*=\s*25/.test(proSales));
check('/pro avoids placeholder preview values', !/XX%|\$XX,XXX|\(placeholder\)|Contoh Tampilan Decision View/.test(proSales));
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
  'Launch-critical SEO titles remain product-and-research led',
  /Bitmomo — Bitcoin Market Intelligence & Research/.test(themeFunctions) && /Bitmomo Pro — BTC Market Intelligence/.test(themeFunctions)
    && /BTC Intelligence — Bitmomo/.test(themeFunctions) && /rank_math\/frontend\/title/.test(themeFunctions) && /pre_get_document_title/.test(themeFunctions)
);
check(
  'Launch-critical SEO descriptions use institutional visitor language rather than engine or translation artifacts',
  /Bitmomo merangkum kondisi BTC, faktor pasar utama, perubahan penting, dan riwayat evaluasi/.test(themeFunctions)
    && /kondisi yang dapat mengubah tesis pasar/.test(themeFunctions)
    && /Lihat kondisi BTC saat ini, perubahan penting, konteks 30 hari, riwayat evaluasi/.test(themeFunctions)
    && /bukti historis Bitmomo Pro/.test(themeFunctions)
    && !/alasan utama|berbasis evidence|\bthesis\b|Opportunity, Directional Bias, Confidence, Market State/i.test(themeFunctions)
);
check(
  'Legacy newsletter modal is structurally disabled globally',
  /public function render_mailpoet_modal\(\)[\s\S]*?return;/.test(frontendTrait) && !/bm-subscribe-modal|bm-subscribe-dialog/.test(frontendTrait)
);
check(
  'Homepage Research stays BTC-first, qualified, and evidence-led',
  /array\( 'bitcoin', 'makro', 'market-structure' \)/.test(research)
    && /wp_trim_words\( get_the_excerpt\(\), 24/.test(research)
    && /get_term_by\( 'slug', 'ai-lab', 'post_tag' \)/.test(research)
    && /tag__not_in/.test(research)
    && /Riset yang membentuk cara Bitmomo membaca pasar\./.test(research)
    && /tesis yang dapat diuji/.test(research)
    && !/Baca selengkapnya/.test(research)
);
check('BTC Intelligence loads repeat-use telemetry only on the product page', /bitmomo_enqueue_btc_retention_telemetry/.test(themeFunctions) && /is_page\('btc-intelligence'\)/.test(themeFunctions) && /bitmomo-retention\.js/.test(themeFunctions));
check(
  'BTC repeat-use/proof telemetry measures only useful product signals',
  /btc_intelligence_view/.test(retentionJs) && /btc_intelligence_return_visit/.test(retentionJs)
    && /btc_methodology_expand/.test(retentionJs) && /btc_pro_interest/.test(retentionJs)
    && /btc_decision_ledger_view/.test(retentionJs) && /btc_pro_archive_view/.test(retentionJs)
    && /btc_pro_archive_expand/.test(retentionJs) && /btc_intelligence_section_nav/.test(retentionJs)
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