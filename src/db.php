<?php
/** Koneksi PDO singleton. Zona waktu sesi MySQL disamakan dengan PHP. */
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $file = __DIR__ . '/../config/config.php';
            if (!is_file($file)) {
                $file = __DIR__ . '/../config/config.sample.php';
            }
            $cfg = require $file;
            date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Jakarta');
        }
        return $cfg;
    }

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $c = self::config()['db'];
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['dbname']};charset={$c['charset']}";
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $offset = (new DateTime('now'))->format('P'); // mis. +07:00
            self::$pdo->exec("SET time_zone = '{$offset}'");
        }
        return self::$pdo;
    }
}
