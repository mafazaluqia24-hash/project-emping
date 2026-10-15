<?php
/** POST orders_track.php {code, no_hp} -> detail + timeline + hitung mundur (detik dari jam server) */
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
    $st = db()->prepare(ORDER_SELECT . ' WHERE o.order_code = ? AND o.no_hp = ?');
    $st->execute([$code, $phone]);
    $o = $st->fetch();
    if (!$o) {
        throw new ApiException('NOT_FOUND', 'Pesanan tidak ditemukan. Periksa kode dan nomor HP.', 404);
    }
    api_success(['order' => order_payload($o)], [
        'seller_phone' => get_setting('phone_seller'),
        'bank_info' => get_setting('bank_info', ''),
    ]);
});
