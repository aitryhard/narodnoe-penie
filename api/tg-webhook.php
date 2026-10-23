<?php
declare(strict_types=1);

/**
 * Telegram-бот: админ-панель доступа к курсам (webhook).
 *
 * Вся работа строится вокруг одного экрана - карточки пользователя:
 *   1. Любое сообщение = поиск по email (точный, затем по подстроке).
 *   2. Карточка: кнопка у каждого курса выдаёт/отзывает доступ, внизу - удаление аккаунта.
 *   3. Меню (/start) - только приглашение ввести email.
 *
 * Состояние диалога не хранится: любое действие адресуется id пользователя
 * прямо в callback_data, поэтому FSM-таблица боту больше не нужна.
 * Доступ только у администраторов; запросы без secret_token отклоняются.
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/tg.php';
require_once __DIR__ . '/lib/access.php';

$cfg = tg_config();
if ($cfg['token'] === '' || $cfg['secret'] === '') {
    http_response_code(403);
    exit;
}

$secret = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($secret === '' || !hash_equals((string) $cfg['secret'], $secret)) {
    http_response_code(403);
    exit;
}

$update = api_input();
$cb = is_array($update['callback_query'] ?? null) ? $update['callback_query'] : null;
$message = is_array($update['message'] ?? null) ? $update['message'] : null;

if ($cb === null && $message === null) {
    http_response_code(200);
    exit;
}

$chatId = 0;
if ($cb !== null) {
    $chatId = (int) ($cb['message']['chat']['id'] ?? $cb['from']['id'] ?? 0);
} else {
    $chatId = (int) ($message['chat']['id'] ?? 0);
    if (($message['chat']['type'] ?? '') !== 'private') {
        http_response_code(200);
        exit;
    }
}

if ($chatId <= 0) {
    http_response_code(200);
    exit;
}

/* ------------------------------------------------------------------ */
/* Доступ: bootstrap первого администратора                           */
/* ------------------------------------------------------------------ */

if (!tg_is_admin($chatId)) {
    if (tg_admin_ids() === []) {
        tg_add_admin($chatId);
        tg_send($chatId, "✅ Вы назначены администратором бота.\n\n" . tg_menu_text());
        http_response_code(200);
        exit;
    }
    if ($cb !== null) {
        tg_answer($cb['id'], '⛔ Нет доступа', true);
    } else {
        tg_send($chatId, "⛔ Доступ запрещён.\nВаш ID: {$chatId}");
    }
    http_response_code(200);
    exit;
}

/* ------------------------------------------------------------------ */
/* Обработка                                                          */
/* ------------------------------------------------------------------ */

if ($cb !== null) {
    tg_handle_callback($chatId, (int) ($cb['message']['message_id'] ?? 0), (string) ($cb['data'] ?? ''), (string) ($cb['id'] ?? ''));
} elseif ($message !== null) {
    tg_handle_message($chatId, (string) ($message['text'] ?? ''));
}

http_response_code(200);

/* ================================================================== */
/* Экраны                                                             */
/* ================================================================== */

function tg_menu_text(): string
{
    return "🧹 Админ-панель курсов\n\n📧 Введите email пользователя — открою его карточку.\nВ карточке: выдать или отозвать курс, удалить аккаунт.";
}

function tg_show_menu(int $chatId, int $messageId): void
{
    tg_render($chatId, $messageId, tg_menu_text(), []);
}

/** [$text, $kb] карточки пользователя или null, если его нет. */
function tg_card_parts(int $userId): ?array
{
    $user = db_one('SELECT id, email, name, role, created_at FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        return null;
    }

    $owned = access_list($userId);
    $all = courses_list();

    $lines = [
        '👤 ' . $user['email'],
        '',
        'Имя: ' . (($user['name'] !== '') ? $user['name'] : '-'),
        'Роль: ' . $user['role'],
        'Регистрация: ' . substr((string) $user['created_at'], 0, 10),
        'ID: ' . $userId,
        '',
        'Курсы: ' . count($owned) . ' из ' . count($all),
        '',
    ];

    $rows = [];
    foreach ($all as $course) {
        $has = in_array($course['slug'], $owned, true);
        $lines[] = ($has ? '✅ ' : '⬜️ ') . $course['title'];
        $rows[] = [tg_btn(
            ($has ? '✗ Отозвать · ' : '＋ Выдать · ') . $course['title'],
            't:' . $userId . ':' . $course['slug']
        )];
    }
    $rows[] = [tg_btn('🗑 Удалить аккаунт', 'd:' . $userId)];
    $rows[] = [tg_btn('🔍 Новый поиск', 'm')];

    return ['text' => implode("\n", $lines), 'kb' => $rows];
}

function tg_show_card(int $chatId, int $messageId, int $userId): void
{
    $parts = tg_card_parts($userId);
    if ($parts === null) {
        tg_show_not_found($chatId, $messageId, '#' . $userId);
        return;
    }
    tg_render($chatId, $messageId, $parts['text'], $parts['kb']);
}

function tg_show_not_found(int $chatId, int $messageId, string $query): void
{
    tg_render(
        $chatId,
        $messageId,
        "❌ Пользователь не найден: {$query}\n\nПроверьте адрес или введите другую часть email.",
        []
    );
}

/** [$text, $kb] экрана подтверждения удаления или null. */
function tg_delete_parts(int $userId): ?array
{
    $user = db_one('SELECT id, email, name, role FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        return null;
    }

    $text = "🗑 Удалить аккаунт?\n\n"
        . $user['email'] . "\n"
        . 'Имя: ' . (($user['name'] !== '') ? $user['name'] : '-') . "\n"
        . 'Роль: ' . $user['role'] . ' · Курсы: ' . count(access_list($userId)) . "\n\n"
        . 'Аккаунт, доступы к курсам и токены входа удаляются безвозвратно.';

    return [
        'text' => $text,
        'kb' => [
            [tg_btn('🗑 Да, удалить', 'x:' . $userId)],
            [tg_btn('⬅️ Отмена', 'u:' . $userId)],
        ],
    ];
}

function tg_show_delete_confirm(int $chatId, int $messageId, int $userId): void
{
    $parts = tg_delete_parts($userId);
    if ($parts === null) {
        tg_show_not_found($chatId, $messageId, '#' . $userId);
        return;
    }
    tg_render($chatId, $messageId, $parts['text'], $parts['kb']);
}

function tg_delete_user(int $chatId, int $messageId, int $userId): void
{
    $user = db_one('SELECT id, email, name, role FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        tg_show_not_found($chatId, $messageId, '#' . $userId);
        return;
    }

    $courses = count(access_list($userId));
    user_delete($userId);

    tg_render(
        $chatId,
        $messageId,
        "🗑 Аккаунт удалён\n\n{$user['email']}\nКурсы: {$courses} · Роль: {$user['role']}",
        [[tg_btn('🔍 Новый поиск', 'm')]]
    );
}

/** Сообщение/редактирование: $messageId = 0 - отправить новое. */
function tg_render(int $chatId, int $messageId, string $text, array $kb): void
{
    if ($messageId > 0) {
        tg_edit($chatId, $messageId, $text, $kb);
    } else {
        tg_send($chatId, $text, $kb);
    }
}

/* ================================================================== */
/* Поиск по email                                                     */
/* ================================================================== */

function tg_search(int $chatId, string $query): void
{
    $q = trim($query);
    if ($q === '') {
        tg_send($chatId, tg_menu_text(), []);
        return;
    }

    $user = user_by_email($q);
    if ($user !== null) {
        tg_show_card($chatId, 0, (int) $user['id']);
        return;
    }

    $found = users_search($q, 11);
    if ($found === []) {
        tg_show_not_found($chatId, 0, $query);
        return;
    }
    if (count($found) === 1) {
        tg_show_card($chatId, 0, (int) $found[0]['id']);
        return;
    }

    $more = count($found) > 10;
    if ($more) {
        $found = array_slice($found, 0, 10);
    }

    $lines = [
        '🔍 Найдено: ' . count($found) . ($more ? '+' : ''),
        '',
        'Уточните email или откройте карточку кнопкой:',
        '',
    ];
    $rows = [];
    foreach ($found as $row) {
        $label = $row['email'] . (($row['name'] !== '') ? ' · ' . $row['name'] : '');
        $lines[] = '• ' . $label;
        $rows[] = [tg_btn($label, 'u:' . $row['id'])];
    }
    $rows[] = [tg_btn('🔍 Новый поиск', 'm')];

    tg_send($chatId, implode("\n", $lines), $rows);
}

/* ================================================================== */
/* Callback-кнопки                                                    */
/* ================================================================== */

function tg_handle_callback(int $chatId, int $messageId, string $data, string $cbId): void
{
    $parts = explode(':', $data);
    $userId = (int) ($parts[1] ?? 0);

    switch ($parts[0] ?? '') {
        case 'm':
            tg_answer($cbId);
            tg_show_menu($chatId, $messageId);
            return;

        case 'u':
            tg_answer($cbId);
            tg_show_card($chatId, $messageId, $userId);
            return;

        case 't':
            tg_answer($cbId, tg_toggle($userId, (string) ($parts[2] ?? '')));
            tg_show_card($chatId, $messageId, $userId);
            return;

        case 'd':
            tg_answer($cbId);
            tg_show_delete_confirm($chatId, $messageId, $userId);
            return;

        case 'x':
            tg_answer($cbId, '🗑 Удаляю…');
            tg_delete_user($chatId, $messageId, $userId);
            return;
    }

    // Кнопка из старой версии бота или устаревшее сообщение.
    tg_answer($cbId, 'Меню обновлено');
    tg_show_menu($chatId, $messageId);
}

/** Выдача/отзыв одного курса. Возвращает текст для всплывающего уведомления. */
function tg_toggle(int $userId, string $slug): string
{
    if ($userId <= 0 || $slug === '') {
        return '❌ Ошибка';
    }
    if (!in_array($slug, array_column(courses_list(), 'slug'), true)) {
        return '❌ Неизвестный курс';
    }

    if (access_has($userId, $slug)) {
        return access_revoke($userId, $slug) ? '🚫 Доступ отозван' : 'ℹ️ Доступа не было';
    }

    return access_grant($userId, $slug) ? '✅ Доступ выдан' : 'ℹ️ Доступ уже был';
}

/* ================================================================== */
/* Текстовые сообщения                                                */
/* ================================================================== */

function tg_handle_message(int $chatId, string $text): void
{
    $text = trim($text);

    if ($text === '/start' || $text === '/menu') {
        tg_send($chatId, tg_menu_text(), []);
        return;
    }

    tg_search($chatId, $text);
}
