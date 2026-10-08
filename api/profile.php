<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_method('POST');
api_check_origin();
api_headers();

$user = require_user();

$raw = api_input();
$name = clean_text(is_scalar($raw['name'] ?? null) ? (string) $raw['name'] : '', 120);
$email = normalize_email(is_scalar($raw['email'] ?? null) ? (string) $raw['email'] : '');
$city = clean_text(is_scalar($raw['city'] ?? null) ? (string) $raw['city'] : '', 120);

$fields = [];

if ($name === '') {
    $fields['name'] = 'Укажите имя';
}

if (!is_valid_email($email)) {
    $fields['email'] = 'Введите корректный почтовый адрес';
}

if ($fields !== []) {
    api_fail('Проверьте заполнение полей', ['fields' => $fields]);
}

$emailChanged = $email !== $user['email'];

if ($emailChanged) {
    $taken = db_one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, (int) $user['id']]);
    if ($taken !== null) {
        api_fail('Пользователь с таким адресом уже зарегистрирован', [
            'fields' => ['email' => 'Этот адрес уже используется'],
        ]);
    }
}

$now = now_iso();
$verifiedAt = $emailChanged ? null : $user['email_verified_at'];

db_write(
    'UPDATE users SET name = ?, email = ?, city = ?, email_verified_at = ?, updated_at = ? WHERE id = ?',
    [$name, $email, $city, $verifiedAt, $now, (int) $user['id']]
);

$data = [];

if ($emailChanged) {
    // Новый адрес нужно подтвердить заново — иначе письма уйдут не туда.
    $fresh = find_user_by_id((int) $user['id']);
    $code = create_confirm_code(
        (int) $fresh['id'],
        (int) api_config()['confirm_code_ttl_minutes']
    );

    if (!send_confirm_email($fresh, $code)) {
        // Письмо не ушло — возвращаем прежнюю почту, иначе вход закроется
        // навсегда: подтвердить новый адрес будет нечем.
        db_write(
            'UPDATE users SET email = ?, email_verified_at = ?, updated_at = ? WHERE id = ?',
            [$user['email'], $user['email_verified_at'], $now, (int) $user['id']]
        );
        api_fail('Не удалось отправить письмо на новый адрес. Попробуйте позже.', ['fields' => ['email' => '']], 502);
    }

    $data['emailChanged'] = true;
    if (api_dev_mode()) {
        $data['devCode'] = $code;
    }
}

$updated = find_user_by_id((int) $user['id']);
$data['user'] = public_user($updated);

api_ok($data);
