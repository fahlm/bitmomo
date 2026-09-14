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
const whitelist = read('website/wp-content/themes/bitmomo-child-v3/template-parts/whitelist.php');
const researchHome = read('website/wp-content/themes/bitmomo-child-v3/template-parts/research.php');
const researchHub = read('website/wp-content/themes/bitmomo-child-v3/template-parts/research-hub.php');
const about = read('website/wp-content/themes/bitmomo-child-v3/template-parts/about-authority.php');
const article = read('website/wp-content/themes/bitmomo-child-v3/single.php');
const functions = read('website/wp-content/themes/bitmomo-child-v3/functions.php');
const keyDrivers = read('website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-key-drivers.php');
const btcMain = read('website/wp-content/plugins/bitmomo-btc-intelligence/bitmomo-btc-intelligence.php');
const marketContextJs = read('website/wp-content/plugins/bitmomo-btc-intelligence/assets/js/market-context-explorer.js');
const proMain = read('website/wp-content/plugins/bitmomo-pro/bitmomo-pro.php');
const proSales = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php');
const proCopy = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-public-copy.php');
const proAccount = read('website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-account.php');
const editorial = read('docs/editorial/BITMOMO_INSTITUTIONAL_COPY_SYSTEM_V1.md');

check('Canonical editorial contract exists and defines the four information levels',
  editorial.includes('DATA — observable market facts') &&
  editorial.includes('ASSESSMENT — bias, confidence') &&
  editorial.includes('SCENARIO — expected range') &&
  editorial.includes('ACCOUNTABILITY — actual results')
);

check('Homepage uses the canonical market-view vocabulary',
  hero.includes('BTC MARKET VIEW') && hero.includes('>BIAS<') && hero.includes('>CONFIDENCE<') &&
  hero.includes('>FAKTOR UTAMA<') && hero.includes('<strong>SUMBER DATA</strong>') &&
  hero.includes('Buka analisis lengkap →')
);
check('Homepage no longer exposes casual or translation-artifact market copy',
  !/ALASAN UTAMA|Arah evidence|Konsistensi evidence|\bmeleset\b|Buka pembacaan lengkap/i.test(hero)
);
check('Homepage mechanism language describes analysis and validation, not internal engineering',
  how.includes('intelligence yang dapat divalidasi') &&
  how.includes('Analisis tidak diterbitkan ketika data tidak memenuhi standar kualitas') &&
  !/Data bermasalah ditahan|bisa diuji|quality gate/i.test(how)
);
check('Homepage founding copy avoids urgency theater and raw product jargon',
  whitelist.includes('Founding Price') && whitelist.includes('AKTIFKAN FOUNDING MEMBERSHIP') &&
  !/KUNCI HARGA FOUNDING|Harga Founding|\binvalidation\b/i.test(whitelist)
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

check('Market Context avoids literal translation artifacts',
  marketContextJs.includes('Pahami BTC dalam konteks pasar yang lebih luas.') &&
  marketContextJs.includes('kapan tesis pasar berubah.') &&
  !/sendirian|\bthesis\b/i.test(marketContextJs)
);

check('Research Hub is publication-first and communicates a testable-thesis value proposition',
  researchHub.includes('Tesis pasar yang dapat diuji.') &&
  researchHub.includes('data, tesis, kondisi invalidasi, dan evaluasi hasil') &&
  researchHub.includes('LATEST RESEARCH') &&
  researchHub.includes('Setiap tesis harus dapat diuji.') &&
  researchHub.includes('Bukti dapat ditelusuri') &&
  !/RESEARCH DOMAINS|RESEARCH PROGRAMS|Kerangka berulang untuk pasar yang kompleks|Dua disiplin\. Satu standar riset\./i.test(researchHub)
);
check('Research Hub does not advertise empty programs as navigation',
  researchHub.includes('$bm_visible_filters') && researchHub.includes('bitmomo_post_matches_research_focus') &&
  researchHub.includes('Do not advertise empty research programs')
);
check('Article research standard uses the same evidence/tesis vocabulary',
  article.includes('Bukti, konteks, batas tesis, dan metode evaluasi') &&
  article.includes('Riset Pasar Terkait') &&
  !/Evidence, konteks, batas thesis|Market Research Terkait/i.test(article)
);
check('About explains the research advantage instead of defensive category positioning',
  about.includes('Dari data pasar menjadi tesis yang dapat diuji.') &&
  about.includes('data pasar, konteks, tesis, kondisi invalidasi, dan evaluasi hasil') &&
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
  functions.includes('bukti, tesis, sumber data, dan metode evaluasi') &&
  !/alasan utama|berbasis evidence|\bthesis\b/i.test(functions)
);

check('BTC Intelligence output is normalized to Bias/Confidence and professional verdict language',
  btcMain.includes("'>ARAH<' => '>BIAS<'" ) &&
  btcMain.includes("'>KEYAKINAN<' => '>CONFIDENCE<'" ) &&
  btcMain.includes("'MELESET' => 'TIDAK SESUAI'") &&
  btcMain.includes("'TAK DINILAI' => 'BELUM DINILAI'") &&
  btcMain.includes('Analisis arah belum dipublikasikan karena data belum memenuhi standar kualitas Bitmomo.')
);

check('Pro sales renderer owns final visitor language directly',
  proSales.includes('Pahami skenario berikutnya — dan kapan tesis BTC berubah.') &&
  proSales.includes('Decision View BTC memetakan Expected Range, skenario Base/Bull/Bear, kondisi invalidasi') &&
  proSales.includes('Analisis hanya ditampilkan ketika data memenuhi standar kualitas Bitmomo.') &&
  proSales.includes('Mengukur konsistensi bukti yang mendukung tesis; bukan probabilitas arah harga atau hasil investasi.') &&
  proSales.includes('Analisis Pro aktif tidak ditampilkan pada halaman publik.') &&
  !/\bthesis\b|quality gate|Bukan sinyal buy \/ sell|Kunci Harga Founding/i.test(proSales)
);
check('Pro buying path is current-product first and does not render roadmap theatre',
  !proSales.includes('$this->render_context_problem();') &&
  !proSales.includes('$this->render_intelligence_flow();') &&
  !proSales.includes('$this->render_market_experience();') &&
  !proSales.includes('$this->render_roadmap();') &&
  !/Altcoin Intelligence|Daily Alpha Discovery|11 AI Analysts|Watchtower/.test(proSales)
);
check('Pro compatibility language layer is Help-only, not a post-render sales owner',
  proMain.includes('class-bitmomo-pro-public-copy.php') &&
  proMain.includes('Bitmomo_Pro_Public_Copy::init()') &&
  proCopy.includes("'bitmomo_help_center' !== $tag") &&
  !proCopy.includes('bitmomo_pro_sales') &&
  !proCopy.includes("add_filter( 'gettext'")
);
check('Help compatibility copy normalizes history and product language',
  proCopy.includes('Bitmomo tidak melakukan backfill retrospektif hanya untuk melengkapi visualisasi.') &&
  proCopy.includes('Mengapa riwayat Market State belum selalu berisi 30 hari?') &&
  proCopy.includes('layanan decision support untuk BTC') &&
  proCopy.includes('ketentuan atau harga yang berbeda')
);
check('Pro account tone is concise and avoids informal kamu copy',
  proAccount.includes('Masuk untuk melihat status akses Bitmomo Pro') &&
  proAccount.includes('Akun ini belum memiliki akses Bitmomo Pro yang aktif.') &&
  !/\bkamu\b/i.test(proAccount)
);

if (failures.length) {
  console.error(`Institutional copy contract failed with ${failures.length} issue(s):`);
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}

console.log('PASS institutional copy contract.');
