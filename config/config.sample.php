<?php
/**
 * Salin menjadi config.php lalu sesuaikan. config.php TIDAK di-commit.
 * Env var (EMPING_DB_HOST, dst.) menimpa nilai di sini -> dipakai CI.
 */
return [
    'db' => [
        'host'    => getenv('EMPING_DB_HOST') ?: '127.0.0.1',
        'port'    => (int)(getenv('EMPING_DB_PORT') ?: 3306),
        'dbname'  => getenv('EMPING_DB_NAME') ?: 'emping_umkm',
        'user'    => getenv('EMPING_DB_USER') ?: 'root',
        'pass'    => getenv('EMPING_DB_PASS') !== false ? getenv('EMPING_DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone' => 'Asia/Jakarta',
        'debug'    => true,   // false di produksi (sembunyikan pesan error internal)
    ],
    'session' => [
        'name'     => 'EMPING_SESS',
        'lifetime' => 3600,
    ],
];
