<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';

$C = constants();
try {
    $siteName = get_setting('site_name', 'Emping Bu Sari');
    $sellerPhone = get_setting('phone_seller');
} catch (Throwable $e) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;padding:2rem"><h2>Database belum siap</h2>'
       . '<p>Pastikan <b>MySQL</b> di XAMPP berjalan dan <code>schema.sql</code> sudah diimpor (lihat README).</p>';
    exit;
}
// Konfigurasi dikirim ke JS: status/label/warna/aksi berasal dari constants.php (sumber tunggal)
$appConfig = [
    'apiBase' => '../api/',
    'siteName' => $siteName,
    'sellerPhone' => $sellerPhone,
    'status' => $C['status'],
    'mainFlow' => $C['main_flow'],
    'paymentStatus' => $C['payment_status'],
    'actionLabels' => $C['action_labels'],
    'actionNeedsReason' => $C['action_needs_reason'],
];
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($siteName) ?> - Pesan Emping</title>
<link rel="stylesheet" href="assets/style.css">
<script>window.APP_CONFIG = <?= json_encode($appConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
</head>
<body>
<header class="navbar">
  <div class="nav-container">
    <a href="#/home" class="brand"><span class="brand-mark">E</span><span><?= htmlspecialchars($siteName) ?></span></a>
    <nav class="nav-menu" id="navMenu" aria-label="Navigasi utama">
      <a href="#/home" data-route="home">Beranda</a>
      <a href="#/order" data-route="order">Pesan <span class="pill" id="cartCount" hidden>0</span></a>
      <a href="#/tracking" data-route="tracking">Pesanan Saya</a>
      <a href="#/admin" data-route="admin">Admin</a>
    </nav>
    <div class="nav-actions">
      <button class="icon-btn" id="bellBtn" aria-label="Notifikasi" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        <span class="pill pill-alert" id="bellBadge" hidden>0</span>
      </button>
      <button class="icon-btn nav-toggle" id="navToggle" aria-label="Menu" aria-expanded="false">&#9776;</button>
    </div>
    <div class="dropdown" id="bellDropdown" hidden>
      <div class="dropdown-head"><strong>Notifikasi</strong></div>
      <div id="bellList"></div>
    </div>
  </div>
</header>
<main id="app" class="container" role="main"></main>
<footer class="footer"><p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> &middot; Pemesanan UMKM Emping</p></footer>
<div class="modal-overlay" id="modalOverlay" hidden><div class="modal" id="modal" role="dialog" aria-modal="true"></div></div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<script src="js/main.js"></script>
</body>
</html>
