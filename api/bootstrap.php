<?php
declare(strict_types=1);

/**
 * Точка входа для всех API-запросов.
 * Подключается первой строкой каждого файла в api/.
 */

function api_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = require __DIR__ . '/config.php';

    $local = __DIR__ . '/config.local.php';
    if (is_file($local)) {
        $config = array_replace($config, require $local);
    }

    return $config;
}

function api_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** Имя сайта из запроса: www.example.ru → example.ru */
function api_site_host(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    // Защита от подстановки мусора в заголовке Host.
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
        return 'localhost';
    }

    return preg_replace('/^www\./i', '', $host) ?? 'localhost';
}

/**
 * Адрес сайта. Задаётся в config.php/config.local.php, а если не задан —
 * берётся из запроса, чтобы ссылки в письмах работали на любом домене.
 */
function api_site_url(): string
{
    $configured = api_config()['site_url'] ?? null;

    if (is_string($configured) && $configured !== '') {
        return rtrim($configured, '/');
    }

    return (api_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** Отправитель письма. По умолчанию — no-reply@домен-сайта. */
function api_mail_from(): string
{
    $configured = api_config()['mail_from'] ?? null;

    if (is_string($configured) && $configured !== '') {
        return $configured;
    }

    return 'no-reply@' . api_site_host();
}

function api_dev_mode(): bool
{
    return (bool) api_config()['dev'];
}

/* ------------------------------------------------------------------ */
/* Вход                                                               */
/* ------------------------------------------------------------------ */

function api_input(): array
{
    static $input = null;
    if ($input !== null) {
        return $input;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);

    if (is_array($decoded)) {
        $input = $decoded;
    } elseif (!empty($_POST)) {
        $input = $_POST;
    } else {
        $input = [];
    }

    return $input;
}

function api_str(string $key, int $max = 500): string
{
    $value = api_input()[$key] ?? '';
    if (!is_scalar($value)) {
        return '';
    }
    return mb_substr(trim((string) $value), 0, $max);
}

/* ------------------------------------------------------------------ */
/* Ответ                                                              */
/* ------------------------------------------------------------------ */

function api_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
}

function api_ok(array $data = [], int $status = 200): void
{
    api_headers();
    http_response_code($status);
    // Пустой массив в JSON должен выглядеть как {}, а не как [].
    $payload = $data === [] ? new stdClass() : $data;
    echo json_encode(['ok' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE);
    exit;
}

function api_fail(string $error, array $extra = [], int $status = 400): void
{
    api_headers();
    http_response_code($status);
    echo json_encode(
        ['ok' => false, 'error' => $error] + $extra,
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/**
 * Запросы должны приходить с нашего же домена.
 * Защищает сессии от чужих сайтов.
 */
function api_check_origin(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($origin === '' || $origin === 'null') {
        return;
    }

    $originHost = parse_url($origin, PHP_URL_HOST);
    $host = parse_url(api_site_url(), PHP_URL_HOST);

    if ($originHost === null || $host === null || $originHost !== $host) {
        api_fail('Запрос с другого домена', [], 403);
    }
}

function api_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        api_fail('Недопустимый метод', [], 405);
    }
}

/* ------------------------------------------------------------------ */
/* Сессия                                                             */
/* ------------------------------------------------------------------ */

function api_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $params = [
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => api_is_https(),
    ];

    if (api_dev_mode()) {
        $params['domain'] = '';
    }

    session_name('napp_sid');
    session_set_cookie_params($params);
    session_start();
}

/**
 * В боевом режиме предупреждения PHP не должны попадать в тело ответа:
 * лишний текст до JSON ломает разбор на клиенте. Ошибки при этом остаются
 * в error_log (их видно в логах хостинга).
 */
if (!api_dev_mode()) {
    ini_set('display_errors', '0');
}

api_session_start();

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validate.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/mailer.php';

