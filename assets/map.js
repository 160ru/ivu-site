// Карта Яндекса через JavaScript API 2.1.
// Ключ привязан к домену: в кабинете developer.tech.yandex.ru должны быть
// перечислены ivu.ru, www.ivu.ru и 160ru.github.io.
//
// Скрипт API грузится после события load — первый экран остаётся быстрым.
//
// Условия бесплатного использования: открытый сайт, логотип и копирайт Яндекса
// не скрывать, кнопка «Открыть в Картах» должна быть доступна (она в контролах),
// лимит запросов в сутки не превышать. Снятие рекламы — платный тариф.

(function () {
  "use strict";

  const box = document.getElementById("map");
  if (!box) return;

  const KEY = "af813594-a490-459e-9232-c618f5be01c6";
  const LAT = 56.99736;
  const LON = 40.97433;

  let loading = false;

  function loadApi() {
    if (loading) return;
    loading = true;

    const script = document.createElement("script");
    script.src =
      "https://api-maps.yandex.ru/2.1/?apikey=" + encodeURIComponent(KEY) + "&lang=ru_RU";
    script.onload = initMap;
    script.onerror = function () {
      box.classList.add("map-failed");
    };
    document.head.appendChild(script);
  }

  function initMap() {
    if (typeof ymaps === "undefined") return;

    ymaps.ready(function () {
      // Высота карты = высота колонки с контактами минус ссылка под картой:
      // так правая и левая колонки заканчиваются на одной линии.
      function matchHeight() {
        if (window.matchMedia("(max-width: 900px)").matches) return;
        const info = document.querySelector(".contacts-info");
        const link = document.querySelector(".map-link");
        if (!info) return;
        const reserve = link ? link.offsetHeight + 14 : 0;
        const target = info.getBoundingClientRect().height - reserve;
        if (target > 240) box.style.height = Math.round(target) + "px";
      }

      matchHeight();

      const map = new ymaps.Map(box, {
        center: [LAT, LON],
        zoom: 17,
        controls: ["zoomControl"]
      });

      // Колесо мыши не должно перехватывать прокрутку страницы
      map.behaviors.disable("scrollZoom");

      const placemark = new ymaps.Placemark(
        [LAT, LON],
        {
          balloonContentHeader: "Интер-Техника-Плюс",
          balloonContentBody:
            "Иваново, ул. Степанова, 24<br>вход с левой стороны здания, 1 этаж" +
            "<br><br>Пн–Пт, 11:00–18:00"
        },
        { preset: "islands#redIcon" }
      );

      map.geoObjects.add(placemark);

      function fit() {
        if (map.container && typeof map.container.fitToViewport === "function") {
          map.container.fitToViewport();
        }
      }

      fit();
      setTimeout(fit, 150);
      setTimeout(fit, 600);

      // Колонка с картой тянется по высоте текста — следим за изменением размера
      if (typeof ResizeObserver !== "undefined") {
        let raf = 0;
        new ResizeObserver(function () {
          cancelAnimationFrame(raf);
          raf = requestAnimationFrame(fit);
        }).observe(box);
      }
    });
  }

  // Карту подключаем после полной загрузки страницы: она не мешает
  // первому экрану, но и не зависит от того, дошёл ли посетитель до низа.
  if (document.readyState === "complete") {
    loadApi();
  } else {
    window.addEventListener("load", loadApi);
  }
})();
