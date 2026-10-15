<?php
declare(strict_types=1);

/**
 * GET /api/course.php?slug=... - доступ и материалы курса для ЛК.
 * Доступ: роль admin ИЛИ покупка (purchases).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/access.php';

api_method('GET');
api_check_origin();
api_headers();

$slug = trim((string) ($_GET['slug'] ?? ''));

if (!preg_match('/^[a-z0-9-]{2,64}$/', $slug)) {
    api_fail('Некорректный курс', [], 404);
}

$known = array_column(courses_list(), 'slug');
if (!in_array($slug, $known, true)) {
    api_fail('Курс не найден', [], 404);
}

$user = require_user();

$isAdmin = (($user['role'] ?? 'user') === 'admin');
$purchased = access_has((int) $user['id'], $slug);
$access = $isAdmin || $purchased;

$content = null;
if ($access) {
    $file = __DIR__ . '/content/courses/' . $slug . '.json';
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded) && is_array($decoded['blocks'] ?? null)) {
            $content = ['blocks' => $decoded['blocks']];
        }
    }
}

api_ok([
    'slug' => $slug,
    'title' => course_title($slug),
    'access' => $access,
    'purchased' => $purchased,
    'admin' => $isAdmin,
    'content' => $content,
]);
