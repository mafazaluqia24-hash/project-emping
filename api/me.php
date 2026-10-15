<?php
/** GET me.php - status login admin (dipakai UI saat halaman dimuat ulang) */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';

api_guard(function () {
    require_method('GET');
    start_session();
    if (empty($_SESSION['admin'])) {
        api_success(['logged_in' => false]);
    }
    api_success(['logged_in' => true, 'admin' => $_SESSION['admin'], 'csrf_token' => csrf_token()]);
});
