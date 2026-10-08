<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_method('POST');
api_check_origin();
api_headers();

$email = normalize_email(api_str('email', 254));
$code = preg_replace('/\D+/', '', (string) (api_input()['code'] ?? '')) ?? '';

$fields = [];

if (!is_valid_email($email)) {
    $fields['email'] = 'Введите корректный почтовый адрес';
}
if (!preg_match('/^\d{6}$/', $code)) {
    $fields['code'] = 'Код состоит из шести цифр';
}

if ($fields !== []) {
    api_fail('Проверьте заполнение полей', ['fields' => $fields]);
}

$maxAttempts = 5;

$user = find_user_by_email($email);

// Ответ одинаков для несуществующего и неверного кода — по нему нельзя
// перебрать, кто зарегистрирован.
if ($user === null) {
    api_fail('Код не подошёл. Проверьте цифры или запросите новый код.', [
        'fields' => ['code' => 'Неверный код'],
    ]);
}

// Повторное подтверждение (например, на другой вкладке) — просто засчитываем.
if ($user['email_verified_at'] !== null) {
    session_login([
        'id' => (int) $user['id'],
        'password_hash' => $user['password_hash'],
    ]);
    api_ok(['email' => $user['email']]);
}

$token = find_confirm_code_token((int) $user['id']);

if ($token === null || $token['expires_at'] < now_iso()) {
    if ($token !== null) {
        db_write('DELETE FROM auth_tokens WHERE id = ?', [(int) $token['id']]);
    }
    api_fail('Код устарел. Запросите новый код.', [
        'fields' => ['code' => 'Код устарел — запросите новый'],
    ]);
}

if ((int) $token['attempts'] >= $maxAttempts) {
    db_write('DELETE FROM auth_tokens WHERE id = ?', [(int) $token['id']]);
    api_fail('Слишком много попыток. Запросите новый код.', [
        'fields' => ['code' => 'Попытки закончились — запросите новый код'],
    ]);
}

if (!hash_equals((string) $token['token_hash'], confirm_code_hash((int) $user['id'], $code))) {
    $attempts = (int) $token['attempts'] + 1;
    db_write('UPDATE auth_tokens SET attempts = ? WHERE id = ?', [$attempts, (int) $token['id']]);

    $left = $maxAttempts - $attempts;
    if ($left <= 0) {
        db_write('DELETE FROM auth_tokens WHERE id = ?', [(int) $token['id']]);
        api_fail('Попытки закончились. Запросите новый код.', [
            'fields' => ['code' => 'Попытки закончились — запросите новый код'],
        ]);
    }

    api_fail("Неверный код. Осталось попыток: {$left}.", [
        'fields' => ['code' => "Неверный код — осталось попыток: {$left}"],
    ]);
}

db_write('DELETE FROM auth_tokens WHERE id = ?', [(int) $token['id']]);

$now = now_iso();
db_write('UPDATE users SET email_verified_at = ?, updated_at = ? WHERE id = ?', [$now, $now, (int) $user['id']]);

session_login([
    'id' => (int) $user['id'],
    'password_hash' => $user['password_hash'],
]);

api_ok(['email' => $user['email']]);
