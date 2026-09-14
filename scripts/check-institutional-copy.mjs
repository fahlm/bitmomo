import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const check = (label, condition) => {
  if (!condition) failures.push(label);
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
};

const hero = read('website/wp-content/themes/bitmomo-child-v3/template-parts/home-hero.php');
const how = read('website/wp-content/themes/bitmomo-child-v3/template-parts/how-it-works.php');
const whitelistHome = read('website/wp-content/themes/bitmomo-child-v3/template-parts/whitelist.php');
const researchHome = read('website/wp-content/themes/bitmomo-child-v3/template-parts/research.php');
const researchHub = read('website/wp-content/themes/bitmomo-child-v3/template-parts/research-hub.php');
const about = read('website/wp-content/themes/bitmomo-child-v3/template-parts/about-authority.php');
const article = read('website/wp-content/themes/bitmomo-child-v3/single.php');
const functions = read('website/wp-content/themes/bitmomo-child-v3/functions.php');
const footer = read('website/wp-content/themes/bitmomo-child-v3/footer.php');
const contentTrait = read('website/wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-content.php');
const ctaConfig = read('website/wp-content/themes/bitmomo-child-v3/inc/bitmomo-cta-config.php');
const keyDrivers = read('website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-key-drivers.php');
const btcMain = read('website/wp-content/plugins/bitmomo-btc-intelligence/bitmomo-btc-intelligence.php');
const btcPage = read('website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-page.php');
const marketContextJs = read('website/wp-content/plugins/bitmomo-btc-intelligence/assets/js/market-context-explorer.js');
const proMain = read('website/wp-content/plugins/bitmomo-pro/bitmomo-pro.php');
const proSales = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php');
const proCopy = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-public-copy.php');
const proAccount = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-account.php');
const proWhitelist = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php');
const helpCenter = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-help-center.php');
const editorial = read('docs/editorial/BITMOMO_INSTITUTIONAL_COPY_SYSTEM_V1.md');

check('Canonical editorial contract exists and defines the four information levels',
  editorial.includes('DATA — observable market facts') &&
  editorial.includes('ASSESSMENT — bias, confidence') &&
  editorial.includes('SCENARIO — expected range') &&
  editorial.includes('ACCOUNTABILITY — actual results')
);

check('Homepage states the Free versus Pro boundary before secondary explanation',
  hero.includes('Pahami kondisi BTC sekarang.') &&
  hero.includes('Gratis menjelaskan kondisi sekarang. Pro memetakan apa yang perlu dipantau berikutnya.') &&
  hero.includes('BTC MARKET VIEW') && hero.includes('>BIAS<') && hero.includes('>CONFIDENCE<') &&
  hero.includes('>FAKTOR UTAMA<') && hero.includes('<strong>SUMBER DATA</strong>')
);
check('Homepage avoids casual or translation-artifact market copy',
  !/ALASAN UTAMA|Arah evidence|Konsistensi evidence|\bmeleset\b|Buka pembacaan lengkap/i.test(hero)
);
check('Homepage mechanism follows the canonical visitor lifecycle rather than internal pipeline language',
  how.includes('01 · UNDERSTAND NOW') &&
  how.includes('02 · MAP WHAT CHANGES') &&
  how.includes('03 · AUDIT THE RESULT') &&
  how.includes('Data yang tidak memenuhi standar tidak dipaksakan menjadi analisis.') &&
  !/Data compression|quality gate|six-stage|11 AI Analysts/i.test(how)
);
check('Homepage founding copy avoids urgency theater and raw product jargon',
  whitelistHome.includes('Founding Price') && whitelistHome.includes('AKTIFKAN FOUNDING MEMBERSHIP') &&
  !/KUNCI HARGA FOUNDING|Harga Founding|\binvalidation\b/i.test(whitelistHome)
);
check('Homepage Research metadata is localized',
  researchHome.includes('%d menit baca') && !researchHome.includes('%d min read')
);

check('Dynamic market factors use professional market terminology',
  keyDrivers.includes('Momentum harga menunjukkan tekanan bearish yang kuat.') &&
  keyDrivers.includes('Momentum harga menunjukkan dorongan bullish yang kuat.') &&
  keyDrivers.includes('Momentum dan struktur harga sama-sama mengonfirmasi bias bearish.') &&
  keyDrivers.includes('Struktur harga mencatat breakdown di bawah level teknikal utama.') &&
  !/cukup kuat ke arah|mendukung arah naik|tekanan ke arah turun|menembus level penting/i.test(keyDrivers)
);

check('Market Context avoids false link affordance and literal translation artifacts',
  marketContextJs.includes('Pahami BTC dalam konteks pasar yang lebih luas.') &&
  marketContextJs.includes('kapan tesis pasar berubah.') &&
  !/Expected Range <b aria-hidden="true">↗|Scenario Map <b aria-hidden="true">↗|Invalidation <b aria-hidden="true">↗|\bthesis\b/i.test(marketContextJs)
);

check('Research Hub is publication-first and communicates a testable-thesis value proposition',
  researchHub.includes('Tesis pasar yang dapat diuji.') &&
  researchHub.includes('LATEST RESEARCH') &&
  researchHub.includes('Setiap tesis harus dapat diuji.') &&
  researchHub.includes('$bm_visible_filters') &&
  researchHub.includes('Do not advertise empty research programs') &&
  !/RESEARCH DOMAINS|RESEARCH PROGRAMS|Kerangka berulang untuk pasar yang kompleks|Dua disiplin\. Satu standar riset\./i.test(researchHub)
);
check('Article trust chrome remains compact and evidence-led',
  article.includes('Bukti, konteks, batas tesis, dan metode evaluasi') &&
  article.includes('Riset Pasar Terkait') &&
  !/Evidence, konteks, batas thesis|Market Research Terkait/i.test(article)
);
check('About explains the research advantage instead of defensive category positioning',
  about.includes('Dari data pasar menjadi tesis yang dapat diuji.') &&
  about.includes('Setiap tesis harus dapat diuji.') &&
  about.includes('Decision Ledger memperlihatkan apa yang Bitmomo katakan sebelumnya') &&
  !/Bitmomo bukan portal berita|Data compression|compression →|memproduksi narasi sebanyak mungkin/i.test(about)
);
check('About reserves the commercial primary action for Founding access',
  /bm-about-button bm-about-button--primary[^>]*founding-whitelist/.test(about) &&
  !/bm-about-button bm-about-button--primary[^>]*btc-intelligence/.test(about)
);

check('SEO descriptions use canonical public terminology',
  functions.includes('faktor pasar utama, perubahan penting, dan riwayat evaluasi') &&
  functions.includes('kondisi yang dapat mengubah tesis pasar') &&
  functions.includes('platform market intelligence dan riset Bitcoin') &&
  !/alasan utama|berbasis evidence|\bthesis\b/i.test(functions)
);

check('Newsletter backend identity is environment-owned and fails closed',
  footer.includes("defined( 'BITMOMO_NEWSLETTER_FORM_ID' )") &&
  footer.includes("get_option( 'bitmomo_newsletter_form_id', 0 )") &&
  footer.includes("shortcode_exists( 'mailpoet_form' )") &&
  !footer.includes('BM_MAILPOET_FORM_ID') &&
  contentTrait.includes('newsletter_surface_available') &&
  contentTrait.includes("home_url('/')")
);

check('Dormant affiliate CTAs cannot render unless explicitly enabled',
  ctaConfig.includes('BITMOMO_AFFILIATE_CTAS_ENABLED') &&
  ctaConfig.includes('bitmomo_affiliate_ctas_enabled()') &&
  ctaConfig.includes('if ( ! bitmomo_affiliate_ctas_enabled() )')
);

check('BTC Intelligence renderer owns final institutional terminology directly',
  btcPage.includes("'missed'       => __( 'TIDAK SESUAI'") &&
  btcPage.includes("'unscored'     => __( 'BELUM DINILAI'") &&
  btcPage.includes('>FAKTOR UTAMA<') &&
  btcPage.includes('CONFIDENCE') &&
  !/strtr\s*\(/.test(btcMain) &&
  !/do_shortcode_tag/.test(btcMain)
);

check('Pro sales renderer owns final visitor language and current-product buying path',
  proSales.includes('Pahami skenario berikutnya — dan kapan tesis BTC berubah.') &&
  proSales.includes('Decision View BTC memetakan Expected Range') &&
  proSales.includes('Analisis hanya ditampilkan ketika data memenuhi standar kualitas Bitmomo.') &&
  !proSales.includes('$this->render_context_problem();') &&
  !proSales.includes('$this->render_intelligence_flow();') &&
  !proSales.includes('$this->render_market_experience();') &&
  !proSales.includes('$this->render_roadmap();') &&
  !/Altcoin Intelligence|Daily Alpha Discovery|11 AI Analysts|Watchtower/.test(proSales)
);
check('Public-copy compatibility shim performs no post-render mutation',
  proMain.includes('class-bitmomo-pro-public-copy.php') &&
  proMain.includes('Bitmomo_Pro_Public_Copy::init()') &&
  proCopy.includes('Intentionally empty. Public copy must be source-owned.') &&
  !/strtr\s*\(|do_shortcode_tag|add_filter\s*\(\s*[\'\"]gettext/.test(proCopy)
);
check('Help Center owns visitor language directly',
  helpCenter.includes('layanan decision support untuk BTC') &&
  helpCenter.includes('Mengapa riwayat Market State belum selalu berisi 30 hari?') &&
  !/quality gate|Bukan sinyal buy \/ sell/i.test(helpCenter)
);
check('Pro account and acquisition success copy avoid informal second-person language',
  proAccount.includes('Masuk untuk melihat status akses Bitmomo Pro') &&
  !/\bkamu\b/i.test(proAccount) &&
  !/\bkamu\b/i.test(proWhitelist)
);

if (failures.length) {
  console.error(`Institutional copy contract failed with ${failures.length} issue(s):`);
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('PASS institutional copy contract.');
