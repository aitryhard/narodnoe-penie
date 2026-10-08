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
    // Не чаще раза в минуту — иначе можно затопить чужой ящик письмами.
    $existing = find_confirm_code_token((int) $user['id']);
    $minuteAgo = gmdate('Y-m-d\TH:i:s\Z', time() - 60);

    if ($existing !== null && $existing['created_at'] >= $minuteAgo) {
        api_fail('Письмо уже отправлено только что — подождите минуту и проверьте почту.', [], 429);
    }

    $code = create_confirm_code(
        (int) $user['id'],
        (int) api_config()['confirm_code_ttl_minutes']
    );

    if (!send_confirm_email($user, $code)) {
        api_fail('Не удалось отправить письмо. Попробуйте чуть позже.', [], 502);
    }

    if (api_dev_mode()) {
        $data['devCode'] = $code;
    }
}

// Ответ одинаков для любого адреса — по нему нельзя перебрать участников.
api_ok($data);
