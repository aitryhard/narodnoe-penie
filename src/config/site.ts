export const siteConfig = {
  name: "Женская школа народного пения",
  shortName: "Народное пение",
  domain: "www.narodnoe-penie.ru",
  tagline: "Живое звучание, бережная традиция",
  description:
    "Женская школа народного пения: живые занятия в Петербурге, онлайн-занятия и обучающие проекты по песенному фольклору.",
  address: "Санкт-Петербург",
};

export type NavLink = { label: string; href: string };

export const navLinks: NavLink[] = [
  { label: "О школе", href: "/o-shkole" },
  { label: "О ведущей", href: "/o-veduschej" },
  { label: "Инфо-продукты", href: "/info-produkty" },
  { label: "Отзывы", href: "/otzyvy" },
  { label: "Контакты", href: "/kontakty" },
];