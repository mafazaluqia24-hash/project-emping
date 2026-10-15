<?php
/** GET/POST admin_settings.php */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/validators.php';

api_guard(function () {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        require_admin();
        $out = [];
        foreach ($pdo->query("SELECT setting_key k, setting_value v FROM settings WHERE setting_key <> 'admin_pin_hash'") as $r) {
            $out[$r['k']] = in_array($r['k'], ['cancel_window_hours', 'dp_threshold', 'dp_percent', 'escalation_hours'], true) ? (int)$r['v'] : $r['v'];
        }
        api_success(['settings' => $out]);
    }
    require_method('POST');
    require_admin(true);
    $in = json_input();
    $errors = validate_settings($in);
    if ($errors) {
        throw new ApiException('VALIDATION_ERROR', implode('; ', $errors));
    }
    $allowed = ['cancel_window_hours', 'dp_threshold', 'dp_percent', 'phone_seller', 'bank_info', 'site_name'];
    $updates = [];
    foreach ($allowed as $k) {
        if (isset($in[$k]) && $in[$k] !== '') {
            $updates[$k] = $k === 'phone_seller' ? normalize_phone((string)$in[$k]) : (string)$in[$k];
        }
    }
    if (!empty($in['new_pin'])) {
        $updates['admin_pin_hash'] = password_hash((string)$in['new_pin'], PASSWORD_BCRYPT);
    }
    if (!$updates) {
        throw new ApiException('VALIDATION_ERROR', 'Tidak ada perubahan');
    }
    $pdo->beginTransaction();
    $st = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
    foreach ($updates as $k => $v) {
        $st->execute([$v, $k]);
    }
    $pdo->commit();
    api_success(['updated' => array_keys($updates)], ['message' => 'Pengaturan disimpan']);
});
