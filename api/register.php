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

$code = create_confirm_code(
    (int) $user['id'],
    (int) api_config()['confirm_code_ttl_minutes']
);

if (!send_confirm_email($user, $code)) {
    // Письмо не ушло — убираем аккаунт, иначе человек останется
    // с неподтверждённой записью и без возможности повторить попытку.
    db_write('DELETE FROM users WHERE id = ?', [(int) $user['id']]);
    api_fail('Не удалось отправить письмо с подтверждением. Попробуйте чуть позже.', [], 502);
}

$data = ['email' => $email];

if (api_dev_mode()) {
    $data['devCode'] = $code;
}

api_ok($data);
