<?php
/** POST login.php {pin} */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';

api_guard(function () {
    require_method('POST');
    start_session();
    $now = time();
    $lockUntil = $_SESSION['login_lock'] ?? 0;
    if ($lockUntil > $now) {
        throw new ApiException('TOO_MANY_ATTEMPTS', 'Terlalu banyak percobaan. Coba lagi dalam ' . ($lockUntil - $now) . ' detik.', 429);
    }
    $pin = (string)(json_input()['pin'] ?? '');
    if ($pin === '') {
        throw new ApiException('VALIDATION_ERROR', 'PIN wajib diisi');
    }
    $hash = get_setting('admin_pin_hash', '');
    if (!$hash || !password_verify($pin, $hash)) {
        $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
        if ($_SESSION['login_fail'] >= 5) {
            $_SESSION['login_lock'] = $now + 60;
            $_SESSION['login_fail'] = 0;
        }
        throw new ApiException('INVALID_CREDENTIALS', 'PIN salah', 401);
    }
    session_regenerate_id(true);
    $_SESSION['login_fail'] = 0;
    $_SESSION['admin'] = ['nama' => 'Admin'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    api_success(['admin' => $_SESSION['admin'], 'csrf_token' => $_SESSION['csrf_token']], ['message' => 'Login berhasil']);
});
