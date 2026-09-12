# Konten: Kebijakan Privasi

Status: source-of-truth draft untuk sinkronisasi ke WordPress `/kebijakan-privasi/` setelah final operational checks. Bagian yang belum dapat dibuktikan dari source diberi catatan verifikasi, bukan ditebak.

---

## SEO METADATA

- **SEO title**: Kebijakan Privasi | Bitmomo
- **Meta description**: Kebijakan privasi Bitmomo untuk website, newsletter, Founding Whitelist, akun Bitmomo Pro, dan telemetry produk.
- **Suggested slug**: `kebijakan-privasi`

---

# Kebijakan Privasi

Kebijakan ini menjelaskan kategori data yang dapat diproses Bitmomo ketika Anda menggunakan bitmomo.id, mendaftar newsletter atau Founding Whitelist, memiliki akun Bitmomo Pro, atau menggunakan fitur produk terkait.

## Data Teknis Website

Server dan infrastruktur WordPress/hosting dapat memproses data teknis yang lazim diperlukan untuk mengirim halaman, menjaga keamanan, mendiagnosis error, dan mencegah penyalahgunaan. Data tersebut dapat mencakup alamat IP, waktu request, user agent, URL yang diminta, dan informasi teknis lain yang tersedia pada level server.

Implementasi Founding Whitelist juga memakai rate limit per-IP. Kode whitelist membentuk key transient dari hash alamat IP untuk membatasi percobaan dalam jendela waktu pendek; whitelist tidak menyimpan raw IP sebagai field permanen pada record pendaftaran.

## Founding Membership Whitelist

Jika Anda bergabung dengan Founding Whitelist Bitmomo Pro, sistem dapat menyimpan:

- alamat email;
- nama depan, jika Anda memilih mengisinya;
- waktu pendaftaran;
- waktu persetujuan menerima komunikasi terkait peluncuran/akses Bitmomo Pro;
- sumber form/landing page;
- parameter UTM seperti source, medium, dan campaign jika tersedia;
- referrer URL jika tersedia;
- status operasional whitelist, seperti waiting, invited, atau converted; dan
- klasifikasi internal untuk membedakan traffic cold/warm/test/internal/unclassified dalam evaluasi demand.

Record whitelist disimpan sebagai record privat WordPress dan tidak memiliki public URL, public REST route, atau public search surface.

### WhatsApp Opsional

Setelah mendaftar whitelist, Anda dapat memilih menambahkan nomor WhatsApp sebagai kanal pemberitahuan opsional. Jika Anda melakukannya, Bitmomo menyimpan nomor yang telah dinormalisasi dan timestamp persetujuan WhatsApp secara terpisah dari persetujuan email.

Memberikan nomor WhatsApp tidak diwajibkan untuk masuk whitelist. Menyimpan nomor sebagai kanal opsional tidak berarti setiap pesan atau notifikasi akan dikirim secara otomatis; availability kanal mengikuti sistem komunikasi yang benar-benar aktif pada saat itu.

## Email Whitelist dan Email Produk

Bitmomo saat ini memiliki email transaksional berbasis fungsi email WordPress untuk beberapa flow produk, termasuk confirmation whitelist, welcome/member access, dan daily Pro brief yang dipicu sesuai workflow produk.

Newsletter publik merupakan flow yang berbeda dan dapat menggunakan MailPoet melalui form newsletter yang tersedia di situs.

Kami tidak akan meminta seed phrase, private key, atau pembayaran crypto melalui pesan pribadi. Pendaftaran dan pembayaran resmi harus dilakukan melalui domain dan flow yang dinyatakan resmi oleh Bitmomo.

## Akun dan Membership Bitmomo Pro

Jika Anda memiliki akun Bitmomo Pro, WordPress dan plugin Pro dapat menyimpan data akun serta metadata operasional yang diperlukan untuk mengelola akses, seperti:

- alamat email dan data akun WordPress;
- status akses/member;
- jenis/sumber entitlement, termasuk status Founding Member atau test account bila relevan;
- tanggal mulai akses;
- tanggal berakhir akses jika ada;
- catatan aktivasi atau status lifecycle yang diperlukan untuk operasional membership; dan
- timestamp tertentu terkait komunikasi atau penggunaan fitur apabila diperlukan oleh flow produk.

Halaman akun publik hanya menampilkan data membership milik user yang sedang login; kode tidak menerima `user_id` dari URL/request untuk memilih data user lain.

## Telemetry Produk

Bitmomo dapat mencatat telemetry ringan untuk memahami apakah fitur digunakan dan apakah produk memberi nilai. Implementasi saat ini dirancang provider-neutral dan menghindari fingerprinting.

Sebagai contoh, retention telemetry BTC Intelligence dapat menyimpan timestamp lokal pada browser untuk mengidentifikasi kunjungan ulang tanpa menyimpan email, user ID, atau device fingerprint pada mekanisme tersebut.

Bitmomo Pro juga dapat mencatat event penggunaan tertentu untuk user yang telah login/berhak, seperti view terhadap brief, untuk evaluasi produk. Data operasional/internal seperti acquisition classification atau usage counters tidak ditampilkan kepada subscriber lain.

## Newsletter

Jika Anda mendaftar newsletter melalui form MailPoet, alamat email Anda diproses untuk mengirim newsletter yang Anda minta. Anda dapat berhenti berlangganan melalui mekanisme unsubscribe yang disediakan pada email.

Newsletter dan Founding Whitelist adalah dua purpose yang berbeda. Persetujuan masuk Founding Whitelist tidak secara otomatis berarti Anda telah memilih seluruh newsletter umum, kecuali interface pada saat pendaftaran menyatakan dan meminta persetujuan tersebut secara terpisah.

## Tautan Afiliasi

Sebagian area publik Bitmomo dapat memuat tautan referral/afiliasi. Sistem theme dapat mencatat jumlah klik CTA secara agregat berdasarkan tombol dan hari. Implementasi native tersebut tidak perlu menyimpan identitas pengunjung pada counter klik.

Setelah Anda berpindah ke situs pihak ketiga, pemrosesan data oleh situs tersebut tunduk pada kebijakan mereka sendiri.

## Cookie dan Local Storage

WordPress dapat menggunakan cookie untuk kebutuhan sesi/login dan fungsi dasar. Plugin atau fitur tertentu juga dapat menggunakan browser storage untuk fungsi produk yang dijelaskan di atas.

**Verifikasi sebelum publikasi:** status aktual Google Analytics/Site Kit/cookie pihak ketiga di production harus diperiksa pada environment yang akan diluncurkan. Draft lama menyatakan Site Kit belum terhubung, tetapi status runtime dapat berubah dan tidak boleh diasumsikan hanya dari histori repo.

Jika tracking non-esensial atau layanan pihak ketiga yang membutuhkan consent diaktifkan, kebijakan dan mekanisme consent harus diperbarui sebelum atau bersamaan dengan aktivasi tersebut.

## Penyedia dan Infrastruktur

Bitmomo berjalan di WordPress dan menggunakan layanan hosting/infrastruktur serta plugin yang diperlukan untuk mengoperasikan situs. Beberapa fitur dapat bergantung pada penyedia data pasar, email transport, caching, SEO, atau layanan teknis lain.

Daftar runtime pihak ketiga harus mengikuti sistem production yang benar-benar aktif; kebijakan ini tidak menganggap plugin yang sekadar terpasang sebagai bukti bahwa layanan eksternalnya sedang terhubung.

## Penyimpanan dan Penghapusan Data

Source code saat ini **tidak menetapkan satu automatic retention period yang berlaku untuk seluruh data whitelist, account, dan entitlement**. Karena itu kebijakan ini tidak mengarang periode seperti “30 hari” atau “selamanya”.

Sebelum publikasi final, Bitmomo perlu menetapkan operational retention policy yang konsisten untuk record whitelist, account/member, email logs, dan data administratif lainnya, termasuk data apa yang perlu dipertahankan untuk operasional, keamanan, penyelesaian transaksi, atau kewajiban hukum.

Untuk data yang dapat dihapus tanpa mengganggu kewajiban tersebut, pengguna dapat meminta peninjauan atau penghapusan dengan menghubungi **hi@bitmomo.id**.

## Keamanan dan Batasan

Bitmomo menggunakan kontrol seperti nonces, access checks, private post types, release gates, dan rate limiting pada bagian sistem tertentu. Tidak ada sistem internet yang dapat dijamin aman 100%, sehingga kontrol tersebut mengurangi risiko tetapi bukan jaminan absolut.

Jangan mengirim seed phrase, private key, atau credential sensitif kepada Bitmomo melalui email, WhatsApp, Telegram, atau form website.

## Hak dan Permintaan Pengguna

Untuk pertanyaan mengenai data Anda, perubahan detail kontak, unsubscribe, atau permintaan penghapusan/akses yang relevan, hubungi **hi@bitmomo.id**. Kami dapat meminta verifikasi yang wajar sebelum menindaklanjuti permintaan yang menyangkut data account/member.

## Perubahan Kebijakan

Kebijakan ini dapat diperbarui ketika produk, provider, data flow, atau kewajiban operasional berubah. Versi terbaru yang berlaku adalah versi yang dipublikasikan di halaman ini.

*Terakhir diperbarui: isi tanggal aktual ketika copy final disinkronkan ke WordPress production.*

---

### Launch checks yang masih wajib sebelum copy ini dipublikasikan

1. Verifikasi runtime analytics/cookie/Site Kit di production.
2. Tetapkan retention policy operasional untuk whitelist/account/entitlement/email logs.
3. Cocokkan payment provider dan data transaksi yang benar-benar dipakai saat checkout dibuka; jangan menambahkan provider ke policy sebelum dipilih/aktif.
4. Sinkronkan halaman WordPress dengan source-of-truth ini dan pastikan tidak ada copy lama yang tertinggal.