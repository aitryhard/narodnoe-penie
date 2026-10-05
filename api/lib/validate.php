<?php
declare(strict_types=1);

function normalize_email(string $email): string
{
    return mb_strtolower(trim($email));
}

function is_valid_email(string $email): bool
{
    if ($email === '' || mb_strlen($email) > 254) {
        return false;
    }
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Пароль: минимум 8 символов, хотя бы одна буква и одна цифра.
 * Возвращает текст ошибки или null, если всё хорошо.
 */
function password_problem(string $password): ?string
{
    if (mb_strlen($password) < 8) {
        return 'Пароль должен быть не короче 8 символов';
    }
    if (mb_strlen($password) > 200) {
        return 'Пароль слишком длинный';
    }
    if (!preg_match('/\p{L}/u', $password)) {
        return 'В пароле должна быть хотя бы одна буква';
    }
    if (!preg_match('/\d/', $password)) {
        return 'В пароле должна быть хотя бы одна цифра';
    }
    return null;
}

/** Убирает управляющие символы и ограничивает длину. */
function clean_text(string $value, int $max = 120): string
{
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim($value);
    return mb_substr($value, 0, $max);
}
