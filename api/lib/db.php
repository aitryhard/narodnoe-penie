<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = api_config();
    $dsn = (string) $config['db_dsn'];
    $isSqlite = str_starts_with($dsn, 'sqlite:');

    if ($isSqlite) {
        $path = substr($dsn, strlen('sqlite:'));
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if ($isSqlite) {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    return $pdo;
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_write(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Момент времени в UTC, ISO-8601 — одинаково сравнивается в SQLite и MySQL. */
function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function iso_plus_hours(int $hours): string
{
    return gmdate('Y-m-d\TH:i:s\Z', time() + $hours * 3600);
}
