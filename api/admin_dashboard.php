<?php
/** GET admin_dashboard.php?status=all|<status>&limit=&offset= */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';

api_guard(function () {
    require_method('GET');
    require_admin();
    $pdo = db();

    // Eskalasi (Rule 5): tandai, JANGAN auto-batal
    $hours = max(1, (int)get_setting('escalation_hours', '6'));
    $pdo->prepare("UPDATE orders SET needs_escalation = 1 WHERE status = 'menunggu_konfirmasi' AND needs_escalation = 0 AND waktu_dibuat <= DATE_SUB(NOW(), INTERVAL ? HOUR)")
        ->execute([$hours]);

    $status = (string)($_GET['status'] ?? 'all');
    $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $where = '';
    $params = [];
    if ($status !== 'all') {
        if (!isset(constants()['status'][$status])) {
            throw new ApiException('VALIDATION_ERROR', 'Filter status tidak valid');
        }
        $where = 'WHERE o.status = ?';
        $params[] = $status;
    }
    $sql = ORDER_SELECT . " {$where} ORDER BY
        CASE o.status WHEN 'menunggu_konfirmasi' THEN 1 WHEN 'menunggu_verifikasi_dp' THEN 2 WHEN 'terkonfirmasi' THEN 3
                      WHEN 'diproduksi' THEN 4 WHEN 'siap_kirim' THEN 5 ELSE 6 END,
        o.needs_escalation DESC, o.waktu_dibuat DESC LIMIT {$limit} OFFSET {$offset}";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $orders = array_map(fn($o) => order_payload($o, false), $st->fetchAll());

    $stats = array_fill_keys(array_keys(constants()['status']), 0);
    foreach ($pdo->query('SELECT status, COUNT(*) c FROM orders GROUP BY status') as $r) {
        $stats[$r['status']] = (int)$r['c'];
    }
    $revenue = (int)$pdo->query("SELECT COALESCE(SUM(total_harga),0) FROM orders WHERE status = 'selesai'")->fetchColumn();
    $escalated = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE needs_escalation = 1 AND status = 'menunggu_konfirmasi'")->fetchColumn();

    api_success(['orders' => $orders, 'stats' => $stats, 'revenue_formatted' => format_rupiah($revenue), 'escalated' => $escalated]);
});
