<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Canonical, public FAQ source for /help and the focused /pro subset. */
class Bitmomo_Pro_Help_Center {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'bitmomo_help_center', array( $this, 'render_help_center' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_filter( 'document_title_parts', array( $this, 'filter_title' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_meta_description' ) );
		add_action( 'wp_head', array( $this, 'render_meta_description' ), 2 );
	}

	public function add_body_class( $classes ) {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bitmomo_help_center' ) ) {
			$classes[] = 'bm-help-page';
		}
		return $classes;
	}

	public function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'bitmomo_help_center' ) || has_shortcode( $post->post_content, 'bitmomo_pro_sales' ) ) ) {
			wp_enqueue_style( 'bitmomo-pro-help', BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-help.css', array(), BITMOMO_PRO_VERSION );
		}
	}

	public function filter_title( $parts ) {
		if ( is_page( 'help' ) ) {
			$parts['title'] = __( 'Help Center Bitmomo', 'bitmomo-pro' );
		}
		return $parts;
	}

	public function render_meta_description() {
		if ( is_page( 'help' ) && ! defined( 'RANK_MATH_VERSION' ) ) {
			echo '<meta name="description" content="' . esc_attr__( 'Pelajari BTC Daily Intelligence, Market State, Bitmomo Pro, Founding Membership, pembayaran, dan metodologi Bitmomo.', 'bitmomo-pro' ) . '" />' . "\n";
		}
	}

	public function filter_meta_description( $description ) {
		if ( is_page( 'help' ) ) {
			return __( 'Pelajari BTC Daily Intelligence, Market State, Bitmomo Pro, Founding Membership, pembayaran, dan metodologi Bitmomo.', 'bitmomo-pro' );
		}
		return $description;
	}

	public static function question_url( $id ) {
		return home_url( '/help/#' . sanitize_title( $id ) );
	}

	private static function q( $id, $question, $answer, $status = '' ) {
		return compact( 'id', 'question', 'answer', 'status' );
	}

	/** Paragraphs are separate array items; inline lists remain readable HTML. */
	public static function categories() {
		return array(
			'mulai-di-sini' => array(
				'title' => 'Mulai di Sini',
				'items' => array(
					self::q( 'apa-itu-bitmomo', 'Apa itu Bitmomo?', array( 'Bitmomo adalah platform BTC intelligence yang dirancang untuk mengubah banyak data dan sinyal pasar menjadi kondisi pasar, skenario, dan perubahan yang lebih mudah dipahami.', 'Bitmomo bukan layanan pengelolaan dana dan tidak menjanjikan profit.' ) ),
					self::q( 'sinyal-buy-atau-sell', 'Apakah Bitmomo memberikan sinyal buy atau sell?', array( 'Tidak dalam bentuk perintah transaksi. Bitmomo menyediakan decision support melalui Market State, Bias, Confidence, Expected Range, skenario, Thesis Invalidation, serta perubahan yang dinilai relevan.', 'Keputusan investasi tetap berada pada pengguna.' ) ),
				),
			),
			'btc-daily-intelligence' => array(
				'title' => 'BTC Daily Intelligence',
				'items' => array(
					self::q( 'apa-itu-btc-daily-intelligence', 'Apa itu BTC Daily Intelligence?', array( 'BTC Daily Intelligence adalah snapshot gratis kondisi BTC terbaru dari Bitmomo.', 'Tujuannya sederhana:', '“Apa yang sedang terjadi dengan BTC sekarang?”' ) ),
					self::q( 'jadwal-btc-daily-intelligence', 'Seberapa sering BTC Daily Intelligence diperbarui?', array( 'BTC Daily Intelligence dijadwalkan dua kali sehari:', '<strong>Morning Intelligence</strong> — sekitar 07.10 WIB<br><strong>US Session Intelligence</strong> — sekitar 19.10 WIB', 'Update hanya dianggap valid jika data memenuhi quality gate Bitmomo.' ) ),
					self::q( 'mengapa-dua-kali-sehari', 'Kenapa Bitmomo memperbarui BTC Intelligence dua kali sehari?', array( 'Kedua update memiliki fungsi yang berbeda.', 'Morning Intelligence merangkum kondisi BTC setelah pergerakan semalam dan perubahan dibanding US Session sebelumnya.', 'US Session Intelligence memberikan kondisi BTC terbaru menjelang periode penting sesi AS dan membandingkannya dengan kondisi pagi.', 'Di antara scheduled updates tersebut, Watchtower nantinya dirancang untuk membantu mendeteksi perubahan penting.' ) ),
					self::q( 'quality-gate', 'Apa yang terjadi jika data tidak memenuhi standar kualitas?', array( 'Bitmomo menggunakan quality gate sebelum intelligence dianggap valid.', 'Jika data tidak memenuhi standar, Bitmomo lebih memilih tidak menampilkan current intelligence daripada mengisi kekosongan dengan angka atau analisis yang tidak dapat dipertanggungjawabkan.' ) ),
				),
			),
			'market-state-intelligence' => array(
				'title' => 'Market State & Intelligence',
				'items' => array(
					self::q( 'apa-itu-market-state', 'Apa itu Market State?', array( 'Market State menggambarkan jenis lingkungan pasar BTC yang sedang terdeteksi oleh sistem Bitmomo.', 'Market State V1 menggunakan lima kondisi:', 'Akumulasi<br>Ekspansi<br>Distribusi<br>Kapitulasi<br>Transisi', 'Market State bukan prediksi harga secara langsung.' ) ),
					self::q( 'market-state-vs-bias', 'Apa bedanya Market State dengan Bias?', array( 'Keduanya menjawab pertanyaan yang berbeda.', 'Market State menjawab:', '“Pasar sedang berada dalam lingkungan seperti apa?”', 'Bias menjawab:', '“Arah pasar saat ini lebih condong ke mana?”', 'Bias menggunakan:', 'Bullish<br>Neutral<br>Bearish', 'Sebagai contoh, Market State dapat berada pada Akumulasi sementara Bias masih Neutral.' ) ),
					self::q( 'apa-arti-confidence', 'Apa arti Confidence?', array( 'Confidence menunjukkan seberapa kuat bukti yang mendukung kesimpulan Bitmomo pada saat intelligence dibuat.', 'Confidence yang tinggi bukan jaminan bahwa hasil tertentu akan terjadi.', 'Confidence yang lebih rendah menunjukkan adanya ketidakpastian atau conflicting evidence yang lebih besar.' ) ),
					self::q( 'market-state-30-hari', 'Apa itu Market State 30 Hari?', array( 'Market State 30 Hari adalah riwayat Market State resmi yang tercatat oleh Bitmomo.', 'Setiap tanggal hanya memiliki satu official Market State pada public history sehingga pengguna dapat melihat bagaimana kondisi pasar berkembang dari waktu ke waktu.' ) ),
					self::q( 'history-belum-30-hari', 'Kenapa Market State History belum selalu berisi 30 hari?', array( 'Bitmomo tidak membuat history palsu hanya agar visualisasi terlihat penuh.', 'Jika baru tujuh hari yang benar-benar tercatat, Bitmomo akan menampilkan:', '“7 / 30 hari tercatat”', 'History akan terisi secara bertahap seiring sistem mencatat state baru.' ) ),
				),
			),
			'bitmomo-pro' => array(
				'title' => 'Bitmomo Pro',
				'items' => array(
					self::q( 'apa-itu-bitmomo-pro', 'Apa itu Bitmomo Pro?', array( 'Bitmomo Pro adalah decision-support layer untuk BTC yang membantu Anda memahami apa yang mungkin terjadi berikutnya, apa yang dapat membatalkan thesis utama, dan apa yang berubah dibanding update sebelumnya.', 'Pro melengkapi BTC Daily Intelligence gratis dengan Expected Range, Scenario Map, Thesis Invalidation, What Changed, dan fitur Pro lainnya.' ) ),
					self::q( 'free-vs-pro', 'Apa bedanya BTC Daily Intelligence gratis dengan Bitmomo Pro?', array( 'BTC Daily Intelligence gratis menjawab:', '“Apa yang sedang terjadi sekarang?”', 'Bitmomo Pro melangkah lebih jauh untuk membantu menjawab:', '“Apa berikutnya?”<br>“Apa yang dapat membatalkan thesis?”<br>“Apa yang berubah?”' ) ),
					self::q( 'apa-itu-expected-range', 'Apa itu Expected Range?', array( 'Expected Range adalah rentang harga yang dinilai masih masuk akal berdasarkan kondisi dan data yang tersedia ketika intelligence dibuat.', 'Expected Range bukan jaminan bahwa BTC pasti tetap berada di dalam rentang tersebut.' ) ),
					self::q( 'apa-itu-scenario-map', 'Apa itu Scenario Map?', array( 'Scenario Map menyusun beberapa kemungkinan kondisi pasar menjadi:', 'Base<br>Bull<br>Bear', 'Tujuannya bukan menebak satu masa depan, tetapi membantu pengguna memahami kemungkinan jalur pasar dan kondisi yang mendukung masing-masing skenario.' ) ),
					self::q( 'apa-itu-thesis-invalidation', 'Apa itu Thesis Invalidation?', array( 'Thesis Invalidation adalah kondisi yang membuat thesis utama Bitmomo tidak lagi layak dipertahankan.', 'Analisis yang baik bukan hanya menjelaskan apa yang mungkin terjadi, tetapi juga kapan thesis tersebut perlu dianggap salah atau dievaluasi ulang.' ) ),
					self::q( 'apa-itu-what-changed', 'Apa itu What Changed?', array( 'What Changed menunjukkan perubahan penting dibanding Decision View sebelumnya.', 'Tujuannya agar pengguna tidak perlu membandingkan banyak chart atau laporan secara manual hanya untuk memahami apa yang berubah.' ) ),
				),
			),
			'founding-membership-pricing' => array(
				'title' => 'Founding Membership & Pricing',
				'items' => array(
					self::q( 'harga-bitmomo-pro', 'Berapa harga Bitmomo Pro?', array( 'Founding Membership tersedia dalam dua pilihan:', '<strong>Bulanan</strong> — Rp149.000/bulan<br><strong>Tahunan</strong> — Rp1.490.000/tahun', 'Keduanya memberikan akses ke fitur Bitmomo Pro yang sama.' ) ),
					self::q( 'apa-itu-founding-membership', 'Apa itu Founding Membership?', array( 'Founding Membership adalah membership khusus untuk member awal Bitmomo Pro.', 'Total Founding Membership dibatasi hingga 149 member.', 'Batch pertama dibuka untuk 25 member.', 'Selama membership tetap aktif, Founding Member mempertahankan founding price dan mendapatkan fitur baru yang ditambahkan ke Bitmomo Pro ketika tersedia.' ) ),
					self::q( 'bulanan-vs-tahunan', 'Apa beda paket bulanan dan tahunan?', array( 'Fitur yang diperoleh sama.', 'Perbedaannya adalah periode pembayaran.', '<strong>Bulanan:</strong><br>Rp149.000 setiap bulan.', '<strong>Tahunan:</strong><br>Rp1.490.000 untuk 12 bulan.', 'Paket tahunan memiliki harga efektif yang lebih rendah dan mengurangi kebutuhan melakukan pembayaran manual setiap bulan.' ) ),
					self::q( 'apakah-founding-price-tetap', 'Apakah harga Bitmomo Pro akan tetap Rp149.000/bulan?', array( 'Rp149.000/bulan adalah Founding Price, bukan harga reguler yang kami rencanakan untuk jangka panjang.', 'Harga publik berikutnya belum ditetapkan. Namun, berdasarkan scope produk yang sedang dikembangkan, saat ini kami memperkirakan harga reguler Bitmomo Pro nantinya berada di sekitar Rp349.000/bulan.', 'Harga tersebut masih dapat berubah sebelum pendaftaran kembali dibuka.' ) ),
					self::q( 'setelah-kuota-149-penuh', 'Apa yang terjadi setelah kuota 149 Founding Members terpenuhi?', array( 'Setelah kuota 149 Founding Members terpenuhi, pendaftaran Bitmomo Pro akan ditutup sementara untuk member baru.', 'Fokus kami akan beralih ke pengembangan produk, peningkatan kualitas intelligence, dan pengumpulan feedback dari Founding Members sebelum membuka akses kembali ke publik.', 'Kami belum menetapkan harga reguler final untuk periode setelah Founding Membership. Namun, berdasarkan scope produk yang sedang dikembangkan, kami memperkirakan harga normal Bitmomo Pro nantinya berada di kisaran Rp349.000/bulan.', 'Harga tersebut masih dapat berubah sebelum pendaftaran dibuka kembali.', 'Founding Members yang menjaga membership tetap aktif akan tetap mempertahankan founding price sesuai paket yang dipilih.' ) ),
					self::q( 'member-baru-setelah-penuh', 'Apakah Bitmomo akan menerima member baru setelah Founding Membership penuh?', array( 'Tidak untuk sementara waktu.', 'Setelah 149 tempat terisi, kami berencana menutup pendaftaran dan fokus bekerja bersama cohort Founding Members untuk menyempurnakan produk sebelum menentukan kapan akses publik dibuka kembali.' ) ),
					self::q( 'semua-produk-masa-depan', 'Apakah Founding Members mendapatkan semua produk Bitmomo di masa depan?', array( 'Tidak.', 'Founding benefit berlaku untuk fitur baru yang ditambahkan ke Bitmomo Pro, termasuk 11 AI Analysts dan Watchtower jika diluncurkan sebagai bagian dari Bitmomo Pro.', 'Produk standalone Bitmomo di masa depan dapat memiliki pricing tersendiri.' ) ),
					self::q( 'berhenti-dan-bergabung-kembali', 'Apa yang terjadi jika saya berhenti berlangganan lalu bergabung kembali?', array( 'Akses tetap tersedia sampai akhir periode yang sudah dibayar.', 'Setelah membership benar-benar berakhir, founding price sebelumnya tidak dijamin masih tersedia jika Anda bergabung kembali di kemudian hari.' ) ),
				),
			),
			'payment-cancellation' => array(
				'title' => 'Payment & Cancellation',
				'items' => array(
					self::q( 'batalkan-kapan-saja', 'Bisa dibatalkan kapan saja?', array( 'Ya.', 'Batalkan kapan saja.', 'Akses tetap aktif sampai akhir periode berlangganan yang sudah dibayar.' ) ),
					self::q( 'setelah-membatalkan', 'Apa yang terjadi setelah saya membatalkan?', array( 'Pembatalan menghentikan kelanjutan membership berikutnya.', 'Akses yang sudah dibayar tetap aktif sampai tanggal berakhirnya periode berlangganan.' ) ),
					self::q( 'kebijakan-refund', 'Apakah pembayaran dapat dikembalikan?', array( 'Secara umum, pembayaran bersifat final setelah aktivasi.', 'Pengecualian dapat berlaku untuk pembayaran ganda, kesalahan transaksi, kegagalan pemberian akses yang berasal dari sisi Bitmomo, atau kondisi lain yang diwajibkan oleh hukum atau penyedia pembayaran.' ) ),
					self::q( 'pembayaran-ganda', 'Bagaimana jika pembayaran saya terpotong dua kali?', array( 'Hubungi Bitmomo melalui kanal support resmi yang tercantum.', 'Pembayaran ganda merupakan salah satu kondisi yang dapat diproses sebagai pengecualian terhadap kebijakan pembayaran final.' ) ),
					self::q( 'setelah-pembayaran', 'Apa yang terjadi setelah pembayaran?', array( 'Selama Founding Beta, pembayaran dapat diverifikasi secara manual.', 'Setelah pembayaran dikonfirmasi, akun Bitmomo Pro akan diaktifkan dan masa akses disesuaikan dengan paket bulanan atau tahunan yang dipilih.' ) ),
				),
			),
			'ai-analysts-watchtower' => array(
				'title' => '11 AI Analysts & Watchtower',
				'statuses' => array( '11 AI ANALYSTS — IN DEVELOPMENT', 'BITMOMO WATCHTOWER — IN DEVELOPMENT', 'TELEGRAM ALERTS — IN DEVELOPMENT' ),
				'items' => array(
					self::q( 'apa-itu-11-ai-analysts', 'Apa itu 11 AI Analysts?', array( '11 AI Analysts adalah sebelas analyst AI spesialis yang sedang dikembangkan untuk membaca BTC dari perspektif berbeda dan memberikan verdict masing-masing.', 'Area analisisnya mencakup antara lain trend, volatility, momentum, market structure, derivatives, flow, sentiment, macro, historical regime, dan event risk.', 'Hasilnya dirancang untuk disatukan menjadi satu Bitmomo Decision View.' ), 'IN DEVELOPMENT' ),
					self::q( 'analysts-dan-watchtower', 'Bagaimana 11 AI Analysts dan Watchtower bekerja bersama?', array( '<strong>“11 AI Analysts membangun thesis. Watchtower menjaganya tetap relevan.”</strong>', '“Dua kali sehari, 11 analyst memberi verdict. Di antaranya, Watchtower mendeteksi perubahan penting dan mengirim update ke Telegram.”', 'Ini menggambarkan capability yang direncanakan dan belum tersedia saat ini.' ), 'IN DEVELOPMENT' ),
					self::q( 'apakah-analysts-tersedia', 'Apakah 11 AI Analysts sudah tersedia?', array( 'Belum.', '11 AI Analysts masih dalam pengembangan.', 'Ketika tersedia, sistem ini dirancang untuk memberikan berbagai perspektif spesialis pada scheduled intelligence Bitmomo.' ), 'IN DEVELOPMENT' ),
					self::q( 'apa-itu-watchtower', 'Apa itu Bitmomo Watchtower?', array( 'Watchtower adalah monitoring layer Bitmomo yang sedang dikembangkan untuk mendeteksi perubahan penting di antara scheduled intelligence updates.', 'Tujuannya adalah membantu menjaga thesis Bitmomo tetap relevan ketika kondisi pasar berubah.' ), 'IN DEVELOPMENT' ),
					self::q( 'watchtower-bukan-news-feed', 'Apakah Watchtower akan mengirim setiap berita dan pergerakan pasar?', array( 'Tidak.', 'Watchtower dirancang sebagai high-signal monitoring system, bukan news feed.', 'Tujuannya adalah menyaring perubahan yang cukup penting untuk membuat kondisi atau thesis pasar perlu diperhatikan kembali.' ), 'IN DEVELOPMENT' ),
					self::q( 'telegram-alerts', 'Apakah Telegram Alerts sudah tersedia?', array( 'Belum.', 'Telegram Alerts merupakan bagian dari Watchtower yang masih dalam pengembangan.' ), 'IN DEVELOPMENT' ),
					self::q( 'founders-mendapat-analysts-watchtower', 'Apakah Founding Members akan mendapatkan 11 AI Analysts dan Watchtower?', array( 'Ya, jika fitur tersebut diluncurkan sebagai bagian dari Bitmomo Pro.', 'Founding Members yang menjaga membership tetap aktif akan mendapatkan fitur tersebut tanpa kehilangan founding price mereka.' ), 'IN DEVELOPMENT' ),
				),
			),
			'ai-lab-decentralized-ai' => array(
				'title' => 'AI Lab & Decentralized AI',
				'items' => array(
					self::q( 'apa-itu-ai-lab', 'Apa itu Bitmomo AI Lab?', array( 'Bitmomo AI Lab adalah R&D layer Bitmomo yang meneliti teknologi dan pendekatan AI yang dapat meningkatkan kualitas intelligence system yang kami bangun.', 'Fokus riset mencakup antara lain:', 'Decentralized AI<br>Agent Systems<br>AI Evaluation', '“Kami tidak hanya meneliti bagaimana membuat AI lebih pintar, tetapi bagaimana memastikan intelligence yang dihasilkan tetap dapat dipercaya ketika model, provider, dan kondisi pasar terus berubah.”' ) ),
					self::q( 'mengapa-decentralized-ai', 'Mengapa Bitmomo AI Lab meneliti decentralized AI?', array( 'Bitmomo meneliti decentralized AI bukan hanya karena sektor ini memiliki potensi investasi yang menarik, tetapi juga karena kami melihat kemungkinan bahwa teknologinya dapat menjadi bagian penting dari infrastructure intelligence Bitmomo di masa depan.', 'Sistem AI tertutup dapat berubah dari waktu ke waktu—baik dari sisi model, behavior, pricing, maupun kebijakan—tanpa memberikan pengguna visibilitas penuh terhadap apa yang berubah di balik sistem tersebut.', 'Untuk produk intelligence yang menuntut konsistensi dan accountability, ketergantungan pada black-box provider menciptakan risiko tersendiri.', 'Decentralized AI menawarkan pendekatan yang berbeda: lebih banyak transparansi terhadap model dan provider yang digunakan, kemungkinan membandingkan performa antar-model secara lebih terbuka, serta mekanisme insentif dan evaluasi yang dapat dibuat lebih auditable.', 'Bagi Bitmomo, hal ini menarik karena kualitas AI tidak hanya perlu tinggi, tetapi juga dapat diuji, dibandingkan, dan dipantau dari waktu ke waktu.', 'Tujuan riset kami bukan berasumsi bahwa decentralized AI selalu lebih baik daripada closed AI. Kami ingin memahami kapan arsitektur terdesentralisasi benar-benar dapat meningkatkan reliability, transparency, model diversity, dan verifiability dari sistem intelligence yang kami bangun.' ) ),
					self::q( 'hubungan-ai-lab-dengan-pro', 'Apa hubungan riset decentralized AI dengan Bitmomo Pro?', array( 'AI Lab berfungsi sebagai R&D layer Bitmomo.', 'Jika suatu teknologi terbukti meningkatkan kualitas, consistency, transparency, atau kemampuan evaluasi sistem intelligence kami, teknologi tersebut dapat diadopsi ke produk Bitmomo di masa depan.' ) ),
				),
			),
			'data-ai-methodology' => array(
				'title' => 'Data, AI & Methodology',
				'items' => array(
					self::q( 'ai-menebak-harga', 'Apakah Bitmomo hanya meminta AI menebak harga Bitcoin?', array( 'Tidak.', 'Bitmomo dibangun sebagai intelligence system berlapis.', 'Data pasar diproses melalui normalized inputs, deterministic logic, quality checks, Market State classification, dan—untuk capability tertentu yang sedang dikembangkan—analisis AI spesialis.', 'AI tidak diposisikan sebagai mesin yang selalu benar.' ) ),
					self::q( 'deterministic-dan-ai', 'Kenapa Bitmomo menggunakan deterministic logic sekaligus AI?', array( 'Keduanya memiliki kelebihan yang berbeda.', 'Deterministic logic membantu menjaga konsistensi, reproducibility, dan aturan yang jelas.', 'AI lebih berguna untuk interpretation, reasoning, dan sintesis informasi yang lebih kompleks.', 'Bitmomo dirancang untuk memanfaatkan keduanya daripada menyerahkan seluruh intelligence process kepada satu black-box analysis.' ) ),
				),
			),
		);
	}

	/**
	 * Conversion-critical only (per the frontend-completion sprint's
	 * aggressive /pro simplification directive) -- what a visitor needs
	 * resolved right before joining the whitelist or paying, not general
	 * product education. Everything else (free-vs-pro, update schedule,
	 * post-149-cap handling, monthly-vs-annual mechanics, whether founders
	 * get future features) already lives at /help and is one click away via
	 * render_pro_subset()'s "Lihat Help Center" link -- trimmed from 10
	 * items to 5 so /pro's FAQ stays a purchase aid, not a second Help
	 * Center.
	 */
	public static function pro_question_ids() {
		return array( 'apa-itu-bitmomo-pro', 'harga-bitmomo-pro', 'apa-itu-founding-membership', 'batalkan-kapan-saja', 'kebijakan-refund' );
	}

	private static function indexed_items() {
		$items = array();
		foreach ( self::categories() as $category ) {
			foreach ( $category['items'] as $item ) {
				$items[ $item['id'] ] = $item;
			}
		}
		return $items;
	}

	private static function render_item( $item, $context = 'help' ) {
		$id = sanitize_title( $item['id'] );
		?>
		<details class="bm-faq__item" id="<?php echo esc_attr( $id ); ?>">
			<summary class="bm-faq__question">
				<span><?php echo esc_html( $item['question'] ); ?></span>
				<?php if ( ! empty( $item['status'] ) ) : ?><small><?php echo esc_html( $item['status'] ); ?></small><?php endif; ?>
			</summary>
			<div class="bm-faq__answer">
				<?php foreach ( $item['answer'] as $paragraph ) : ?>
					<p><?php echo wp_kses( $paragraph, array( 'strong' => array(), 'br' => array() ) ); ?></p>
				<?php endforeach; ?>
				<?php if ( 'help' === $context ) : ?><a class="bm-faq__permalink" href="#<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Tautan langsung: %s', $item['question'] ) ); ?>"># Tautan langsung</a><?php endif; ?>
			</div>
		</details>
		<?php
	}

	public function render_help_center() {
		$categories = self::categories();
		ob_start();
		?>
		<div class="bm-help" id="bm-help-content">
			<header class="bm-help__hero">
				<p class="bm-help__eyebrow">BITMOMO HELP CENTER</p>
				<p class="bm-help__title">Temukan jawaban tentang Bitmomo</p>
				<p>Pelajari cara kerja BTC Daily Intelligence, Market State, Bitmomo Pro, Founding Membership, dan metodologi kami.</p>
			</header>
			<nav class="bm-help__nav" aria-label="Kategori Help Center">
				<?php foreach ( $categories as $slug => $category ) : ?><a href="#<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $category['title'] ); ?></a><?php endforeach; ?>
			</nav>
			<?php foreach ( $categories as $slug => $category ) : ?>
				<section class="bm-help__section" id="<?php echo esc_attr( $slug ); ?>" aria-labelledby="<?php echo esc_attr( $slug ); ?>-title">
					<h2 id="<?php echo esc_attr( $slug ); ?>-title"><?php echo esc_html( $category['title'] ); ?></h2>
					<?php if ( ! empty( $category['statuses'] ) ) : ?><div class="bm-help__statuses" aria-label="Status fitur"><?php foreach ( $category['statuses'] as $status ) : ?><span><?php echo esc_html( $status ); ?></span><?php endforeach; ?></div><?php endif; ?>
					<div class="bm-faq"><?php foreach ( $category['items'] as $item ) { self::render_item( $item ); } ?></div>
				</section>
			<?php endforeach; ?>
			<nav class="bm-help__related" aria-label="Tautan terkait">
				<a href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">Bitmomo Pro</a>
				<a href="<?php echo esc_url( home_url( '/#bm-btc-title' ) ); ?>">BTC Daily Intelligence</a>
				<a href="<?php echo esc_url( home_url( '/category/tren-ai/' ) ); ?>">AI Lab</a>
				<a href="<?php echo esc_url( home_url( '/category/riset/' ) ); ?>">Research</a>
				<a href="<?php echo esc_url( home_url( '/kebijakan-privasi/' ) ); ?>">Privacy</a>
				<a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>">Disclaimer</a>
			</nav>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_pro_subset() {
		$items = self::indexed_items();
		?>
		<section class="bm-pro-sales__faq" aria-labelledby="bm-pro-faq-title">
			<h2 id="bm-pro-faq-title">Pertanyaan yang Sering Diajukan</h2>
			<div class="bm-faq"><?php foreach ( self::pro_question_ids() as $id ) { if ( isset( $items[ $id ] ) ) { self::render_item( $items[ $id ], 'pro' ); } } ?></div>
			<div class="bm-pro-sales__faq-more"><p>Punya pertanyaan lain?</p><a href="<?php echo esc_url( home_url( '/help/' ) ); ?>">Lihat Help Center →</a></div>
		</section>
		<?php
	}
}
