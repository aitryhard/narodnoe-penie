<?php
declare(strict_types=1);

/**
 * Отдача медиафайлов курсов (video_files, voices).
 * Прямые URL закрыты на уровне .htaccess, поэтому весь трафик идёт через
 * этот скрипт: сессия + проверка покупки, затем потоковая отдача с Range.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/access.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit;
}

$path = (string) ($_GET['path'] ?? '');

// Формат: {slug}/{video_files|voices}/{имя файла}
if (!preg_match('#^([a-z0-9-]{2,64})/(video_files|voices)/([A-Za-z0-9._-]{1,120})$#', $path, $m)) {
    http_response_code(400);
    exit;
}

$slug = $m[1];
$subdir = $m[2];
$fileName = $m[3];

$known = array_column(courses_list(), 'slug');
if (!in_array($slug, $known, true)) {
    http_response_code(404);
    exit;
}

$user = require_user(); // без входа - 401

$isAdmin = (($user['role'] ?? 'user') === 'admin');
if (!($isAdmin || access_has((int) $user['id'], $slug))) {
    http_response_code(403);
    exit;
}

// Сессия больше не нужна - снимаем блокировку для параллельных запросов плеера.
session_write_close();

// Файлы лежат за пределами веб-директории (narodnoe-penie.ru/media),
// поэтому прямой доступ к ним невозможен - только через этот скрипт.
$file = __DIR__ . '/../../media/courses/' . $slug . '/' . $subdir . '/' . $fileName;
if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit;
}

$size = (int) filesize($file);

$mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
    'mp4', 'm4v' => 'video/mp4',
    'mov', 'qt' => 'video/quicktime',
    'webm' => 'video/webm',
    'm4a' => 'audio/mp4',
    'mp3' => 'audio/mpeg',
    'ogg', 'oga' => 'audio/ogg',
    'wav' => 'audio/wav',
    default => 'application/octet-stream',
};

$start = 0;
$end = $size - 1;
$status = 200;

$range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $rm) && ($rm[1] !== '' || $rm[2] !== '')) {
    if ($rm[1] === '') {
        $suffix = (int) $rm[2];
        $start = max(0, $size - $suffix);
    } else {
        $start = (int) $rm[1];
        if ($rm[2] !== '') {
            $end = min((int) $rm[2], $size - 1);
        }
    }
    if ($start > $end || $start >= $size) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $status = 206;
}

http_response_code($status);
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');
header('Accept-Ranges: bytes');
header('Content-Length: ' . ($end - $start + 1));
header('Cache-Control: private, max-age=3600');
if ($status === 206) {
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    exit;
}

set_time_limit(0);

$fp = fopen($file, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}
fseek($fp, $start);

$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
    $chunk = fread($fp, min(65536, $remaining));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
}

fclose($fp);
exit;
