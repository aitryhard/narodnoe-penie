<?php
declare(strict_types=1);

/**
 * Отправка писем.
 *
 * В режиме dev письмо не уходит, а пишется в var/mail.log — так можно
 * проверить код подтверждения без настроенного SMTP.
 * На хостинге (dev = false) используется стандартная mail() с почтой Beget.
 */

function send_mail(string $to, string $subject, string $body, ?string $html = null): bool
{
    if (api_dev_mode()) {
        $log = dirname(__DIR__, 2) . '/var/mail.log';
        $dir = dirname($log);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $entry = sprintf(
            "=== %s ===\nКому: %s\nТема: %s\n\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body,
            $html !== null ? "\n--- HTML ---\n" . $html . "\n" : ''
        );
        file_put_contents($log, $entry, FILE_APPEND);
        return true;
    }

    $config = api_config();
    $from = sprintf('From: %s <%s>', $config['mail_from_name'], api_mail_from());
    $subjectHeader = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if ($html === null) {
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            $from,
        ]);
        // @ подавляет Warning от mail(): он попал бы в вывод, сломал бы заголовки
        // и превратил бы JSON-ответ в HTML со статусом 200.
        $sent = @mail($to, $subjectHeader, $body, $headers);
    } else {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            $from,
        ]);
        $message = implode("\r\n", [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            str_replace("\n", "\r\n", $body),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $html,
            '--' . $boundary . '--',
            '',
        ]);
        $sent = @mail($to, $subjectHeader, $message, $headers);
    }

    if (!$sent) {
        error_log(sprintf('[api] Не удалось отправить письмо "%s" на %s', $subject, $to));
    }

    return $sent;
}

function send_confirm_email(array $user, string $code): bool
{
    $name = $user['name'] !== '' ? $user['name'] : 'участник';
    $minutes = (int) api_config()['confirm_code_ttl_minutes'];
    $confirmUrl = api_site_url() . '/lk/confirm/?email=' . urlencode($user['email']);

    $plain = "Здравствуйте, {$name}!\n\n"
        . "Вы зарегистрировались в Женской школе народного пения.\n"
        . "Код подтверждения: {$code}\n\n"
        . "Введите его на странице:\n{$confirmUrl}\n\n"
        . "Код действует {$minutes} минут.\n"
        . "Если это были не вы — просто проигнорируйте письмо.\n";

    $html = confirm_email_html($name, $code, $confirmUrl, $minutes);

    return send_mail($user['email'], 'Код подтверждения — Школа народного пения', $plain, $html);
}

function send_reset_email(array $user, string $rawToken): bool
{
    $name = $user['name'] !== '' ? $user['name'] : 'участник';
    $link = api_site_url() . '/lk/update-password/?token=' . urlencode($rawToken);

    $body = "Здравствуйте, {$name}!\n\n"
        . "Кто-то запросил сброс пароля для {$user['email']}.\n"
        . "Задайте новый пароль по ссылке:\n\n{$link}\n\n"
        . "Ссылка действует 2 часа.\n"
        . "Если это были не вы — ничего делать не нужно.\n";

    return send_mail($user['email'], 'Сброс пароля — Школа народного пения', $body);
}

/**
 * HTML-письмо с кодом — в оформлении карточки регистрации:
 * бежевый фон, белая карточка с большим скруглением, горчичный код.
 * Только инлайн-стили и таблицы: почтовые клиенты не читают внешние CSS.
 */
function confirm_email_html(string $name, string $code, string $confirmUrl, int $minutes): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Код подтверждения</title>
</head>
<body style="margin:0; padding:0; background-color:#d0c593; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing:antialiased;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#d0c593; padding:32px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">

          <tr>
            <td style="padding:0 4px 10px;">
              <span style="display:inline-block; background-color:#e9e0c3; border:1px solid #dcd6ca; border-radius:999px; padding:6px 14px; font-size:12px; line-height:1.4; color:#4e4c44; letter-spacing:0.04em;">
                Женская школа народного пения
              </span>
            </td>
          </tr>

          <tr>
            <td style="background-color:#ffffff; border:1px solid #ebe9e4; border-radius:32px; padding:40px 34px;">
              <h1 style="margin:0; font-family:'Playfair Display', Georgia, serif; font-size:27px; line-height:1.25; font-weight:600; color:#262522;">
                Здравствуйте, {$safeName}!
              </h1>
              <p style="margin:14px 0 0; font-size:16px; line-height:1.65; color:#4e4c44;">
                Вы зарегистрировались в Женской школе народного пения. Введите код из письма на странице подтверждения — и всё готово.
              </p>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0;">
                <tr>
                  <td align="center" style="background-color:#e9e0c3; border:2px dashed #decd95; border-radius:18px; padding:22px 12px 18px;">
                    <div style="font-family:'Playfair Display', Georgia, serif; font-size:42px; line-height:1.1; font-weight:600; letter-spacing:12px; color:#5c5228; padding-left:12px;">
                      {$safeCode}
                    </div>
                    <div style="margin-top:8px; font-size:13px; line-height:1.5; color:#4a473b;">
                      Действует {$minutes} минут
                    </div>
                  </td>
                </tr>
              </table>

              <p style="margin:24px 0 0; font-size:16px; line-height:1.65; color:#4e4c44;">
                Страница подтверждения уже открыта у вас в браузере — либо откройте её по кнопке:
              </p>

              <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:14px 0 0;">
                <tr>
                  <td align="center" style="background-color:#decd95; border-radius:999px;">
                    <a href="{$safeUrl}" style="display:inline-block; padding:13px 26px; font-size:16px; font-weight:500; color:#262522; text-decoration:none;">
                      Открыть страницу подтверждения
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:26px 0 0; font-size:13px; line-height:1.6; color:#4a473b;">
                Если это были не вы — просто проигнорируйте это письмо, ничего делать не нужно.
              </p>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:18px 8px 4px; font-size:12px; line-height:1.5; color:#4a473b;">
              © Женская школа народного пения
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
