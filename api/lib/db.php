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
 * Создаёт таблицы, если их ещё нет. Повторные обращения ничего не делают -
 * лишний запрос к information_schema выполняется один раз за запрос к API.
 */
function db_ensure_schema(PDO $pdo, string $dialect): void
{
    $required = ['users', 'auth_tokens', 'purchases', 'tg_state', 'settings'];
    $allExist = true;
    foreach ($required as $table) {
        if (!db_table_exists($pdo, $table)) {
            $allExist = false;
            break;
        }
    }

    if (!$allExist) {
        $statements = require __DIR__ . '/schema.php';
        $statements = $statements[$dialect] ?? [];

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    db_ensure_columns($pdo, $dialect);
}

function db_table_exists(PDO $pdo, string $table): bool
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
        $stmt->execute([$table]);
        return $stmt->fetch() !== false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return $stmt->fetch() !== false;
}

/**
 * Добавляет колонки, появившиеся после создания таблиц на живых базах.
 * Ошибок «колонка уже есть» не бывает - перед exec стоит проверка.
 */
function db_ensure_columns(PDO $pdo, string $dialect): void
{
    $migrations = [
        'auth_tokens' => [
            'attempts' => $dialect === 'sqlite'
                ? 'ALTER TABLE auth_tokens ADD COLUMN attempts INTEGER NOT NULL DEFAULT 0'
                : 'ALTER TABLE auth_tokens ADD COLUMN attempts INT NOT NULL DEFAULT 0',
        ],
        'users' => [
            'role' => $dialect === 'sqlite'
                ? 'ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT "user"'
                : 'ALTER TABLE users ADD COLUMN role VARCHAR(16) NOT NULL DEFAULT \'user\'',
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

/** Момент времени в UTC, ISO-8601 - одинаково сравнивается в SQLite и MySQL. */
function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

/* ------------------------------------------------------------------ */
/* Настройки (ключ-значение)                                          */
/* ------------------------------------------------------------------ */

function settings_get(string $key, mixed $default = null): mixed
{
    $row = db_one('SELECT value FROM settings WHERE `key` = ?', [$key]);
    if ($row === null) {
        return $default;
    }
    $decoded = json_decode((string) $row['value'], true);
    return $decoded === null && $row['value'] !== 'null' ? $row['value'] : $decoded;
}

function settings_set(string $key, mixed $value): void
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE);
    $existing = db_one('SELECT `key` FROM settings WHERE `key` = ?', [$key]);
    if ($existing === null) {
        db_write('INSERT INTO settings (`key`, `value`) VALUES (?, ?)', [$key, $json]);
    } else {
        db_write('UPDATE settings SET value = ? WHERE `key` = ?', [$json, $key]);
    }
}

function iso_plus_hours(int $hours): string
{
    return gmdate('Y-m-d\TH:i:s\Z', time() + $hours * 3600);
}

function iso_plus_minutes(int $minutes): string
{
    return gmdate('Y-m-d\TH:i:s\Z', time() + $minutes * 60);
}
