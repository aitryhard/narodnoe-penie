<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_method('POST');
api_check_origin();
api_headers();

$raw = api_input();
$name = clean_text(api_str('name', 200), 120);
$email = normalize_email(api_str('email', 254));
$password = is_scalar($raw['password'] ?? null) ? (string) $raw['password'] : '';

$fields = [];

if ($name === '') {
    $fields['name'] = 'Укажите имя';
}

if (!is_valid_email($email)) {
    $fields['email'] = 'Введите корректный почтовый адрес';
}

$passwordError = password_problem($password);
if ($passwordError !== null) {
    $fields['password'] = $passwordError;
}

if ($fields !== []) {
    api_fail('Проверьте заполнение полей', ['fields' => $fields]);
}

if (find_user_by_email($email) !== null) {
    api_fail('Пользователь с таким адресом уже зарегистрирован', [
        'fields' => ['email' => 'Этот адрес уже используется'],
    ]);
}

$now = now_iso();

db_write(
    'INSERT INTO users (email, password_hash, name, city, email_verified_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)',
    [$email, password_hash($password, PASSWORD_DEFAULT), $name, '', null, $now, $now]
);

$user = find_user_by_email($email);
if ($user === null) {
    api_fail('Не удалось создать аккаунт. Попробуйте ещё раз.', [], 500);
}

$token = create_token(
    (int) $user['id'],
    'confirm',
    (int) api_config()['confirm_ttl_hours']
);

send_confirm_email($user, $token);

$data = ['email' => $email];

if (api_dev_mode()) {
    $data['devConfirmationUrl'] = confirm_url($token);
}

api_ok($data);
