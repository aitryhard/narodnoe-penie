<?php
declare(strict_types=1);

function current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    return $id === null ? null : (int) $id;
}

/**
 * Отпечаток пароля в сессии. Если пароль сменили — отпечаток в старых
 * сессиях разойдётся, и они закроются на следующем обращении.
 */
function password_epoch(string $passwordHash): string
{
    return substr($passwordHash, -12);
}

function session_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['pw_epoch'] = password_epoch($user['password_hash']);
}

/** Переводит текущую сессию на новый пароль — сама она не разлогинивается. */
function session_refresh_password(string $passwordHash): void
{
    $_SESSION['pw_epoch'] = password_epoch($passwordHash);
    session_regenerate_id(true);
}

function session_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function find_user_by_email(string $email): ?array
{
    return db_one('SELECT * FROM users WHERE email = ?', [normalize_email($email)]);
}

function find_user_by_id(int $id): ?array
{
    return db_one('SELECT * FROM users WHERE id = ?', [$id]);
}

function public_user(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        'name' => $user['name'] ?? '',
        'city' => $user['city'] ?? '',
        'emailVerified' => $user['email_verified_at'] !== null,
        'createdAt' => $user['created_at'],
    ];
}

/**
 * Пользователь, сделавший запрос. Если его нет — отвечает 401 и завершает.
 */
function require_user(): array
{
    $id = current_user_id();
    $user = $id === null ? null : find_user_by_id($id);

    $epoch = (string) ($_SESSION['pw_epoch'] ?? '');
    $fresh = $user !== null && hash_equals($epoch, password_epoch($user['password_hash']));

    if (!$fresh) {
        session_logout();
        api_fail('Требуется вход', [], 401);
    }

    return $user;
}

/**
 * Создаёт одноразовый токен. Возвращает «сырой» токен для ссылки в письме,
 * в базу попадает только его SHA-256.
 */
function create_token(int $userId, string $purpose, int $ttlHours): string
{
    $raw = bin2hex(random_bytes(32));

    db_write('DELETE FROM auth_tokens WHERE user_id = ? AND purpose = ?', [$userId, $purpose]);
    db_write(
        'INSERT INTO auth_tokens (user_id, token_hash, purpose, expires_at, created_at)
         VALUES (?, ?, ?, ?, ?)',
        [
            $userId,
            hash('sha256', $raw),
            $purpose,
            iso_plus_hours($ttlHours),
            now_iso(),
        ]
    );

    return $raw;
}

/**
 * Находит пользователя по действующему токену и помечает его использованным.
 * Возвращает пользователя или null, если токен неверен, просрочен или уже использован.
 */
function consume_token(string $raw, string $purpose): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
        return null;
    }

    $token = db_one(
        'SELECT * FROM auth_tokens WHERE token_hash = ? AND purpose = ?',
        [hash('sha256', $raw), $purpose]
    );

    if ($token === null) {
        return null;
    }

    if ($token['used_at'] !== null || $token['expires_at'] < now_iso()) {
        db_write('DELETE FROM auth_tokens WHERE id = ?', [$token['id']]);
        return null;
    }

    $user = find_user_by_id((int) $token['user_id']);
    if ($user === null) {
        return null;
    }

    db_write('DELETE FROM auth_tokens WHERE id = ?', [$token['id']]);

    return $user;
}

/** Удаляет все действующие токены пользователя. */
function drop_tokens(int $userId, string $purpose): void
{
    db_write('DELETE FROM auth_tokens WHERE user_id = ? AND purpose = ?', [$userId, $purpose]);
}
