<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_method('POST');
api_check_origin();
api_headers();

$user = require_user();

$raw = api_input();
$current = is_scalar($raw['current_password'] ?? null) ? (string) $raw['current_password'] : '';
$password = is_scalar($raw['password'] ?? null) ? (string) $raw['password'] : '';
$confirm = is_scalar($raw['password_confirm'] ?? null) ? (string) $raw['password_confirm'] : '';

$fields = [];

if ($current === '') {
    $fields['current_password'] = 'Введите текущий пароль';
} elseif (!password_verify($current, $user['password_hash'])) {
    $fields['current_password'] = 'Неверный текущий пароль';
}

$passwordError = password_problem($password);
if ($passwordError !== null) {
    $fields['password'] = $passwordError;
}

if ($confirm !== '' && $confirm !== $password) {
    $fields['password_confirm'] = 'Пароли не совпадают';
}

if ($password !== '' && hash_equals($password, $current)) {
    $fields['password'] = 'Новый пароль должен отличаться от текущего';
}

if ($fields !== []) {
    api_fail('Проверьте заполнение полей', ['fields' => $fields]);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$now = now_iso();

db_write(
    'UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
    [$hash, $now, (int) $user['id']]
);

// Меняем «эпоху» пароля: остальные сессии перестают проходить проверку.
session_refresh_password($hash);

api_ok(['user' => public_user(find_user_by_id((int) $user['id']))]);
