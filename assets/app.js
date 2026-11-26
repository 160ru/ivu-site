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
