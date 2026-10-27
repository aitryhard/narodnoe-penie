/**
 * Изображения главной страницы.
 *
 * Пустая строка означает, что фотографии пока нет - компонент рисует
 * нейтральную заглушку. Заказчик кладёт файл в public/img/ и прописывает
 * путь здесь, вёрстка не меняется.
 */
export const images: {
  /** Фото первого экрана. */
  hero: string;
  /** Блок «Как проходят занятия». */
  lessons: string;
  /** Портрет ведущей. */
  lead: string;
  /** Обложки курсов: ключ - slug курса. */
  courses: Record<string, string>;
  /** Фото форматов участия: ключ - якорь формата. */
  formats: Record<string, string>;
} = {
  hero: "/img/zastavka.jpg",
  lessons: "/img/o-shkole.jpg",
  lead: "/img/o-vedushej.jpg",
  courses: {
    "pesni-bannyh-obryadov": "",
    perehody: "",
    "nevestina-banya": "",
    "pesni-maslenicy": "",
    "zaklichki-vesny": "",
    "troice-kupalskie-pesni": "",
  },
  formats: {
    zhivye: "",
    online: "",
    ansambl: "",
  },
};
