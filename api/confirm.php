<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$token = (string) (api_input()['token'] ?? ($_GET['token'] ?? ''));

$isJson = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

if ($isJson) {
    api_check_origin();
    api_headers();
}

if ($token === '') {
    if ($isJson) {
        api_fail('Ссылка подтверждения неполная', [], 422);
    }
    header('Location: ' . api_site_url() . '/lk/confirm/?error=1');
    exit;
}

$user = consume_token($token, 'confirm');

if ($user === null) {
    if ($isJson) {
        api_fail('Ссылка недействительна или устарела', [], 410);
    }
    header('Location: ' . api_site_url() . '/lk/confirm/?error=1');
    exit;
}

$now = now_iso();
db_write('UPDATE users SET email_verified_at = ?, updated_at = ? WHERE id = ?', [$now, $now, (int) $user['id']]);

// Заодно открываем вход, если человек уже стоял в сессии.
session_login([
    'id' => (int) $user['id'],
    'password_hash' => $user['password_hash'],
]);

if ($isJson) {
    api_ok(['email' => $user['email']]);
}

header('Location: ' . api_site_url() . '/lk/confirm/');
exit;
