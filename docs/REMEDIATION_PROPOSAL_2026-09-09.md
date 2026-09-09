# Bitmomo — Proposal Perbaikan Staging → Produksi

**Tanggal:** 9 September 2026
**Lingkup:** `seagreen-snail-158456.hostingersite.com` (staging). Produksi `bitmomo.id` tidak disentuh.
**Audiens produk:** aset manager berlisensi di family office dan perusahaan manajemen aset.

---

## 1. Ringkasan eksekutif

Diagnosis awal — "semua perbaikan hilang dan tidak ada yang bisa memulihkan" — **tidak akurat, dan masalah sebenarnya lebih serius.**

Kualitas kodenya sendiri bagus. 19.000 baris kode custom, nonce dan sanitasi konsisten di seluruh permukaan input, arsitektur fail-closed yang benar-benar fail-closed, 500+ assertion test yang ditulis dengan serius, dan dokumentasi in-line yang jauh di atas rata-rata proyek WordPress. Tidak ada satu pun error sintaks di 120 file.

Yang rusak bukan kodenya. **Yang rusak adalah jalur dari kode ke server.**

Tiga fakta yang menjelaskan semuanya:

**a. Tidak ada deployment pipeline.** Perubahan masuk ke staging lewat `wp-admin/plugin-editor.php` — mengetik langsung ke server produksi-staging melalui browser. Tidak ada build, tidak ada review, tidak ada rollback, tidak ada jejak.

**b. Lebih dari satu agen menulis ke server yang sama.** Transkrip sesi sebelumnya mencatat `functions.php` diganti dengan versi "ULTRA v4.2" berisi class dan modal MailPoet yang tidak pernah ditulis oleh sesi mana pun yang tercatat. Dua proses otonom mengedit file yang sama tanpa penguncian, tanpa version control, tanpa saling tahu. Ini bukan glitch — ini kondisi berjalan.

**c. Akibatnya, ~45 KB kode hanya hidup di server dan tidak ada di branch mana pun.**

Jadi bukan "pekerjaan hilang". Yang terjadi: **pekerjaan ada di tempat yang tidak bisa dilihat oleh alat apa pun yang melihat Git**, dan setiap agen baru yang membuka repo menyimpulkan "kosong", lalu menulis ulang di atas server — memperbesar selisihnya.

---

## 2. Bukti

### 2.1 Repo lokal tertinggal 147 commit

```
main (lokal)   a4e629c  27 Agu 2026  PR #20
origin/main    13528d3   3 Sep 2026  PR #69
git rev-list --left-right --count main...origin/main  →  0  147
```

Working directory di Mac berada di branch `docs/record-production-cron-and-freshness`, yang bercabang dari `main` lama. Branch itu hanya berisi `bitmomo-ai` dan 22 file tema — sementara `origin` sudah punya `bitmomo-pro`, `bitmomo-regime`, `bitmomo-btc-intelligence`, dan 31 file tema.

**Semua yang terlihat "hilang" di Mac, ada di GitHub.**

### 2.2 Tapi staging tidak sama dengan branch mana pun

Ukuran file live dibandingkan terhadap 84 branch di `origin`:

| File | Live | Branch terbaik | Selisih |
|---|---:|---:|---:|
| `themes/bitmomo-child-v3/custom.css` | 81.870 | 59.798 (`riset-nav-polish`) | **+22.072** |
| `themes/.../assets/js/bitmomo-frontend.js` | 15.963 | 6.636 | **+9.327** |
| `themes/bitmomo-child-v3/footer.php` | 3.959 | 822 | **+3.137** |
| `bitmomo-regime/.../class-bitmomo-regime-shortcodes.php` | 10.656 | 6.435 | **+4.221** |
| `bitmomo-btc-intelligence/.../class-…-page.php` | 52.498 | 50.598 | **+1.900** |
| `bitmomo-btc-intelligence/assets/css/…css` | 16.258 | 15.356 | **+902** |
| `bitmomo-pro/assets/js/bitmomo-pro-whitelist.js` | 8.617 | 7.694 | **+923** |
| `bitmomo-pro/assets/css/bitmomo-pro-whitelist.css` | 5.382 | 4.719 | **+663** |

Merge dua branch termaju sekalipun tidak menutup selisih — `custom.css` hasil merge 62.631 byte (dengan konflik), live 81.870.

`bitmomo-ai` adalah satu-satunya plugin yang **identik byte-per-byte** antara live dan Git. Sisanya melenceng.

### 2.3 Branch "reconcile" tidak benar-benar mereconcile

Branch `codex/final-preproduction-reconciliation` (di-commit hari ini, 9 Sep) berjudul *"chore: reconcile approved staging release changes"*. Handoff mencatat tiga perbaikan yang sudah diterapkan di staging. Ketiganya dicek terhadap branch itu:

| Perbaikan yang diklaim sudah masuk | Status di branch |
|---|---|
| `public_version_label()` — perbaikan label "Classifier belum diketahui" | **Method-nya tidak ada sama sekali** |
| `render_historical_regime()` dipindah ke atas `render_how_it_works()` | **Masih di bawah** (baris 377 vs 374) |
| Baris `bm-bi__history-note` dihapus | **Masih ada** di baris 660 |

Nol dari tiga. Orang mengira rekonsiliasi sudah selesai; sebenarnya belum. Itulah kenapa perbaikan terasa "hilang lagi" setiap kali ada yang bekerja dari Git.

### 2.4 Modal "Lihat semua riwayat" — tidak hilang, terpotong

Ini temuan yang paling langsung menjawab keluhan awal.

Di `class-bitmomo-regime-shortcodes.php` versi live, **CSS dan JS untuk chart + modal masih utuh**: `.bmreg-history-chart`, `.bmreg-history-bars`, `.bmreg-history-modal`, `.bmreg-history-dialog`, `.bmreg-history-pro-link`, plus handler klik lengkap dengan Escape-to-close dan focus management.

Yang hancur hanya badan `render_history()`. Isinya:

```php
<div class="bmreg-history">
  <?php if ( empty( $full['days'] ) ) : ?>
     <div class="bmreg-card bmreg-empty">No regime history yet.</div>
  <?php else : ?>
     <script type="application/json" class="bmreg-history-data">…</script>
  <?php endif; ?>
</div>
```

Hanya satu blok JSON. Tanpa chart, tanpa bar, tanpa tombol, tanpa modal.

Artinya: setiap pengunjung `/btc-intelligence/` mengunduh CSS dan JS untuk komponen yang markup-nya tidak pernah dicetak. Sisa lain dari edit yang terpotong masih terlihat di file — parameter `days` di-parse lalu tidak dipakai, properti `$history_instance` dideklarasikan lalu tidak pernah dibaca.

**Konsekuensinya penting:** modal itu bisa dipulihkan, bukan dibangun ulang dari tebakan. Kontraknya sudah tertulis di CSS dan JS yang selamat. (Sudah dikerjakan — lihat §8.)

### 2.5 Kode dibentuk oleh keterbatasan alat, bukan oleh desain

Komentar di `category.php` versi live, ditulis oleh salah satu agen:

> *"Kept inline in this shared file — rather than as a separate `category-riset.php` — because this environment's deploy path can only edit existing theme files, not create new ones."*

Sebuah keputusan arsitektur diambil karena editor `wp-admin` tidak bisa membuat file baru. Ini biaya nyata dari tidak punya pipeline: bukan cuma risiko kehilangan, tapi desain yang memburuk secara permanen.

---

## 3. P0 — Pemulihan & pengamanan

**Ini yang paling mendesak. Sebelum ini beres, semua perbaikan lain berisiko hilang lagi.**

| # | Tindakan | Kenapa |
|---|---|---|
| P0-1 | Tarik seluruh kode custom live (5 folder) sebagai ZIP dari hPanel, commit ke branch `rescue/staging-snapshot-2026-09-09` | Satu-satunya salinan ~45 KB kode itu ada di server. Kalau server hilang, kode hilang. |
| P0-2 | Diff snapshot itu terhadap `codex/final-preproduction-reconciliation`, review per-hunk, merge yang disetujui | Menentukan mana yang overlay staging yang sah dan mana yang kerusakan Codex |
| P0-3 | Cabut/putuskan akses tulis "Codex" ke staging sampai jalurnya jelas | Selama dua proses menulis ke server yang sama, snapshot apa pun langsung basi |
| P0-4 | Ekspor setting LiteSpeed Cache (exclusion list) ke file di repo | Form whitelist bergantung pada exclusion UCSS/defer yang hanya ada di database. Rebuild staging = form rusak. Ada 5 commit yang isinya menambal interaksi LiteSpeed; tidak satu pun menyimpan settingnya. |
| P0-5 | Hapus salinan liar `wp-content/plugins/wp-mail-smtp/bitmomo-pro 2/` | Duplikat penuh plugin Pro bersarang di dalam plugin lain. Tidak dimuat WordPress, tapi file-nya bisa diakses dan bikin bingung audit berikutnya |

---

## 4. P1 — AI engine

Engine-nya adalah produknya. Dua temuan di bawah ini bukan gaya, tapi kesalahan yang bisa dibuktikan.

### 4.1 🔴 Confidence tidak mengukur apa yang diklaim

`Bitmomo_AI_Signal_Engine::evaluate()` menghitung:

```php
$agreement  = (abs($direction) + abs($carry) + abs($structure) + abs($crowding)) / 400;
$confidence = round(min(90, max(25, 35 + ($agreement * 45) + $confirmation - $volatility_penalty)));
```

`$agreement` mengukur **besaran**, bukan **kesepakatan**. Empat axis yang saling bertentangan dengan keyakinan tinggi menghasilkan angka yang sama dengan empat axis yang sepakat sempurna.

Dibuktikan dengan menjalankan engine aslinya:

| Skenario | dir | carry | struct | crowd | score | bias | **confidence** |
|---|---:|---:|---:|---:|---:|---|---:|
| **A.** Semua axis sepakat bullish | +100 | +75 | +100 | +80 | +92 | bullish | **85** |
| **B.** Axis bertentangan maksimal | +100 | −75 | −100 | −80 | −22 | bearish | **85** |
| **C.** Tidak ada sinyal sama sekali | 0 | 0 | 0 | 0 | 0 | neutral | **35** |

Skenario A dan B menghasilkan angka confidence **identik**. Skenario C — nol informasi — tetap dilaporkan 35%.

Ini persis yang terlihat di homepage sekarang: "Momentum BTC cukup kuat ke arah **turun**" bersebelahan dengan "Struktur harga jangka pendek masih menunjukkan pola **naik**", dengan CONFIDENCE "Sedang". Menampilkan bukti yang bertentangan adalah keputusan desain yang benar dan sengaja (didokumentasikan di `Bitmomo_AI_Key_Drivers`). Yang salah adalah confidence tidak ikut turun ketika itu terjadi.

Untuk audiens yang menjual keputusan alokasi ke komite investasi, angka confidence yang tidak turun saat bukti bertentangan bukan cuma cacat teknis — itu masalah kredibilitas metodologi.

**Perbaikan yang diusulkan** — pisahkan konviksi dari koherensi:

```php
$net       = ($direction * 0.35) + ($carry * 0.15) + ($structure * 0.30) + ($crowding * 0.20);
$gross     = (abs($direction) * 0.35) + (abs($carry) * 0.15)
           + (abs($structure) * 0.30) + (abs($crowding) * 0.20);
$coherence = $gross > 0 ? abs($net) / $gross : 0.0;   // 0 = saling meniadakan, 1 = searah
$conviction = $gross / 100;                            // seberapa kuat sinyalnya

$confidence = round(min(90, max(0,
    ($coherence * $conviction * 70) + $confirmation - $volatility_penalty
)));
```

Dengan formula ini skenario B turun drastis dan skenario C jadi 0. Ini **tidak** mengubah `direction_strength`, threshold bias ±20, bobot axis, atau metodologi Expected Range — hanya cara confidence dihitung. Perlu persetujuan eksplisit karena confidence adalah angka yang dilihat pelanggan.

### 4.2 🟠 Floor confidence 25 tidak bisa dipertahankan

`max(25, …)` berarti sistem tidak pernah bisa mengatakan "saya tidak tahu". Bandingkan dengan `bitmomo-regime` yang punya `CONFIDENCE_FLOOR = 10` dan `TRANSITION_CONFIDENCE_CEILING = 65` — plugin regime melakukannya dengan benar. Turunkan floor ke 0 dan biarkan angkanya jujur.

### 4.3 🟠 Diskontinuitas di gerbang ADX

```php
if ($adx < 18) return 0;
return $sign * round(min(100, max(25, ($adx - 10) * 4)));
```

ADX 17,9 → axis direction = 0. ADX 18,1 → axis direction = 32. Loncatan 32 poin dari pergerakan 0,2. Dibuktikan: confidence 45 → 49 dari perubahan sekecil itu. Solusi: ramp linier 15–20 alih-alih step, atau hysteresis seperti yang sudah dipakai `Bitmomo_Regime_Hysteresis`.

### 4.4 🟠 Quality gate mengarang angka kelengkapan

```php
$computed_completeness = 60 + ($axis_count * 8);   // 5 axis → 100%
$completeness = isset($quality['completeness_pct']) ? … : $computed_completeness;
```

Kalau payload upstream tidak melaporkan kelengkapan, gate **mengarang 100%** dan lolos sendiri. Gate yang mengisi nilainya sendiri bukan gate. Harus fail-closed: tidak ada laporan kelengkapan = `blocked`.

### 4.5 🟠 Dua engine, dua standar

| | `bitmomo-regime` | `bitmomo-ai` signal engine |
|---|---|---|
| Threshold | Terpusat di `Bitmomo_Regime_Config` | **166 angka hardcoded inline** |
| Versi ruleset | `CLASSIFIER_VERSION = 'regime-v1'` | **Tidak ada** |
| Hysteresis | Ada (`HYSTERESIS_CONFIRMATION_STREAK`) | Tidak ada |
| State "tidak yakin" | `TRANSITION` eksplisit | Floor 25% |

Plugin regime sudah menunjukkan standar yang benar. Usulan: naikkan signal engine ke standar itu — `Bitmomo_AI_Signal_Config` + `SIGNAL_ENGINE_VERSION` yang disimpan bersama setiap record, supaya track record historis tidak berubah makna diam-diam saat threshold di-tune.

Ini juga syarat praktis untuk menjual ke aset manager: mereka akan bertanya "versi model mana yang menghasilkan angka ini". Sekarang tidak ada jawabannya.

### 4.6 🟡 Konflik metodologis carry vs direction

`carry_score()` memakai konvensi kontrarian (funding positif → skor negatif), lalu dijumlahkan linier dengan `direction_score` yang trend-following. Di bull market yang sehat, funding positif itu normal dan persisten — jadi carry akan sistematis menarik skor melawan tren yang valid. Bukan bug, tapi asumsi metodologis yang perlu dikondisikan pada regime (`bitmomo-regime` sudah menyediakan regime-nya). Butuh keputusan riset, bukan patch.

---

## 5. P2 — Backend & keamanan

### 5.1 🔴 Email whitelist bisa dibaca siapa pun yang bisa menulis post

```php
// class-bitmomo-pro-whitelist.php
'post_title' => $email,          // judul post = alamat email
…
'capability_type' => 'post',
'show_ui'         => true,
```

CPT-nya sudah dikunci dengan benar dari publik (`public => false`, `publicly_queryable => false`, `show_in_rest => false`) — itu bagus. Meta box yang menampilkan nomor WhatsApp juga sudah di-gate ke `manage_options`.

Tapi `capability_type => 'post'` berarti **list table-nya terbuka untuk siapa pun yang punya `edit_posts`** — Author, Editor, Contributor. Dan kolom Title menampilkan alamat email setiap pendaftar dalam teks polos. Begitu ada satu penulis riset lepas diberi akun, dia bisa membaca seluruh daftar Founding Member.

**Perbaikan:**
```php
'capability_type' => array( 'bm_wl_entry', 'bm_wl_entries' ),
'map_meta_cap'    => true,
'capabilities'    => array(
    'create_posts' => 'do_not_allow',   // hanya form publik yang boleh membuat
),
```
plus grant cap-nya hanya ke administrator, dan ganti `post_title` jadi bentuk ter-mask (`f***@gmail.com` + id stabil) dengan email asli tetap di meta yang ter-gate.

### 5.2 🟠 Tidak ada handler privasi sama sekali

Nol hook `wp_privacy_personal_data_exporters` / `…_erasers` di seluruh codebase. Yang disimpan: email, nama depan, nomor WhatsApp, landing page, referrer, UTM, timestamp consent. Untuk UU PDP — dan untuk klien institusional yang akan menanyakan proses penghapusan data — ini gap kepatuhan, bukan sekadar nice-to-have. Tambahkan exporter + eraser untuk CPT `bm_pro_whitelist`, plus kebijakan retensi.

### 5.3 🟠 Token webhook ada di URL path

```php
register_rest_route(self::NAMESPACE, '/tradingview/(?P<token>[A-Za-z0-9_-]{32,128})', …);
```

`hash_equals()` dipakai dengan benar (perbandingan constant-time — bagus). Tapi menaruh secret di path berarti secret itu masuk ke access log server, log proxy, dan header Referer. Dan tidak ada HMAC atas body: siapa pun yang mendapatkan token bisa mengirim data pasar sembarang yang langsung menggerakkan "intelligence" publik.

**Perbaikan:** terima header `X-Bitmomo-Signature` = HMAC-SHA256 atas raw body; pertahankan route lama sementara masa transisi, lalu matikan.

Catatan kecil: `get_transient()` lalu `set_transient()` untuk lock webhook bukan operasi atomik — dua payload bersamaan bisa lolos berdua. Risiko rendah pada kadensi harian, tapi layak dirapikan.

### 5.4 🟢 Yang sudah benar, dan sebaiknya tidak diubah

Supaya jelas mana yang tidak perlu disentuh: nonce diverifikasi di setiap handler POST/AJAX; `wp_unslash` + `sanitize_*` konsisten; tidak ada satu pun query `$wpdb` mentah; rate limiting per-IP berbasis transient tanpa menyimpan IP mentah; cap panjang meta untuk mencegah storage tak terbatas; `update_option(..., false)` supaya tidak autoload. Ini bukan kode yang perlu ditulis ulang.

---

## 6. P3 — Frontend & performa

**Konteks penting supaya tidak salah prioritas:** untuk pengunjung anonim, LiteSpeed sudah menggabungkan asset dengan sangat baik — 1 stylesheet, 2–3 script, ~65 KB HTML, tanpa React, tanpa Elementor. Performa untuk pengunjung nyata **bukan masalah besar saat ini.**

Yang justru jadi masalah adalah ketergantungannya.

### 6.1 🔴 Kebenaran situs bergantung pada setting LiteSpeed yang tidak di-version-control

Lima commit di riwayat hanya untuk menambal interaksi dengan LiteSpeed:
`Harden whitelist config against LiteSpeed` · `Exclude whitelist runtime from deferred optimization` · `Preserve homepage whitelist styles from UCSS` · `Add cache-safe whitelist config fallback` · `Keep LiteSpeed behavior unchanged`

Semua tambalan itu ada di kode. **Setting yang membuatnya bekerja ada di database.** Rebuild staging, reset plugin, atau restore dari backup lama = form whitelist rusak dan tidak ada satu pun file yang menjelaskan kenapa. Ini P0-4.

### 6.2 🟠 Tumpukan plugin terlalu besar untuk situs seperti ini

Terpasang: Elementor + ElementsKit + Prime Slider + The Plus Addons + Metform + Ultimate Addons for Gutenberg + Astra Sites + Envato Elements + Presto Player + WPForms — sementara seluruh front-end publik dirender oleh child theme custom, dan Elementor **tidak dimuat sama sekali** di halaman publik.

Lebih serius: **tiga plugin SEO terpasang bersamaan** — Rank Math (aktif), Yoast, dan All in One SEO. Dua yang tidak aktif tetap ada file-nya di disk, tetap perlu di-patch, dan tetap jadi permukaan serangan. Plugin SEO ganda juga rutin bikin tag kanonis dan schema bertabrakan begitu salah satu tidak sengaja diaktifkan.

Usulan: audit, nonaktifkan, lalu **hapus** yang tidak dipakai. Target: hanya plugin yang benar-benar dipakai halaman publik atau alur admin.

### 6.3 🟠 Judul halaman menampilkan hostname staging

`/pro/` → `Bitmomo Pro - seagreen-snail-158456.hostingersite.com`

Homepage benar karena Rank Math mengaturnya, tapi halaman lain jatuh ke `blogname` yang belum diisi. Template judul Rank Math untuk Page belum dikonfigurasi. Harus beres sebelum produksi.

### 6.4 🟡 Inkonsistensi kecil di theme assets

- `add_resource_hints()` preconnect ke `fonts.gstatic.com` padahal `optimize_assets()` sengaja mematikan Google Fonts, dan preconnect ke `cdn.bitmomo.id` yang tidak dipakai sama sekali. Dua handshake TLS terbuang per halaman.
- `enqueue_styles()` melakukan `printf('<style>…</style>')` dari dalam callback `wp_enqueue_scripts` — kebetulan jalan, tapi rapuh; seharusnya `wp_add_inline_style()`.
- `assets/images/bitmomo-logo.png` = **786 KB**, 88% dari total ukuran tema — dan tidak pernah disajikan (header pakai `get_site_icon_url()`). Bobot mati di setiap paket deploy.
- Gambar disalurkan lewat `spcdn.shortpixel.ai`. Perlu keputusan sadar apakah host pihak ketiga dapat diterima untuk audiens institusional.

### 6.5 🟡 `/pro/` menampilkan 7× "SEGERA HADIR"

Halaman penjualan produk Rp1.490.000/tahun memuat tujuh label "coming soon". Copy-nya jujur dan itu benar — tapi bagi aset manager yang mengevaluasi vendor, tujuh label belum-tersedia di halaman jual adalah masalah konversi, bukan masalah kejujuran. Pertimbangkan mengelompokkan roadmap ke satu blok "Roadmap" ketimbang menyebarnya sebagai badge di seluruh halaman. Keputusan produk, bukan engineering.

---

## 7. P4 — Proses (ini yang benar-benar menyembuhkan)

Semua di atas adalah gejala. Ini penyebabnya.

### 7.1 🔴 CI tidak menjalankan test yang sudah ditulis

Ada 17 test suite dengan 500+ assertion. CI yang ada hanya dua workflow — `theme-safety.yml` dan `regime-safety.yml` — dan **tidak satu pun menjalankan suite `bitmomo-ai`, `bitmomo-pro`, atau `bitmomo-btc-intelligence`.**

Akibat langsung: dua suite berstatus merah di branch termaju dan tidak ada yang tahu. (Sudah diperbaiki — §8.)

Selain itu trigger `push` hanya mencakup `main`, `chore/**`, `feature/**`, `fix/**` — **bukan `claude/**` atau `codex/**`**, yaitu tempat hampir semua pekerjaan sebenarnya terjadi.

**Perbaikan:** satu workflow `php-tests.yml` yang menjalankan `php tests/test-*.php` untuk keempat plugin, pada semua branch, sebagai required check.

### 7.2 🔴 Tidak ada deployment pipeline

Ini akar dari semuanya: 45 KB kode tak ter-version, arsitektur yang dibentuk keterbatasan editor, rekonsiliasi yang gagal, tabrakan antar agen.

**Usulan minimum yang cukup:**

1. GitHub Action pada push ke `main`: bundle `website/wp-content/` → deploy ke staging via SFTP/rsync.
2. Deploy dari commit, tidak pernah dari editor. `wp-admin` file editor **dimatikan permanen**:
   ```php
   // wp-config.php
   define( 'DISALLOW_FILE_EDIT', true );
   ```
   Satu baris ini sendirian mencegah seluruh kelas masalah ini terulang.
3. Setiap deploy menulis commit SHA ke sebuah opsi WP, ditampilkan di admin bar. "Versi apa yang live?" harus punya jawaban satu-baris.
4. Drift check terjadwal: bandingkan checksum file live vs commit yang di-deploy, laporkan bila beda. Kalau ada yang mengedit langsung di server, ketahuan dalam hitungan jam, bukan minggu.

### 7.3 🟠 Aturan satu penulis

Selama lebih dari satu agen otonom punya akses tulis ke staging, tidak ada snapshot yang bisa dipercaya. Tetapkan: **staging hanya ditulis oleh pipeline.** Manusia dan agen mengusulkan lewat PR. Kalau "Codex" perlu tetap ada, dia harus masuk lewat jalur yang sama.

---

## 8. Yang sudah dikerjakan di sesi ini

Sudah di-commit ke branch `rescue/staging-recovery-2026-09-09` (commit `a942ba0`), berbasis `codex/final-preproduction-reconciliation`. **Belum di-push** — menunggu persetujuan.

**Modal "Lihat semua riwayat" dipulihkan.** CSS dan JS diambil verbatim dari server (satu-satunya salinan yang selamat); markup di antaranya direkonstruksi agar memenuhi kontrak yang sudah tertulis di keduanya. Diverifikasi terhadap halaman live: markup hasil rekonstruksi disuntikkan ke `/btc-intelligence/` dari sisi browser dan dijalankan oleh CSS + handler milik server sendiri — chart tampil, klik bar memperbarui baris detail, tombol membuka modal. Server tidak disentuh.

Perbaikan yang ikut masuk: `days` dipakai lagi untuk chart sementara modal selalu menampilkan seluruh hari yang tercatat; confidence di-clamp 0–100; bias tak dikenal jatuh ke `unknown` alih-alih ditebak jadi arah; hari 0% tetap punya bar yang bisa diklik; tanggal tampil "09 Sep 2026" bukan "2026-09-09 11:59:59"; modal id unik per instance; `role="dialog"`, `aria-modal`, `aria-labelledby`, dan `aria-label` per bar ditambahkan — CSS/JS yang selamat sama sekali tidak punya lapisan aksesibilitas.

Ditambah link upsell **"Data lengkap tersedia di Bitmomo Pro →"**, yang sekaligus menutup item feedback lama yang sebelumnya tidak bisa dikerjakan karena teksnya memang belum ada di codebase mana pun.

**Test baru:** `test-bitmomo-regime-history-widget.php` — 39 assertion yang mengunci kontrak markup ↔ CSS ↔ JS, plus empty state, riwayat parsial, clamping, degradasi bias, kebocoran field diagnostik, dan guard style/script sekali-per-halaman.

**Dua suite merah diperbaiki:**

| Suite | Penyebab | Hasil |
|---|---|---|
| `test-bitmomo-pro-performance` | Fixture membuat brief dengan `post_type` default `'post'`, sedangkan `settle()` query CPT brief — 4 check gagal. Kode produksi benar, fixture-nya yang salah. | 6/6 hijau |
| `test-bitmomo-pro-sales` | Dua assertion masih menuntut `METHODOLOGY_PAGE_LIVE === false`, padahal `/btc-intelligence/` sudah live. Ditulis ulang terhadap konstantanya, bukan satu state hardcoded, supaya guard-nya tetap berlaku di kedua konfigurasi. | 88/88 hijau |

**17 suite hijau semua.**

---

## 9. Yang butuh keputusan kamu

| # | Keputusan | Kenapa aku tidak memutuskan sendiri |
|---|---|---|
| 1 | ZIP kode live dari hPanel | Aku tidak boleh memasukkan password ke form mana pun. Ini satu-satunya langkah yang harus kamu kerjakan sendiri, dan yang paling mendesak. |
| 2 | Push `rescue/staging-recovery-2026-09-09` ke GitHub? | Menulis ke remote kamu — minta izin dulu |
| 3 | Formula confidence baru (§4.1) | Confidence adalah angka yang dilihat pelanggan. Perubahan metodologi butuh persetujuan eksplisit. |
| 4 | Status "Codex" — apa itu, siapa yang menjalankan, akses tulisnya dari mana | Tanpa jawaban ini, snapshot apa pun langsung basi |
| 5 | Arah rekonsiliasi per-file: staging atau Git yang menang | Hanya kamu yang tahu keputusan visual/copy mana yang sudah disetujui |
| 6 | Konsolidasi "SEGERA HADIR" di `/pro/` (§6.5) | Keputusan produk, bukan engineering |

---

## 10. Urutan yang disarankan

1. **Hari ini** — P0-1 sampai P0-5. Amankan dulu. Semua sisanya sia-sia kalau kode masih bisa hilang.
2. **Minggu ini** — P4-1 (CI menjalankan semua test) dan `DISALLOW_FILE_EDIT`. Dua perubahan kecil yang menutup jalur kerusakan.
3. **Minggu depan** — P4-2 (pipeline deploy + drift check), P2-1 (kebocoran PII whitelist).
4. **Sebelum jual ke aset manager** — P1-1 (confidence), P1-5 (versi engine), P2-2 (privasi), P3-3 (judul halaman).
5. **Setelah stabil** — P3-2 (bersihkan plugin), P1-6 (carry vs direction, keputusan riset).

Prioritas nomor 1 dan 2 saja sudah mengubah situasi dari "kerja bisa hilang kapan saja" jadi "kerja tidak bisa hilang". Sisanya bisa dijadwalkan.
