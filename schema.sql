-- ============================================================
-- Emping UMKM - skema MySQL/MariaDB (aman dijalankan ulang)
-- Impor: D:\xampp\mysql\bin\mysql.exe -u root < schema.sql
-- Waktu memakai DATETIME + NOW() milik database (satu sumber waktu)
-- ============================================================
CREATE DATABASE IF NOT EXISTS `emping_umkm` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `emping_umkm`;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(64) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `description`   VARCHAR(255) DEFAULT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`,`description`) VALUES
('cancel_window_hours','2','Durasi batas pembatalan (jam) dihitung dari waktu konfirmasi'),
('dp_threshold','200000','Pesanan dengan total di atas nilai ini wajib DP (Rp)'),
('dp_percent','30','Persentase DP dari total'),
('escalation_hours','6','Jam tanpa respons penjual sebelum ditandai eskalasi'),
('admin_pin_hash','$2y$10$6qinPhPVZ7AUvu/Wy3WuxuZLluOeyDfoebG1jXVBDURK5fNGlabnq','Hash bcrypt PIN admin (default 1234 - GANTI)'),
('phone_seller','6281234567890','Nomor WhatsApp penjual'),
('bank_info','BCA 1234567890 a.n. Bu Sari','Info rekening untuk transfer DP'),
('site_name','Emping Bu Sari','Nama toko');

CREATE TABLE IF NOT EXISTS `products` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nama_produk`   VARCHAR(150) NOT NULL,
  `varian`        VARCHAR(100) DEFAULT NULL,
  `harga`         INT UNSIGNED NOT NULL DEFAULT 0,
  `stok`          INT UNSIGNED NOT NULL DEFAULT 0,
  `stok_reserved` INT UNSIGNED NOT NULL DEFAULT 0,
  `deskripsi`     TEXT DEFAULT NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` (`nama_produk`,`varian`,`harga`,`stok`,`deskripsi`)
SELECT * FROM (
  SELECT 'Emping Mentah Super' a,'1 kg' b,65000 c,50 d,'Kualitas pilihan, tebal sedang, siap jemur/goreng.' e UNION ALL
  SELECT 'Emping Mentah Manis Pedas','500 g',40000,30,'Mentah bumbu balado khas, siap goreng.' UNION ALL
  SELECT 'Emping Matang Original Asin','250 g',30000,40,'Goreng matang renyah dan gurih, siap santap.' UNION ALL
  SELECT 'Emping Matang Balado','250 g',35000,35,'Goreng matang berbalut bumbu balado spesial.'
) t WHERE NOT EXISTS (SELECT 1 FROM `products`);

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nama`          VARCHAR(100) NOT NULL,
  `no_hp`         VARCHAR(20) NOT NULL,
  `role`          ENUM('admin','kurir') NOT NULL DEFAULT 'admin',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_no_hp` (`no_hp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `users` (`nama`,`no_hp`,`role`) VALUES ('Admin Bu Sari','6281234567890','admin');

CREATE TABLE IF NOT EXISTS `orders` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_code`             VARCHAR(32) NOT NULL,
  `nama_pemesan`           VARCHAR(100) NOT NULL,
  `no_hp`                  VARCHAR(20) NOT NULL,
  `alamat`                 TEXT NOT NULL,
  `status` ENUM('pesanan_dibuat','menunggu_konfirmasi','terkonfirmasi','menunggu_verifikasi_dp',
                'diproduksi','siap_kirim','selesai','dibatalkan_pelanggan','ditolak_penjual')
           NOT NULL DEFAULT 'menunggu_konfirmasi',
  `status_pembayaran` ENUM('belum_dibayar','menunggu_verifikasi','lunas','dikembalikan','hangus')
           NOT NULL DEFAULT 'belum_dibayar',
  `total_harga`            INT UNSIGNED NOT NULL DEFAULT 0,
  `dp_required`            TINYINT(1) NOT NULL DEFAULT 0,
  `dp_amount`              INT UNSIGNED NOT NULL DEFAULT 0,
  `stock_committed`        TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=stok baru di-reserve, 1=stok sudah dipotong permanen',
  `waktu_dibuat`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `waktu_konfirmasi`       DATETIME DEFAULT NULL,
  `batas_waktu_pembatalan` DATETIME DEFAULT NULL,
  `catatan`                TEXT DEFAULT NULL,
  `alasan_tolak`           TEXT DEFAULT NULL,
  `needs_escalation`       TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_order_code` (`order_code`),
  INDEX `idx_status` (`status`),
  INDEX `idx_no_hp` (`no_hp`),
  INDEX `idx_waktu_dibuat` (`waktu_dibuat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id`         INT UNSIGNED NOT NULL,
  `product_id`       INT UNSIGNED NOT NULL,
  `nama_produk_snap` VARCHAR(150) NOT NULL,
  `varian_snap`      VARCHAR(100) DEFAULT NULL,
  `harga_snap`       INT UNSIGNED NOT NULL,
  `jumlah`           INT UNSIGNED NOT NULL DEFAULT 1,
  `subtotal`         INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  INDEX `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_status_logs` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id`        INT UNSIGNED NOT NULL,
  `status_lama`     VARCHAR(32) DEFAULT NULL,
  `status_baru`     VARCHAR(32) NOT NULL,
  `keterangan`      TEXT DEFAULT NULL,
  `aktor`           ENUM('pelanggan','admin','sistem') NOT NULL DEFAULT 'sistem',
  `waktu_perubahan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_order_id` (`order_id`),
  INDEX `idx_waktu` (`waktu_perubahan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id`         INT UNSIGNED NOT NULL,
  `jumlah`           INT UNSIGNED NOT NULL DEFAULT 0,
  `metode`           ENUM('transfer_manual','lainnya') NOT NULL DEFAULT 'transfer_manual',
  `no_referensi`     VARCHAR(100) DEFAULT NULL,
  `bukti_catatan`    TEXT DEFAULT NULL,
  `status`           ENUM('menunggu_verifikasi','lunas','ditolak','dikembalikan','hangus') NOT NULL DEFAULT 'menunggu_verifikasi',
  `waktu_bayar`      DATETIME DEFAULT NULL,
  `waktu_verifikasi` DATETIME DEFAULT NULL,
  `catatan_admin`    TEXT DEFAULT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
