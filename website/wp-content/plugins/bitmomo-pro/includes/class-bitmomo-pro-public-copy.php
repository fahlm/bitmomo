<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transitional visitor-language compatibility for the Help Center only.
 *
 * Canonical Pro sales copy now lives directly in Bitmomo_Pro_Sales. This
 * compatibility layer remains temporarily for legacy Help strings while that
 * renderer is migrated; it must not mutate sales, whitelist, account, pricing,
 * entitlement, data, or legal behavior after render.
 */
final class Bitmomo_Pro_Public_Copy {

	public static function init() {
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'filter_shortcode_output' ), 20, 4 );
	}

	private static function help_output_map() {
		return array(
			'Apakah Bitmomo memberikan sinyal buy atau sell?' => 'Apakah Bitmomo memberikan sinyal beli atau jual?',
			'Tidak dalam bentuk perintah transaksi. Bitmomo menyediakan decision support melalui Market State, Bias, Confidence, Expected Range, skenario, Thesis Invalidation, serta perubahan yang dinilai relevan.' => 'Tidak dalam bentuk perintah transaksi. Bitmomo menyediakan decision support melalui Market State, Bias, Confidence, Expected Range, Scenario Map, kondisi invalidasi tesis, dan perubahan yang relevan.',
			'Bitmomo adalah platform BTC intelligence yang dirancang untuk mengubah banyak data dan sinyal pasar menjadi kondisi pasar, skenario, dan perubahan yang lebih mudah dipahami.' => 'Bitmomo adalah platform market intelligence untuk BTC yang merangkum data pasar menjadi kondisi, skenario, dan perubahan yang relevan.',
			'Update hanya ditampilkan sebagai current intelligence jika data memenuhi quality gate Bitmomo.' => 'Analisis terbaru hanya ditampilkan jika data memenuhi standar kualitas Bitmomo.',
			'Di antara scheduled updates tersebut, Watchtower direncanakan sebagai capability monitoring tambahan dan belum tersedia saat ini.' => 'Di antara jadwal analisis tersebut, Watchtower direncanakan sebagai sistem monitoring tambahan dan belum tersedia saat ini.',
			'Bitmomo menggunakan quality gate sebelum intelligence dianggap valid.' => 'Bitmomo menerapkan standar kualitas data sebelum analisis dapat dipublikasikan.',
			'Confidence yang lebih rendah menunjukkan adanya ketidakpastian atau conflicting evidence yang lebih besar.' => 'Confidence yang lebih rendah menunjukkan ketidakpastian atau bukti yang saling bertentangan.',
			'Market State 30 Hari adalah riwayat Market State resmi yang tercatat oleh Bitmomo.' => 'Market State 30 Hari menampilkan riwayat Market State yang benar-benar tercatat oleh Bitmomo.',
			'Setiap tanggal hanya memiliki satu official Market State pada public history sehingga pengguna dapat melihat bagaimana kondisi pasar berkembang dari waktu ke waktu.' => 'Setiap tanggal hanya memiliki satu Market State resmi dalam riwayat publik sehingga perubahan kondisi pasar dapat ditelusuri dari waktu ke waktu.',
			'Kenapa Market State History belum selalu berisi 30 hari?' => 'Mengapa riwayat Market State belum selalu berisi 30 hari?',
			'Bitmomo tidak membuat history palsu hanya agar visualisasi terlihat penuh.' => 'Bitmomo tidak melakukan backfill retrospektif hanya untuk melengkapi visualisasi.',
			'History akan terisi secara bertahap seiring sistem mencatat state baru.' => 'Riwayat akan bertambah secara bertahap seiring sistem mencatat Market State baru.',
			'Bitmomo Pro adalah decision-support layer untuk BTC yang membantu Anda memahami apa yang mungkin terjadi berikutnya, apa yang dapat membatalkan thesis utama, dan apa yang berubah dibanding update sebelumnya.' => 'Bitmomo Pro adalah layanan decision support untuk BTC yang membantu memahami skenario berikutnya, kondisi yang membatalkan tesis utama, dan perubahan sejak analisis sebelumnya.',
			'Pro melengkapi BTC Intelligence gratis dengan Expected Range, Scenario Map, Thesis Invalidation, What Changed, dan fitur Pro lainnya.' => 'Pro melengkapi BTC Intelligence gratis dengan Expected Range, Scenario Map, kondisi invalidasi tesis, What Changed, dan fitur Pro lainnya.',
			'“Apa berikutnya?”<br>“Apa yang dapat membatalkan thesis?”<br>“Apa yang berubah?”' => '“Skenario apa yang perlu dipantau?”<br>“Kondisi apa yang membatalkan tesis?”<br>“Apa yang berubah?”',
			'Apa itu Thesis Invalidation?' => 'Apa itu invalidasi tesis?',
			'Thesis Invalidation adalah kondisi yang membuat thesis utama Bitmomo tidak lagi layak dipertahankan.' => 'Invalidasi tesis adalah kondisi yang membuat tesis utama Bitmomo tidak lagi berlaku.',
			'Analisis yang baik bukan hanya menjelaskan apa yang mungkin terjadi, tetapi juga kapan thesis tersebut perlu dianggap salah atau dievaluasi ulang.' => 'Analisis yang baik menjelaskan skenario yang relevan sekaligus kondisi yang membuat tesis perlu dievaluasi ulang.',
			'Bitmomo dapat membuka bentuk akses berikutnya di kemudian hari dengan terms atau harga yang berbeda.' => 'Bitmomo dapat membuka bentuk akses berikutnya di kemudian hari dengan ketentuan atau harga yang berbeda.',
			'Jika akses publik lain dibuka di kemudian hari, terms dan harga dapat berbeda dari Founding Membership.' => 'Jika akses publik lain dibuka di kemudian hari, ketentuan dan harga dapat berbeda dari Founding Membership.',
			'Founding benefit berlaku untuk fitur baru yang ditambahkan ke Bitmomo Pro, termasuk 11 AI Analysts dan Watchtower jika diluncurkan sebagai bagian dari Bitmomo Pro.' => 'Manfaat Founding Membership berlaku untuk fitur baru yang ditambahkan ke Bitmomo Pro, termasuk 11 AI Analysts dan Watchtower jika diluncurkan sebagai bagian dari Bitmomo Pro.',
			'Produk standalone Bitmomo di masa depan dapat memiliki pricing tersendiri.' => 'Produk Bitmomo yang terpisah di masa depan dapat memiliki harga tersendiri.',
			'Payment & Cancellation' => 'Pembayaran & Pembatalan',
		);
	}

	public static function filter_shortcode_output( $output, $tag, $attr, $m ) {
		if ( 'bitmomo_help_center' !== $tag ) {
			return $output;
		}
		return strtr( $output, self::help_output_map() );
	}
}
