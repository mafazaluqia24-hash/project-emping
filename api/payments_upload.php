<?php
/** POST payments_upload.php {code, no_hp, no_referensi?, bukti_catatan?} - kirim bukti DP */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/validators.php';

api_guard(function () {
    require_method('POST');
    $in = json_input();
    $errors = validate_payment_upload($in);
    if ($errors) {
        throw new ApiException('VALIDATION_ERROR', implode('; ', $errors));
    }
    $code = strtoupper(trim($in['code']));
    $phone = normalize_phone($in['no_hp']);

    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare(ORDER_SELECT . ' WHERE o.order_code = ? AND o.no_hp = ? FOR UPDATE');
    $st->execute([$code, $phone]);
    $o = $st->fetch();
    if (!$o) {
        throw new ApiException('NOT_FOUND', 'Pesanan tidak ditemukan', 404);
    }
    if (!(int)$o['dp_required']) {
        throw new ApiException('INVALID_STATE', 'Pesanan ini tidak memerlukan DP', 409);
    }
    if (!in_array($o['status'], ['menunggu_konfirmasi', 'menunggu_verifikasi_dp'], true) || $o['status_pembayaran'] !== 'belum_dibayar') {
        throw new ApiException('INVALID_STATE', 'Bukti DP tidak dapat dikirim pada status pesanan saat ini', 409);
    }

    $pdo->prepare("INSERT INTO payments (order_id, jumlah, no_referensi, bukti_catatan, status, waktu_bayar) VALUES (?,?,?,?, 'menunggu_verifikasi', NOW())")
        ->execute([$o['id'], (int)$o['dp_amount'], trim((string)($in['no_referensi'] ?? '')) ?: null, trim((string)($in['bukti_catatan'] ?? '')) ?: null]);
    $pdo->prepare("UPDATE orders SET status_pembayaran = 'menunggu_verifikasi' WHERE id = ?")->execute([$o['id']]);
    add_log((int)$o['id'], $o['status'], $o['status'], 'Bukti DP ' . format_rupiah((int)$o['dp_amount']) . ' dikirim, menunggu verifikasi', 'pelanggan');
    $pdo->commit();

    api_success(['order_code' => $code], ['message' => 'Bukti pembayaran terkirim, menunggu verifikasi admin']);
});
