// Карта Яндекса через Static API: без рекламы и без тяжёлого JS-виджета.
//
// КАК ПОЛУЧИТЬ КЛЮЧ (5 минут, бесплатно):
//   1. Зайдите на https://developer.tech.yandex.ru/ под своим Яндекс-аккаунтом.
//   2. «Подключить API» → выберите «API Яндекс Карт» (или «Static API»).
//   3. Тариф — бесплатный для небольших сайтов, лимит запросов указан там же.
//   4. В настройках ключа ограничьте его своим доменом:
//      ivu.ru, www.ivu.ru, 160ru.github.io — так ключ не украдут.
//   5. Скопируйте ключ и вставьте в константу ниже, в кавычки.
//
// Пока ключа нет — остаётся встроенный виджет, который уже стоит на странице.
// Как только ключ появится, виджет заменится статичной картой без рекламы.

const YANDEX_MAPS_KEY = "";

(function () {
  "use strict";

  const box = document.querySelector(".map-embed");
  if (!box || !YANDEX_MAPS_KEY) return;

  const lon = "40.974334";
  const lat = "56.997362";
  const src =
    "https://static-maps.yandex.ru/v1" +
    "?ll=" + lon + "," + lat +
    "&z=17" +
    "&pt=" + lon + "," + lat + ",pm2rdm" +
    "&size=650,450" +
    "&lang=ru_RU" +
    "&apikey=" + encodeURIComponent(YANDEX_MAPS_KEY);

  const img = document.createElement("img");
  img.className = "map-image";
  img.alt = "Интер-Техника-Плюс на карте: Иваново, улица Степанова, 24";
  img.loading = "lazy";
  img.decoding = "async";

  // Меняем виджет только когда карта реально загрузилась
  img.addEventListener("load", () => {
    box.textContent = "";
    box.appendChild(img);
  });

  img.src = src;
})();
