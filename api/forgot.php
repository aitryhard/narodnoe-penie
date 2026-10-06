<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_method('POST');
api_check_origin();
api_headers();

$email = normalize_email(api_str('email', 254));

if (!is_valid_email($email)) {
    api_fail('Проверьте заполнение полей', ['fields' => ['email' => 'Введите корректный почтовый адрес']]);
}

$user = find_user_by_email($email);

$data = ['email' => $email];

if ($user !== null) {
    $token = create_token(
        (int) $user['id'],
        'reset',
        (int) api_config()['reset_ttl_hours']
    );

    if (!send_reset_email($user, $token)) {
        api_fail('Не удалось отправить письмо. Попробуйте чуть позже.', [], 502);
    }

    if (api_dev_mode()) {
        $data['devResetUrl'] = api_site_url() . '/lk/update-password/?token=' . urlencode($token);
    }
}

// Ответ одинаков при существующем и несуществующем адресе,
// чтобы по ответу нельзя было перебирать, кто зарегистрирован.
api_ok($data);
