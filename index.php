<?php
include 'koneksi.php';

// =========================================================================
// 1. BACKEND CONTROLLER (API INTERNAL & PROSES FORM SUBMIT)
// =========================================================================

// Aksi A: Pengubahan Stok Langsung dari Tabel Manajemen Stok
if (isset($_POST['action']) && $_POST['action'] == 'update_stok') {
    $id = intval($_POST['id']);
    $qty = intval($_POST['qty']);
    $type = $_POST['type'];

    if ($type == 'plus') {
        $conn->query("UPDATE produk SET stok = stok + $qty WHERE id = $id");
    } else {
        $res = $conn->query("SELECT stok FROM produk WHERE id = $id");
        $row = $res->fetch_assoc();
        if ($row['stok'] - $qty >= 0) {
            $conn->query("UPDATE produk SET stok = stok - $qty WHERE id = $id");
        }
    }
    header("Location: index.php?page=stok");
    exit;
}

// Aksi B: Proses Checkout Transaksi Baru (Kasir)
if (isset($_POST['action']) && $_POST['action'] == 'checkout') {
    if (!isset($_POST['cart_data']) || empty($_POST['cart_data']) || $_POST['cart_data'] == '[]') {
        header("Location: index.php?page=kasir&status=error_empty");
        exit;
    }

    $cart = json_decode($_POST['cart_data'], true);
    $method = $_POST['pay_method'];

    if (empty($cart) || !is_array($cart)) {
        header("Location: index.php?page=kasir&status=error_empty");
        exit;
    }

    $total_omzet = 0;
    $total_profit = 0;
    $valid_transaction = true;

    foreach ($cart as $item) {
        $p_id = intval($item['id']);
        $qty = intval($item['qty']);
        
        $p_res = $conn->query("SELECT * FROM produk WHERE id = $p_id");
        $prod = $p_res->fetch_assoc();

        if (!$prod || $prod['stok'] < $qty) {
            $valid_transaction = false;
            break;
        }

        $total_omzet += $prod['harga'] * $qty;
        $total_profit += ($prod['harga'] - $prod['harga_modal']) * $qty;
    }

    if (!$valid_transaction) {
        header("Location: index.php?page=kasir&status=error_stock");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO transaksi (total, keuntungan, metode) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $total_omzet, $total_profit, $method);
    $stmt->execute();
    $id_transaksi = $stmt->insert_id;

    foreach ($cart as $item) {
        $p_id = intval($item['id']);
        $qty = intval($item['qty']);
        $p_res = $conn->query("SELECT * FROM produk WHERE id = $p_id");
        $prod = $p_res->fetch_assoc();

        $stmt_det = $conn->prepare("INSERT INTO detail_transaksi (id_transaksi, nama_produk, qty, harga_jual, harga_modal) VALUES (?, ?, ?, ?, ?)");
        $stmt_det->bind_param("isiii", $id_transaksi, $prod['nama'], $qty, $prod['harga'], $prod['harga_modal']);
        $stmt_det->execute();

        $conn->query("UPDATE produk SET stok = stok - $qty WHERE id = $p_id");
    }
    
    header("Location: index.php?page=kasir&status=success&last_id=" . $id_transaksi);
    exit;
}

// Aksi C: Pengaturan Informasi & Upload QRIS
if (isset($_POST['action']) && $_POST['action'] == 'save_qris') {
    $nama_merchant = mysqli_real_escape_string($conn, $_POST['qrisNama']);
    $conn->query("UPDATE pengaturan SET nilai = '$nama_merchant' WHERE kunci = 'qris_nama'");
    
    if (!empty($_FILES['qrisUpload']['tmp_name'])) {
        $path = "uploads/";
        if (!is_dir($path)) mkdir($path);
        
        $file_name = time() . "_" . $_FILES['qrisUpload']['name'];
        if (move_uploaded_file($_FILES['qrisUpload']['tmp_name'], $path . $file_name)) {
            $conn->query("UPDATE pengaturan SET nilai = '$path$file_name' WHERE kunci = 'qris_img'");
        }
    }
    header("Location: index.php?page=pengaturan");
    exit;
}

// Aksi D: Tambah Menu/Produk Baru dengan Gambar (CRUD)
if (isset($_POST['action']) && $_POST['action'] == 'add_product') {
    $nama = trim($_POST['nama']);
    $kategori = $_POST['kategori'];
    $harga = intval($_POST['harga']);
    $harga_modal = intval($_POST['harga_modal']);
    $stok = intval($_POST['stok']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    
    if (empty($nama)) {
        header("Location: index.php?page=menu&status=error_invalid");
        exit;
    }

    $nama_clean = mysqli_real_escape_string($conn, $nama);
    $gambar_db = "uploads/soto.jpg"; 

    if (!empty($_FILES['produkGambar']['tmp_name'])) {
        $path = "uploads/";
        if (!is_dir($path)) mkdir($path);
        
        $file_name = time() . "_" . $_FILES['produkGambar']['name'];
        if (move_uploaded_file($_FILES['produkGambar']['tmp_name'], $path . $file_name)) {
            $gambar_db = $path . $file_name;
        }
    }

    $stmt = $conn->prepare("INSERT INTO produk (nama, kategori, harga, harga_modal, stok, icon, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiiiss", $nama_clean, $kategori, $harga, $harga_modal, $stok, $gambar_db, $deskripsi);
    $stmt->execute();
    
    header("Location: index.php?page=menu");
    exit;
}

// Aksi E: Hapus Produk (CRUD)
if (isset($_GET['action']) && $_GET['action'] == 'delete_product') {
    $id = intval($_GET['id']);
    
    $res = $conn->query("SELECT icon FROM produk WHERE id = $id");
    $row = $res->fetch_assoc();
    
    $default_files = ["uploads/soto.jpg", "uploads/kopi.jpg", "uploads/koren.jpg", "uploads/teh.jpg", "uploads/sopi.jpg"];
    if ($row && !in_array($row['icon'], $default_files) && file_exists($row['icon'])) {
        unlink($row['icon']);
    }

    $conn->query("DELETE FROM produk WHERE id = $id");
    header("Location: index.php?page=menu");
    exit;
}

// Aksi F: Reset Semua Riwayat Transaksi Finansial
if (isset($_GET['action']) && $_GET['action'] == 'reset_rekap') {
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("TRUNCATE TABLE detail_transaksi");
    $conn->query("TRUNCATE TABLE transaksi");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    header("Location: index.php?page=rekap");
    exit;
}

// =========================================================================
// 2. QUERY READING DATA DARI MYSQL UNTUK INTERFACE (UI)
// =========================================================================
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

$query_produk = $conn->query("SELECT * FROM produk WHERE nama IS NOT NULL AND nama != ''");
$products = [];
while($r = $query_produk->fetch_assoc()) { $products[] = $r; }

$query_trx = $conn->query("SELECT * FROM transaksi ORDER BY waktu DESC");
$history = [];
while($r = $query_trx->fetch_assoc()) { $history[] = $r; }

$query_details = $conn->query("SELECT nama_produk, SUM(qty) as total_terjual FROM detail_transaksi GROUP BY nama_produk ORDER BY total_terjual DESC LIMIT 5");
$top_products = [];
while($r = $query_details->fetch_assoc()) { $top_products[] = $r; }

$qris_nama = $conn->query("SELECT nilai FROM pengaturan WHERE kunci = 'qris_nama'")->fetch_assoc()['nilai'] ?? 'Kedai Sotokopi';
$qris_img = $conn->query("SELECT nilai FROM pengaturan WHERE kunci = 'qris_img'")->fetch_assoc()['nilai'] ?? '';

$today = date('Y-m-d');
$omzet_hari_ini = $conn->query("SELECT SUM(total) as total FROM transaksi WHERE DATE(waktu) = '$today'")->fetch_assoc()['total'] ?? 0;
$profit_hari_ini = $conn->query("SELECT SUM(keuntungan) as total FROM transaksi WHERE DATE(waktu) = '$today'")->fetch_assoc()['total'] ?? 0;
$trx_hari_ini = $conn->query("SELECT COUNT(*) as total FROM transaksi WHERE DATE(waktu) = '$today'")->fetch_assoc()['total'] ?? 0;
$low_stock_count = $conn->query("SELECT COUNT(*) as total FROM produk WHERE stok <= 5 AND nama != ''")->fetch_assoc()['total'] ?? 0;

// MODIFIKASI: Mengolah data transaksi 7 hari terakhir ke dalam Array PHP untuk Grafik
$chart_labels = [];
$chart_omzet = [];
$chart_untung = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date));
    
    $day_res = $conn->query("SELECT SUM(total) as omzet, SUM(keuntungan) as untung FROM transaksi WHERE DATE(waktu) = '$date'")->fetch_assoc();
    $chart_omzet[] = (int)($day_res['omzet'] ?? 0);
    $chart_untung[] = (int)($day_res['untung'] ?? 0);
}

function getValidImage($db_path) {
    if (!empty($db_path) && file_exists($db_path)) {
        return $db_path;
    }
    $clean_filename = strtolower(basename($db_path));
    $extensions = ['.jpg', '.jpeg', '.png', '.JPG', '.PNG'];
    foreach ($extensions as $ext) {
        $test_path = "uploads/" . pathinfo($clean_filename, PATHINFO_FILENAME) . $ext;
        if (file_exists($test_path)) {
            return $test_path;
        }
    }
    return "uploads/soto.jpg";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sotokopi - Dashboard POS UMKM</title>
<!-- MODIFIKASI: Memasukkan Library Chart.js Resmi via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  :root{
    --kuning:#FFC72C; --kuning-tua:#F0A202; --kuning-muda:#FFF3D6; --kuning-pucat:#FFFBEF;
    --coklat:#4A2E0A; --coklat-tua:#2E1B05; --hijau:#2E7D32; --hijau-bg:#E7F5E8;
    --merah:#C62828; --merah-bg:#FCE8E8; --biru:#1565C0; --biru-bg:#E7F1FC;
    --abu:#847A68; --putih:#FFFFFF; --r-lg:26px; --r-md:20px; --r-sm:14px;
    --shadow:0 2px 10px rgba(74,46,10,0.06); --shadow-hover:0 14px 30px rgba(74,46,10,0.14);
    --border:1px solid #F1E4BC;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Segoe UI',Arial,sans-serif; background:#FFF8E4; color:var(--coklat-tua); display:flex; min-height:100vh;}
  
  .sidebar{width:96px; background:var(--coklat-tua); display:flex; flex-direction:column; align-items:center; padding:22px 0; gap:26px; position:sticky; top:0; height:100vh; flex-shrink:0; z-index:60;}
  .sidebar .logo{width:52px; height:52px; border-radius:18px; background:var(--kuning); display:flex; align-items:center; justify-content:center; font-size:26px; box-shadow:0 6px 16px rgba(255,199,44,0.35);}
  .sidebar nav{display:flex; flex-direction:column; gap:8px; width:100%; align-items:center;}
  .navbtn{width:68px; height:60px; background:transparent; border:none; border-radius:16px; color:#C9BBA0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; cursor:pointer; font-size:10.5px; font-weight:700; transition:.2s;}
  .navbtn:hover{background:rgba(255,255,255,0.06); color:#FFE9A8;}
  .navbtn.active{background:var(--kuning); color:var(--coklat-tua); box-shadow:0 6px 14px rgba(255,199,44,0.3);}
  .sidebar-bottom{margin-top:auto; color:#7A6A4A; font-size:9px; text-align:center;}

  .main-wrap{flex:1; min-width:0;}
  .topbar{padding:22px 30px 8px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;}
  .topbar h1{font-size:24px; font-weight:800;}
  .topbar .datepill{background:var(--putih); border:var(--border); padding:9px 16px; border-radius:999px; font-size:12.5px; font-weight:700; box-shadow:var(--shadow);}
  main{max-width:1500px; padding:14px 30px 50px;}
  
  .page{display:none;} .page.active{display:block;}
  .bento{display:grid; grid-template-columns:repeat(12,1fr); gap:16px;}
  .tile{background:var(--putih); border-radius:var(--r-lg); border:var(--border); box-shadow:var(--shadow); padding:20px; transition:.25s; overflow:hidden; position:relative;}
  .tile:hover{box-shadow:var(--shadow-hover); transform:translateY(-2px);}
  
  .c3{grid-column:span 3;} .c4{grid-column:span 4;} .c5{grid-column:span 5;} .c6{grid-column:span 6;} .c7{grid-column:span 7;} .c8{grid-column:span 8;} .c12{grid-column:span 12;}
  @media(max-width:1100px){ .c3,.c4,.c5,.c6,.c7,.c8{grid-column:span 12;} }

  .stat-tile{background:linear-gradient(135deg,var(--kuning) 0%, var(--kuning-tua) 100%); color:var(--coklat-tua);}
  .stat-tile.alt{background:var(--coklat-tua); color:var(--kuning);}
  .stat-tile .val{font-size:26px; font-weight:900; margin-top:6px;}
  .stat b{display:block; font-size:23px;}
  
  .cat-tabs{display:flex; gap:8px; margin-bottom:16px;}
  .cat-tabs button{background:var(--putih); border:var(--border); padding:8px 18px; border-radius:999px; font-weight:700; cursor:pointer; color:var(--coklat); border:1px solid #F1E4BC; transition:0.2s;}
  .cat-tabs button.active{background:var(--coklat-tua); color:var(--kuning); border-color:var(--coklat-tua);}
  
  .grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px;}
  .card{background:var(--kuning-pucat); border-radius:var(--r-md); overflow:hidden; border:var(--border); display:flex; flex-direction:column;}
  
  .card .img-box {height:120px; width:100%; overflow:hidden; background:#eee; position:relative;}
  .card .img-box img {width:100%; height:100%; object-fit:cover;}
  
  .card .body{padding:11px 12px 13px; display:flex; flex-direction:column; gap:5px; flex:1;}
  .card .name{font-weight:800; font-size:13.5px;}
  .card .price{font-weight:800; color:var(--kuning-tua);}
  
  .stock.ok{color:var(--hijau);} .stock.low{color:#B8860B;} .stock.empty{color:var(--merah);}
  .card .addbtn{margin-top:5px; background:var(--coklat-tua); color:var(--kuning); border:none; padding:8px; border-radius:10px; cursor:pointer; font-weight:700;}
  .card .addbtn:disabled{background:#ddd; color:#999; cursor:not-allowed;}
  
  .cart-item{display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px dashed #eee;}
  .cart-total{display:flex; justify-content:space-between; font-weight:800; font-size:17px; margin-top:12px; padding-top:12px; border-top:2px solid var(--kuning-muda);}
  .pay-methods{display:flex; gap:8px; margin:12px 0;}
  .pay-methods button{flex:1; padding:9px; border-radius:12px; border:2px solid var(--kuning-muda); background:#fff; font-weight:700; cursor:pointer;}
  .pay-methods button.active{background:var(--kuning); border-color:var(--kuning-tua);}
  .btn-checkout{width:100%; padding:13px; border:none; border-radius:14px; background:var(--hijau); color:#fff; font-weight:800; cursor:pointer;}
  .btn-checkout:disabled{background:#ddd; cursor:not-allowed;}
  
  .modal-bg{display:none; position:fixed; inset:0; background:rgba(46,27,5,0.55); z-index:200; align-items:center; justify-content:center;}
  .modal-bg.show{display:flex;}
  .modal{background:#fff; border-radius:24px; padding:26px; max-width:360px; width:100%; text-align:center;}
  .qris-frame{margin:16px auto; width:220px; height:220px; border:6px solid var(--kuning); padding:10px; background:#fff;}
  .qris-frame img{width:100%; height:100%; object-fit:contain;}
  
  .stock-table, .history-table{width:100%; border-collapse:collapse;}
  .stock-table th, .history-table th{background:var(--kuning-muda); padding:11px; text-align:left; color:var(--coklat-tua);}
  .history-table th{background:var(--kuning-tua); color:#fff;}
  .stock-table td, .history-table td{padding:10px 11px; border-bottom:1px solid #f4e9c8; font-size:13px;}
  .stock-actions input{width:52px; padding:6px; text-align:center; margin-right:4px; border:1px solid #ddd; border-radius:8px;}
  .stock-actions button{border:none; padding:6px 10px; border-radius:8px; cursor:pointer; color:#fff; font-weight:bold;}
  .btn-plus{background:var(--hijau);} .btn-minus{background:var(--merah);}
  
  .badge-cat{padding:3px 9px; border-radius:999px; font-size:10.5px; font-weight:700;}
  .badge-makanan{background:#FFE0B2; color:#9C4A00;}
  .badge-minuman{background:var(--biru-bg); color:var(--biru);}
  .pill-cash{background:var(--hijau-bg); color:var(--hijau); padding:2px 8px; border-radius:999px; font-size:10.5px; font-weight:bold;}
  .pill-qris{background:var(--biru-bg); color:var(--biru); padding:2px 8px; border-radius:999px; font-size:10.5px; font-weight:bold;}
  
  .mini-bar-row{display:flex; align-items:center; gap:10px; margin-bottom:8px; font-size:13px;}
  .mini-bar-track{flex:1; height:10px; background:var(--kuning-muda); border-radius:999px; overflow:hidden;}
  .mini-bar-fill{height:100%; background:linear-gradient(90deg, var(--kuning), var(--kuning-tua));}
  
  .settings-box label{font-weight:700; display:block; margin:12px 0 5px; font-size:13px;}
  .settings-box input[type=text], .settings-box input[type=number], .settings-box select{width:100%; padding:9px; border:1px solid #ddd; border-radius:10px; font-family:inherit;}
  
  .toast{position:fixed; bottom:24px; left:50%; transform:translateX(-50%); background:var(--coklat-tua); color:#FFE9A8; padding:12px 22px; border-radius:999px; display:none; z-index:999; font-weight:bold; box-shadow:0 8px 20px rgba(0,0,0,0.3);}
  footer{text-align:center; padding:20px; color:var(--abu); font-size:12px;}
</style>
</head>
<body>

<div class="sidebar">
  <div class="logo">☕</div>
  <nav>
    <button class="navbtn <?= $page=='dashboard'?'active':'' ?>" onclick="location.href='index.php?page=dashboard'">🏠Dasbor</button>
    <button class="navbtn <?= $page=='menu'?'active':'' ?>" onclick="location.href='index.php?page=menu'">🍜Menu</button>
    <button class="navbtn <?= $page=='kasir'?'active':'' ?>" onclick="location.href='index.php?page=kasir'">🧾Kasir</button>
    <button class="navbtn <?= $page=='stok'?'active':'' ?>" onclick="location.href='index.php?page=stok'">📦Stok</button>
    <button class="navbtn <?= $page=='rekap'?'active':'' ?>" onclick="location.href='index.php?page=rekap'">📊Rekap</button>
    <button class="navbtn <?= $page=='pengaturan'?'active':'' ?>" onclick="location.href='index.php?page=pengaturan'">⚙️QRIS</button>
  </nav>
  <div class="sidebar-bottom">Sotokopi<br>v3.3</div>
</div>

<div class="main-wrap">
  <div class="topbar">
    <div>
      <h1>Kedai Kopi & Soto Nusantara</h1>
    </div>
    <div class="datepill"><?= date('l, d F Y') ?></div>
  </div>

  <main>
    <!-- =================================================================== -->
    <!-- 1. DASHBOARD PANEL -->
    <!-- =================================================================== -->
    <section class="page <?= $page=='dashboard'?'active':'' ?>">
      <div class="bento">
        <div class="tile stat-tile c3">
          <div>Omzet Hari Ini</div>
          <div class="val">Rp <?= number_format($omzet_hari_ini, 0, ',', '.') ?></div>
        </div>
        <div class="tile stat-tile alt c3">
          <div>Untung Hari Ini</div>
          <div class="val">Rp <?= number_format($profit_hari_ini, 0, ',', '.') ?></div>
        </div>
        <div class="tile stat c3">
          <b><?= $trx_hari_ini ?></b><span>Transaksi Berhasil</span>
        </div>
        <div class="tile stat c3">
          <b><?= $low_stock_count ?></b><span>Stok Menipis (<=5)</span>
        </div>

        <!-- MODIFIKASI: Tile Grid Bento Utama Mengakomodasi Grafik Keuangan Dinamis -->
        <div class="tile c12">
          <h3>📈 Grafik Tren Grafik Keuangan Toko (7 Hari Terakhir)</h3><br>
          <div style="height: 280px; position: relative; width: 100%;">
            <canvas id="salesAnalyticsChart"></canvas>
          </div>
        </div>

        <div class="tile c7">
          <h3>📊 5 Produk Terlaris (All-Time)</h3><br>
          <?php if(empty($top_products)): ?>
            <p style="color:var(--abu)">Belum ada rincian data transaksi.</p>
          <?php else: ?>
            <?php foreach($top_products as $tp): ?>
              <div class="mini-bar-row">
                <div style="width:160px; font-weight:bold;"><?= htmlspecialchars($tp['nama_produk']) ?></div>
                <div class="mini-bar-track"><div class="mini-bar-fill" style="width: <?= min(($tp['total_terjual'] * 8), 100) ?>%"></div></div>
                <div style="width:70px; text-align:right; font-weight:bold;"><?= $tp['total_terjual'] ?> Porsi</div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        
        <div class="tile c5">
          <h3>📦 Total Ringkasan Kategori</h3><br>
          <div style="background:#FFF3E0; border-radius:14px; padding:14px; display:flex; justify-content:space-between; margin-bottom:10px;">
            <span style="font-weight:bold; color:#9C4A00;">🍛 Makanan</span>
            <strong><?= count(array_filter($products, function($p){return $p['kategori']=='makanan';})) ?> Menu</strong>
          </div>
          <div style="background:var(--biru-bg); border-radius:14px; padding:14px; display:flex; justify-content:space-between;">
            <span style="font-weight:bold; color:var(--biru);">🥤 Minuman</span>
            <strong><?= count(array_filter($products, function($p){return $p['kategori']=='minuman';})) ?> Menu</strong>
          </div>
        </div>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- 2. MENU MANAGEMENT PANEL (CRUD + UPLOAD FOTO) -->
    <!-- =================================================================== -->
    <section class="page <?= $page=='menu'?'active':'' ?>">
      <div class="bento">
        <div class="tile c4 settings-box" style="align-self: start;">
          <h3>➕ Tambah Menu Baru</h3><br>
          <form action="index.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_product">
            
            <label>Nama Produk</label>
            <input type="text" name="nama" placeholder="Contoh: Soto Ranjau Pedas" required>
            
            <label>Kategori Menu</label>
            <select name="kategori" required>
              <option value="makanan">🍛 Makanan</option>
              <option value="minuman">🥤 Minuman</option>
            </select>
            
            <div style="display:flex; gap:10px;">
              <div>
                <label>Harga Jual</label>
                <input type="number" name="harga" placeholder="18000" required>
              </div>
              <div>
                <label>Harga Modal</label>
                <input type="number" name="harga_modal" placeholder="11000" required>
              </div>
            </div>

            <div style="display:flex; gap:10px;">
              <div style="flex:1;">
                <label>Stok Awal</label>
                <input type="number" name="stok" placeholder="15" required>
              </div>
            </div>

            <label>Foto/Gambar Produk Asli</label>
            <input type="file" name="produkGambar" accept="image/*" required style="width:100%;">

            <label>Deskripsi Singkat</label>
            <input type="text" name="deskripsi" placeholder="Kuah gurih melimpah...">
            
            <button type="submit" style="margin-top:16px; background:var(--coklat-tua); color:var(--kuning); padding:11px; border:none; border-radius:12px; font-weight:800; cursor:pointer; width:100%;">➕ Daftarkan Menu</button>
          </form>
        </div>

        <div class="tile c8">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
            <h3>🍜 Etalase Menu Aktif</h3>
            <div class="cat-tabs" style="margin-bottom:0;">
              <button class="active" onclick="filterCat('all', this)">Semua</button>
              <button onclick="filterCat('makanan', this)">🍛 Makanan</button>
              <button onclick="filterCat('minuman', this)">🥤 Minuman</button>
            </div>
          </div>
          
          <div class="grid">
            <?php foreach ($products as $p): ?>
              <div class="card prod-card" data-cat="<?= $p['kategori'] ?>">
                <div class="img-box">
                  <img src="<?= htmlspecialchars(getValidImage($p['icon'])) ?>" alt="<?= htmlspecialchars($p['nama']) ?>">
                </div>
                <div class="body">
                  <div style="display:flex; justify-content:space-between; align-items:start; gap:4px;">
                    <div class="name" style="font-size:13px; line-height:1.3; font-weight:800; color:var(--coklat-tua);"><?= htmlspecialchars($p['nama']) ?></div>
                    <a href="index.php?action=delete_product&id=<?= $p['id'] ?>" onclick="return confirm('Hapus menu ini beserta fotonya?')" style="text-decoration:none;">❌</a>
                  </div>
                  <div style="font-size:11px; color:var(--abu); min-height:22px; line-height:1.3;"><?= htmlspecialchars($p['deskripsi']) ?></div>
                  <div class="price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                  <div class="stock <?= $p['stok']<=0?'empty':($p['stok']<=5?'low':'ok') ?>">Stok: <?= $p['stok'] ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- 3. INTERFACE KASIR (TRANSAKSI POS JUAL BELI) -->
    <!-- =================================================================== -->
    <section class="page <?= $page=='kasir'?'active':'' ?>">
      <div class="bento">
        <div class="tile c8">
          <!-- MODIFIKASI: Menambahkan etalase button filter kategori yang mandiri di halaman Kasir -->
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3>🍜 Pilih Produk Belanja</h3>
            <div class="cat-tabs" style="margin-bottom:0;">
              <button class="active" onclick="filterCat('all', this)">Semua</button>
              <button onclick="filterCat('makanan', this)">🍛 Makanan</button>
              <button onclick="filterCat('minuman', this)">🥤 Minuman</button>
            </div>
          </div>
          
          <div class="grid">
            <?php foreach ($products as $p): ?>
              <!-- MODIFIKASI: Disuntikkan class prod-card dan data-cat agar JavaScript Kasir dapat memfilter produk -->
              <div class="card prod-card" data-cat="<?= $p['kategori'] ?>">
                <div class="img-box">
                  <img src="<?= htmlspecialchars(getValidImage($p['icon'])) ?>" alt="<?= htmlspecialchars($p['nama']) ?>">
                </div>
                <div class="body">
                  <div class="name" style="font-size:13px; font-weight:800; color:var(--coklat-tua);"><?= htmlspecialchars($p['nama']) ?></div>
                  <div class="price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                  <div class="stock" style="font-weight:bold; margin-bottom:4px; font-size:11px;">Tersedia: <?= $p['stok'] ?></div>
                  <button class="addbtn" onclick="addToCart(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama']) ?>', <?= $p['harga'] ?>, <?= $p['stok'] ?>)" <?= $p['stok'] <= 0 ? 'disabled' : '' ?>>
                    <?= $p['stok'] <= 0 ? 'Habis' : '+ Tambah' ?>
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        
        <div class="tile c4" style="align-self: start;">
          <h3>🛒 Nota Belanja</h3><br>
          <div id="cartList" style="font-size:13px; color:var(--abu);">Belum ada produk dipilih.</div>
          <div class="cart-total"><span>Total Tagihan</span><span id="cartTotalText">Rp 0</span></div>
          
          <div class="pay-methods">
            <button id="btnMethodCash" class="paybtn active" onclick="setMethod('cash')">💵 Tunai</button>
            <button id="btnMethodQris" class="paybtn" onclick="setMethod('qris')">📱 QRIS</button>
          </div>

          <form action="index.php" method="POST" id="formKasirCheckout">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="cart_data" id="cartDataInput">
            <input type="hidden" name="pay_method" id="payMethodInput" value="cash">
            <button type="button" class="btn-checkout" id="btnCheckoutSubmit" onclick="processPayment()" disabled>Proses Ambil Bayar</button>
          </form>
        </div>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- 4. MANAJEMEN STOK (+/- JUMLAH STOK) -->
    <!-- =================================================================== -->
    <section class="page <?= $page=='stok'?'active':'' ?>">
      <div class="tile c12">
        <table class="stock-table">
          <thead>
            <tr><th>Varian Menu</th><th>Kategori</th><th>Harga Jual</th><th>Stok Fisik</th><th>Aksi Kontrol Jumlah</th></tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
              <tr>
                <td>
                  <img src="<?= htmlspecialchars(getValidImage($p['icon'])) ?>" style="width:32px; height:32px; object-fit:cover; border-radius:6px; vertical-align:middle; margin-right:8px;">
                  <?= htmlspecialchars($p['nama']) ?>
                </td>
                <td><span class="badge-cat badge-<?= $p['kategori'] ?>"><?= ucfirst($p['kategori']) ?></span></td>
                <td>Rp <?= number_format($p['harga'], 0, ',', '.') ?></td>
                <td><strong><?= $p['stok'] ?></strong></td>
                <td>
                  <form action="index.php" method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="update_stok">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <div class="stock-actions">
                      <input type="number" name="qty" value="1" min="1">
                      <button type="submit" name="type" value="plus" class="btn-plus">+ Tambah</button>
                      <button type="submit" name="type" value="minus" class="btn-minus">− Kurang</button>
                    </div>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- 5. REKAPAN KEUNTUNGAN FINANSIAL -->
    <!-- =================================================================== -->
    <section class="page <?= $page=='rekap'?'active':'' ?>">
      <div class="bento">
        <?php
          $total_omzet_all = array_sum(array_column($history, 'total'));
          $total_untung_all = array_sum(array_column($history, 'keuntungan'));
        ?>
        <div class="tile stat-tile c6">
          <div>Akumulasi Seluruh Omzet</div>
          <div class="val">Rp <?= number_format($total_omzet_all, 0, ',', '.') ?></div>
        </div>
        <div class="tile stat-tile alt c6">
          <div>Laba Bersih Toko (Margin Bersih)</div>
          <div class="val">Rp <?= number_format($total_untung_all, 0, ',', '.') ?></div>
        </div>

        <div class="tile c12">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <h3>📋 Riwayat Kas Buku Penjualan</h3>
            <a href="index.php?action=reset_rekap" onclick="return confirm('Hapus seluruh riwayat pembukuan kas?')" style="color:var(--merah); text-decoration:none; font-weight:bold; font-size:13px;">🗑️ Bersihkan Histori Kas</a>
          </div><br>
          <table class="history-table">
            <thead>
              <tr><th>Waktu Pencatatan</th><th>Total Penerimaan</th><th>Metode</th><th>Laba Bersih Masuk</th></tr>
            </thead>
            <tbody>
              <?php if(empty($history)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--abu);">Belum ada rekam transaksi penjualan masuk.</td></tr>
              <?php else: ?>
                <?php foreach($history as $h): ?>
                  <tr>
                    <td><?= $h['waktu'] ?></td>
                    <td>Rp <?= number_format($h['total'], 0, ',', '.') ?></td>
                    <td><span class="pill-<?= $h['metode'] ?>"><?= strtoupper($h['metode']) ?></span></td>
                    <td style="color:var(--hijau); font-weight:bold;">Rp <?= number_format($h['keuntungan'], 0, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- 6. INTERFACES SETTINGS QRIS -->
    <!-- =================================================================== -->
    <section class="page <?= $page=='pengaturan'?'active':'' ?>">
      <div class="bento">
        <div class="tile c6 settings-box">
          <h3>⚙️ Setup Akun QRIS Merchant</h3><br>
          <form action="index.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_qris">
            <label>Unggah File/Screenshot QRIS Rekening</label>
            <input type="file" name="qrisUpload" accept="image/*"><br>
            <label>Nama Pemilik Rekening / Nama Toko</label>
            <input type="text" name="qrisNama" value="<?= htmlspecialchars($qris_nama) ?>">
            <button type="submit" style="margin-top:12px; background:var(--kuning); padding:10px 18px; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">💾 Simpan Setup Konfigurasi</button>
          </form>
        </div>
        
        <div class="tile c6" style="text-align:center;">
          <h3>Pratinjau Lembar QRIS</h3><br>
          <div style="width:180px; height:180px; margin:0 auto; border:2px dashed #ddd; border-radius:12px; overflow:hidden;">
            <?php if(!empty($qris_img)): ?>
              <img src="<?= $qris_img ?>" style="width:100%; height:100%; object-fit:contain;">
            <?php else: ?>
              <div style="padding-top:70px; color:#aaa; font-size:12px;">Kosong / Belum Diupload</div>
            <?php endif; ?>
          </div>
          <p style="margin-top:12px; font-weight:bold; font-size:14px;"><?= htmlspecialchars($qris_nama) ?></p>
        </div>
      </div>
    </section>
  </main>
  <footer>© 2026 Sotokopi — Sistem Aplikasi POS Internal UMKM</footer>
</div>

<!-- BOX DOCKING SCAN MODAL QRIS -->
<div class="modal-bg" id="qrisModal">
  <div class="modal">
    <h2>Scan QRIS Pembayaran</h2>
    <p id="qrisMerchantName" style="color:var(--abu); font-weight:bold; margin-top:4px;"><?= htmlspecialchars($qris_nama) ?></p>
    <div class="qris-frame">
      <?php if(!empty($qris_img)): ?>
        <img src="<?= $qris_img ?>" alt="QRIS Toko">
      <?php else: ?>
        <p style="color:#999; padding-top:70px; font-size:12px;">Gambar QRIS belum diatur di menu pengaturan ⚙️.</p>
      <?php endif; ?>
    </div>
    <div class="amt" id="qrisAmountText" style="font-size:24px; font-weight:800; color:var(--kuning-tua); margin-bottom:14px;">Rp 0</div>
    <div style="display:flex; gap:10px;">
      <button onclick="closeQrisModal()" style="flex:1; padding:10px; background:#eee; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">Batal</button>
      <button onclick="submitCheckoutForm()" style="flex:1; padding:10px; background:var(--hijau); color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">✅ Dana Diterima</button>
    </div>
  </div>
</div>

<div class="toast" id="toastContainer"></div>

<!-- =================================================================== -->
<!-- FRONTEND OPERATION (JAVASCRIPT RUNNER) -->
<!-- =================================================================== -->
<script>
let cart = [];
let selectedMethod = 'cash';

// MODIFIKASI: Peningkatan fungsi filter agar terisolasi per halaman aktif & memindah class active button
function filterCat(cat, btn) {
  const activePage = document.querySelector('.page.active');
  activePage.querySelectorAll('.prod-card').forEach(card => {
    if(cat === 'all' || card.dataset.cat === cat) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
  
  if(btn) {
    activePage.querySelectorAll('.cat-tabs button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }
}

function addToCart(id, name, price, maxStock) {
  let item = cart.find(c => c.id === id);
  if (item) {
    if(item.qty + 1 > maxStock) { alert('Stok pesanan melebihi ketersediaan toko!'); return; }
    item.qty++;
  } else {
    cart.push({id: id, name: name, price: price, qty: 1});
  }
  renderCart();
}

function renderCart() {
  const list = document.getElementById('cartList');
  if(cart.length === 0) {
    list.innerHTML = "Belum ada produk dipilih.";
    document.getElementById('btnCheckoutSubmit').disabled = true;
    return;
  }
  
  let html = '';
  let total = 0;
  cart.forEach(item => {
    total += item.price * item.qty;
    html += `<div class="cart-item">
      <div><strong>${item.name}</strong><br><small>Rp ${item.price.toLocaleString('id-ID')} x ${item.qty}</small></div>
      <div style="font-weight:bold;">Rp ${(item.price * item.qty).toLocaleString('id-ID')}</div>
    </div>`;
  });
  
  list.innerHTML = html;
  document.getElementById('cartTotalText').innerText = 'Rp ' + total.toLocaleString('id-ID');
  document.getElementById('cartDataInput').value = JSON.stringify(cart);
  document.getElementById('btnCheckoutSubmit').disabled = false;
}

function setMethod(method) {
  selectedMethod = method;
  document.getElementById('payMethodInput').value = method;
  document.getElementById('btnMethodCash').classList.toggle('active', method === 'cash');
  document.getElementById('btnMethodQris').classList.toggle('active', method === 'qris');
}

function processPayment() {
  if(selectedMethod === 'qris') {
    let total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    document.getElementById('qrisAmountText').innerText = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('qrisModal').classList.add('show');
  } else {
    submitCheckoutForm();
  }
}

function closeQrisModal() {
  document.getElementById('qrisModal').classList.remove('show');
}

function submitCheckoutForm() {
  document.getElementById('formKasirCheckout').submit();
}

// MODIFIKASI: Render Grafik Analitik Chart.js 7 Hari Terakhir Sisi Client
const ctx = document.getElementById('salesAnalyticsChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'Total Omzet (Rp)',
                data: <?php echo json_encode($chart_omzet); ?>,
                borderColor: '#F0A202',
                backgroundColor: 'rgba(240, 162, 2, 0.08)',
                borderWidth: 3,
                fill: true,
                tension: 0.25
            },
            {
                label: 'Keuntungan Bersih (Rp)',
                data: <?php echo json_encode($chart_untung); ?>,
                borderColor: '#2E7D32',
                backgroundColor: 'rgba(46, 125, 50, 0.08)',
                borderWidth: 3,
                fill: true,
                tension: 0.25
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { font: { family: 'Segoe UI', weight: '600' } } }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: '#F1E4BC' } },
            x: { grid: { display: false } }
        }
    }
});

const urlParams = new URLSearchParams(window.location.search);
if(urlParams.get('status') === 'success') {
  const lastId = urlParams.get('last_id');
  const t = document.getElementById('toastContainer');
  
  t.innerHTML = `🎉 Transaksi Sukses! &nbsp;&nbsp; <button onclick="window.open('cetak_struk.php?id=${lastId}', '_blank', 'width=320,height=500')" style="background:var(--kuning); border:none; padding:5px 12px; border-radius:6px; cursor:pointer; font-weight:bold; color:var(--coklat-tua);">🖨️ Cetak Struk Nota</button>`;
  t.style.display = "block";
  
  setTimeout(() => t.style.display = "none", 8000);
} else if(urlParams.get('status') === 'error_empty') {
  const t = document.getElementById('toastContainer');
  t.innerText = "❌ Keranjang Anda masih kosong!";
  t.style.backgroundColor = "var(--merah)";
  t.style.display = "block";
  setTimeout(() => t.style.display = "none", 4000);
} else if(urlParams.get('status') === 'error_stock') {
  const t = document.getElementById('toastContainer');
  t.innerText = "❌ Transaksi gagal. Stok produk di toko tidak mencukupi!";
  t.style.backgroundColor = "var(--merah)";
  t.style.display = "block";
  setTimeout(() => t.style.display = "none", 4000);
} else if(urlParams.get('status') === 'error_invalid') {
  const t = document.getElementById('toastContainer');
  t.innerText = "❌ Gagal: Nama menu baru tidak boleh kosong!";
  t.style.backgroundColor = "var(--merah)";
  t.style.display = "block";
  setTimeout(() => t.style.display = "none", 4000);
}
</script>
</body>
</html>