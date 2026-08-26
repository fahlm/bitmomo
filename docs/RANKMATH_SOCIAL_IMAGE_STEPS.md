# Rank Math — Cara Mengatur Default Social Share Image

Status: dokumen panduan, bukan perubahan kode/konfigurasi. Langkah di bawah harus dijalankan manual oleh founder di wp-admin. Belum di-commit/push.

## Konteks

Audit sebelumnya (production-readiness audit) memverifikasi langsung dari halaman depan bitmomo.id bahwa tag `og:title`, `og:description`, `og:type`, `og:url`, dan `og:locale` semuanya ada dan terisi benar, tetapi `og:image` dan `twitter:image` tidak ditemukan. Akibatnya, saat tautan ke bitmomo.id dibagikan di X/Twitter atau platform lain, tidak ada gambar pratinjau yang muncul.

## Aset gambar

Satu-satunya gambar bermerek yang terdeteksi di HTML live adalah `cropped-ChatGPT-Image-Aug-22-2025-11_21_04-PM-270x270.png` (dipakai sebagai site icon, ukuran 270×270) — terlalu kecil/persegi untuk og:image (idealnya ~1200×630, rasio ~1.91:1).

**Gambar 1200×630 sudah dibuat**: `docs/assets/bitmomo-og-image.png`. Menggunakan warna brand asli dari `custom.css` (--bg, --teal, --cta, --ink, --muted) dan tagline yang sama persis dengan title tag live saat ini ("Insight AI & Crypto Terbaru untuk Indonesia"). Belum di-commit ke git -- file gambar biasa, bukan diperlukan di repo, cukup diunggah langsung ke Media Library WordPress lewat langkah di bawah.

**Catatan terpisah, perlu perhatian founder**: file logo asli tema di `website/wp-content/themes/bitmomo-child-v3/assets/images/bitmomo-logo.png` ternyata corrupt/tidak bisa dibuka (dikonfirmasi gagal decode dengan tiga alat berbeda: PIL, ImageMagick, ffmpeg -- semua melaporkan data PNG tidak valid). Ini tidak menghalangi og:image (gambar di atas dibuat tanpa logo tersebut, murni tipografi), tetapi berarti logo asli kemungkinan juga tidak tampil dengan benar di tempat lain yang memakai file yang sama. Ini bukan sesuatu yang saya perbaiki di sesi ini (di luar cakupan tugas SEO/konten) -- perlu diperiksa terpisah, kemungkinan logo perlu diekspor ulang dari sumber aslinya.

## Langkah di wp-admin (setelah gambar tersedia)

Menu Rank Math bisa sedikit berbeda penamaannya tergantung versi plugin — jika label tidak persis sama, cari tab dengan kata kunci yang disebut di bawah.

**A. Set gambar khusus untuk homepage (memperbaiki masalah yang teraudit langsung):**
1. wp-admin → **Rank Math SEO** → **Titles & Meta**.
2. Buka tab **Homepage**.
3. Cari field **Social** / **Thumbnail** (biasanya berlabel "Homepage Thumbnail" atau serupa).
4. Unggah/pilih gambar 1200×630 yang sudah disiapkan founder.
5. Simpan perubahan.

**B. Set default sitewide (fallback untuk halaman/artikel lain yang belum punya featured image):**
1. wp-admin → **Rank Math SEO** → **Titles & Meta**.
2. Buka tab **Global Meta** (atau tab **Social Meta**, tergantung versi).
3. Cari field **Default Social Image** / **Open Graph Image**.
4. Unggah/pilih gambar yang sama atau variannya.
5. Simpan perubahan.

## Verifikasi setelah pengaturan

Setelah disimpan, minta founder (atau minta saya di sesi mendatang bila alat browser tersedia) memeriksa hasilnya lewat salah satu cara berikut:
- Google/Facebook/Twitter card validator (mis. Facebook Sharing Debugger, Twitter Card Validator) — masukkan URL bitmomo.id dan lihat apakah gambar pratinjau muncul.
- Lihat langsung sumber halaman (`view-source:`) dan cari `og:image` / `twitter:image` untuk memastikan URL gambar sudah terisi.

Tidak ada file kode yang perlu diubah untuk langkah ini — seluruhnya adalah pengaturan di wp-admin.
