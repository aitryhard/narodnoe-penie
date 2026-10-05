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

if ($user !== null && $user['email_verified_at'] === null) {
    $token = create_token(
        (int) $user['id'],
        'confirm',
        (int) api_config()['confirm_ttl_hours']
    );
    send_confirm_email($user, $token);

    if (api_dev_mode()) {
        $data['devConfirmationUrl'] = confirm_url($token);
    }
}

// Ответ одинаков для любого адреса — по нему нельзя перебрать участников.
api_ok($data);
