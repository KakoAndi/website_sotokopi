<?php
include 'koneksi.php';

echo "<h2>🔧 Sedang menyelaraskan gambar menu dengan database...</h2><hr>";

// Hubungkan ID menu dengan nama file gambar yang ada di folder uploads kamu
$update_list = [
    'Soto Ayam Kampung'   => 'uploads/soto.jpg',
    'Soto Daging Sapi'    => 'uploads/sopi.jpg',
    'Kopi Tubruk'         => 'uploads/kopi.jpg',
    'Kopi Susu Gula Aren' => 'uploads/koren.jpg',
    'Es Teh Manis'        => 'uploads/teh.jpg'
];

foreach ($update_list as $nama_menu => $path_gambar) {
    // Validasi apakah file fisiknya benar-benar ada di folder uploads
    if (file_exists($path_gambar)) {
        $stmt = $conn->prepare("UPDATE produk SET icon = ? WHERE nama = ?");
        $stmt->bind_param("ss", $path_gambar, $nama_menu);
        $stmt->execute();
        echo "✅ Menu <b>{$nama_menu}</b> berhasil diarahkan ke <code>{$path_gambar}</code><br>";
    } else {
        echo "❌ File <code>{$path_gambar}</code> TIDAK ditemukan di folder uploads. Pastikan nama & ekstensi file di VS Code sama permainannya.<br>";
    }
}

echo "<hr>✨ Selesai! Silakan buka kembali <a href='index.php?page=menu'>Halaman Menu</a> untuk melihat hasilnya.";
?>