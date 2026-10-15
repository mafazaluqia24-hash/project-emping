<?php
/**
 * Bootstrap file - loads constants & config for index.php
 * Dipakai opsional; public/index.php tidak wajib memakainya.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';

$constants = constants();
$statusConstants = [];
$paymentConstants = [];

foreach ($constants['status'] as $key => $val) {
    $statusConstants[$key] = [
        'label' => $val['label'],
        'color' => $val['color'],
        'description' => $val['description'] ?? '',
    ];
}

foreach ($constants['payment_status'] as $key => $val) {
    $paymentConstants[$key] = [
        'label' => $val['label'],
        'color' => $val['color'],
    ];
}

try {
    if (!defined('APP_NAME')) {
        define('APP_NAME', get_setting('site_name', 'Emping Bu Sari'));
    }
} catch (Throwable) {
    // DB belum siap (schema.sql belum diimpor) -> biarkan pemanggil yang menangani.
    if (!defined('APP_NAME')) {
        define('APP_NAME', 'Emping Bu Sari');
    }
}