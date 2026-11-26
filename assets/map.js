// Карта Яндекса через Static API: картинка без рекламы, грузится одним запросом.
//
// ГДЕ ВЗЯТЬ КЛЮЧ (бесплатно, 5 минут):
//   1. Открыть https://developer.tech.yandex.ru/services
//   2. Войти под своим Яндекс-аккаунтом.
//   3. «Подключить API» → «API Яндекс Карт» → Static API.
//   4. Принять условия бесплатного использования.
//   5. Создать ключ, тип «Static API». В ограничениях указать домены:
//          ivu.ru
//          www.ivu.ru
//          160ru.github.io
//   6. Скопировать ключ и вставить в const YANDEX_MAPS_KEY ниже.
//
// УСЛОВИЯ БЕСПЛАТНОГО ИСПОЛЬЗОВАНИЯ (кратко):
//   • сайт должен быть открыт всем без регистрации — у нас так;
//   • нельзя убирать логотип и копирайт Яндекса, нужна кнопка «Открыть в Картах»;
//   • нельзя сохранять данные карты к себе;
//   • лимит — 1 000 запросов в сутки, для визитки это с огромным запасом.
//
// Пока ключа нет — на странице остаётся встроенный виджет, сайт работает.

const YANDEX_MAPS_KEY = "";

(function () {
  "use strict";

  const box = document.querySelector(".map-embed");
  if (!box || !YANDEX_MAPS_KEY) return;

  const lon = "40.974334";
  const lat = "56.997362";
  const size = "650,450";

  const src =
    "https://static-maps.yandex.ru/v1" +
    "?ll=" + lon + "," + lat +
    "&z=17" +
    "&size=" + size +
    "&pt=" + lon + "," + lat + ",pm2rdm" +
    "&lang=ru_RU" +
    "&apikey=" + encodeURIComponent(YANDEX_MAPS_KEY);

  // Условия Яндекса требуют кнопку «Открыть в Картах» — картинка кликабельна
  const link = document.createElement("a");
  link.className = "map-image-link";
  link.href = "https://yandex.ru/maps/?ll=" + lon + "%2C" + lat + "&pt=" + lon + "," + lat + "&z=17";
  link.target = "_blank";
  link.rel = "noopener";
  link.setAttribute("aria-label", "Открыть адрес в Яндекс.Картах");

  const img = document.createElement("img");
  img.className = "map-image";
  img.alt = "Интер-Техника-Плюс на карте: Иваново, улица Степанова, 24";
  img.loading = "lazy";
  img.decoding = "async";

  // Меняем виджет только когда карта реально загрузилась
  img.addEventListener("load", () => {
    link.appendChild(img);
    box.textContent = "";
    box.classList.add("is-static");
    box.appendChild(link);
  });

  img.src = src;
})();
