<?php
declare(strict_types=1);

/**
 * Доступы к курсам (покупки). Используется ботом сейчас,
 * гейтом контента курсов - дальше.
 */

function courses_list(): array
{
    $courses = require __DIR__ . '/courses.php';
    return is_array($courses) ? $courses : [];
}

function course_title(string $slug): string
{
    foreach (courses_list() as $course) {
        if (($course['slug'] ?? '') === $slug) {
            return (string) ($course['title'] ?? $slug);
        }
    }
    return $slug;
}

function user_by_email(string $email): ?array
{
    return db_one('SELECT id, email, name, role, created_at FROM users WHERE email = ?', [
        mb_strtolower(trim($email)),
    ]);
}

function access_has(int $userId, string $slug): bool
{
    return db_one('SELECT id FROM purchases WHERE user_id = ? AND course_slug = ?', [$userId, $slug]) !== null;
}

function access_grant(int $userId, string $slug): bool
{
    if (access_has($userId, $slug)) {
        return false;
    }
    db_write(
        'INSERT INTO purchases (user_id, course_slug, created_at) VALUES (?, ?, ?)',
        [$userId, $slug, now_iso()]
    );
    return true;
}

function access_revoke(int $userId, string $slug): bool
{
    return db_write('DELETE FROM purchases WHERE user_id = ? AND course_slug = ?', [$userId, $slug]) > 0;
}

function access_list(int $userId): array
{
    return array_column(
        db_all('SELECT course_slug FROM purchases WHERE user_id = ? ORDER BY id', [$userId]),
        'course_slug'
    );
}

/** Поиск по подстроке email (регистронезависимый). $limit - сколько строк максимум. */
function users_search(string $query, int $limit = 11): array
{
    $q = mb_strtolower(trim($query));
    if ($q === '') {
        return [];
    }

    return db_all(
        'SELECT id, email, name FROM users WHERE INSTR(LOWER(email), ?) > 0 ORDER BY LENGTH(email), email LIMIT ' . max(1, $limit),
        [$q]
    );
}

/** Удаление аккаунта со всеми связанными данными (доступы, токены входа). */
function user_delete(int $userId): void
{
    db_write('DELETE FROM purchases WHERE user_id = ?', [$userId]);
    db_write('DELETE FROM auth_tokens WHERE user_id = ?', [$userId]);
    db_write('DELETE FROM users WHERE id = ?', [$userId]);
}

function user_set_role(int $userId, string $role): void
{
    db_write('UPDATE users SET role = ?, updated_at = ? WHERE id = ?', [$role, now_iso(), $userId]);
}
