<?php
include 'koneksi.php';

$id_transaksi = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data transaksi induk
$query_trx = $conn->query("SELECT * FROM transaksi WHERE id = $id_transaksi");
$trx = $query_trx->fetch_assoc();

if (!$trx) {
    die("Data transaksi tidak ditemukan.");
}

// Ambil rincian produk yang dibeli
$query_detail = $conn->query("SELECT * FROM detail_transaksi WHERE id_transaksi = $id_transaksi");

// Ambil nama merchant QRIS untuk nama toko
$qris_nama = $conn->query("SELECT nilai FROM pengaturan WHERE kunci = 'qris_nama'")->fetch_assoc()['nilai'] ?? 'Kedai Sotokopi';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk #<?= $trx['id'] ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 280px; margin: 0 auto; padding: 10px; color: #000; font-size: 12px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .line { border-bottom: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        .btn-print { background: #4A2E0A; color: #fff; padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; display: block; margin: 10px auto; width: 100%; text-align: center; font-weight: bold; }
        @media print { .btn-print { display: none; } body { margin: 0; padding: 0; } }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">🖨️ Cetak Struk</button>
    
    <div class="text-center">
        <strong><?= htmlspecialchars($qris_nama) ?></strong><br>
        Sistem Kasir Digital UMKM<br>
        ================================
    </div>
    
    <table>
        <tr>
            <td>Nota: #<?= $trx['id'] ?></td>
            <td class="text-right"><?= date('d/m/y H:i', strtotime($trx['waktu'])) ?></td>
        </tr>
        <tr>
            <td>Metode: <?= strtoupper($trx['metode']) ?></td>
            <td class="text-right">Status: LUNAS</td>
        </tr>
    </table>
    
    <div class="line"></div>
    
    <table>
        <?php while ($item = $query_detail->fetch_assoc()): ?>
            <tr>
                <td colspan="2"><?= htmlspecialchars($item['nama_produk']) ?></td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;<?= $item['qty'] ?> x Rp <?= number_format($item['harga_jual'], 0, ',', '.') ?></td>
                <td class="text-right">Rp <?= number_format($item['harga_jual'] * $item['qty'], 0, ',', '.') ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
    
    <div class="line"></div>
    
    <table>
        <tr style="font-weight: bold;">
            <td>TOTAL</td>
            <td class="text-right">Rp <?= number_format($trx['total'], 0, ',', '.') ?></td>
        </tr>
    </table>
    
    <div class="line"></div>
    <div class="text-center" style="margin-top: 15px;">
        Terima Kasih Atas Kunjungan Anda!<br>
        *Layanan Konsumen / UMKM Berdaya*
    </div>
</body>
</html>