// Витрины «Новая техника» и «Барахолка».
// Данные отдаёт catalog-api.php (PHP + SQLite на Бегете).

(function () {
  "use strict";

  var root = document.getElementById("catalog");
  if (!root) return;

  var category = root.getAttribute("data-category") || "new";
  var CHAT = "https://yandex.ru/maps/org/1043257440/";
  var API = "catalog-api.php";
  var PER = 24;

  var state = { page: 1, query: "", loading: false, total: 0 };

  var emptyText = {
    new: "Пока пусто. Новая техника появится здесь, как только выставим первые позиции.",
    used: "Пока пусто. Барахолка откроется, как только появятся первые лоты."
  };

  var grid = document.createElement("div");
  grid.className = "catalog-grid";

  var status = document.createElement("p");
  status.className = "catalog-status";

  var moreWrap = document.createElement("p");
  moreWrap.className = "catalog-more";

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }

  function money(value) {
    var number = Number(value);
    if (!number || number <= 0) return "цена по запросу";
    return number.toLocaleString("ru-RU") + " ₽";
  }

  function gallery(item) {
    var photos = item.photos || [];
    var wrap = el("div", "product__media");

    if (!photos.length) {
      wrap.classList.add("product__media--empty");
      wrap.appendChild(el("span", "product__nophoto", "без фото"));
      return wrap;
    }

    var img = document.createElement("img");
    img.src = "uploads/products/" + photos[0];
    img.alt = item.title || "";
    img.loading = "lazy";
    img.width = 640;
    img.height = 480;
    wrap.appendChild(img);

    if (item.sold) wrap.appendChild(el("span", "product__sold", "продано"));

    if (photos.length > 1) {
      var thumbs = el("div", "product__thumbs");
      photos.slice(0, 5).forEach(function (file) {
        var button = document.createElement("button");
        button.type = "button";
        button.className = "product__thumb";
        var thumb = document.createElement("img");
        thumb.src = "uploads/products/" + file;
        thumb.alt = "";
        thumb.loading = "lazy";
        button.appendChild(thumb);
        button.addEventListener("click", function () {
          img.src = "uploads/products/" + file;
        });
        thumbs.appendChild(button);
      });
      wrap.appendChild(thumbs);
    }

    return wrap;
  }

  function card(item) {
    var article = el("article", "product");
    article.appendChild(gallery(item));

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

  function render(items, append) {
    if (!append) grid.textContent = "";
    items.forEach(function (item) {
      grid.appendChild(card(item));
    });
  }

  function updateStatus() {
    if (!state.total) {
      status.textContent = "";
      return;
    }
    status.textContent = "Показано " + grid.children.length + " из " + state.total;
  }

  function updateMore(pages) {
    moreWrap.textContent = "";
    if (state.page < pages) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "btn btn--ghost";
      button.textContent = "Показать ещё";
      button.addEventListener("click", function () {
        state.page += 1;
        load(true);
      });
      moreWrap.appendChild(button);
    }
  }

  function load(append) {
    if (state.loading) return;
    state.loading = true;
    status.textContent = "Загружаем…";

    var url = API + "?cat=" + encodeURIComponent(category) +
      "&page=" + state.page + "&per=" + PER +
      (state.query ? "&q=" + encodeURIComponent(state.query) : "");

    fetch(url, { headers: { "Accept": "application/json" } })
      .then(function (response) {
        if (!response.ok) throw new Error("HTTP " + response.status);
        return response.json();
      })
      .then(function (data) {
        state.total = data.total || 0;
        var items = data.items || [];

        if (!append) {
          root.textContent = "";
          root.appendChild(grid);
          root.appendChild(status);
          root.appendChild(moreWrap);
        }

        if (!items.length && !append) {
          root.textContent = "";
          root.appendChild(el("p", "catalog-empty",
            state.query ? "По запросу «" + state.query + "» ничего не нашлось." : (emptyText[category] || emptyText.new)));
          return;
        }

        render(items, append);
        updateStatus();
        updateMore(data.pages || 1);
      })
      .catch(function () {
        root.textContent = "";
        root.appendChild(el("p", "catalog-empty", "Не удалось загрузить каталог. Обновите страницу."));
      })
      .then(function () {
        state.loading = false;
      });
  }

  // поиск
  var searchBox = document.getElementById("catalog-search");
  if (searchBox) {
    var timer = null;
    searchBox.addEventListener("input", function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        state.query = searchBox.value.trim();
        state.page = 1;
        load(false);
      }, 350);
    });
  }

  load(false);
})();
