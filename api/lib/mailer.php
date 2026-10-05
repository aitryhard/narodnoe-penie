<?php
declare(strict_types=1);

/**
 * Отправка писем.
 *
 * В режиме dev письмо не уходит, а пишется в var/mail.log — так можно
 * проверить ссылку подтверждения без настроенного SMTP.
 * На хостинге (dev = false) используется стандартная mail() с почтой Beget.
 */

function send_mail(string $to, string $subject, string $body): bool
{
    if (api_dev_mode()) {
        $log = dirname(__DIR__, 2) . '/var/mail.log';
        $dir = dirname($log);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $entry = sprintf(
            "=== %s ===\nКому: %s\nТема: %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body
        );
        file_put_contents($log, $entry, FILE_APPEND);
        return true;
    }

    $config = api_config();
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        sprintf('From: %s <%s>', $config['mail_from_name'], $config['mail_from']),
    ]);

    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function confirm_url(string $rawToken): string
{
    return api_site_url() . '/api/confirm.php?token=' . urlencode($rawToken);
}

function send_confirm_email(array $user, string $rawToken): bool
{
    $name = $user['name'] !== '' ? $user['name'] : 'участник';
    $link = confirm_url($rawToken);

    $body = "Здравствуйте, {$name}!\n\n"
        . "Вы зарегистрировались в Женской школе народного пения.\n"
        . "Подтвердите адрес почты, перейдя по ссылке:\n\n"
        . "{$link}\n\n"
        . "Ссылка действует 24 часа.\n"
        . "Если это были не вы — просто проигнорируйте письмо.\n";

    return send_mail($user['email'], 'Подтвердите почту — Школа народного пения', $body);
}

function send_reset_email(array $user, string $rawToken): bool
{
    $name = $user['name'] !== '' ? $user['name'] : 'участник';
    $link = api_site_url() . '/lk/update-password/?token=' . urlencode($rawToken);

    $body = "Здравствуйте, {$name}!\n\n"
        . "Кто-то запросил сброс пароля для {$user['email']}.\n"
        . "Задайте новый пароль по ссылке:\n\n"
        . "{$link}\n\n"
        . "Ссылка действует 2 часа.\n"
        . "Если это были не вы — ничего делать не нужно.\n";

    return send_mail($user['email'], 'Сброс пароля — Школа народного пения', $body);
}
