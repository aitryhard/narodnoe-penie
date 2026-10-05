/**
 * Связь личного кабинета с сервером.
 *
 * На GitHub Pages API нет — режим выключен, формы показывают подсказку
 * и ничего не отправляют. После заливки на хостинг собирайте сайт с
 * переменной окружения PUBLIC_API_ENABLED=1, тогда формы станут рабочими
 * без единой правки вёрстки.
 *
 *   PowerShell:  $env:PUBLIC_API_ENABLED = "1"; npm run build
 *   bash:        PUBLIC_API_ENABLED=1 npm run build
 */

export const API_ENABLED = import.meta.env.PUBLIC_API_ENABLED === "1";

/** Пустая строка — API на том же домене, что и сайт. */
export const API_BASE = import.meta.env.PUBLIC_API_BASE ?? "";

/** Демо-режим: текст подсказки, если сервер недоступен. */
export const DEMO_HINT =
  "На демонстрационной версии форма не отправляется. Заработает после запуска сайта на хостинге.";
