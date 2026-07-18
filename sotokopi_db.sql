-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 18, 2026 at 04:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sotokopi_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `detail_transaksi`
--

CREATE TABLE `detail_transaksi` (
  `id` int(11) NOT NULL,
  `id_transaksi` int(11) DEFAULT NULL,
  `nama_produk` varchar(100) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `harga_jual` int(11) DEFAULT NULL,
  `harga_modal` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_transaksi`
--

INSERT INTO `detail_transaksi` (`id`, `id_transaksi`, `nama_produk`, `qty`, `harga_jual`, `harga_modal`) VALUES
(1, 1, 'Kopi Susu Gula Aren', 1, 15000, 6000),
(2, 1, 'Kopi Tubruk', 1, 8000, 3000),
(3, 1, 'Soto Daging Sapi', 1, 22000, 14000),
(4, 2, 'Es Teh Manis', 3, 5000, 1500),
(5, 2, 'Soto Ayam Kampung', 2, 18000, 11000),
(6, 2, 'Kopi Susu Gula Aren', 1, 15000, 6000),
(7, 3, 'Bakso Ranjau', 1, 20000, 17000),
(8, 3, 'Es Teh Manis', 1, 5000, 1500),
(9, 3, 'Kopi Susu Gula Aren', 1, 15000, 6000),
(10, 4, 'Kopi Tubruk', 2, 8000, 3000),
(11, 4, 'Kopi Susu Gula Aren', 1, 15000, 6000),
(12, 4, 'Soto Daging Sapi', 1, 22000, 14000),
(13, 4, 'Soto Ayam Kampung', 1, 18000, 11000),
(14, 4, 'Bakso Ranjau', 1, 20000, 17000);

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan`
--

CREATE TABLE `pengaturan` (
  `kunci` varchar(50) NOT NULL,
  `nilai` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengaturan`
--

INSERT INTO `pengaturan` (`kunci`, `nilai`) VALUES
('qris_img', 'uploads/1784384045_WhatsApp Image 2026-07-18 at 21.12.54.jpeg'),
('qris_nama', 'Kedai Sotokopi - a.n. Andi Rayhan');

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kategori` enum('makanan','minuman') NOT NULL,
  `harga` int(11) NOT NULL,
  `harga_modal` int(11) NOT NULL,
  `stok` int(11) NOT NULL,
  `icon` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id`, `nama`, `kategori`, `harga`, `harga_modal`, `stok`, `icon`, `deskripsi`) VALUES
(1, 'Soto Ayam Kampung', 'makanan', 18000, 11000, 17, 'uploads/soto.jpg', 'Soto ayam kuah bening, khas nusantara'),
(2, 'Soto Daging Sapi', 'makanan', 22000, 14000, 13, 'uploads/sopi.jpg', 'Soto daging sapi empuk, kuah gurih'),
(3, 'Kopi Tubruk', 'minuman', 8000, 3000, 37, 'uploads/kopi.jpg', 'Kopi hitam khas warung kopi'),
(4, 'Kopi Susu Gula Aren', 'minuman', 15000, 6000, 31, 'uploads/koren.jpg', 'Kopi susu manis gula aren'),
(5, 'Es Teh Manis', 'minuman', 5000, 1500, 46, 'uploads/teh.jpg', 'Teh manis dingin segar'),
(10, '', 'makanan', 0, 0, 0, 'uploads/soto.jpg', ''),
(11, '', 'makanan', 0, 0, 0, 'uploads/soto.jpg', ''),
(12, 'Bakso Ranjau', 'makanan', 20000, 17000, 8, 'uploads/1784380242_bakso.jpg', 'Gurih, Pedas, jeletot');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp(),
  `total` int(11) NOT NULL,
  `keuntungan` int(11) NOT NULL,
  `metode` enum('cash','qris') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id`, `waktu`, `total`, `keuntungan`, `metode`) VALUES
(1, '2026-07-18 13:05:04', 45000, 22000, 'cash'),
(2, '2026-07-18 13:07:05', 66000, 33500, 'qris'),
(3, '2026-07-18 13:10:52', 40000, 15500, 'cash'),
(4, '2026-07-18 14:11:33', 91000, 37000, 'cash');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_transaksi` (`id_transaksi`);

--
-- Indexes for table `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`kunci`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD CONSTRAINT `detail_transaksi_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
