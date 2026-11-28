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

    var stock = item.sold ? "продано" : (item.date || "");
    if (stock) {
      meta.appendChild(el("span", "product__stock" + (item.sold ? " product__stock--sold" : ""), stock));
    }
    if (meta.childNodes.length) body.appendChild(meta);

    var titleNode = el("h2", "product__title");
    var titleLink = document.createElement("a");
    titleLink.href = "tovar/" + item.id;
    titleLink.textContent = item.title || "Без названия";
    titleNode.appendChild(titleLink);
    body.appendChild(titleNode);
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

    var secondary = el("p", "product__secondary");

    var call = document.createElement("a");
    call.className = "product__link";
    call.href = "tel:+74932353070";
    call.textContent = "+7 4932 353070";
    secondary.appendChild(call);

    var questionsRow = el("p", "product__questions");
    var questionsLink = document.createElement("button");
    questionsLink.type = "button";
    questionsLink.className = "link-question";
    questionsLink.textContent = "Вопросы" + (item.comments ? " (" + item.comments + ")" : "");
    questionsLink.addEventListener("click", function () {
      openQuestions(item);
    });
    questionsRow.appendChild(questionsLink);
    secondary.appendChild(questionsRow);
    body.appendChild(secondary);

    article.appendChild(body);
    return article;
  }

  // ---------- окно вопросов ----------
  var modal = null;

  function buildModal() {
    var wrap = el("div", "modal");
    wrap.setAttribute("role", "dialog");
    wrap.setAttribute("aria-modal", "true");

    var panel = el("div", "modal__panel");

    var close = document.createElement("button");
    close.type = "button";
    close.className = "modal__close";
    close.setAttribute("aria-label", "Закрыть");
    close.textContent = "×";
    close.addEventListener("click", closeQuestions);
    panel.appendChild(close);

    var title = el("h2", "modal__title");
    panel.appendChild(title);

    var list = el("div", "modal__list");
    panel.appendChild(list);

    var form = document.createElement("form");
    form.className = "modal__form";
    form.noValidate = true;

    var hp = document.createElement("input");
    hp.type = "text";
    hp.name = "hp";
    hp.className = "modal__hp";
    hp.tabIndex = -1;
    hp.autocomplete = "off";
    form.appendChild(hp);

    var nameLabel = el("label", "modal__label");
    nameLabel.appendChild(el("span", null, "Ваше имя"));
    var nameInput = document.createElement("input");
    nameInput.type = "text";
    nameInput.name = "name";
    nameInput.required = true;
    nameInput.maxLength = 60;
    nameLabel.appendChild(nameInput);
    form.appendChild(nameLabel);

    var contactLabel = el("label", "modal__label");
    contactLabel.appendChild(el("span", null, "Телефон или как связаться (необязательно)"));
    var contactInput = document.createElement("input");
    contactInput.type = "text";
    contactInput.name = "contact";
    contactInput.maxLength = 80;
    contactLabel.appendChild(contactInput);
    form.appendChild(contactLabel);

    var textLabel = el("label", "modal__label");
    textLabel.appendChild(el("span", null, "Вопрос"));
    var textInput = document.createElement("textarea");
    textInput.name = "text";
    textInput.required = true;
    textInput.maxLength = 1500;
    textLabel.appendChild(textInput);
    form.appendChild(textLabel);

    var submit = document.createElement("button");
    submit.type = "submit";
    submit.className = "btn btn--sm";
    submit.textContent = "Отправить вопрос";
    form.appendChild(submit);

    var status = el("p", "modal__status");
    form.appendChild(status);

    panel.appendChild(form);

    var loginBox = el("div", "modal__login");
    var loginText = el("p", "modal__login-text", "");
    var loginLink = document.createElement("a");
    loginLink.className = "btn btn--sm";
    loginLink.href = "auth.php?action=start";
    loginLink.textContent = "Войти через Яндекс";
    loginBox.appendChild(loginText);
    loginBox.appendChild(loginLink);
    panel.appendChild(loginBox);

    wrap.appendChild(panel);

    wrap.addEventListener("click", function (event) {
      if (event.target === wrap) closeQuestions();
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") closeQuestions();
    });

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      status.textContent = "";
      status.className = "modal__status";

      var payload = {
        item: modalItemId,
        name: nameInput.value.trim(),
        text: textInput.value.trim(),
        contact: contactInput.value.trim(),
        hp: hp.value
      };

      if (payload.name.length < 2) {
        status.textContent = "Напишите, как к вам обращаться";
        status.classList.add("modal__status--err");
        return;
      }
      if (payload.text.length < 10) {
        status.textContent = "Вопрос слишком короткий — добавьте пару слов";
        status.classList.add("modal__status--err");
        return;
      }

      submit.disabled = true;
      status.textContent = "Отправляем…";

      fetch("comments-api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.error) throw new Error(data.error);
          form.reset();
          status.textContent = "Спасибо! Вопрос получили — ответим и опубликуем после проверки.";
          status.classList.add("modal__status--ok");
        })
        .catch(function (error) {
          status.textContent = error.message || "Не удалось отправить. Попробуйте позже.";
          status.classList.add("modal__status--err");
        })
        .then(function () { submit.disabled = false; });
    });

    modal = { wrap: wrap, title: title, list: list, form: form, loginBox: loginBox, loginText: loginText, nameInput: nameInput, contactInput: contactInput };
    document.body.appendChild(wrap);
    return modal;
  }

  var modalItemId = 0;

  function renderComments(items) {
    modal.list.textContent = "";
    if (!items.length) {
      modal.list.appendChild(el("p", "modal__empty", "Пока вопросов нет. Задайте первый — ответим."));
      return;
    }
    items.forEach(function (comment) {
      var box = el("div", "modal__comment");
      var head = el("p", "modal__comment-head");
      head.appendChild(el("strong", null, comment.name));
      head.appendChild(el("span", null, comment.date));
      box.appendChild(head);
      box.appendChild(el("p", "modal__comment-text", comment.text));
      modal.list.appendChild(box);
    });
  }

  function openQuestions(item) {
    if (!modal) buildModal();
    modalItemId = item.id;
    modal.title.textContent = "Вопросы по товару";
    modal.list.textContent = "";
    modal.list.appendChild(el("p", "modal__empty", "Загружаем…"));
    modal.wrap.classList.add("is-open");

    // если вход через Яндекс настроен — спрашивать могут только вошедшие
    fetch("auth.php?action=me")
      .then(function (response) { return response.json(); })
      .then(function (auth) {
        var needLogin = auth.configured && !auth.authorized;
        modal.form.style.display = needLogin ? "none" : "";
        modal.loginBox.style.display = needLogin ? "" : "none";

        if (needLogin) {
          modal.loginText.textContent =
            "Вопросы могут задавать только авторизованные пользователи — так меньше спама. Вход через Яндекс занимает несколько секунд.";
        } else if (auth.configured && auth.authorized) {
          modal.nameInput.value = (auth.user && auth.user.name) || "";
          modal.nameInput.readOnly = true;
          if (modal.contactInput.parentNode) modal.contactInput.parentNode.style.display = "none";
        }
      })
      .catch(function () { /* без ответа сервера показываем форму как есть */ });

    fetch("comments-api.php?item=" + item.id)
      .then(function (response) { return response.json(); })
      .then(function (data) {
        renderComments(data.items || []);
      })
      .catch(function () {
        renderComments([]);
      });
  }

  function closeQuestions() {
    if (modal) modal.wrap.classList.remove("is-open");
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
      (state.query ? "&q=" + encodeURIComponent(state.query) : "") +
      (state.priceMin ? "&price_min=" + state.priceMin : "") +
      (state.priceMax ? "&price_max=" + state.priceMax : "") +
      (state.stock ? "&stock=1" : "") +
      (state.sort && state.sort !== "new" ? "&sort=" + encodeURIComponent(state.sort) : "");

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

  // поиск и фильтры
  var searchBox = document.getElementById("catalog-search");
  var minBox = document.getElementById("filter-min");
  var maxBox = document.getElementById("filter-max");
  var sortBox = document.getElementById("filter-sort");
  var stockBox = document.getElementById("filter-stock");
  var resetBtn = document.getElementById("filter-reset");

  function collectFilters() {
    state.query = searchBox ? searchBox.value.trim() : "";
    state.priceMin = minBox && minBox.value ? Math.max(0, parseInt(minBox.value, 10) || 0) : 0;
    state.priceMax = maxBox && maxBox.value ? Math.max(0, parseInt(maxBox.value, 10) || 0) : 0;
    state.sort = sortBox ? sortBox.value : "new";
    state.stock = stockBox ? stockBox.checked : false;
  }

  function reloadWithFilters() {
    collectFilters();
    state.page = 1;
    load(false);
  }

  var debounce = null;
  function debouncedReload() {
    clearTimeout(debounce);
    debounce = setTimeout(reloadWithFilters, 400);
  }

  if (searchBox) searchBox.addEventListener("input", debouncedReload);
  if (minBox) minBox.addEventListener("input", debouncedReload);
  if (maxBox) maxBox.addEventListener("input", debouncedReload);
  if (sortBox) sortBox.addEventListener("change", reloadWithFilters);
  if (stockBox) stockBox.addEventListener("change", reloadWithFilters);

  if (resetBtn) {
    resetBtn.addEventListener("click", function () {
      if (searchBox) searchBox.value = "";
      if (minBox) minBox.value = "";
      if (maxBox) maxBox.value = "";
      if (sortBox) sortBox.value = "new";
      if (stockBox) stockBox.checked = true;
      reloadWithFilters();
    });
  }

  collectFilters();
  load(false);
})();