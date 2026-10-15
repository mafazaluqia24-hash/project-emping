<?php
/** POST orders_cancel.php {code, no_hp} - batas waktu dicek di server */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';

api_guard(function () {
    require_method('POST');
    $in = json_input();
    $code = strtoupper(trim((string)($in['code'] ?? '')));
    $phone = normalize_phone((string)($in['no_hp'] ?? ''));
    if ($code === '' || $phone === '') {
        throw new ApiException('VALIDATION_ERROR', 'Kode pesanan dan nomor HP wajib diisi');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare(ORDER_SELECT . ' WHERE o.order_code = ? AND o.no_hp = ? FOR UPDATE');
    $st->execute([$code, $phone]);
    $o = $st->fetch();
    if (!$o) {
        throw new ApiException('NOT_FOUND', 'Pesanan tidak ditemukan', 404);
    }

    if (!can_customer_cancel($o)) {
        $why = match (true) {
            in_array($o['status'], ['terkonfirmasi', 'menunggu_verifikasi_dp'], true) => 'Batas waktu pembatalan sudah lewat. Silakan hubungi penjual via WhatsApp.',
            $o['status'] === 'diproduksi' => 'Pesanan sudah masuk produksi dan tidak dapat dibatalkan.',
            default => 'Pesanan pada status "' . status_info($o['status'])['label'] . '" tidak dapat dibatalkan.',
        };
        throw new ApiException('CANNOT_CANCEL', $why, 409);
    }

    release_stock($o);

    $payStatus = $o['status_pembayaran'];
    if (in_array($payStatus, ['lunas', 'menunggu_verifikasi'], true)) {
        $pdo->prepare("UPDATE payments SET status = 'dikembalikan' WHERE order_id = ? AND status IN ('lunas','menunggu_verifikasi')")->execute([$o['id']]);
        $payStatus = 'dikembalikan';
    }
    set_status((int)$o['id'], $o['status'], 'dibatalkan_pelanggan',
        'Dibatalkan pelanggan' . ($payStatus === 'dikembalikan' ? '; DP dikembalikan penuh' : ''), 'pelanggan',
        ['status_pembayaran' => $payStatus, 'needs_escalation' => 0]);
    $pdo->commit();

    api_success(['order_code' => $code, 'status' => 'dibatalkan_pelanggan'], ['message' => 'Pesanan dibatalkan']);
});
