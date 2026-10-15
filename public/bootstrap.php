<?php
/**
 * Bootstrap file - loads constants & config for index.php
 */
require_once __DIR__ . '/../src/constants.php';
require_once __DIR__ . '/../src/functions.php';

$constants = require __DIR__ . '/../src/constants.php';
$statusConstants = [];
$paymentConstants = [];

foreach ($constants['status_flow'] as $key => $val) {
    $statusConstants[$key] = [
        'label' => $val['label'],
        'color' => $val['color'],
        'description' => $val['description'],
    ];
}

foreach ($constants['payment_status'] as $key => $val) {
    $paymentConstants[$key] = [
        'label' => $val['label'],
        'color' => $val['color'],
    ];
}

define('APP_NAME', get_setting('site_name', 'Emping Bu Sari'));