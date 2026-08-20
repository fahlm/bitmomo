# Bitmomo Technical Audit v1

**Status:** audit statis baseline production, tanpa perubahan source atau deployment  
**Repository:** `fahlm/bitmomo`  
**Baseline:** `d7a7149a4e0b459296f88452fecede2938d008b0` — `fix: restore exact production theme baseline`  
**Path:** `website/wp-content/themes/bitmomo-child-v3/`  
**Tanggal audit:** 20 Agustus 2026

## Ruang lingkup dan batasan

Audit mencakup delapan file berikut:

- `functions.php`
- `custom.css`
- `front-page.php`
- `single.php`
- `home.php`
- `category.php`
- `tag.php`
- `style.css`

Ini adalah **static code audit**, bukan pengujian runtime. Tidak dilakukan perubahan pada production, database, konfigurasi WordPress, plugin, CDN, cache, DNS, atau delapan file baseline. Temuan terkait Core Web Vitals, kompatibilitas plugin, aksesibilitas interaktif, dan output SEO perlu dikonfirmasi di staging dengan data nyata.

## Ringkasan eksekutif

Baseline sudah cukup untuk situs editorial sederhana: theme support dasar tersedia, output utama umumnya memakai API escaping WordPress, gambar kartu memiliki rasio tetap, dan query tambahan dibatasi. Namun source belum aman untuk langsung diperluas menjadi platform AI Market Insight dan Bitcoin Signal.

Risiko terbesar bukan satu celah keamanan kritis, melainkan kombinasi optimasi global yang rapuh, markup/template yang tidak konsisten, dan belum adanya domain model serta guardrail untuk data finansial. Prioritas awal adalah membuat staging dan safety net, lalu menstabilkan rendering/aset sebelum menambah fitur.

| Prioritas | Jumlah | Makna |
|---|---:|---|
| Critical | 3 | Harus ditangani sebelum feature development atau deployment otomatis |
| High | 8 | Masuk sprint stabilisasi pertama |
| Medium | 8 | Dikerjakan setelah fondasi stabil |
| Low | 5 | Hygiene dan penyempurnaan |

## Temuan Critical

### C-01 — Filter `script_loader_tag` membuang atribut script

**Area:** arsitektur, performance, kompatibilitas  
**File:** `functions.php` (`defer_javascript`)

Untuk setiap handle di luar allowlist, tag asli diganti menjadi hanya `<script src="..." defer></script>`. Atribut seperti `id`, `type`, `nomodule`, `integrity`, `crossorigin`, nonce CSP, dan atribut `data-*` hilang. Ini dapat mematahkan plugin, analytics, module scripts, consent manager, atau kebijakan keamanan.

**Rekomendasi:** jangan merekonstruksi tag. Gunakan WordPress Script Strategy API/`wp_script_add_data`, atau tambahkan `defer` pada tag asli secara konservatif per-handle. Buat daftar kompatibilitas dan regression test di staging.

### C-02 — Pipeline gambar dapat menghasilkan atribut `loading` ganda dan prioritas LCP yang keliru

**Area:** performance, markup correctness  
**File:** `functions.php` (`optimize_content_images`, `set_lcp_image_priority`, `optimize_thumbnail_loading`)

Filter pertama menambahkan `loading="lazy"`, lalu filter berikutnya dapat menambahkan `loading="eager" fetchpriority="high"` ke gambar pertama tanpa menghapus atribut lama. Hasilnya berpotensi memiliki dua atribut `loading`. Pemilihan “gambar pertama di content” juga tidak selalu sama dengan elemen LCP, sementara featured image sudah diperlakukan terpisah.

**Rekomendasi:** gunakan WordPress image APIs/HTML Tag Processor, tetapkan satu pemilik kebijakan gambar, dan ukur LCP per template. Jangan preload/eager lebih dari kandidat LCP yang terbukti.

### C-03 — Belum ada kontrak data dan guardrail untuk AI Market Insight / Bitcoin Signal

**Area:** arsitektur fitur, security, product risk

Saat ini semua konten memakai post/tag/category generik. Belum ada schema untuk sumber, waktu observasi, horizon, confidence, metodologi/model version, harga referensi, invalidation, status editorial, maupun audit trail. Belum ada boundary untuk secret/API provider, validation, caching, rate limit, retry, deduplication, monitoring, atau human approval. Disclaimer statis saja tidak cukup untuk output signal finansial.

**Rekomendasi:** blokir implementasi signal di theme/template. Definisikan domain model dan workflow editorial lebih dulu; tempatkan ingestion/AI di plugin atau service terpisah; simpan provenance dan versi; wajibkan review manusia, stale-data handling, disclaimer kontekstual, dan kill switch sebelum publikasi.

## Temuan High

### H-01 — Template shell terduplikasi dan memakai dua pola rendering

**Area:** arsitektur  
**File:** `front-page.php`, `single.php`, `home.php`, `category.php`, `tag.php`

Empat template menulis ulang dokumen HTML/header/nav/footer, sedangkan `tag.php` memakai `get_header()/get_footer()`. Perbaikan menu, semantic markup, analytics, consent, dan accessibility mudah menjadi tidak seragam.

**Rekomendasi:** konsolidasikan ke `header.php`, `footer.php`, dan template parts setelah snapshot visual tersedia.

### H-02 — Markup custom logo menghasilkan anchor bersarang

**Area:** HTML validity, SEO, accessibility  
**File:** `front-page.php`, `single.php`, `category.php`

`the_custom_logo()` sudah menghasilkan link, tetapi dipanggil di dalam `<a class="bm-brand">`. JavaScript di footer mencoba memperbaikinya setelah halaman dirender. Markup awal tetap invalid dan dapat memengaruhi crawler, keyboard navigation, serta layout sebelum JavaScript berjalan.

**Rekomendasi:** render custom logo tanpa wrapper anchor; gunakan wrapper non-link atau fallback link terpisah.

### H-03 — Implementasi hamburger tersebar dan state accessibility tidak konsisten

**Area:** mobile, accessibility, maintainability  
**File:** `functions.php`, `home.php`, `custom.css`

Menu dapat dibuat oleh template atau diinjeksi dari footer. Versi injeksi tidak mengelola `aria-expanded`/`aria-controls`; versi home mengelola sebagian state. Tidak ada Escape-to-close, focus return, atau focus management. Banyak selector `!important` menunjukkan konflik CSS yang ditambal.

**Rekomendasi:** satu komponen menu server-rendered, satu script enqueue, state ARIA lengkap, Escape/focus handling, dan test pada 320/375/768/1024 px serta admin bar.

### H-04 — Cache busting child CSS dinonaktifkan kembali

**Area:** performance, deployment correctness  
**File:** `functions.php`

`custom.css` diberi versi `filemtime`, tetapi filter global `filter_loader_src` menghapus query `ver` dari semua CSS/JS. Setelah deploy, CDN/browser dapat mempertahankan asset lama.

**Rekomendasi:** pertahankan version query untuk asset lokal atau gunakan nama file ber-hash dan purge cache yang eksplisit.

### H-05 — Preload berpotensi berlebih dan tidak cocok dengan resource yang dirender

**Area:** performance  
**File:** `functions.php`, `front-page.php`, `home.php`

Halaman depan dapat preload hero yang belum terlihat di markup/CSS, preload post terbaru yang belum tentu kartu pertama query `big-stories`, dan preload featured image lain. Featured image memakai ukuran `full`, sedangkan render dapat memakai `large`/`bm-card`. Ini membuang bandwidth, terutama mobile.

**Rekomendasi:** preload hanya URL/srcset kandidat LCP yang identik dengan output template; hapus preload spekulatif; validasi dengan WebPageTest/Lighthouse pada staging.

### H-06 — Query dan UI homepage tidak memiliki model section yang dapat dikembangkan

**Area:** arsitektur, AI readiness  
**File:** `front-page.php`, `home.php`

Homepage hard-coded ke tag `big-stories`; latest posts memakai alur lain. Tidak ada service/query layer, konfigurasi editorial, pagination strategy untuk insight/signal, ataupun separation antara presentation dan retrieval.

**Rekomendasi:** definisikan content types/taxonomy, query functions yang dapat diuji, dan template parts untuk story/insight/signal.

### H-07 — Output finansial belum memiliki freshness/provenance UX

**Area:** SEO, trust, AI readiness

Template artikel hanya menampilkan tanggal dan kategori pertama. Tidak ada “last updated”, sumber data, author/reviewer, methodology, confidence, horizon, timestamp harga, atau indikator stale/retracted.

**Rekomendasi:** tetapkan field wajib dan tampilkan provenance serta freshness secara eksplisit; tambah schema yang sesuai hanya setelah model data stabil.

### H-08 — Tidak ada safety net repository untuk perubahan berikutnya

**Area:** engineering, security

Dari delapan file yang diaudit tidak terlihat lint/test/build contract. Optimasi regex dan fragmentasi template membutuhkan pengujian sebelum deployment otomatis.

**Rekomendasi:** tambah PHP syntax check, WordPress Coding Standards, static analysis bertahap, CSS lint, link/markup checks, smoke test template, visual regression mobile/desktop, dan security/dependency scan di CI.

## Temuan Medium

### M-01 — Penghapusan jQuery Migrate dan dequeue asset dilakukan global

Dapat mematahkan plugin atau halaman Elementor yang masih bergantung pada asset tersebut. Terapkan berdasarkan halaman/handle setelah dependency audit, bukan global.

### M-02 — Manipulasi HTML menggunakan regex

Regex pada `the_content`, thumbnail HTML, dan subscribe link rapuh untuk variasi atribut/markup. Gunakan WordPress HTML API/Tag Processor atau APIs pada saat render.

### M-03 — External placeholder menambah dependency dan potensi privacy/performance cost

`home.php`, `category.php`, dan `single.php` memakai `placehold.co`. Gunakan asset lokal/attachment fallback dengan dimensi tetap dan cache lokal.

### M-04 — Related posts belum dioptimalkan penuh

Query related posts belum memakai `no_found_rows`, cache, atau urutan yang eksplisit; “primary category” berarti kategori pertama, bukan field editorial yang nyata.

### M-05 — Inline CSS/JS tersebar

Critical archive CSS ada langsung di `category.php`; modal dan hamburger JS dicetak dari `functions.php`; home punya script sendiri. Ini menyulitkan CSP, caching, minification, observability, dan testing.

### M-06 — Accessibility modal belum lengkap

Dialog tidak memiliki `aria-labelledby`, focus trap, focus restoration, dan semantics tombol close yang tepat. Backdrop/close memakai anchor. Lakukan audit keyboard dan screen reader.

### M-07 — Semantic metadata artikel minimal

Tidak ada author/byline, link kategori, `<time datetime>` yang konsisten, modified date, breadcrumbs, atau structured data eksplisit. Koordinasikan dengan plugin SEO untuk mencegah schema/canonical ganda.

### M-08 — Responsiveness belum teruji untuk konten editorial kompleks

CSS dasar cukup responsif, tetapi belum ada aturan yang terlihat untuk tabel lebar, embed, code block, long URL, nav panjang, safe areas, landscape, atau font scaling 200%.

## Temuan Low

### L-01 — Versi theme tidak sinkron

`style.css` menyatakan 1.0, `functions.php` 4.2, dan `custom.css` 5.0. Tetapkan satu release version.

### L-02 — Internationalization tidak konsisten

Sebagian string memakai fungsi terjemahan, sebagian hard-coded Bahasa Indonesia/Inggris (`Prev`, `Next`, heading, empty states).

### L-03 — Public health endpoint mengekspos versi theme

Endpoint tanpa autentikasi hanya mengembalikan status/version sehingga dampaknya rendah, tetapi version disclosure tidak diperlukan untuk publik. Batasi, gunakan token/allowlist, atau pindahkan ke monitoring internal.

### L-04 — Debug timing di HTML kurang representatif

Timing dimulai saat theme boot dan ditulis di footer; ini bukan end-to-end metric dan dapat membingungkan. Gunakan observability server/RUM terpisah.

### L-05 — CSS override debt

Blok hamburger mengulang selector dan menggunakan banyak `!important`. Bersihkan setelah komponen header tunggal tersedia.

## Catatan per file

| File | Observasi utama |
|---|---|
| `functions.php` | Terlalu banyak tanggung jawab: setup, asset policy, image mutation, modal, navigation, redirects, debug, health endpoint |
| `custom.css` | Fondasi responsif cukup baik; debt terbesar di override hamburger, aksesibilitas state, dan konten kompleks |
| `front-page.php` | Query terbatas dan `no_found_rows` baik; shell terduplikasi, nested logo link, query/tag hard-coded |
| `single.php` | Struktur artikel sederhana; provenance/author/updated/schema kurang, related query dan placeholder perlu perbaikan |
| `home.php` | Hamburger paling lengkap tetapi menduplikasi injeksi global; shell dan hero terduplikasi |
| `category.php` | Escaping gambar/judul cukup baik; critical CSS inline dan shell khusus menambah fragmentasi |
| `tag.php` | Satu-satunya yang memakai header/footer standard; dua layout hard-coded dan metadata minimal |
| `style.css` | Metadata child theme valid; version tidak sinkron dengan source lain |

## Hal positif yang dipertahankan

- Guard `ABSPATH`, escaping URL/atribut pada banyak output, dan API query WordPress sudah digunakan.
- Theme support dasar, nav registration, ukuran `bm-card`, dan content width sudah didefinisikan.
- Query Big Stories memakai `post_status=publish`, `ignore_sticky_posts`, dan `no_found_rows`.
- Card images memakai aspect ratio/dimensi sehingga membantu mengurangi CLS.
- Disclaimer risiko sudah ada sebagai baseline, walau belum cukup untuk signal.
- Audit statis tidak menemukan hard-coded credential atau eksekusi input user langsung pada delapan file.

## Target architecture untuk AI Market Insight + Bitcoin Signal

Fitur sebaiknya tidak ditanam langsung di child theme.

1. **Theme/presentation:** template dan components tanpa business logic atau secret.
2. **Domain plugin:** custom post types atau entity model untuk insight/signal, typed metadata, permissions, validation, editorial states, REST schema.
3. **Ingestion/service layer:** provider adapters, secret management, retries, rate limits, caching, deduplication, circuit breaker.
4. **AI pipeline:** prompt/model version, source snapshot, citations, confidence/calibration, deterministic validation, cost/latency logs.
5. **Editorial workflow:** draft → machine-validated → human-reviewed → scheduled/published → stale/retracted.
6. **Trust & safety:** timestamp, horizon, methodology, conflicts/disclaimer, stale banner, kill switch, audit log.
7. **Observability:** job health, provider freshness, publication failures, RUM/Core Web Vitals, alerting.

Minimum data contract untuk sebuah signal:

- instrument/symbol dan market/provider;
- `observed_at`, `generated_at`, `expires_at`, timezone;
- reference price dan source snapshot;
- horizon dan direction/scenario, bukan janji hasil;
- confidence beserta definisi/calibration;
- rationale dan citations;
- model/prompt/pipeline version;
- author, reviewer, approval timestamp;
- status: draft/reviewed/published/stale/retracted;
- immutable audit trail dan correction history.

## Roadmap yang direkomendasikan

### Phase 0 — Safety & staging (Critical)

- Buat staging terisolasi dengan snapshot production yang disanitasi.
- Dokumentasikan rollback, backup/restore drill, environment variables, dan deployment approval.
- Tambah branch protection dan CI baseline.
- Ambil baseline visual, Lighthouse/Core Web Vitals, query count, error log, serta plugin/theme compatibility matrix.
- **Exit criteria:** deploy/rollback staging teruji; production tidak menjadi tempat eksperimen.

### Phase 1 — Stabilization (Critical/High)

- Perbaiki script strategy dan pipeline image tanpa regex/atribut ganda.
- Konsolidasikan header/footer/menu; perbaiki nested anchors dan accessibility.
- Pertahankan cache versioning; audit preload dan dependency dequeue.
- Tambah smoke/visual/accessibility tests.
- **Exit criteria:** tidak ada console/PHP error; menu/modal keyboard-safe; no invalid duplicate attributes; performance tidak regresi.

### Phase 2 — Architecture & content model (High)

- Pisahkan domain plugin dari theme.
- Definisikan entity, taxonomy, metadata, permissions, REST contract, provenance, dan editorial workflow.
- Buat reusable template parts dan design tokens.
- **Exit criteria:** insight/signal draft dapat dibuat dan divalidasi tanpa AI/provider eksternal.

### Phase 3 — AI/data pipeline in staging (High/Medium)

- Implement provider adapters, secrets, caching, retry/rate limit, validation, citations, model versioning, observability.
- Tambahkan human approval, stale/retraction behavior, kill switch, dan cost controls.
- **Exit criteria:** replayable test fixtures; zero auto-publish; failure/stale scenarios teruji.

### Phase 4 — SEO, mobile, performance hardening (Medium)

- Tambah provenance UX, author/reviewer/updated time, breadcrumbs/schema terkoordinasi.
- Uji tabel/chart/embed, 320–1440 px, 200% zoom, keyboard/screen reader.
- Uji CWV dengan data staging realistis dan performance budgets.
- **Exit criteria:** schema valid tanpa duplikasi; accessibility dan performance budget lulus.

### Phase 5 — Controlled launch

- Canary/feature flag, manual approval, monitoring dan alerting aktif.
- Publikasikan insight terlebih dahulu; signal hanya setelah legal/compliance/product review yang sesuai.
- Review metrik kualitas, corrections, latency, cost, dan user feedback sebelum memperluas traffic.

## Definition of Done sebelum refactor dimulai

- Branch kerja dan staging tersedia.
- Backup serta rollback sudah diuji.
- Baseline screenshot dan performance metrics tersimpan.
- Plugin/theme/PHP/WordPress version matrix tercatat.
- CI minimal berjalan.
- Scope refactor Phase 1 disetujui dan tidak mencampur feature AI.
- Setiap perubahan production memerlukan review, staging verification, dan deployment approval.

## Keputusan audit

**Go** untuk membuat staging, CI, dan refactor stabilisasi terkontrol.  
**No-go** untuk menambah AI Market Insight atau Bitcoin Signal langsung ke child theme saat ini.  
**No-go** untuk deployment otomatis ke production sebelum Phase 0–1 selesai.
