<?php
declare(strict_types=1);

/**
 * Применяет схему базы данных.
 *
 *   php database/install.php
 *
 * Схема выбирается по настройке db_dsn в api/config.php:
 * sqlite -> schema.sqlite.sql,  mysql -> schema.mysql.sql.
 * Повторный запуск безопасен (IF NOT EXISTS).
 */

require __DIR__ . '/../api/bootstrap.php';

$dsn = (string) api_config()['db_dsn'];
$file = str_starts_with($dsn, 'sqlite:') ? 'schema.sqlite.sql' : 'schema.mysql.sql';
$path = __DIR__ . '/' . $file;

$sql = file_get_contents($path);
if ($sql === false) {
    fwrite(STDERR, "Не найден файл схемы: {$path}\n");
    exit(1);
}

// Убираем комментарии — иначе они попадут внутрь запросов.
$sql = preg_replace('/^\s*--.*$/m', '', $sql);

$statements = array_filter(array_map('trim', explode(';', $sql)));

$pdo = db();
$applied = 0;

foreach ($statements as $statement) {
    if ($statement === '') {
        continue;
    }
    $pdo->exec($statement);
    $applied++;
}

echo "Схема применена ({$file}), запросов: {$applied}\n";
echo "База: {$dsn}\n";
