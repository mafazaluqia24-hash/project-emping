<?php
/** Helper bersama: envelope API, sesi/CSRF, aturan bisnis stok & status. */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function constants(): array
{
    static $c = null;
    return $c ??= require __DIR__ . '/constants.php';
}

// ---------- Envelope API (seragam) ----------

class ApiException extends Exception
{
    public function __construct(public string $errCode, string $message, public int $http = 400)
    {
        parent::__construct($message);
    }
}

function api_send(array $payload, int $http = 200): never
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_success(array $data = [], array $meta = []): never
{
    api_send(['ok' => true, 'data' => $data, 'meta' => (object)$meta]);
}

function api_error(string $code, string $message, int $http = 400): never
{
    api_send(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $http);
}

/** Jalankan handler endpoint dengan penanganan error seragam. */
function api_guard(callable $handler): void
{
    try {
        $handler();
    } catch (ApiException $e) {
        if (Database::get()->inTransaction()) {
            Database::get()->rollBack();
        }
        api_error($e->errCode, $e->getMessage(), $e->http);
    } catch (Throwable $e) {
        try {
            if (Database::get()->inTransaction()) {
                Database::get()->rollBack();
            }
        } catch (Throwable) {
        }
        $debug = (Database::config()['app']['debug'] ?? false);
        api_error('SERVER_ERROR', $debug ? $e->getMessage() : 'Terjadi kesalahan pada server', 500);
    }
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        throw new ApiException('METHOD_NOT_ALLOWED', "Gunakan {$method}", 405);
    }
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw === false ? '' : $raw, true);
    return is_array($data) ? $data : [];
}

function db(): PDO
{
    return Database::get();
}

// ---------- Settings ----------

function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

// ---------- Sesi, admin, CSRF ----------

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $s = Database::config()['session'];
        session_name($s['name']);
        session_set_cookie_params([
            'lifetime' => $s['lifetime'], 'path' => '/', 'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function csrf_token(): string
{
    start_session();
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function require_admin(bool $checkCsrf = false): array
{
    start_session();
    if (empty($_SESSION['admin'])) {
        throw new ApiException('UNAUTHORIZED', 'Login admin diperlukan', 401);
    }
    if ($checkCsrf) {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
            throw new ApiException('CSRF_INVALID', 'Token keamanan tidak valid, muat ulang halaman', 403);
        }
    }
    return $_SESSION['admin'];
}

// ---------- Format & util ----------

function format_rupiah(int $n): string
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function normalize_phone(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($d, '0')) {
        $d = '62' . substr($d, 1);
    } elseif (str_starts_with($d, '8')) {
        $d = '62' . $d;
    }
    return $d;
}

function generate_order_code(): string
{
    return 'EPG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function status_info(string $s): array
{
    return constants()['status'][$s] ?? ['label' => $s, 'color' => '#6b7280', 'description' => ''];
}

function payment_info(string $s): array
{
    return constants()['payment_status'][$s] ?? ['label' => $s, 'color' => '#6b7280'];
}

// ---------- Pesanan: query & aturan ----------

/** Kolom pesanan + hitungan waktu dari jam database (anti manipulasi klien). */
const ORDER_SELECT = 'SELECT o.*,
    TIMESTAMPDIFF(SECOND, NOW(), o.batas_waktu_pembatalan) AS seconds_left,
    TIMESTAMPDIFF(SECOND, o.waktu_dibuat, NOW()) AS age_seconds
    FROM orders o';

function can_customer_cancel(array $o): bool
{
    $rule = constants()['customer_cancel'][$o['status']] ?? null;
    if ($rule === null) {
        return false;
    }
    if ($rule['deadline']) {
        return $o['seconds_left'] !== null && (int)$o['seconds_left'] > 0;
    }
    return true;
}

function dp_needed(int $total): bool
{
    return $total > (int)get_setting('dp_threshold', '200000');
}

function dp_amount_for(int $total): int
{
    return (int)round($total * (int)get_setting('dp_percent', '30') / 100);
}

/** Ubah status + catat audit log. Dipanggil di dalam transaksi pemanggil. */
function set_status(int $orderId, string $old, string $new, string $note, string $actor, array $extra = []): void
{
    $sets = ['status = ?'];
    $vals = [$new];
    foreach ($extra as $col => $val) {
        $sets[] = "{$col} = ?";
        $vals[] = $val;
    }
    $vals[] = $orderId;
    db()->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    add_log($orderId, $old, $new, $note, $actor);
}

function add_log(int $orderId, ?string $old, string $new, string $note, string $actor): void
{
    db()->prepare('INSERT INTO order_status_logs (order_id, status_lama, status_baru, keterangan, aktor) VALUES (?,?,?,?,?)')
        ->execute([$orderId, $old, $new, $note, $actor]);
}

/** Lepas reserve (belum committed) atau kembalikan stok (sudah committed). */
function release_stock(array $order): void
{
    $items = db()->prepare('SELECT product_id, jumlah FROM order_items WHERE order_id = ?');
    $items->execute([$order['id']]);
    foreach ($items->fetchAll() as $it) {
        if ((int)$order['stock_committed'] === 1) {
            $sql = 'UPDATE products SET stok = stok + ? WHERE id = ?';
        } else {
            $sql = 'UPDATE products SET stok_reserved = GREATEST(CAST(stok_reserved AS SIGNED) - ?, 0) WHERE id = ?';
        }
        db()->prepare($sql)->execute([$it['jumlah'], $it['product_id']]);
    }
    db()->prepare('UPDATE orders SET stock_committed = 0 WHERE id = ?')->execute([$order['id']]);
}

/** Saat pesanan diterima: reserve -> potong stok permanen. */
function commit_stock(int $orderId): void
{
    $items = db()->prepare('SELECT product_id, jumlah FROM order_items WHERE order_id = ?');
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $it) {
        db()->prepare('UPDATE products SET stok = GREATEST(stok - ?, 0), stok_reserved = GREATEST(CAST(stok_reserved AS SIGNED) - ?, 0) WHERE id = ?')
            ->execute([$it['jumlah'], $it['jumlah'], $it['product_id']]);
    }
    db()->prepare('UPDATE orders SET stock_committed = 1 WHERE id = ?')->execute([$orderId]);
}

/** Susun payload pesanan seragam untuk pelanggan & admin. */
function order_payload(array $o, bool $detail = true): array
{
    $si = status_info($o['status']);
    $pi = payment_info($o['status_pembayaran']);
    $secondsLeft = $o['seconds_left'] === null ? null : max(0, (int)$o['seconds_left']);
    $remind = constants()['reminder_seconds'];
    $isCountdown = in_array($o['status'], ['terkonfirmasi', 'menunggu_verifikasi_dp'], true) && $secondsLeft !== null;

    $p = [
        'id' => (int)$o['id'],
        'order_code' => $o['order_code'],
        'nama_pemesan' => $o['nama_pemesan'],
        'no_hp' => $o['no_hp'],
        'alamat' => $o['alamat'],
        'status' => $o['status'],
        'status_label' => $si['label'],
        'status_color' => $si['color'],
        'status_pembayaran' => $o['status_pembayaran'],
        'status_pembayaran_label' => $pi['label'],
        'status_pembayaran_color' => $pi['color'],
        'total_harga' => (int)$o['total_harga'],
        'total_formatted' => format_rupiah((int)$o['total_harga']),
        'dp_required' => (bool)$o['dp_required'],
        'dp_amount' => (int)$o['dp_amount'],
        'dp_formatted' => format_rupiah((int)$o['dp_amount']),
        'waktu_dibuat' => $o['waktu_dibuat'],
        'waktu_konfirmasi' => $o['waktu_konfirmasi'],
        'batas_waktu_pembatalan' => $o['batas_waktu_pembatalan'],
        'catatan' => $o['catatan'],
        'alasan_tolak' => $o['alasan_tolak'],
        'needs_escalation' => (bool)$o['needs_escalation'],
        'can_cancel' => can_customer_cancel($o),
        'cancel_expired' => $isCountdown && $secondsLeft === 0,
        'seconds_left' => $isCountdown ? $secondsLeft : null,
        'reminder' => $isCountdown && $secondsLeft > 0 && $secondsLeft <= $remind,
    ];

    if ($detail) {
        $q = db()->prepare('SELECT product_id, nama_produk_snap, varian_snap, harga_snap, jumlah, subtotal FROM order_items WHERE order_id = ?');
        $q->execute([$o['id']]);
        $p['items'] = $q->fetchAll();

        $q = db()->prepare('SELECT status_lama, status_baru, keterangan, aktor, waktu_perubahan FROM order_status_logs WHERE order_id = ? ORDER BY id ASC');
        $q->execute([$o['id']]);
        $p['timeline'] = $q->fetchAll();

        $q = db()->prepare('SELECT id, jumlah, no_referensi, bukti_catatan, status, waktu_bayar, catatan_admin FROM payments WHERE order_id = ? ORDER BY id DESC');
        $q->execute([$o['id']]);
        $p['payments'] = $q->fetchAll();
        $p['has_pending_payment'] = (bool)array_filter($p['payments'], fn($x) => $x['status'] === 'menunggu_verifikasi');
        $p['can_upload_dp'] = $p['dp_required']
            && in_array($o['status'], ['menunggu_konfirmasi', 'menunggu_verifikasi_dp'], true)
            && $o['status_pembayaran'] === 'belum_dibayar';
    } else {
        $q = db()->prepare("SELECT GROUP_CONCAT(CONCAT(nama_produk_snap, ' x', jumlah) SEPARATOR ', ') s FROM order_items WHERE order_id = ?");
        $q->execute([$o['id']]);
        $p['items_summary'] = (string)$q->fetchColumn();
        $q = db()->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ? AND status = 'menunggu_verifikasi'");
        $q->execute([$o['id']]);
        $p['has_pending_payment'] = (int)$q->fetchColumn() > 0;
    }
    $p['admin_actions'] = constants()['admin_actions'][$o['status']] ?? [];
    if (!$p['has_pending_payment']) {
        $p['admin_actions'] = array_values(array_diff($p['admin_actions'], ['verify_dp', 'reject_dp']));
    }
    return $p;
}
