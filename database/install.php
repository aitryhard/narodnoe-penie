<?php
declare(strict_types=1);

/**
 * Проверяет подключение к базе и создаёт таблицы, если их ещё нет.
 *
 *   php database/install.php
 *
 * В боевом окружении делать это вручную не нужно: API создаёт таблицы
 * само при первом обращении (см. api/lib/schema.php).
 */

require __DIR__ . '/../api/bootstrap.php';

$dsn = (string) api_config()['db_dsn'];
$dialect = str_starts_with($dsn, 'sqlite:') ? 'sqlite' : 'mysql';

db();

$pdo = db();
$tables = $dialect === 'sqlite'
    ? $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('users', 'auth_tokens')")->fetchAll()
    : $pdo->query(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users', 'auth_tokens')"
    )->fetchAll();

echo 'Подключение: ' . ($dialect === 'sqlite' ? 'SQLite' : 'MySQL') . "\n";
echo 'База: ' . $dsn . "\n";
echo 'Таблицы: ' . count($tables) . " из 2 (users, auth_tokens)\n";
echo count($tables) === 2 ? "Готово.\n" : "Таблицы не созданы — смотрите лог ошибок PHP.\n";
exit(count($tables) === 2 ? 0 : 1);
