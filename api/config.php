<?php
declare(strict_types=1);

/**
 * Настройки API.
 *
 * Локально работает SQLite (файл var/app.sqlite) — базу ставить не нужно,
 * таблицы создаются сами при первом обращении.
 *
 * На Beget создайте рядом файл config.local.php — он подключается поверх
 * этих настроек, поэтому сам config.php править не придётся.
 *
 * Пример config.local.php для Beget (имя базы = имя пользователя MySQL):
 *
 *   <?php
 *   return [
 *       'dev'      => false,
 *       'db_dsn'   => 'mysql:host=localhost;dbname=ваша_база;charset=utf8mb4',
 *       'db_user'  => 'ваша_база',
 *       'db_pass'  => 'ваш_пароль',
 *   ];
 */

return [
    // true  — письма не отправляются, ссылка подтверждения возвращается в ответе
    // false — письма уходят по-настоящему (нужен рабочий mail на хостинге)
    'dev' => true,

    // null — адрес сайта определяется автоматически из запроса.
    // Задайте явно, только если сайт открывается по нескольким доменам.
    'site_url' => null,

    // null — письма уходят с no-reply@ваш-домен.
    'mail_from' => null,
    'mail_from_name' => 'Народное пение',

    'db_dsn' => 'sqlite:' . dirname(__DIR__) . '/var/app.sqlite',
    'db_user' => null,
    'db_pass' => null,

    // Время жизни кода подтверждения почты, в минутах
    'confirm_code_ttl_minutes' => 30,

    // Время жизни ссылки сброса пароля, в часах
    'reset_ttl_hours' => 2,
];
