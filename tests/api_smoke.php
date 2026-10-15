<?php
/**
 * Smoke test end-to-end terhadap server yang sedang berjalan.
 * Pakai: php -S 127.0.0.1:8080 -t . &  lalu  php tests/api_smoke.php [http://127.0.0.1:8080]
 * Database harus baru (schema.sql baru diimpor) dengan PIN admin default 1234.
 */
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$cookie = tempnam(sys_get_temp_dir(), 'emping');
$csrf = null;
$fails = 0;

function call(string $path, ?array $body = null, bool $withCsrf = false): array
{
    global $base, $cookie, $csrf;
    $ch = curl_init("$base/api/$path");
    $headers = ['Content-Type: application/json'];
    if ($withCsrf && $csrf) {
        $headers[] = "X-CSRF-Token: $csrf";
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_HTTPHEADER => $headers]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $json = json_decode((string)$raw, true);
    if (!is_array($json) || !isset($json['ok'])) {
        fwrite(STDERR, "Respons bukan envelope valid dari $path ($http): $raw\n");
        exit(2);
    }
    return $json + ['_http' => $http];
}
function check(string $name, bool $cond, $info = ''): void
{
    global $fails;
    echo ($cond ? "  ok   " : "  FAIL ") . $name . ($cond ? '' : ' -> ' . json_encode($info)) . "\n";
    $fails += $cond ? 0 : 1;
}
function admin(string $action, int $id, array $extra = []): array
{
    return call('admin.php', ['action' => $action, 'order_id' => $id] + $extra, true);
}
function stock(int $pid): array
{
    foreach (call('products.php')['data']['products'] as $p) {
        if ($p['id'] === $pid) {
            return $p;
        }
    }
    return [];
}

echo "Produk\n";
$before = stock(1)['stok_tersedia'];
check('daftar produk tampil', $before > 0);

echo "Pesanan kecil (tanpa DP) + batal sebelum konfirmasi\n";
$r = call('orders_create.php', ['nama_pemesan' => 'Uji', 'no_hp' => '08123456789', 'alamat' => 'Jl. Uji 1', 'items' => [['product_id' => 3, 'jumlah' => 2]]]);
check('order dibuat', $r['ok'] === true, $r);
$small = $r['data']['order_code'] ?? '';
$hp = $r['data']['no_hp'] ?? '';
check('nomor HP dinormalkan ke 62', str_starts_with($hp, '62'));
check('tanpa DP', $r['data']['dp_required'] === false);
check('stok ter-reserve', stock(3)['stok_tersedia'] === 38);
$t = call('orders_track.php', ['code' => $small, 'no_hp' => '000']);
check('lacak dengan HP salah ditolak', $t['ok'] === false && $t['_http'] === 404);
$c = call('orders_cancel.php', ['code' => $small, 'no_hp' => $hp]);
check('batal berhasil', $c['ok'] === true, $c);
check('stok kembali', stock(3)['stok_tersedia'] === 40);

echo "Validasi & stok\n";
check('validasi gagal', call('orders_create.php', ['nama_pemesan' => '', 'items' => []])['ok'] === false);
check('stok tidak cukup ditolak', call('orders_create.php', ['nama_pemesan' => 'U', 'no_hp' => '08123456789', 'alamat' => 'x', 'items' => [['product_id' => 3, 'jumlah' => 999]]])['_http'] === 409);

echo "Admin: auth & CSRF\n";
check('admin tanpa login 401', call('admin_dashboard.php')['_http'] === 401);
check('PIN salah 401', call('login.php', ['pin' => '0000'])['_http'] === 401);
$l = call('login.php', ['pin' => '1234']);
check('login berhasil', $l['ok'] === true, $l);
$csrf = $l['data']['csrf_token'] ?? null;
check('aksi tanpa CSRF ditolak', call('admin.php', ['action' => 'confirm', 'order_id' => 1])['_http'] === 403);

echo "Alur DP penuh\n";
$r = call('orders_create.php', ['nama_pemesan' => 'Besar', 'no_hp' => '08129999999', 'alamat' => 'Jl. Besar', 'items' => [['product_id' => 1, 'jumlah' => 4]]]);
$code = $r['data']['order_code'];
$hp2 = $r['data']['no_hp'];
check('DP wajib & 30%', $r['data']['dp_required'] === true && $r['data']['dp_amount'] === 78000, $r['data']);
$o = call('orders_track.php', ['code' => $code, 'no_hp' => $hp2])['data']['order'];
$id = $o['id'];
check('produksi sebelum konfirmasi ditolak', admin('start_production', $id)['_http'] === 409);
check('terima', admin('confirm', $id)['ok'] === true);
$o = call('orders_track.php', ['code' => $code, 'no_hp' => $hp2])['data']['order'];
check('status menunggu verifikasi DP', $o['status'] === 'menunggu_verifikasi_dp', $o['status']);
check('countdown dari server (~2 jam)', $o['seconds_left'] > 7000 && $o['seconds_left'] <= 7200, $o['seconds_left']);
check('stok dipotong permanen', stock(1)['stok_tersedia'] === $before - 4);
check('produksi diblokir sebelum DP', admin('start_production', $id)['_http'] === 409);
check('verifikasi tanpa bukti ditolak', admin('verify_dp', $id)['_http'] === 409);
$up = call('payments_upload.php', ['code' => $code, 'no_hp' => $hp2, 'no_referensi' => 'TRX123']);
check('upload bukti DP', $up['ok'] === true, $up);
check('verifikasi DP', admin('verify_dp', $id)['ok'] === true);
check('produksi', admin('start_production', $id)['ok'] === true);
$c = call('orders_cancel.php', ['code' => $code, 'no_hp' => $hp2]);
check('batal saat produksi ditolak', $c['ok'] === false && $c['_http'] === 409);
check('siap kirim', admin('ready', $id)['ok'] === true);
check('selesai', admin('complete', $id)['ok'] === true);
$o = call('orders_track.php', ['code' => $code, 'no_hp' => $hp2])['data']['order'];
check('status akhir selesai & timeline terisi', $o['status'] === 'selesai' && count($o['timeline']) >= 6, count($o['timeline']));

echo "Penolakan penjual\n";
$r = call('orders_create.php', ['nama_pemesan' => 'Tolak', 'no_hp' => '08127777777', 'alamat' => 'x', 'items' => [['product_id' => 2, 'jumlah' => 1]]]);
$o = call('orders_track.php', ['code' => $r['data']['order_code'], 'no_hp' => $r['data']['no_hp']])['data']['order'];
check('tolak tanpa alasan ditolak', admin('reject', $o['id'])['ok'] === false);
check('tolak dengan alasan', admin('reject', $o['id'], ['alasan' => 'Bahan habis'])['ok'] === true);
check('stok reserve dilepas', stock(2)['stok_tersedia'] === 30);

echo "Dashboard & pengaturan\n";
$d = call('admin_dashboard.php');
check('dashboard memuat pesanan & statistik', $d['ok'] && count($d['data']['orders']) >= 3 && isset($d['data']['stats']['selesai']));
check('ubah batas pembatalan', call('admin_settings.php', ['cancel_window_hours' => 3], true)['ok'] === true);
check('nilai tidak valid ditolak', call('admin_settings.php', ['cancel_window_hours' => 99], true)['ok'] === false);
call('admin_settings.php', ['cancel_window_hours' => 2], true);

echo $fails === 0 ? "\nSEMUA TES LULUS\n" : "\n{$fails} TES GAGAL\n";
exit($fails === 0 ? 0 : 1);
