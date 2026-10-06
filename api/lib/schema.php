<?php
declare(strict_types=1);

/**
 * Схема базы данных — единый источник правды.
 *
 * Используется двумя способами:
 *  - автоматически при первом обращении к API (если таблиц ещё нет);
 *  - вручную:  php database/install.php
 *
 * Синтаксис должен подходить и для SQLite (разработка), и для MySQL (Beget).
 */

return [
    'sqlite' => [
        'CREATE TABLE IF NOT EXISTS users (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          email TEXT NOT NULL,
          password_hash TEXT NOT NULL,
          name TEXT NOT NULL DEFAULT "",
          city TEXT NOT NULL DEFAULT "",
          email_verified_at TEXT,
          created_at TEXT NOT NULL,
          updated_at TEXT NOT NULL
        )',
        'CREATE UNIQUE INDEX IF NOT EXISTS ux_users_email ON users (email)',
        'CREATE TABLE IF NOT EXISTS auth_tokens (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL,
          token_hash TEXT NOT NULL,
          purpose TEXT NOT NULL,
          expires_at TEXT NOT NULL,
          used_at TEXT,
          created_at TEXT NOT NULL,
          CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )',
        'CREATE UNIQUE INDEX IF NOT EXISTS ux_auth_tokens_hash ON auth_tokens (token_hash)',
        'CREATE INDEX IF NOT EXISTS ix_auth_tokens_user ON auth_tokens (user_id, purpose)',
    ],

    'mysql' => [
        'CREATE TABLE IF NOT EXISTS `users` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `email` VARCHAR(254) NOT NULL,
          `password_hash` VARCHAR(255) NOT NULL,
          `name` VARCHAR(120) NOT NULL DEFAULT \'\',
          `city` VARCHAR(120) NOT NULL DEFAULT \'\',
          `email_verified_at` VARCHAR(32) NULL DEFAULT NULL,
          `created_at` VARCHAR(32) NOT NULL,
          `updated_at` VARCHAR(32) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `ux_users_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS `auth_tokens` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` BIGINT UNSIGNED NOT NULL,
          `token_hash` CHAR(64) NOT NULL,
          `purpose` VARCHAR(16) NOT NULL,
          `expires_at` VARCHAR(32) NOT NULL,
          `used_at` VARCHAR(32) NULL DEFAULT NULL,
          `created_at` VARCHAR(32) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `ux_auth_tokens_hash` (`token_hash`),
          KEY `ix_auth_tokens_user` (`user_id`, `purpose`),
          CONSTRAINT `fk_auth_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
];
