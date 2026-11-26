// Карта на OpenStreetMap через Leaflet.
// Без API-ключей, без рекламы и без внешних CDN: библиотека лежит в assets/vendor/leaflet.
// Тайлы грузятся из tile.openstreetmap.org только когда карта доскроллена до экрана.

(function () {
  "use strict";

  const box = document.getElementById("map");
  if (!box || typeof L === "undefined") return;

  const LAT = 56.99736;
  const LON = 40.97433;
  const ADDRESS = "Иваново, ул. Степанова, 24";

  function init() {
    const map = L.map(box, {
      center: [LAT, LON],
      zoom: 17,
      scrollWheelZoom: false,   // не перехватываем прокрутку страницы
      zoomControl: true
    });

    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    const pin = L.divIcon({
      className: "map-pin",
      html: "<span></span>",
      iconSize: [28, 36],
      iconAnchor: [14, 36],
      popupAnchor: [0, -32]
    });

    L.marker([LAT, LON], { icon: pin, title: "Интер-Техника-Плюс" })
      .addTo(map)
      .bindPopup("<strong>Интер-Техника-Плюс</strong><br>" + ADDRESS)
      .openPopup();
  }

  // Загружаем карту при приближении к экрану, а не сразу при открытии страницы
  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          observer.disconnect();
          init();
        }
      },
      { rootMargin: "300px" }
    );
    observer.observe(box);
  } else {
    init();
  }
})();
