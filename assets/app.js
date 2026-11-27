// Меню на узких экранах, копирование реквизитов, год в подвале.
// Без библиотек: всё, что нужно визитке, — полсотни строк.

(function () {
  "use strict";

  /* ---------- Мобильное меню ---------- */

  const toggle = document.querySelector(".nav-toggle");
  const nav = document.getElementById("nav");

  if (toggle && nav) {
    const setOpen = (open) => {
      toggle.setAttribute("aria-expanded", String(open));
      nav.classList.toggle("is-open", open);
    };

    toggle.addEventListener("click", () => {
      setOpen(toggle.getAttribute("aria-expanded") !== "true");
    });

    nav.addEventListener("click", (event) => {
      if (event.target.closest("a")) setOpen(false);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") setOpen(false);
    });

    window.addEventListener("resize", () => {
      if (window.innerWidth > 860) setOpen(false);
    });
  }

  /* ---------- Копирование реквизитов ---------- */

  // navigator.clipboard работает на https и localhost, поэтому нужен запасной путь
  function fallbackCopy(text) {
    const area = document.createElement("textarea");
    area.value = text;
    area.setAttribute("readonly", "");
    area.style.position = "fixed";
    area.style.top = "-1000px";
    document.body.appendChild(area);
    area.select();

    let ok = false;
    try {
      ok = document.execCommand("copy");
    } catch (error) {
      ok = false;
    }
    document.body.removeChild(area);
    return ok;
  }

  function flash(button) {
    // У кнопки-иконки нет текста: показываем состояние цветом и подписью
    const icon = button.querySelector("svg");

    if (icon) {
      const label = button.getAttribute("aria-label");
      const title = button.getAttribute("title");
      button.classList.add("is-done");
      button.setAttribute("aria-label", "Номер скопирован");
      if (title !== null) button.setAttribute("title", "Скопировано");

      window.setTimeout(() => {
        button.classList.remove("is-done");
        if (label !== null) button.setAttribute("aria-label", label);
        if (title !== null) button.setAttribute("title", title);
      }, 1600);
      return;
    }

    const original = button.textContent;
    button.textContent = "скопировано";
    button.classList.add("is-done");
    window.setTimeout(() => {
      button.textContent = original;
      button.classList.remove("is-done");
    }, 1600);
  }

  document.querySelectorAll(".copy").forEach((button) => {
    button.addEventListener("click", async () => {
      const text = button.dataset.copy || "";
      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
        flash(button);
      } catch (error) {
        if (fallbackCopy(text)) flash(button);
      }
    });
  });

  /* ---------- Год в подвале ---------- */

  const year = document.getElementById("year");
  if (year) year.textContent = String(new Date().getFullYear());
})();

// Логотипы партнёров: если файл не загрузился, показываем название текстом
(function () {
  "use strict";
  document.querySelectorAll(".partner").forEach((item) => {
    const img = item.querySelector("img");
    if (!img) return;
    const apply = (ok) => item.classList.add(ok ? "has-logo" : "no-logo");
    if (img.complete) apply(img.naturalWidth > 0);
    img.addEventListener("load", () => apply(true));
    img.addEventListener("error", () => apply(false));
  });
})();
// Новостная лента: данные собирает scripts/update-news.mjs,
// страница только показывает готовый data/news.json
(function () {
  "use strict";

  const list = document.getElementById("news-list");
  const section = document.getElementById("news");
  if (!list || !section) return;

  const MONTHS = ["янв", "фев", "мар", "апр", "мая", "июн", "июл", "авг", "сен", "окт", "ноя", "дек"];

  function formatDate(iso) {
    const parts = String(iso).split("-");
    if (parts.length !== 3) return "";
    const day = Number(parts[2]);
    const month = MONTHS[Number(parts[1]) - 1] || "";
    return day + " " + month;
  }

  function render(items) {
    list.textContent = "";
    items.slice(0, 6).forEach(function (item) {
      const li = document.createElement("li");
      li.className = "news-item" + (item.manual ? " news-item--own" : "");

      const meta = document.createElement("p");
      meta.className = "news-item__meta";
      meta.textContent = formatDate(item.date) + " · " + item.source;

      const title = document.createElement(item.url ? "a" : "span");
      title.className = "news-item__title";
      title.textContent = item.title;
      if (item.url) {
        title.href = item.url;
        title.target = "_blank";
        title.rel = "noopener";
      }

      li.appendChild(meta);
      li.appendChild(title);
      list.appendChild(li);
    });
  }

  fetch("data/news.json?v=" + Date.now())
    .then(function (response) {
      if (!response.ok) throw new Error("нет файла новостей");
      return response.json();
    })
    .then(function (data) {
      const items = (data && data.items) || [];
      if (!items.length) throw new Error("пусто");
      render(items);
    })
    .catch(function () {
      // Нечего показать — убираем блок целиком, чтобы не висела заглушка
      section.remove();
    });
})();