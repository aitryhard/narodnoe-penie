<?php
declare(strict_types=1);

/**
 * Мелкий клиент Telegram Bot API (отправка сообщений, клавиатуры).
 * Токен и секрет вебхука лежат в api/tg.config.php (генерируется в CI).
 */

function tg_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = ['token' => '', 'secret' => '', 'admin_ids' => []];
    $file = __DIR__ . '/../tg.config.php';
    if (is_file($file)) {
        $loaded = require $file;
        if (is_array($loaded)) {
            $cfg = array_replace($cfg, $loaded);
        }
    }
    if (!is_array($cfg['admin_ids'])) {
        $cfg['admin_ids'] = [];
    }

    return $cfg;
}

function tg_api(string $method, array $params = []): array
{
    $token = tg_config()['token'];
    if ($token === '') {
        return ['ok' => false, 'description' => 'bot disabled'];
    }

    // Режим без сети: локальные тесты (флаг есть только в api/tg.config.php на разработке).
    if (!empty(tg_config()['dry'])) {
        return ['ok' => true, 'result' => ['message_id' => 1]];
    }

    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $body = json_encode($params, JSON_UNESCAPED_UNICODE);
    $raw = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        if (is_string($res)) {
            $raw = $res;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $res = @file_get_contents($url, false, $context);
        if (is_string($res)) {
            $raw = $res;
        }
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        error_log('[tg] ' . $method . ': пустой ответ');
        return ['ok' => false];
    }
    if (($decoded['ok'] ?? false) !== true) {
        error_log('[tg] ' . $method . ': ' . ($decoded['description'] ?? '?'));
    }

    return $decoded;
}

/** Ряды кнопок: [['текст' => ..., 'callback_data' => ...], ...] */
function tg_kb(?array $rows): ?array
{
    if ($rows === null || $rows === []) {
        return null;
    }
    return ['inline_keyboard' => $rows];
}

function tg_btn(string $text, string $data): array
{
    return ['text' => $text, 'callback_data' => $data];
}

function tg_send(int $chatId, string $text, ?array $kb = null): int
{
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ];
    $keyboard = tg_kb($kb);
    if ($keyboard !== null) {
        $params['reply_markup'] = $keyboard;
    }

    $result = tg_api('sendMessage', $params);
    return (int) ($result['result']['message_id'] ?? 0);
}

function tg_edit(int $chatId, int $messageId, string $text, ?array $kb = null): bool
{
    $params = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ];
    $keyboard = tg_kb($kb);
    if ($keyboard !== null) {
        $params['reply_markup'] = $keyboard;
    }

    $result = tg_api('editMessageText', $params);
    return (bool) ($result['ok'] ?? false);
}

function tg_answer(string $callbackId, string $text = '', bool $alert = false): void
{
    tg_api('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => $text,
        'show_alert' => $alert,
    ]);
}

function tg_set_webhook(string $url, string $secret): array
{
    return tg_api('setWebhook', [
        'url' => $url,
        'secret_token' => $secret,
        'allowed_updates' => ['message', 'callback_query'],
        'drop_pending_updates' => true,
    ]);
}

/* ------------------------------------------------------------------ */
/* Состояние диалога (FSM)                                            */
/* ------------------------------------------------------------------ */

function tg_state_get(int $chatId): array
{
    $row = db_one('SELECT step, payload FROM tg_state WHERE chat_id = ?', [$chatId]);
    if ($row === null) {
        return ['step' => '', 'payload' => []];
    }
    $payload = json_decode((string) $row['payload'], true);

    return [
        'step' => (string) $row['step'],
        'payload' => is_array($payload) ? $payload : [],
    ];
}

function tg_state_set(int $chatId, string $step, array $payload = []): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $existing = db_one('SELECT chat_id FROM tg_state WHERE chat_id = ?', [$chatId]);
    if ($existing === null) {
        db_write(
            'INSERT INTO tg_state (chat_id, step, payload, updated_at) VALUES (?, ?, ?, ?)',
            [$chatId, $step, $json, now_iso()]
        );
    } else {
        db_write(
            'UPDATE tg_state SET step = ?, payload = ?, updated_at = ? WHERE chat_id = ?',
            [$step, $json, now_iso(), $chatId]
        );
    }
}

function tg_state_clear(int $chatId): void
{
    db_write('DELETE FROM tg_state WHERE chat_id = ?', [$chatId]);
}

/* ------------------------------------------------------------------ */
/* Администраторы                                                      */
/* ------------------------------------------------------------------ */

function tg_admin_ids(): array
{
    $ids = tg_config()['admin_ids'];
    $stored = settings_get('tg_admins', []);
    if (is_array($stored)) {
        $ids = array_merge($ids, $stored);
    }

    return array_values(array_unique(array_map('intval', $ids)));
}

function tg_is_admin(int $chatId): bool
{
    return in_array($chatId, tg_admin_ids(), true);
}

function tg_add_admin(int $chatId): void
{
    $ids = tg_admin_ids();
    if (!in_array($chatId, $ids, true)) {
        $ids[] = $chatId;
    }
    settings_set('tg_admins', $ids);
}
