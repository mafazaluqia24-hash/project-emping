<?php
/**
 * POST admin.php {action, order_id, alasan?, catatan_admin?}  (header X-CSRF-Token)
 * Transisi yang sah diambil dari constants.php['admin_actions'] (sumber tunggal dengan UI).
 */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';

api_guard(function () {
    require_method('POST');
    require_admin(true);
    $in = json_input();
    $action = (string)($in['action'] ?? '');
    $orderId = (int)($in['order_id'] ?? 0);
    $labels = constants()['action_labels'];
    if (!isset($labels[$action]) || $orderId < 1) {
        throw new ApiException('VALIDATION_ERROR', 'Aksi atau order_id tidak valid');
    }
    $reason = trim((string)($in['alasan'] ?? ''));
    if (in_array($action, constants()['action_needs_reason'], true) && $reason === '') {
        throw new ApiException('VALIDATION_ERROR', 'Alasan wajib diisi');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare(ORDER_SELECT . ' WHERE o.id = ? FOR UPDATE');
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o) {
        throw new ApiException('NOT_FOUND', 'Pesanan tidak ditemukan', 404);
    }
    $old = $o['status'];
    if (!in_array($action, constants()['admin_actions'][$old] ?? [], true)) {
        throw new ApiException('INVALID_TRANSITION', "Aksi \"{$labels[$action]}\" tidak berlaku untuk status \"" . status_info($old)['label'] . '"', 409);
    }

    switch ($action) {
        case 'confirm':
            // Batas pembatalan dihitung dari waktu konfirmasi, memakai jam database
            $hours = (int)get_setting('cancel_window_hours', '2');
            commit_stock((int)$o['id']);
            $new = (int)$o['dp_required'] ? 'menunggu_verifikasi_dp' : 'terkonfirmasi';
            $pdo->prepare('UPDATE orders SET waktu_konfirmasi = NOW(), batas_waktu_pembatalan = DATE_ADD(NOW(), INTERVAL ? HOUR), needs_escalation = 0 WHERE id = ?')
                ->execute([$hours, $o['id']]);
            set_status((int)$o['id'], $old, $new,
                "Pesanan diterima penjual; batas pembatalan {$hours} jam" . ($new === 'menunggu_verifikasi_dp' ? '; DP wajib diverifikasi sebelum produksi' : ''), 'admin');
            break;

        case 'reject':
            release_stock($o);
            $pay = $o['status_pembayaran'];
            if (in_array($pay, ['lunas', 'menunggu_verifikasi'], true)) {
                $pdo->prepare("UPDATE payments SET status = 'dikembalikan' WHERE order_id = ? AND status IN ('lunas','menunggu_verifikasi')")->execute([$o['id']]);
                $pay = 'dikembalikan';
            }
            set_status((int)$o['id'], $old, 'ditolak_penjual', 'Ditolak penjual: ' . $reason . ($pay === 'dikembalikan' ? '; DP dikembalikan penuh' : ''), 'admin',
                ['alasan_tolak' => $reason, 'status_pembayaran' => $pay, 'needs_escalation' => 0]);
            $new = 'ditolak_penjual';
            break;

        case 'verify_dp':
            $p = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? AND status = 'menunggu_verifikasi' ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $p->execute([$o['id']]);
            $pid = $p->fetchColumn();
            if (!$pid) {
                throw new ApiException('NO_PAYMENT', 'Belum ada bukti DP yang menunggu verifikasi', 409);
            }
            $pdo->prepare("UPDATE payments SET status = 'lunas', waktu_verifikasi = NOW(), catatan_admin = ? WHERE id = ?")
                ->execute([trim((string)($in['catatan_admin'] ?? '')) ?: null, $pid]);
            set_status((int)$o['id'], $old, 'terkonfirmasi', 'DP terverifikasi, pesanan siap diproduksi', 'admin', ['status_pembayaran' => 'lunas']);
            $new = 'terkonfirmasi';
            break;

        case 'reject_dp':
            $pdo->prepare("UPDATE payments SET status = 'ditolak', waktu_verifikasi = NOW(), catatan_admin = ? WHERE order_id = ? AND status = 'menunggu_verifikasi'")
                ->execute([$reason, $o['id']]);
            // Status pesanan tetap; pelanggan dapat mengirim ulang bukti
            $pdo->prepare("UPDATE orders SET status_pembayaran = 'belum_dibayar' WHERE id = ?")->execute([$o['id']]);
            add_log((int)$o['id'], $old, $old, 'Bukti DP ditolak: ' . $reason . ' (kirim ulang bukti)', 'admin');
            $new = $old;
            break;

        case 'start_production':
            if ((int)$o['dp_required'] && $o['status_pembayaran'] !== 'lunas') {
                throw new ApiException('DP_NOT_VERIFIED', 'DP belum terverifikasi, produksi diblokir', 409);
            }
            set_status((int)$o['id'], $old, 'diproduksi', 'Produksi dimulai', 'admin');
            $new = 'diproduksi';
            break;

        case 'ready':
            set_status((int)$o['id'], $old, 'siap_kirim', 'Pesanan siap dikirim/diambil', 'admin');
            $new = 'siap_kirim';
            break;

        case 'complete':
            set_status((int)$o['id'], $old, 'selesai', 'Pesanan selesai', 'admin');
            $new = 'selesai';
            break;
    }
    $pdo->commit();
    api_success(['order_id' => $orderId, 'old_status' => $old, 'new_status' => $new], ['message' => $labels[$action] . ' berhasil']);
});
