<?php
declare(strict_types=1);

/**
 * GET /api/courses.php - список курсов с признаком доступа (для ЛК).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/access.php';

api_method('GET');
api_check_origin();
api_headers();

$user = require_user();
$isAdmin = (($user['role'] ?? 'user') === 'admin');
$owned = access_list((int) $user['id']);

$list = [];
foreach (courses_list() as $course) {
    $purchased = in_array($course['slug'], $owned, true);
    $list[] = [
        'slug' => $course['slug'],
        'title' => $course['title'],
        'purchased' => $purchased,
        'access' => $isAdmin || $purchased,
    ];
}

api_ok([
    'admin' => $isAdmin,
    'courses' => $list,
]);
