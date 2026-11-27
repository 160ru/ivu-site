// Витрины «Новая техника» и «Барахолка».
// Данные лежат в data/products.json, фотографии — в assets/products/.
// Формат записи описан в CATALOG.md.

(function () {
  "use strict";

  var root = document.getElementById("catalog");
  if (!root) return;

  var category = root.getAttribute("data-category") || "new";
  var CHAT = "https://yandex.ru/maps/org/1043257440/";

  var EMPTY = {
    new: "Пока пусто. Новая техника появится здесь, как только выставим первые позиции.",
    used: "Пока пусто. Барахолка откроется, как только появятся первые лоты."
  };

  function money(value) {
    var number = Number(value);
    if (!number || number <= 0) return "цена по запросу";
    return number.toLocaleString("ru-RU") + " ₽";
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  }

  function card(item) {
    var article = el("article", "product");

    if (item.image) {
      var media = el("div", "product__media");
      var img = document.createElement("img");
      img.src = "assets/products/" + item.image;
      img.alt = item.title || "";
      img.loading = "lazy";
      img.width = 640;
      img.height = 480;
      media.appendChild(img);
      if (item.sold) media.appendChild(el("span", "product__sold", "продано"));
      article.appendChild(media);
    }

    var body = el("div", "product__body");

    var meta = el("p", "product__meta");
    if (item.condition) meta.appendChild(el("span", "product__badge", item.condition));
    if (item.date) meta.appendChild(el("span", "product__date", item.date));
    if (meta.childNodes.length) body.appendChild(meta);

    body.appendChild(el("h2", "product__title", item.title || "Без названия"));
    body.appendChild(el("p", "product__price", money(item.price)));

    if (item.desc) body.appendChild(el("p", "product__desc", item.desc));

    if (!item.sold) {
      var actions = el("p", "product__actions");
      var order = document.createElement("a");
      order.className = "btn btn--sm";
      order.href = CHAT;
      order.target = "_blank";
      order.rel = "noopener";
      order.textContent = "Заказать";
      actions.appendChild(order);

      var call = document.createElement("a");
      call.className = "btn btn--sm btn--ghost";
      call.href = "tel:+74932353070";
      call.textContent = "Позвонить";
      actions.appendChild(call);

      body.appendChild(actions);
    }

    if (item.avito) {
      var avitoRow = el("p", "product__actions");
      var avito = document.createElement("a");
      avito.className = "link-arrow";
      avito.href = item.avito;
      avito.target = "_blank";
      avito.rel = "noopener";
      avito.textContent = "Смотреть на Авито →";
      avitoRow.appendChild(avito);
      body.appendChild(avitoRow);
    }

    article.appendChild(body);
    return article;
  }

  function render(items) {
    root.textContent = "";

    if (!items.length) {
      root.appendChild(el("p", "catalog-empty", EMPTY[category] || EMPTY.new));
      return;
    }

    var grid = el("div", "catalog-grid");
    items.forEach(function (item) {
      grid.appendChild(card(item));
    });
    root.appendChild(grid);
  }

  fetch("data/products.json?v=" + Date.now())
    .then(function (response) {
      if (!response.ok) throw new Error("нет файла товаров");
      return response.json();
    })
    .then(function (data) {
      var list = (data && data[category]) || [];
      render(list);
    })
    .catch(function () {
      render([]);
    });
})();
