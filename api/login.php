<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_method('POST');
api_check_origin();
api_headers();

$raw = api_input();
$email = normalize_email(is_scalar($raw['email'] ?? null) ? (string) $raw['email'] : '');
$password = is_scalar($raw['password'] ?? null) ? (string) $raw['password'] : '';

$fields = [];

if (!is_valid_email($email)) {
    $fields['email'] = 'Введите корректный почтовый адрес';
}

if ($password === '') {
    $fields['password'] = 'Введите пароль';
}

if ($fields !== []) {
    api_fail('Проверьте заполнение полей', ['fields' => $fields]);
}

$user = find_user_by_email($email);

if ($user === null || !password_verify($password, $user['password_hash'])) {
    api_fail('Неверная почта или пароль', [
        'fields' => ['password' => 'Неверная почта или пароль'],
    ]);
}

if ($user['email_verified_at'] === null) {
    api_fail('Подтвердите адрес почты, письмо уже отправлено', [
        'needsConfirm' => true,
        'email' => $user['email'],
    ], 409);
}

session_login($user);

api_ok(['user' => public_user($user)]);
