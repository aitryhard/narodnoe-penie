<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_method('POST');
api_check_origin();
api_headers();

$raw = api_input();
$token = is_scalar($raw['token'] ?? null) ? (string) $raw['token'] : '';
$password = is_scalar($raw['password'] ?? null) ? (string) $raw['password'] : '';
$confirm = is_scalar($raw['password_confirm'] ?? null) ? (string) $raw['password_confirm'] : '';

if ($token === '') {
    api_fail('Ссылка для сброса пароля неполная', [], 422);
}

$fields = [];

$passwordError = password_problem($password);
if ($passwordError !== null) {
    $fields['password'] = $passwordError;
}

if ($confirm !== '' && $confirm !== $password) {
    $fields['password_confirm'] = 'Пароли не совпадают';
}

if ($fields !== []) {
    api_fail('Проверьте заполнение полей', ['fields' => $fields]);
}

$user = consume_token($token, 'reset');

if ($user === null) {
    api_fail('Ссылка недействительна или устарела', [], 410);
}

$now = now_iso();
db_write(
    'UPDATE users SET password_hash = ?, email_verified_at = ?, updated_at = ? WHERE id = ?',
    [
        password_hash($password, PASSWORD_DEFAULT),
        // Письмо со ссылкой пришло на этот адрес — значит, почта подтверждена.
        $now,
        $now,
        (int) $user['id'],
    ]
);

api_ok(['email' => $user['email']]);
