import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const root = process.cwd();
const theme = path.join(root, 'website/wp-content/themes/bitmomo-child-v3');
const failures = [];

function read(file) { return fs.readFileSync(path.join(root, file), 'utf8'); }
function check(label, condition) {
  if (!condition) failures.push(label);
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
}
function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });
}

const header = read('website/wp-content/themes/bitmomo-child-v3/header.php');
const frontPage = read('website/wp-content/themes/bitmomo-child-v3/front-page.php');
const home = read('website/wp-content/themes/bitmomo-child-v3/home.php');
const functions = read('website/wp-content/themes/bitmomo-child-v3/functions.php');
const helpers = read('website/wp-content/themes/bitmomo-child-v3/inc/template-functions.php');
const assets = read('website/wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-assets.php');
const hero = read('website/wp-content/themes/bitmomo-child-v3/template-parts/home-hero.php');
const howItWorks = read('website/wp-content/themes/bitmomo-child-v3/template-parts/how-it-works.php');
const whitelistHome = read('website/wp-content/themes/bitmomo-child-v3/template-parts/whitelist.php');
const researchHub = read('website/wp-content/themes/bitmomo-child-v3/template-parts/research-hub.php');
const aboutAuthority = read('website/wp-content/themes/bitmomo-child-v3/template-parts/about-authority.php');
const single = read('website/wp-content/themes/bitmomo-child-v3/single.php');
const design = read('website/wp-content/themes/bitmomo-child-v3/assets/css/design-system.css');
const navCss = read('website/wp-content/themes/bitmomo-child-v3/assets/css/navigation-footer.css');
const homeCss = read('website/wp-content/themes/bitmomo-child-v3/assets/css/home.css');
const frontendJs = read('website/wp-content/themes/bitmomo-child-v3/assets/js/bitmomo-frontend.js');
const aiMain = read('website/wp-content/plugins/bitmomo-ai/bitmomo-ai.php');
const keyDrivers = read('website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-key-drivers.php');
const proMain = read('website/wp-content/plugins/bitmomo-pro/bitmomo-pro.php');
const proCopy = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-public-copy.php');
const proSales = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php');
const proHelp = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-help-center.php');
const whitelistPhp = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php');
const whitelistJs = read('website/wp-content/plugins/bitmomo-pro/assets/js/bitmomo-pro-whitelist.js');
const emailService = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-email-service.php');
const launchReadiness = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-launch-readiness.php');
const privacyDoc = read('docs/content/kebijakan-privasi.md');
const btcMain = read('website/wp-content/plugins/bitmomo-btc-intelligence/bitmomo-btc-intelligence.php');
const btcPage = read('website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-page.php');
const btcAccountability = read('website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-accountability.php');
const btcMarketContext = read('website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-market-context.php');
const btcMarketCss = read('website/wp-content/plugins/bitmomo-btc-intelligence/assets/css/market-context-explorer.css');
const account = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-account.php');
const runtime = read('config/production-runtime.json');

check('Canonical design-system layer exists and is runtime-required',
  fs.existsSync(path.join(theme, 'assets/css/design-system.css')) &&
  assets.includes('assets/css/design-system.css') &&
  assets.includes("'bitmomo-design-system'") &&
  runtime.includes('"assets/css/design-system.css"')
);
check('Institutional homepage layer exists, is loaded, and is runtime-required',
  fs.existsSync(path.join(theme, 'assets/css/home.css')) &&
  assets.includes('assets/css/home.css') &&
  assets.includes("'bitmomo-home'") &&
  runtime.includes('"assets/css/home.css"') &&
  homeCss.includes('.bm-home-hero') && homeCss.includes('.bm-home-reading') &&
  homeCss.includes('.bm-home-evidence') && homeCss.includes('.bm-home-ledger') &&
  homeCss.includes('.bm-home-pro-proof') && homeCss.includes('.bm-home-research')
);

const customCss = path.join(theme, 'custom.css');
let customGitSha = '';
try { customGitSha = execFileSync('git', ['hash-object', customCss], { encoding: 'utf8' }).trim(); } catch {}
check('Legacy custom.css is byte-frozen rather than merely size-capped', customGitSha === '7d27f9a30071f9cbc4c1d8fce7d0cd04c35be176');

const themePhp = walk(theme).filter((file) => file.endsWith('.php'));
const inlineStyleOwners = themePhp.filter((file) => /<style\b/i.test(fs.readFileSync(file, 'utf8')));
check('Theme templates contain no ad-hoc inline <style> ownership', inlineStyleOwners.length === 0);
check('Homepage hero has no private breakpoint/style island', !/<style\b/i.test(hero) && !hero.includes('Decision View'));
check('Homepage is product-first and exposes accountability proof before commitment',
  hero.includes('Buka BTC Intelligence') &&
  hero.includes('/btc-intelligence/#decision-ledger') &&
  hero.includes('Periksa rekam jejak') &&
  howItWorks.includes('BUKTI, BUKAN KLAIM') &&
  howItWorks.includes('DECISION LEDGER') &&
  howItWorks.indexOf('BUKTI, BUKAN KLAIM') < howItWorks.indexOf('HOW BITMOMO WORKS') &&
  frontPage.indexOf("template-parts/research") < frontPage.indexOf("template-parts/whitelist")
);
check('Homepage market view exposes finance-grade context without engine internals',
  hero.includes('>BTC MARKET VIEW<') && hero.includes('>BIAS<') && hero.includes('>CONFIDENCE<') &&
  hero.includes('>REFERENSI BTC<') && hero.includes('>DIPERBARUI<') && hero.includes('>FAKTOR UTAMA<') &&
  hero.includes('<strong>SUMBER DATA</strong>') &&
  hero.includes('APA YANG TERJADI') && hero.includes('APA YANG BERUBAH') &&
  hero.includes('MENGAPA PENTING') && hero.includes('PANTAU BERIKUTNYA') &&
  !/market_state_certainty|source_diagnostics|private_note|Bitmomo_Public_Intelligence_Adapter::history/.test(hero) &&
  !/\$bm_snapshot\s*\[\s*['"]market_state['"]\s*\]/.test(hero)
);
check('Homepage delayed snapshot is transparent but cannot masquerade as current intelligence',
  hero.includes("$bm_snapshot_available = is_array( $bm_snapshot ) && in_array( $bm_status, array( 'fresh', 'delayed' ), true );") &&
  hero.includes("$bm_current_available = $bm_snapshot_available && 'fresh' === $bm_status;") &&
  hero.includes("$bm_delayed = $bm_snapshot_available && 'delayed' === $bm_status;") &&
  hero.includes("$bm_bias = $bm_current_available") &&
  hero.includes("$bm_confidence = $bm_current_available") &&
  hero.includes("$bm_price = $bm_current_available") &&
  hero.includes("$bm_drivers = $bm_current_available") &&
  hero.includes('Faktor pasar terbaru tidak ditampilkan karena Major Brief sedang tertunda.') &&
  hero.includes('Bias terbaru tidak ditampilkan dari brief yang tertunda.') &&
  hero.includes('Confidence terbaru ditahan sampai Major Brief kembali valid.') &&
  hero.includes('Observasi terverifikasi terakhir.')
);
check('Homepage public copy avoids non-institutional legacy language',
  !/ALASAN UTAMA|Arah evidence|Konsistensi evidence|\bmeleset\b/i.test(hero) &&
  hero.includes('Konsistensi bukti pendukung; bukan probabilitas pergerakan harga.')
);
check('Dynamic market factors use concise professional market language',
  keyDrivers.includes('Momentum harga menunjukkan tekanan bearish yang kuat.') &&
  keyDrivers.includes('Momentum dan struktur harga sama-sama mengonfirmasi bias bullish.') &&
  keyDrivers.includes('Struktur harga mencatat breakdown di bawah level teknikal utama.') &&
  !/cukup kuat ke arah|mendukung arah naik|tekanan ke arah turun|menembus level penting/i.test(keyDrivers)
);
check('Homepage product model follows the visitor lifecycle without reverting to pipeline jargon',
  howItWorks.includes('BUKTI, BUKAN KLAIM') &&
  howItWorks.includes('30D STATE TAPE') &&
  howItWorks.includes('DECISION LEDGER') &&
  howItWorks.includes('ARSIP PRO ≥48 JAM') &&
  howItWorks.includes('EXPECTED RANGE') &&
  howItWorks.indexOf('BUKTI, BUKAN KLAIM') < howItWorks.indexOf('HOW BITMOMO WORKS') &&
  howItWorks.includes('01 · UNDERSTAND NOW') &&
  howItWorks.includes('02 · MAP WHAT CHANGES') &&
  howItWorks.includes('03 · AUDIT THE RESULT') &&
  howItWorks.includes('Data yang tidak memenuhi standar tidak dipaksakan menjadi analisis.') &&
  !/quality gate|logic deterministik|classifier|axis|funding\/basis|\bstale\b|\bthesis\b/i.test(howItWorks)
);
check('Homepage founding surface uses restrained commercial language',
  whitelistHome.includes('Founding Price') &&
  whitelistHome.includes('AKTIFKAN FOUNDING MEMBERSHIP') &&
  !/Harga Founding|KUNCI HARGA FOUNDING|\binvalidation\b/i.test(whitelistHome)
);
check('Research and article surfaces use natural Indonesian explanatory copy',
  researchHub.includes('Judul, tesis, topik…') &&
  researchHub.includes('bukti, konteks, batas tesis') &&
  single.includes('Bukti, konteks, batas tesis') &&
  !/institutional research|batas thesis|Research Bitmomo harus menjelaskan evidence/i.test(researchHub + single)
);
check('About page removes internal engineering and mixed-language filler',
  aboutAuthority.includes('platform market intelligence dan riset Bitcoin') &&
  aboutAuthority.includes('masukan, koreksi riset, atau bantuan') &&
  aboutAuthority.includes('Gratis membantu memahami sekarang. Pro membantu menavigasi berikutnya.') &&
  !/feedback|support,|outcome|uncertainty|black box|\bClaim\b/i.test(aboutAuthority)
);

check('Every route has one keyboard skip target contract',
  /class="bm-skip-link"[^>]*href="#primary"/.test(header) && /<main id="primary"/.test(frontPage)
);
check('Foundation owns reduced-motion and tokenized mobile touch comfort',
  design.includes('prefers-reduced-motion: reduce') &&
  design.includes('--bm-touch-target-mobile: 44px') &&
  design.includes('var(--bm-touch-target-mobile)') &&
  navCss.includes('var(--bm-touch-target-mobile') &&
  design.includes('.bm-skip-link')
);
check('Mobile nav traps keyboard focus while open and still closes on Escape',
  frontendJs.includes("event.key !== 'Tab'") && frontendJs.includes('menuFocusable()') && frontendJs.includes("event.key === 'Escape'")
);

check('Research nav active state consumes canonical classification, never generic category membership',
  header.includes('bitmomo_post_research_classification') &&
  header.includes("array( 'market', 'ai-systems' )") &&
  !header.includes("is_single() && has_category( 'riset' )")
);
check('Signed-in navigation can identify account state instead of always saying Masuk',
  header.includes("is_user_logged_in() ? __( 'Akun'") && header.includes('$bm_account_label')
);

check('Posts index is explicitly neutral and cannot call itself institutional Research',
  home.includes('Arsip Publikasi') && home.includes('intentionally noindex') && !home.includes('BITMOMO RESEARCH')
);
check('Qualified Research posts remain indexable with canonical URLs while legacy Riset stays noindex',
  functions.includes('function bitmomo_is_qualified_research_post()') &&
  functions.includes("in_array($classification, ['market', 'ai-systems'], true)") &&
  functions.includes('if (bitmomo_is_qualified_research_post())') &&
  functions.includes("unset($robots['noindex'], $robots['nofollow']);") &&
  functions.includes('return get_permalink((int) get_queried_object_id());') &&
  functions.includes('bitmomo_is_unclassified_legacy_research_post()')
);
check('Launch social preview contract owns canonical URL and OG/X image fallbacks',
  functions.includes('function bitmomo_public_social_preview_url()') &&
  functions.includes('function bitmomo_public_social_preview_image_url()') &&
  functions.includes('function bitmomo_render_public_social_preview_fallback()') &&
  functions.includes('<link rel="canonical"') &&
  functions.includes('<meta property="og:image"') &&
  functions.includes('<meta name="twitter:image"') &&
  functions.includes("add_action('wp_head', 'bitmomo_render_public_social_preview_fallback', 2)")
);
check('One public noindex predicate owns all utility/archive side doors',
  functions.includes('bitmomo_should_noindex_public_view') &&
  functions.includes('bitmomo_is_pro_account_page() || is_search()') &&
  functions.includes("is_home() && !is_front_page()") &&
  functions.includes("is_archive() && !is_category('riset')") &&
  functions.includes('rank_math/frontend/robots')
);

check('Pro runtime detaches its legacy duplicate SEO owner and loads the no-op compatibility shim',
  proMain.includes("remove_filter( 'rank_math/frontend/description'") &&
  proMain.includes("remove_action( 'wp_head'") &&
  proMain.includes('class-bitmomo-pro-public-copy.php') &&
  proMain.includes('Bitmomo_Pro_Public_Copy::init()')
);
check('Pro public copy is source-owned; the compatibility shim performs no post-render mutation',
  proCopy.includes('Intentionally empty. Public copy must be source-owned.') &&
  !/strtr\s*\(|do_shortcode_tag|add_filter\s*\(\s*[\'\"]gettext/.test(proCopy) &&
  proSales.includes('Founding Price berlaku selama membership tetap aktif.') &&
  proSales.includes('Analisis Pro aktif tidak ditampilkan pada halaman publik.') &&
  proHelp.includes('Bitmomo tidak melakukan backfill retrospektif hanya untuk melengkapi visualisasi.') &&
  proHelp.includes('Mengapa riwayat Market State belum selalu berisi 30 hari?')
);
check('BTC Intelligence runtime owns accountability and market context but not a second SEO layer',
  btcMain.includes('class-bitmomo-btc-intelligence-accountability.php') &&
  btcMain.includes('class-bitmomo-btc-intelligence-market-context.php') &&
  btcMain.includes('Bitmomo_Btc_Intelligence_Market_Context::init()') &&
  btcMain.includes("remove_filter( 'rank_math/frontend/description'") &&
  btcMain.includes("remove_action( 'wp_head'")
);
check('Opportunity fast polling is explicit opt-in, not an implicit plugin-load side effect',
  aiMain.includes("defined('BITMOMO_AI_OPPORTUNITY_ENABLED') && BITMOMO_AI_OPPORTUNITY_ENABLED") &&
  aiMain.indexOf("defined('BITMOMO_AI_OPPORTUNITY_ENABLED') && BITMOMO_AI_OPPORTUNITY_ENABLED") < aiMain.indexOf('Bitmomo_AI_Opportunity::register();') &&
  !/define\s*\(\s*[\'\"]BITMOMO_AI_OPPORTUNITY_ENABLED[\'\"]/.test(aiMain)
);
check('BTC Intelligence locks the valuable Free session brief and truthful fast-layer cadence',
  btcPage.includes('MAJOR BRIEF') &&
  btcPage.includes('APA YANG BERUBAH?') &&
  btcPage.includes('MENGAPA PENTING') &&
  btcPage.includes('PANTAU BERIKUTNYA') &&
  btcPage.includes('directional_consistency') &&
  btcPage.includes('structure_continuity') &&
  btcPage.includes('evaluasi 15 menit dari candle 5 menit')
);
check('BTC public accountability boundary remains read-only',
  btcAccountability.includes('recorded_live') && btcAccountability.includes('window_missed') &&
  !/update_post_meta|delete_post_meta|wp_update_post|wp_insert_post|wp_delete_post/.test(btcAccountability)
);
check('BTC market-context boundary remains read-only and cannot query protected Pro records',
  btcMarketContext.includes('Bitmomo_Public_Intelligence_Adapter::snapshot()') &&
  btcMarketContext.includes('Bitmomo_Btc_Intelligence_Accountability::decision_ledger( 20 )') &&
  !/update_post_meta|delete_post_meta|wp_update_post|wp_insert_post|wp_delete_post/.test(btcMarketContext) &&
  !/Bitmomo_Pro_Briefs|Bitmomo_Pro_Performance|_bitmomo_pro_/.test(btcMarketContext)
);
check('BTC market-context shell is server-stable and series are not color-only',
  btcMarketCss.includes('.bm-bi{max-width:var(--bm-shell-width,1180px)}') &&
  btcMarketCss.includes('.bm-mc__line.is-gold{stroke:var(--bmc-gold);stroke-dasharray:') &&
  btcMarketCss.includes('.bm-mc__line.is-eth{stroke:var(--bmc-eth);stroke-dasharray:') &&
  btcMarketCss.includes('.bm-mc__line.is-sol{stroke:var(--bmc-sol);stroke-dasharray:')
);
check('Theme runtime version remains the reconciled v4.7 contract', functions.includes("define('BM_VERSION', '4.7')"));
check('Runtime manifest includes integrated public + Telegram runtime and 126 managed files',
  runtime.includes('"expected_file_count": 126') &&
  runtime.includes('"expected_file_count": 47') &&
  runtime.includes('"expected_file_count": 14') &&
  runtime.includes('"expected_file_count": 29') &&
  runtime.includes('"assets/css/home.css"') &&
  runtime.includes('class-bitmomo-btc-intelligence-accountability.php') &&
  runtime.includes('class-bitmomo-btc-intelligence-market-context.php') &&
  runtime.includes('class-bitmomo-btc-telegram-brief.php') &&
  runtime.includes('class-bitmomo-btc-telegram-transport.php') &&
  runtime.includes('class-bitmomo-btc-telegram-publisher.php') &&
  runtime.includes('class-bitmomo-pro-founding149-acquisition.php') &&
  runtime.includes('class-bitmomo-pro-public-copy.php') &&
  runtime.includes('assets/js/market-context-explorer.js') &&
  runtime.includes('assets/css/market-context-explorer.css') &&
  runtime.includes('assets/css/accountability-surface.css')
);

check('Account flow has no pre-checkout dead end and renders human dates',
  account.includes("home_url( '/pro/#bm-pro-whitelist' )") &&
  account.includes('wp_lostpassword_url') &&
  account.includes('wp_date(') &&
  !account.includes('Aktivasi Bitmomo Pro saat ini dilakukan secara manual. Hubungi tim Bitmomo')
);

check('Whitelist exposes accessible async state and field-directed errors',
  whitelistJs.includes("form.setAttribute('aria-busy', 'true')") &&
  whitelistJs.includes("setAttribute('aria-invalid', 'true')") &&
  whitelistJs.includes("setAttribute('role', 'alert')") &&
  whitelistJs.includes('successTitle.focus()') &&
  whitelistJs.includes("waDoneEl.setAttribute('role', 'status')")
);
check('Whitelist browser acquisition context strips query strings from ordinary flow',
  whitelistJs.includes('safeUrlWithoutQuery(document.referrer)') &&
  whitelistJs.includes('window.location.origin + window.location.pathname')
);
check('Whitelist V1 fails WhatsApp acquisition closed until explicitly enabled',
  whitelistPhp.includes('BITMOMO_PRO_WHATSAPP_OPT_IN_ENABLED') &&
  whitelistPhp.includes('bitmomo_pro_whatsapp_opt_in_enabled') &&
  whitelistPhp.includes('if ( self::whatsapp_opt_in_enabled() )') &&
  whitelistPhp.includes("'whatsappEnabled'  => $whatsapp_enabled") &&
  whitelistPhp.includes("$whatsapp_enabled ? $result['post_id'] : 0") &&
  whitelistPhp.includes("home_url( '/kebijakan-privasi/' )")
);
check('Whitelist confirmation copy follows enabled channels instead of promising WhatsApp by default',
  emailService.includes("$whatsapp_enabled = method_exists( 'Bitmomo_Pro_Whitelist', 'whatsapp_opt_in_enabled' )") &&
  emailService.includes('Kami akan mengirim pemberitahuan melalui email ini saat akses dibuka.') &&
  emailService.includes('if ( $whatsapp_enabled )') &&
  emailService.includes('Tambahkan nomor WhatsApp (opsional):')
);
check('Canonical Privacy copy matches Whitelist V1 channel behavior',
  privacyDoc.includes('## WhatsApp Jika Diaktifkan') &&
  privacyDoc.includes('form publik Bitmomo tidak meminta nomor WhatsApp secara default') &&
  privacyDoc.includes('Terakhir diperbarui: 14 September 2026')
);
check('Pro public claims cannot contradict a fail-closed current intelligence state',
  proSales.includes('Decision View · produk inti Pro') &&
  proSales.includes('Analisis hanya ditampilkan ketika data memenuhi standar kualitas Bitmomo.') &&
  !proSales.includes('Decision View aktif hari ini') &&
  !proSales.includes('Decision View BTC, aktif setiap hari.')
);
check('Pro founding copy avoids lifetime and guaranteed-future-price overclaims',
  proSales.includes('Harga untuk member baru dapat berubah') &&
  proSales.includes('selama membership tersebut tetap aktif') &&
  !proSales.includes('Founding Price selamanya') &&
  !proSales.includes('Harga membership baru akan berubah')
);
check('Help Center uses one BTC product identity and no legacy Tren AI escape hatch',
  proHelp.includes("'title' => 'BTC Intelligence'") &&
  !proHelp.includes("'title' => 'BTC Daily Intelligence'") &&
  !proHelp.includes('/category/tren-ai/') &&
  proHelp.includes("add_query_arg( 'focus', 'systems'")
);
check('Public support links use the canonical configured support email',
  proSales.includes('bitmomo_pro_support_email') &&
  proHelp.includes('bitmomo_pro_support_email') &&
  !proSales.includes('mailto:hi@bitmomo.id') &&
  !proHelp.includes('mailto:hi@bitmomo.id')
);
check('Admin readiness cannot impersonate canonical staging/production authorization',
  launchReadiness.includes('PLUGIN-INTERNAL STAGING CANDIDATE') &&
  launchReadiness.includes('This screen can never authorize staging or production on its own.') &&
  launchReadiness.includes('Founding Whitelist is the public conversion path') &&
  launchReadiness.includes('Disabled/fail-closed — expected for Whitelist V1.')
);

check('Homepage product analytics keeps PII out while preserving continuation',
  frontendJs.includes('bitmomo:analytics') && frontendJs.includes('homepage_post_signup_ledger_click') &&
  !/payload\.(?:email|first_name|whatsapp|phone|user_id|client_id|device_id)\s*=/.test(frontendJs)
);

check('No speculative third-party preconnect remains without an owned dependency',
  !assets.includes('fonts.gstatic.com') && !assets.includes('cdn.bitmomo.id')
);
check('Heavy static logo fallback is gone from code and runtime tree',
  !helpers.includes('bitmomo-logo.png') && !fs.existsSync(path.join(theme, 'assets/images/bitmomo-logo.png'))
);

const oversizedImages = walk(path.join(theme, 'assets'))
  .filter((file) => /\.(?:png|jpe?g|webp|gif)$/i.test(file))
  .filter((file) => fs.statSync(file).size > 300 * 1024)
  .map((file) => `${path.relative(root, file)}=${fs.statSync(file).size}`);
check('Theme ships no >300KB static image payloads', oversizedImages.length === 0);
if (oversizedImages.length) console.error(oversizedImages.join('\n'));

if (failures.length) {
  console.error(`Terminal-grade public contract failed with ${failures.length} issue(s):`);
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('PASS terminal-grade public contract.');