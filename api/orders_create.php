<?php
/** POST orders_create.php {nama_pemesan,no_hp,alamat,catatan?,items:[{product_id,jumlah}]} */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/validators.php';

api_guard(function () {
    require_method('POST');
    $in = json_input();
    $errors = validate_create_order($in);
    if ($errors) {
        throw new ApiException('VALIDATION_ERROR', implode('; ', $errors));
    }

    // Gabungkan item duplikat
    $qtyByProduct = [];
    foreach ($in['items'] as $it) {
        $pid = (int)$it['product_id'];
        $qtyByProduct[$pid] = ($qtyByProduct[$pid] ?? 0) + (int)$it['jumlah'];
    }
    ksort($qtyByProduct); // urutan tetap -> hindari deadlock

    $pdo = db();
    $pdo->beginTransaction();

    $total = 0;
    $lines = [];
    foreach ($qtyByProduct as $pid => $qty) {
        $st = $pdo->prepare('SELECT id, nama_produk, varian, harga, stok, stok_reserved FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
        $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p) {
            throw new ApiException('PRODUCT_NOT_FOUND', "Produk #{$pid} tidak ditemukan");
        }
        $available = (int)$p['stok'] - (int)$p['stok_reserved'];
        if ($qty > $available) {
            throw new ApiException('OUT_OF_STOCK', "Stok {$p['nama_produk']} tidak cukup (tersedia {$available})", 409);
        }
        $sub = (int)$p['harga'] * $qty;
        $total += $sub;
        $lines[] = [$p, $qty, $sub];
    }

    $needsDp = dp_needed($total);
    $dpAmount = $needsDp ? dp_amount_for($total) : 0;
    $phone = normalize_phone($in['no_hp']);

    $orderId = 0;
    $code = '';
    for ($try = 0; $try < 5 && !$orderId; $try++) {
        $code = generate_order_code();
        try {
            $pdo->prepare('INSERT INTO orders (order_code, nama_pemesan, no_hp, alamat, status, total_harga, dp_required, dp_amount, catatan)
                           VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$code, trim($in['nama_pemesan']), $phone, trim($in['alamat']), 'menunggu_konfirmasi', $total, (int)$needsDp, $dpAmount,
                           isset($in['catatan']) ? trim((string)$in['catatan']) : null]);
            $orderId = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e; // bukan tabrakan kode unik
            }
        }
    }
    if (!$orderId) {
        throw new ApiException('CODE_GENERATION_FAILED', 'Gagal membuat kode pesanan, coba lagi', 500);
    }

    foreach ($lines as [$p, $qty, $sub]) {
        $pdo->prepare('INSERT INTO order_items (order_id, product_id, nama_produk_snap, varian_snap, harga_snap, jumlah, subtotal) VALUES (?,?,?,?,?,?,?)')
            ->execute([$orderId, $p['id'], $p['nama_produk'], $p['varian'], $p['harga'], $qty, $sub]);
        $pdo->prepare('UPDATE products SET stok_reserved = stok_reserved + ? WHERE id = ?')->execute([$qty, $p['id']]);
    }

    add_log($orderId, null, 'pesanan_dibuat', 'Pesanan dibuat, stok di-reserve', 'pelanggan');
    add_log($orderId, 'pesanan_dibuat', 'menunggu_konfirmasi', 'Menunggu konfirmasi penjual', 'sistem');
    $pdo->commit();

    api_success([
        'order_code' => $code,
        'no_hp' => $phone,
        'status' => 'menunggu_konfirmasi',
        'total_harga' => $total,
        'total_formatted' => format_rupiah($total),
        'dp_required' => $needsDp,
        'dp_amount' => $dpAmount,
        'dp_formatted' => format_rupiah($dpAmount),
        'seller_phone' => get_setting('phone_seller'),
    ], ['message' => 'Pesanan berhasil dibuat']);
});
