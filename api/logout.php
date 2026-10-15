<?php
/** POST logout.php */
declare(strict_types=1);
require_once __DIR__ . '/../src/functions.php';

api_guard(function () {
    require_method('POST');
    start_session();
    $_SESSION = [];
    session_destroy();
    api_success([], ['message' => 'Logout berhasil']);
});
