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
    $dialect = $isSqlite ? 'sqlite' : 'mysql';

    if ($isSqlite) {
        $path = substr($dsn, strlen('sqlite:'));
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if ($isSqlite) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        db_ensure_schema($pdo, $dialect);
    } catch (PDOException $exception) {
        error_log('[api] База данных: ' . $exception->getMessage());

        if (PHP_SAPI === 'cli') {
            throw $exception;
        }

        api_fail(
            'Не удалось подключиться к базе данных. Проверьте данные в api/config.local.php.',
            [],
            500
        );
    }

    return $pdo;
}

/**
 * Создаёт таблицы, если их ещё нет. Повторные обращения ничего не делают —
 * лишний запрос к information_schema выполняется один раз за запрос к API.
 */
function db_ensure_schema(PDO $pdo, string $dialect): void
{
    $exists = $dialect === 'sqlite'
        ? $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetch()
        : $pdo->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users' LIMIT 1"
        )->fetch();

    if ($exists === false) {
        $statements = require __DIR__ . '/schema.php';
        $statements = $statements[$dialect] ?? [];

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    db_ensure_columns($pdo, $dialect);
}

/**
 * Добавляет колонки, появившиеся после создания таблиц на живых базах.
 * Ошибок «колонка уже есть» не бывает — перед exec стоит проверка.
 */
function db_ensure_columns(PDO $pdo, string $dialect): void
{
    $migrations = [
        'auth_tokens' => [
            'attempts' => $dialect === 'sqlite'
                ? 'ALTER TABLE auth_tokens ADD COLUMN attempts INTEGER NOT NULL DEFAULT 0'
                : 'ALTER TABLE auth_tokens ADD COLUMN attempts INT NOT NULL DEFAULT 0',
        ],
    ];

    foreach ($migrations as $table => $columns) {
        foreach ($columns as $column => $alter) {
            if (!db_column_exists($pdo, $table, $column)) {
                $pdo->exec($alter);
            }
        }
    }
}

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $stmt = $pdo->query('PRAGMA table_info(`' . $table . '`)');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if (($col['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return $stmt->fetch() !== false;
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

function iso_plus_minutes(int $minutes): string
{
    return gmdate('Y-m-d\TH:i:s\Z', time() + $minutes * 60);
}
