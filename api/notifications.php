<?php
/**
 * Notifikasi in-app dari order_status_logs.
 * POST {orders:[{code,no_hp}]} -> notifikasi pelanggan (+ pengingat H-30 menit)
 * GET (sesi admin)             -> aktivitas terbaru + jumlah eskalasi
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';

api_guard(function () {
    $pdo = db();
    $items = [];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        require_admin();
        $rows = $pdo->query("SELECT o.order_code, l.status_baru, l.keterangan, l.aktor, l.waktu_perubahan
            FROM order_status_logs l JOIN orders o ON o.id = l.order_id ORDER BY l.id DESC LIMIT 30")->fetchAll();
        foreach ($rows as $r) {
            $items[] = ['code' => $r['order_code'], 'title' => status_info($r['status_baru'])['label'], 'text' => $r['keterangan'], 'time' => $r['waktu_perubahan'], 'kind' => 'log'];
        }
        $esc = $pdo->query("SELECT order_code FROM orders WHERE needs_escalation = 1 AND status = 'menunggu_konfirmasi'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($esc as $c) {
            array_unshift($items, ['code' => $c, 'title' => 'Perlu eskalasi', 'text' => 'Belum direspons melewati batas waktu. Segera hubungi pelanggan.', 'time' => null, 'kind' => 'warn']);
        }
        api_success(['notifications' => $items]);
    }

    require_method('POST');
    $list = json_input()['orders'] ?? [];
    if (!is_array($list)) {
        $list = [];
    }
    foreach (array_slice($list, 0, 10) as $ref) {
        $code = strtoupper(trim((string)($ref['code'] ?? '')));
        $phone = normalize_phone((string)($ref['no_hp'] ?? ''));
        $st = $pdo->prepare(ORDER_SELECT . ' WHERE o.order_code = ? AND o.no_hp = ?');
        $st->execute([$code, $phone]);
        $o = $st->fetch();
        if (!$o) {
            continue;
        }
        $pl = order_payload($o, false);
        if ($pl['reminder']) {
            $items[] = ['code' => $code, 'title' => 'Batas pembatalan hampir habis', 'text' => 'Sisa ' . max(1, (int)ceil($pl['seconds_left'] / 60)) . ' menit untuk membatalkan pesanan.', 'time' => null, 'kind' => 'warn'];
        }
        $lg = $pdo->prepare('SELECT status_baru, keterangan, waktu_perubahan FROM order_status_logs WHERE order_id = ? ORDER BY id DESC LIMIT 5');
        $lg->execute([$o['id']]);
        foreach ($lg->fetchAll() as $r) {
            $items[] = ['code' => $code, 'title' => status_info($r['status_baru'])['label'], 'text' => $r['keterangan'], 'time' => $r['waktu_perubahan'], 'kind' => 'log'];
        }
    }
    usort($items, fn($a, $b) => strcmp((string)($b['time'] ?? '9999'), (string)($a['time'] ?? '9999')));
    api_success(['notifications' => array_slice($items, 0, 30)]);
});
