import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const check = (label, condition) => {
  if (!condition) failures.push(label);
  console.log(`[${condition ? 'PASS' : 'FAIL'}] ${label}`);
};

const frontPage = read('website/wp-content/themes/bitmomo-child-v3/front-page.php');
const infraCss = read('website/wp-content/themes/bitmomo-child-v3/assets/css/home-infrastructure.css');
const infraLogos = read('website/wp-content/themes/bitmomo-child-v3/assets/images/infrastructure-logos.svg');
const hero = read('website/wp-content/themes/bitmomo-child-v3/template-parts/home-hero.php');
const how = read('website/wp-content/themes/bitmomo-child-v3/template-parts/how-it-works.php');
const whitelistHome = read('website/wp-content/themes/bitmomo-child-v3/template-parts/whitelist.php');
const researchHome = read('website/wp-content/themes/bitmomo-child-v3/template-parts/research.php');
const researchHub = read('website/wp-content/themes/bitmomo-child-v3/template-parts/research-hub.php');
const templateFunctions = read('website/wp-content/themes/bitmomo-child-v3/inc/template-functions.php');
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

check('Homepage demonstrates current intelligence before secondary explanation',
  hero.includes('Apa yang berubah di Bitcoin hari ini?') &&
  hero.includes('Bitmomo menganalisis kondisi pasar, perubahan antar-brief, dan kekuatan bukti.') &&
  hero.includes('Analisis terbaru tampil langsung di halaman ini dan disimpan untuk evaluasi.') &&
  hero.includes('BTC MARKET VIEW') && hero.includes('>BIAS<') && hero.includes('>CONFIDENCE<') &&
  hero.includes('>FAKTOR UTAMA<') && hero.includes('<strong>SUMBER DATA</strong>') &&
  hero.includes('APA YANG TERJADI') && hero.includes('APA YANG BERUBAH') &&
  hero.includes('MENGAPA PENTING') && hero.includes('PANTAU BERIKUTNYA')
);
check('Homepage delayed intelligence fails closed instead of rendering stale current market values',
  hero.includes("$bm_current_available = $bm_snapshot_available && 'fresh' === $bm_status;") &&
  hero.includes("$bm_delayed = $bm_snapshot_available && 'delayed' === $bm_status;") &&
  hero.includes("$bm_bias = $bm_current_available") &&
  hero.includes("$bm_confidence = $bm_current_available") &&
  hero.includes("$bm_price = $bm_current_available") &&
  hero.includes("$bm_drivers = $bm_current_available") &&
  hero.includes("$bm_delayed ? 'Ditahan'") &&
  hero.includes('Faktor pasar terbaru tidak ditampilkan karena Major Brief sedang tertunda.') &&
  hero.includes('Bias terbaru tidak ditampilkan dari brief yang tertunda.') &&
  hero.includes('Confidence terbaru ditahan sampai Major Brief kembali valid.') &&
  hero.includes('Observasi terverifikasi terakhir.')
);
check('Homepage avoids casual, literal-translation and internal-engineering copy',
  !/ALASAN UTAMA|Arah evidence|Konsistensi evidence|\bmeleset\b|\bpembacaan\b|Buka pembacaan lengkap|menavigasi berikutnya|standar freshness|Referensi current|snapshot tertunda|quality gate|fail-closed/i.test(hero)
);
check('Homepage proves accountability before explaining the product mechanism',
  how.includes('BUKTI, BUKAN KLAIM') &&
  how.includes('30D STATE TAPE') &&
  how.includes('DECISION LEDGER') &&
  how.includes('ARSIP PRO ≥48 JAM') &&
  how.includes('EXPECTED RANGE') &&
  how.indexOf('BUKTI, BUKAN KLAIM') < how.indexOf('CARA KERJA BITMOMO') &&
  how.includes('Analisis terdahulu yang sudah dievaluasi') &&
  how.includes('Belum ada hasil yang sudah dapat dievaluasi.') &&
  how.includes('Data pasar menjadi analisis yang dapat diuji.') &&
  how.includes('01 · PAHAMI SEKARANG') &&
  how.includes('02 · PETAKAN PERUBAHAN') &&
  how.includes('03 · EVALUASI HASIL') &&
  how.includes('Data yang tidak memenuhi standar tidak dipaksakan menjadi analisis.') &&
  !/PEMBACAAN TERBARU|Data pasar menjadi pembacaan|mature outcome|Data compression|quality gate|six-stage|11 AI Analysts/i.test(how)
);
check('Homepage infrastructure uses local visual marks with truthful source semantics',
  frontPage.includes('DATA &amp; INFRASTRUKTUR') &&
  frontPage.includes('SUMBER DATA PASAR') &&
  frontPage.includes('RISET &amp; ANALISIS') &&
  frontPage.includes('infrastructure-logos.svg') &&
  frontPage.includes("array( 'slug' => 'binance'") &&
  frontPage.includes("array( 'slug' => 'bybit'") &&
  frontPage.includes("array( 'slug' => 'glassnode'") &&
  frontPage.includes('tidak menyiratkan afiliasi, kemitraan, atau endorsement') &&
  infraCss.includes('.bm-home-infrastructure__mark') &&
  infraLogos.includes('<symbol id="binance"') &&
  infraLogos.includes('<symbol id="glassnode"') &&
  infraLogos.includes('<symbol id="dune"')
);
check('Homepage founding copy avoids urgency theater and raw product jargon',
  whitelistHome.includes('Founding Price') && whitelistHome.includes('AKTIFKAN FOUNDING MEMBERSHIP') &&
  whitelistHome.includes('Butuh skenario yang lebih lengkap? Masuk ke Bitmomo Pro.') &&
  !/KUNCI HARGA FOUNDING|Harga Founding|\binvalidation\b/i.test(whitelistHome)
);
check('Homepage Research is positioned as testable market research with localized metadata',
  researchHome.includes('Riset yang membentuk cara Bitmomo membaca pasar.') &&
  researchHome.includes('tesis yang dapat diuji') &&
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

check('Research taxonomy models desk-specific Market and AI topics',
  templateFunctions.includes("'market' => array(") &&
  templateFunctions.includes("'label' => 'Market Research'") &&
  templateFunctions.includes("'systems' => array(") &&
  templateFunctions.includes("'label' => 'AI & Intelligence Systems'") &&
  templateFunctions.includes("'inference'           => 'Inference'") &&
  templateFunctions.includes("'compute'             => 'Compute'") &&
  templateFunctions.includes("'discipline' => 'ai-systems'") &&
  templateFunctions.includes("array_intersect($filter['terms'], $topics)")
);
check('Research Hub is publication-first with Desk then Topic navigation',
  researchHub.includes('Riset pasar dan AI yang dapat diuji.') &&
  researchHub.includes('Riset Pasar · Sistem AI &amp; Intelligence') &&
  researchHub.includes('aria-label="Desk riset"') &&
  researchHub.includes('bm-research-filter--topics') &&
  researchHub.includes('bukti, konteks, batas tesis, dan evaluasi hasil') &&
  researchHub.includes('RISET TERBARU') &&
  researchHub.includes('Setiap tesis harus dapat diuji.') &&
  researchHub.includes('$bm_visible_desks') && researchHub.includes('$bm_visible_topics') &&
  researchHub.includes('bitmomo_research_focus_has_posts( $bm_filter_key, $bm_research_q )') &&
  !/Batas klasifikasi|outcome aktual|>\s*RESEARCH (?:DOMAINS|PROGRAMS)\s*</i.test(researchHub) &&
  !/Kerangka berulang untuk pasar yang kompleks|Dua disiplin\. Satu standar riset\./i.test(researchHub)
);
check('Article trust chrome remains compact and evidence-led',
  article.includes('Bukti, konteks, batas tesis, dan metode evaluasi') &&
  article.includes('Riset Pasar Terkait') &&
  !/Evidence, konteks, batas thesis|Market Research Terkait/i.test(article)
);
check('About explains the research advantage and the same Free versus Pro depth boundary',
  about.includes('Dari data pasar menjadi tesis yang dapat diuji.') &&
  about.includes('Setiap tesis harus dapat diuji.') &&
  about.includes('Ringkasan kondisi BTC saat ini, perubahan material, maknanya, dan satu konteks yang layak dipantau') &&
  about.includes('Pemantauan lengkap, Expected Range, Scenario Map, dan invalidasi tesis') &&
  about.includes('Gratis membantu memahami sekarang. Pro membantu menavigasi berikutnya.') &&
  about.includes('Decision Ledger memperlihatkan apa yang Bitmomo katakan sebelumnya') &&
  !/Bitmomo bukan portal berita|Data compression|compression →|memproduksi narasi sebanyak mungkin/i.test(about)
);
check('About reserves the commercial primary action for Founding access',
  /bm-about-button bm-about-button--primary[^>]*founding-whitelist/.test(about) &&
  !/bm-about-button bm-about-button--primary[^>]*btc-intelligence/.test(about)
);

check('About and footer avoid internal engineering language',
  !/\bpembacaan\b|CURRENT VIEW|DECISION SUPPORT|FROM RESEARCH TO PRODUCT/i.test(about) &&
  footer.includes('BUKTI SEBELUM NARASI') &&
  footer.includes('RISET YANG DAPAT DIUJI') &&
  footer.includes('DATA INVALID DITAHAN') &&
  !/FAIL CLOSED|quality gate|AI systems|\bstale\b|\bthesis\b/i.test(footer)
);
check('Pro keeps branded product primitives but localizes utility language',
  proSales.includes('BUKTI PRODUK') &&
  proSales.includes('Pemantauan Lengkap') &&
  proSales.includes('Penjelasan Confidence') &&
  proSales.includes('APA YANG BERUBAH') &&
  proSales.includes('GRATIS → PRO') &&
  proSales.includes('REKAM EVALUASI') &&
  proSales.includes('SEBELUM BERGABUNG') &&
  !/Evidence before narrative|Full Monitoring|Confidence Context|PRODUCT PROOF|WHAT CHANGED|BEFORE YOU JOIN/.test(proSales)
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

check('BTC Intelligence renderer owns final institutional terminology and valuable Free brief directly',
  btcPage.includes("'missed'       => __( 'TIDAK SESUAI'") &&
  btcPage.includes("'unscored'     => __( 'BELUM DINILAI'") &&
  btcPage.includes('MAJOR BRIEF') &&
  btcPage.includes('APA YANG BERUBAH?') &&
  btcPage.includes('MENGAPA PENTING') &&
  btcPage.includes('PANTAU BERIKUTNYA') &&
  btcPage.includes('evaluasi 15 menit dari candle 5 menit') &&
  btcPage.includes('ANALISIS SAAT INI DITAHAN — MAJOR BRIEF TERTUNDA.') &&
  btcPage.includes('STATUS DATA') &&
  btcPage.includes('bias akhir berada di') &&
  btcPage.includes('Market Pulse mengevaluasi kondisi intraday setiap 15 menit menggunakan candle 5 menit.') &&
  btcPage.includes('directional_consistency') &&
  btcPage.includes('structure_continuity') &&
  btcPage.includes('CONFIDENCE') &&
  !/PEMBACAAN SAAT INI|Pembacaan arah|pembacaan berakhir|terhadap pembacaan|pendorong pembacaan|pembacaan canonical|evaluasi canonical|freshness intraday|recorded-live|outcome-nya|window evaluasi|Canonical brief|Clock sesi|Belum ada outcome matang|outcome konklusif/i.test(btcPage) &&
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
check('Help Center owns visitor language directly and documents the two-clock BTC product accurately',
  helpCenter.includes('layanan decision support untuk BTC') &&
  helpCenter.includes('Market Pulse</strong> mengevaluasi kondisi intraday setiap 15 menit dari candle 5 menit') &&
  helpCenter.includes('Major Brief</strong> terbit pada anchor US Post-Close sekitar 20.10 New York dan US Pre-Open sekitar 08.10 New York') &&
  helpCenter.includes('jam WIB dapat bergeser satu jam ketika daylight-saving AS berubah') &&
  helpCenter.includes('BTC Intelligence gratis sudah cukup untuk memahami kondisi sekarang, perubahan material, mengapa perubahan itu penting, dan satu konteks yang layak dipantau') &&
  helpCenter.includes('Mengapa riwayat Market State belum selalu berisi 30 hari?') &&
  !/Morning Intelligence|US Session Intelligence|Watchtower direncanakan sebagai sistem monitoring tambahan dan belum tersedia saat ini|quality gate|Bukan sinyal buy \/ sell/i.test(helpCenter)
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