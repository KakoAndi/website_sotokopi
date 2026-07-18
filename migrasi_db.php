<?php
$host = "localhost";
$user = "root";
$pass = "";

// 1. Koneksi awal ke MySQL Server
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Gagal terkoneksi ke server XAMPP: " . $conn->connect_error);
}

// 2. Buat Database jika belum ada
$conn->query("CREATE DATABASE IF NOT EXISTS sotokopi_db");
$conn->select_db("sotokopi_db");

echo "<h2>🛠️ Memulai Migrasi Database Sotokopi...</h2><hr>";

// 3. Eksekusi Pembuatan Tabel Produk
$table_produk = "CREATE TABLE IF NOT EXISTS produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    kategori ENUM('makanan', 'minuman') NOT NULL,
    harga INT NOT NULL,
    harga_modal INT NOT NULL,
    stok INT NOT NULL,
    icon VARCHAR(10) NOT NULL,
    deskripsi TEXT
)";
if ($conn->query($table_produk)) echo "✅ Tabel 'produk' siap.<br>";

// 4. Eksekusi Pembuatan Tabel Transaksi Induk
$table_transaksi = "CREATE TABLE IF NOT EXISTS transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total INT NOT NULL,
    keuntungan INT NOT NULL,
    metode ENUM('cash', 'qris') NOT NULL
)";
if ($conn->query($table_transaksi)) echo "✅ Tabel 'transaksi' siap.<br>";

// 5. Eksekusi Pembuatan Tabel Detail Transaksi
$table_detail = "CREATE TABLE IF NOT EXISTS detail_transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT,
    nama_produk VARCHAR(100),
    qty INT,
    harga_jual INT,
    harga_modal INT,
    FOREIGN KEY (id_transaksi) REFERENCES transaksi(id) ON DELETE CASCADE
)";
if ($conn->query($table_detail)) echo "✅ Tabel 'detail_transaksi' siap.<br>";

// 6. Eksekusi Pembuatan Tabel Pengaturan Sistem
$table_pengaturan = "CREATE TABLE IF NOT EXISTS pengaturan (
    kunci VARCHAR(50) PRIMARY KEY,
    nilai TEXT
)";
if ($conn->query($table_pengaturan)) echo "✅ Tabel 'pengaturan' siap.<br>";

// 7. Mengisi Data Awal (Seeders) jika tabel masih kosong
$check_produk = $conn->query("SELECT id FROM produk LIMIT 1");
if ($check_produk->num_rows == 0) {
    $conn->query("INSERT INTO produk (nama, kategori, harga, harga_modal, stok, icon, deskripsi) VALUES
    ('Soto Ayam Kampung', 'makanan', 18000, 11000, 20, '🍲', 'Soto ayam kuah bening, khas nusantara'),
    ('Soto Daging Sapi', 'makanan', 22000, 14000, 15, '🥘', 'Soto daging sapi empuk, kuah gurih'),
    ('Kopi Tubruk', 'minuman', 8000, 3000, 40, '☕', 'Kopi hitam khas warung kopi'),
    ('Kopi Susu Gula Aren', 'minuman', 15000, 6000, 35, '🥤', 'Kopi susu manis gula aren'),
    ('Es Teh Manis', 'minuman', 5000, 1500, 50, '🧊', 'Teh manis dingin segar')");
    echo "🌱 Data master produk berhasil disuntikkan.<br>";
}

$check_config = $conn->query("SELECT kunci FROM pengaturan LIMIT 1");
if ($check_config->num_rows == 0) {
    $conn->query("INSERT INTO pengaturan (kunci, nilai) VALUES 
    ('qris_nama', 'Kedai Sotokopi - a.n. Budi'),
    ('qris_img', '')");
    echo "🌱 Data default QRIS berhasil disuntikkan.<br>";
}

echo "<hr>🎉 <strong>Migrasi Selesai!</strong> Anda bisa menghapus berkas ini atau langsung menuju ke <a href='index.php'>Halaman Utama Dasbor</a>.";
?>