/**
 * Демонстрационные данные для личного кабинета.
 * Нужны только для показа заказчику: после запуска на хостинге
 * вместо них подставятся строки из MySQL.
 */

export const demoProfile = {
  name: "Анна",
  email: "anna@example.ru",
  since: "октябрь 2026",
  city: "Санкт-Петербург",
};

export type OrderStatus = "paid" | "pending";

export const demoOrders: {
  id: string;
  date: string;
  courseTitle: string;
  courseSlug: string;
  amount: string;
  status: OrderStatus;
}[] = [
  {
    id: "1043",
    date: "4 октября 2026",
    courseTitle: "Песни Масленицы",
    courseSlug: "pesni-maslenicy",
    amount: "3 400 ₽",
    status: "pending",
  },
  {
    id: "1042",
    date: "3 октября 2026",
    courseTitle: "Переходы",
    courseSlug: "perehody",
    amount: "4 900 ₽",
    status: "paid",
  },
  {
    id: "1037",
    date: "21 сентября 2026",
    courseTitle: "Заклички весны",
    courseSlug: "zaklichki-vesny",
    amount: "2 900 ₽",
    status: "paid",
  },
];

/** Курсы, к которым у демо-ученицы есть доступ. */
export const demoAccess: string[] = ["perehody", "zaklichki-vesny"];

export const demoMaterials: Record<string, { title: string; kind: string }[]> = {
  "perehody": [
    { title: "Введение: что такое песни перехода", kind: "Видео, 12 мин" },
    { title: "Свадебные переходы — строение и припевы", kind: "Текст и аудио" },
    { title: "Крестинные переходы", kind: "Текст и аудио" },
    { title: "Календарные переходы", kind: "Текст" },
    { title: "Работа с дыханием в длинном тексте", kind: "Видео, 18 мин" },
    { title: "Практика в ансамбле — запись занятия", kind: "Запись, 47 мин" },
    { title: "Итоги и обратная связь", kind: "Текст" },
  ],
  "zaklichki-vesny": [
    { title: "Как устроена закличка", kind: "Видео, 9 мин" },
    { title: "Первые пять закличек", kind: "Текст и аудио" },
    { title: "Заклички на каждый день", kind: "Текст" },
    { title: "Дыхание и короткая строка", kind: "Видео, 14 мин" },
    { title: "Групповая практика", kind: "Запись, 35 мин" },
    { title: "Итоги проекта", kind: "Текст" },
  ],
};

export const orderStatusLabel: Record<OrderStatus, string> = {
  paid: "Оплачен",
  pending: "Ожидает оплаты",
};

export function hasAccess(slug: string): boolean {
  return demoAccess.includes(slug);
}
