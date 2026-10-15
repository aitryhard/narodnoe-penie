<?php
declare(strict_types=1);

/**
 * Telegram-бот админ-панель (webhook).
 * Только inline-кнопки, доступ — только для администраторов.
 * Регистрация: setWebhook с secret_token; запросы без него отклоняются.
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
    $chatType = (string) ($message['chat']['type'] ?? '');
    if ($chatType !== 'private') {
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
        tg_send($chatId, "✅ Вы назначены администратором бота.\n\nПанель управления доступом к курсам:", tg_menu_kb());
        tg_state_clear($chatId);
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
/* Меню и экраны                                                      */
/* ================================================================== */

function tg_menu_kb(): array
{
    return [
        [tg_btn('🎓 Выдать доступ', 'g'), tg_btn('🚫 Отозвать доступ', 'r')],
        [tg_btn('🔍 Найти пользователя', 'f'), tg_btn('👥 Пользователи', 'us:0')],
        [tg_btn('👤 Мои курсы', 'my')],
    ];
}

function tg_menu_text(): string
{
    return '🧹 Админ-панель курсов';
}

function tg_show_menu(int $chatId, int $messageId): void
{
    tg_state_clear($chatId);
    tg_edit($chatId, $messageId, tg_menu_text(), tg_menu_kb());
}

/** Список курсов кнопками. $next — куда вести после выбора: 'g' или 'gu:<uid>' */
function tg_show_courses(int $chatId, int $messageId, string $next, string $title): void
{
    $rows = [];
    foreach (courses_list() as $course) {
        $rows[] = [tg_btn($course['title'], $next . ':' . $course['slug'])];
    }
    $rows[] = [tg_btn('⬅️ Назад', 'm')];
    tg_edit($chatId, $messageId, $title, $rows);
}

function tg_prompt_email(int $chatId, int $messageId, string $step, array $payload, string $title): void
{
    tg_state_set($chatId, $step, $payload);
    tg_edit($chatId, $messageId, $title . "\n\n📧 Отправьте email пользователя:", [
        [tg_btn('⬅️ Назад', 'm')],
    ]);
}

function tg_user_card(int $chatId, int $messageId, int $userId): void
{
    $user = db_one('SELECT id, email, name, role, created_at FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        tg_edit($chatId, $messageId, '❌ Пользователь не найден.', [[tg_btn('⬅️ Назад', 'us:0')]]);
        return;
    }

    $purchased = access_list($userId);
    $lines = [
        '👤 Пользователь',
        '',
        'Email: ' . $user['email'],
        'Имя: ' . (($user['name'] !== '') ? $user['name'] : '—'),
        'Роль: ' . $user['role'],
        'Регистрация: ' . substr((string) $user['created_at'], 0, 10),
        '',
        'Курсы (' . count($purchased) . '):',
    ];
    if ($purchased === []) {
        $lines[] = '— нет доступов —';
    } else {
        foreach ($purchased as $slug) {
            $lines[] = '✅ ' . course_title($slug);
        }
    }

    $rows = [];
    foreach ($purchased as $slug) {
        $rows[] = [tg_btn('✗ Отозвать: ' . course_title($slug), 'rq:' . $userId . ':' . $slug)];
    }
    $rows[] = [tg_btn('🎓 Выдать курс', 'gu:' . $userId)];
    $rows[] = [tg_btn('🔄 Обновить', 'u:' . $userId), tg_btn('⬅️ Назад', 'us:0')];

    tg_edit($chatId, $messageId, implode("\n", $lines), $rows);
}

function tg_show_users(int $chatId, int $messageId, int $offset): void
{
    $per = 8;
    $total = (int) (db_one('SELECT COUNT(*) AS c FROM users')['c'] ?? 0);
    $users = db_all('SELECT id, email, name FROM users ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . $offset);

    $lines = ['👥 Пользователи: ' . $total, ''];
    $rows = [];
    foreach ($users as $user) {
        $label = $user['email'] . (($user['name'] !== '') ? ' · ' . $user['name'] : '');
        $lines[] = '• ' . $label;
        $rows[] = [tg_btn($label, 'u:' . $user['id'])];
    }
    if ($users === []) {
        $lines[] = '— пусто —';
    }

    $nav = [];
    if ($offset > 0) {
        $nav[] = tg_btn('⬅️', 'us:' . max(0, $offset - $per));
    }
    if ($offset + $per < $total) {
        $nav[] = tg_btn('➡️', 'us:' . ($offset + $per));
    }
    if ($nav !== []) {
        $rows[] = $nav;
    }
    $rows[] = [tg_btn('⬅️ Назад', 'm')];

    tg_edit($chatId, $messageId, implode("\n", $lines), $rows);
}

function tg_my_email(): ?string
{
    $email = settings_get('tg_admin_email', '');
    return is_string($email) && $email !== '' ? $email : null;
}

function tg_show_my(int $chatId, int $messageId): void
{
    $email = tg_my_email();
    if ($email === null) {
        tg_state_set($chatId, 'm_email', []);
        tg_edit($chatId, $messageId, "👤 Мои курсы\n\n📧 Отправьте ваш email на сайте:", [
            [tg_btn('⬅️ Назад', 'm')],
        ]);
        return;
    }

    $user = user_by_email($email);
    if ($user === null) {
        tg_edit($chatId, $messageId, "❌ Пользователь {$email} не найден.", [
            [tg_btn('🔁 Указать другой email', 'mym')],
            [tg_btn('⬅️ Назад', 'm')],
        ]);
        return;
    }

    $owned = access_list((int) $user['id']);
    $isAdminRole = ($user['role'] ?? 'user') === 'admin';
    $lines = ['👤 Мои курсы', '', 'Email: ' . $email, ''];

    $rows = [];
    foreach (courses_list() as $course) {
        $has = in_array($course['slug'], $owned, true);
        $lines[] = ($has ? '✅ ' : '⬜️ ') . $course['title'];
        $rows[] = [tg_btn(($has ? '✓ ' : '＋ ') . $course['title'], 'myc:' . $course['slug'])];
    }
    $lines[] = '';
    $lines[] = 'Роль admin (все курсы): ' . ($isAdminRole ? '✅' : '⬜️');

    $rows[] = [tg_btn(($isAdminRole ? '✓' : '＋') . ' Роль admin', 'myr')];
    $rows[] = [tg_btn('🔁 Другой email', 'mym'), tg_btn('⬅️ Назад', 'm')];

    tg_edit($chatId, $messageId, implode("\n", $lines), $rows);
}

/* ================================================================== */
/* Callback-кнопки                                                    */
/* ================================================================== */

function tg_handle_callback(int $chatId, int $messageId, string $data, string $cbId): void
{
    $parts = explode(':', $data);
    $action = $parts[0] ?? '';

    switch ($action) {
        case 'm':
            tg_answer($cbId);
            tg_show_menu($chatId, $messageId);
            return;

        case 'g':
            tg_answer($cbId);
            tg_show_courses($chatId, $messageId, 'gsel', '🎓 Выдать доступ\n\nВыберите курс:');
            return;

        case 'gsel':
            $slug = (string) ($parts[1] ?? '');
            tg_answer($cbId);
            tg_prompt_email($chatId, $messageId, 'g_email', ['slug' => $slug], '🎓 Выдать: ' . course_title($slug));
            return;

        case 'r':
            tg_answer($cbId);
            tg_show_courses($chatId, $messageId, 'rsel', '🚫 Отозвать доступ\n\nВыберите курс:');
            return;

        case 'rsel':
            $slug = (string) ($parts[1] ?? '');
            tg_answer($cbId);
            tg_prompt_email($chatId, $messageId, 'r_email', ['slug' => $slug], '🚫 Отозвать: ' . course_title($slug));
            return;

        case 'gu':
            $uid = (int) ($parts[1] ?? 0);
            tg_answer($cbId);
            tg_show_courses($chatId, $messageId, 'gus:' . $uid, '🎓 Выдать курс пользователю #' . $uid . "\n\nВыберите курс:");
            return;

        case 'gus':
            $uid = (int) ($parts[1] ?? 0);
            $slug = (string) ($parts[2] ?? '');
            tg_answer($cbId, access_grant($uid, $slug) ? '✅ Доступ выдан' : 'Уже был');
            tg_user_card($chatId, $messageId, $uid);
            return;

        case 'rq':
            $uid = (int) ($parts[1] ?? 0);
            $slug = (string) ($parts[2] ?? '');
            tg_answer($cbId, access_revoke($uid, $slug) ? '🚫 Доступ отозван' : 'Не было');
            tg_user_card($chatId, $messageId, $uid);
            return;

        case 'gok':
            tg_confirm_grant($chatId, $messageId, $cbId);
            return;

        case 'f':
            tg_answer($cbId);
            tg_state_set($chatId, 'f_email', []);
            tg_edit($chatId, $messageId, "🔍 Найти пользователя\n\n📧 Отправьте email:", [
                [tg_btn('⬅️ Назад', 'm')],
            ]);
            return;

        case 'us':
            $offset = max(0, (int) ($parts[1] ?? 0));
            tg_answer($cbId);
            tg_show_users($chatId, $messageId, $offset);
            return;

        case 'u':
            $uid = (int) ($parts[1] ?? 0);
            tg_answer($cbId);
            tg_user_card($chatId, $messageId, $uid);
            return;

        case 'my':
            tg_answer($cbId);
            tg_show_my($chatId, $messageId);
            return;

        case 'mym':
            tg_answer($cbId);
            tg_state_set($chatId, 'm_email', []);
            tg_edit($chatId, $messageId, "👤 Мои курсы\n\n📧 Отправьте ваш email на сайте:", [
                [tg_btn('⬅️ Назад', 'm')],
            ]);
            return;

        case 'myc':
            $slug = (string) ($parts[1] ?? '');
            tg_my_toggle($chatId, $messageId, $cbId, $slug);
            return;

        case 'myr':
            tg_answer($cbId);
            $email = tg_my_email();
            $user = $email !== null ? user_by_email($email) : null;
            if ($user === null) {
                tg_answer($cbId, 'Email не задан', true);
                return;
            }
            $newRole = (($user['role'] ?? 'user') === 'admin') ? 'user' : 'admin';
            user_set_role((int) $user['id'], $newRole);
            tg_answer($cbId, $newRole === 'admin' ? '✅ Роль admin выдана' : 'Роль снята');
            tg_show_my($chatId, $messageId);
            return;
    }

    tg_answer($cbId, 'Неизвестная команда');
}

function tg_confirm_grant(int $chatId, int $messageId, string $cbId): void
{
    $state = tg_state_get($chatId);
    $slug = (string) ($state['payload']['slug'] ?? '');
    $email = (string) ($state['payload']['email'] ?? '');

    if ($state['step'] !== 'g_confirm' || $slug === '' || $email === '') {
        tg_answer($cbId, 'Сессия устарела', true);
        tg_show_menu($chatId, $messageId);
        return;
    }

    $user = user_by_email($email);
    if ($user === null) {
        tg_answer($cbId, 'Пользователь не найден', true);
        tg_show_menu($chatId, $messageId);
        return;
    }

    $created = access_grant((int) $user['id'], $slug);
    tg_answer($cbId, $created ? '✅ Доступ выдан' : 'Уже был');
    tg_state_clear($chatId);
    tg_edit(
        $chatId,
        $messageId,
        ($created ? '✅ Доступ выдан' : 'ℹ️ Доступ уже был') . "\n\n" . $email . ' → ' . course_title($slug),
        [[tg_btn('🎓 Выдать ещё', 'g'), tg_btn('🧹 В меню', 'm')]]
    );
}

function tg_my_toggle(int $chatId, int $messageId, string $cbId, string $slug): void
{
    $email = tg_my_email();
    $user = $email !== null ? user_by_email($email) : null;
    if ($user === null) {
        tg_answer($cbId, 'Email не задан', true);
        return;
    }

    $uid = (int) $user['id'];
    if (access_has($uid, $slug)) {
        access_revoke($uid, $slug);
        tg_answer($cbId, '🚫 Доступ отозван');
    } else {
        access_grant($uid, $slug);
        tg_answer($cbId, '✅ Доступ выдан');
    }
    tg_show_my($chatId, $messageId);
}

/* ================================================================== */
/* Текстовые сообщения (ввод email, /start)                           */
/* ================================================================== */

function tg_handle_message(int $chatId, string $text): void
{
    $text = trim($text);

    if ($text === '/start' || $text === '/menu') {
        tg_state_clear($chatId);
        tg_send($chatId, tg_menu_text(), tg_menu_kb());
        return;
    }

    $state = tg_state_get($chatId);
    $step = $state['step'];

    if ($step === '') {
        tg_send($chatId, tg_menu_text(), tg_menu_kb());
        return;
    }

    $email = mb_strtolower($text);

    switch ($step) {
        case 'g_email':
            tg_email_for_grant($chatId, $email, $state);
            return;

        case 'r_email':
            tg_email_for_revoke($chatId, $email, $state);
            return;

        case 'f_email':
            tg_state_clear($chatId);
            $user = user_by_email($email);
            if ($user === null) {
                tg_send($chatId, "❌ Пользователь {$email} не найден.", [[tg_btn('🔁 Ещё раз', 'f'), tg_btn('🧹 В меню', 'm')]]);
                return;
            }
            $purchased = access_list((int) $user['id']);
            $list = $purchased === [] ? '— нет доступов —' : implode("\n", array_map(fn($s) => '✅ ' . course_title($s), $purchased));
            tg_send(
                $chatId,
                "👤 {$user['email']}\nИмя: " . (($user['name'] !== '') ? $user['name'] : '—') . "\nРоль: {$user['role']}\n\nКурсы:\n{$list}",
                [[tg_btn('🗂 Открыть карточку', 'u:' . $user['id']), tg_btn('🧹 В меню', 'm')]]
            );
            return;

        case 'm_email':
            tg_state_clear($chatId);
            $user = user_by_email($email);
            if ($user === null) {
                tg_send($chatId, "❌ Пользователь {$email} не найден.", [[tg_btn('🔁 Ещё раз', 'mym'), tg_btn('🧹 В меню', 'm')]]);
                return;
            }
            settings_set('tg_admin_email', mb_strtolower($email));
            tg_send($chatId, '✅ Сохранено.', [[tg_btn('👤 Мои курсы', 'my')]]);
            return;
    }

    tg_state_clear($chatId);
    tg_send($chatId, tg_menu_text(), tg_menu_kb());
}

function tg_email_for_grant(int $chatId, string $email, array $state): void
{
    $slug = (string) ($state['payload']['slug'] ?? '');
    if ($slug === '') {
        tg_state_clear($chatId);
        tg_send($chatId, tg_menu_text(), tg_menu_kb());
        return;
    }

    $user = user_by_email($email);
    if ($user === null) {
        tg_send(
            $chatId,
            "❌ Пользователь {$email} не найден.\nОтправьте другой email:",
            [[tg_btn('⬅️ Отмена', 'm')]]
        );
        return;
    }

    tg_state_set($chatId, 'g_confirm', ['slug' => $slug, 'email' => $email]);
    tg_send(
        $chatId,
        "🎓 Выдать доступ?\n\n{$email} → " . course_title($slug),
        [[tg_btn('✅ Подтвердить', 'gok'), tg_btn('🚫 Отмена', 'm')]]
    );
}

function tg_email_for_revoke(int $chatId, string $email, array $state): void
{
    $slug = (string) ($state['payload']['slug'] ?? '');
    tg_state_clear($chatId);

    $user = user_by_email($email);
    if ($user === null) {
        tg_send($chatId, "❌ Пользователь {$email} не найден.", [[tg_btn('🔁 Ещё раз', 'r'), tg_btn('🧹 В меню', 'm')]]);
        return;
    }
    if ($slug === '') {
        tg_send($chatId, tg_menu_text(), tg_menu_kb());
        return;
    }

    $revoked = access_revoke((int) $user['id'], $slug);
    tg_send(
        $chatId,
        ($revoked ? '🚫 Доступ отозван' : 'ℹ️ Доступа и не было') . "\n\n{$email} · " . course_title($slug),
        [[tg_btn('🗂 Карточка', 'u:' . $user['id']), tg_btn('🧹 В меню', 'm')]]
    );
}
