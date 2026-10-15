<?php
/** GET products.php - katalog aktif + stok tersedia (stok - reserved) */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';

api_guard(function () {
    require_method('GET');
    $rows = db()->query('SELECT id, nama_produk, varian, harga, stok, stok_reserved, deskripsi FROM products WHERE is_active = 1 ORDER BY id')->fetchAll();
    foreach ($rows as &$p) {
        $p['id'] = (int)$p['id'];
        $p['harga'] = (int)$p['harga'];
        $p['harga_formatted'] = format_rupiah($p['harga']);
        $p['stok_tersedia'] = max(0, (int)$p['stok'] - (int)$p['stok_reserved']);
        unset($p['stok'], $p['stok_reserved']);
    }
    api_success(['products' => $rows], [
        'dp_threshold' => (int)get_setting('dp_threshold', '200000'),
        'dp_percent' => (int)get_setting('dp_percent', '30'),
        'bank_info' => get_setting('bank_info', ''),
    ]);
});
