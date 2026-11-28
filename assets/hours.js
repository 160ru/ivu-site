/**
 * Нерабочее время: звонок не начинается сразу, а предлагает чат.
 *
 * Работаем Пн–Пт, 11:00–18:00 по Москве. Если сейчас не рабочее время,
 * клик по номеру телефона перехватывается и открывается окно с выбором:
 * написать в чат, позвонить всё равно или посмотреть часы работы.
 */
(function () {
  "use strict";

  var CHAT = "https://yandex.ru/maps/org/1043257440/";
  var OPEN_HOUR = 11;
  var CLOSE_HOUR = 18;
  var WORKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri"];
  // какой день будет следующим рабочим после закрытия
  var NEXT_WORKDAY = {
    Mon: "во вторник",
    Tue: "в среду",
    Wed: "в четверг",
    Thu: "в пятницу",
    Fri: "в понедельник",
    Sat: "в понедельник",
    Sun: "в понедельник"
  };

  function msk() {
    var parts = new Intl.DateTimeFormat("en-GB", {
      timeZone: "Europe/Moscow",
      weekday: "short",
      hour: "2-digit",
      minute: "2-digit",
      hour12: false
    }).formatToParts(new Date());

    var values = {};
    parts.forEach(function (part) {
      values[part.type] = part.value;
    });

    return {
      weekday: values.weekday,
      hour: parseInt(values.hour, 10),
      minute: parseInt(values.minute, 10),
      time: values.hour + ":" + values.minute
    };
  }

  function isOpen(now) {
    return WORKDAYS.indexOf(now.weekday) !== -1 &&
      now.hour >= OPEN_HOUR && now.hour < CLOSE_HOUR;
  }

  // короткая форма для пометки в карточке: «завтра», «в пн», «сегодня»
  function shortOpening(now) {
    var isWorkday = WORKDAYS.indexOf(now.weekday) !== -1;
    if (isWorkday && now.hour < OPEN_HOUR) {
      return "сегодня";
    }
    var names = { Mon: "во вт", Tue: "в ср", Wed: "в чт", Thu: "в пт", Fri: "в пн", Sat: "в пн", Sun: "в пн" };
    return names[now.weekday] || "в пн";
  }
  function nextOpening(now) {
    var isWorkday = WORKDAYS.indexOf(now.weekday) !== -1;

    // утро рабочего дня — откроемся сегодня
    if (isWorkday && now.hour < OPEN_HOUR) {
      return "сегодня в " + OPEN_HOUR + ":00";
    }
    // вечер рабочего дня, выходные — в следующий рабочий день
    return (NEXT_WORKDAY[now.weekday] || "в понедельник") + " в " + OPEN_HOUR + ":00";
  }

  // ---------- окно ----------
  var modal = null;
  var openingText = "";

  function buildModal() {
    var wrap = document.createElement("div");
    wrap.className = "modal hours-modal";
    wrap.setAttribute("role", "dialog");
    wrap.setAttribute("aria-modal", "true");
    wrap.setAttribute("aria-labelledby", "hours-modal-title");

    var panel = document.createElement("div");
    panel.className = "modal__panel hours-modal__panel";

    var close = document.createElement("button");
    close.type = "button";
    close.className = "modal__close";
    close.setAttribute("aria-label", "Закрыть");
    close.textContent = "×";
    close.addEventListener("click", hide);
    panel.appendChild(close);

    var title = document.createElement("h2");
    title.className = "modal__title";
    title.id = "hours-modal-title";
    title.textContent = "Сейчас нерабочее время";
    panel.appendChild(title);

    var text = document.createElement("p");
    text.className = "hours-modal__text";
    panel.appendChild(text);

    var hours = document.createElement("p");
    hours.className = "hours-modal__hours";
    hours.textContent = "Работаем по будням с 11:00 до 18:00, суббота и воскресенье — выходные.";
    panel.appendChild(hours);

    var note = document.createElement("p");
    note.className = "hours-modal__note";
    panel.appendChild(note);

    var actions = document.createElement("div");
    actions.className = "hours-modal__actions";

    var chat = document.createElement("a");
    chat.className = "btn btn--sm";
    chat.href = CHAT;
    chat.target = "_blank";
    chat.rel = "noopener";
    chat.textContent = "Написать в Яндекс.Картах";
    actions.appendChild(chat);

    var call = document.createElement("a");
    call.className = "btn btn--sm btn--ghost";
    call.href = "tel:+74932353070";
    call.setAttribute("data-hours-bypass", "1");
    call.textContent = "Всё равно позвонить";
    actions.appendChild(call);

    panel.appendChild(actions);

    // ---- заявка на обратный звонок ----
    var form = document.createElement("form");
    form.className = "hours-form";
    form.noValidate = true;

    var hp = document.createElement("input");
    hp.type = "text";
    hp.name = "hp";
    hp.className = "hours-form__hp";
    hp.tabIndex = -1;
    hp.autocomplete = "off";
    form.appendChild(hp);

    var formTitle = document.createElement("p");
    formTitle.className = "hours-form__title";
    formTitle.textContent = "Или оставьте номер — перезвоним первым делом";
    form.appendChild(formTitle);

    var row = document.createElement("div");
    row.className = "hours-form__row";

    var input = document.createElement("input");
    input.type = "tel";
    input.name = "phone";
    input.className = "hours-form__input";
    input.placeholder = "+7 900 000-00-00";
    input.autocomplete = "tel";
    input.inputMode = "tel";
    input.required = true;
    input.setAttribute("aria-label", "Ваш телефон");
    row.appendChild(input);

    var submit = document.createElement("button");
    submit.type = "submit";
    submit.className = "btn btn--sm btn--ghost";
    submit.textContent = "Перезвоните мне";
    row.appendChild(submit);

    form.appendChild(row);

    var formStatus = document.createElement("p");
    formStatus.className = "hours-form__status";
    formStatus.setAttribute("role", "status");
    form.appendChild(formStatus);

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var phone = input.value.trim();
      var digits = phone.replace(/\D+/g, "");

      formStatus.className = "hours-form__status";
      if (digits.length < 10) {
        formStatus.textContent = "Проверьте номер — нужно не меньше 10 цифр";
        formStatus.classList.add("hours-form__status--err");
        input.focus();
        return;
      }

      submit.disabled = true;
      formStatus.textContent = "Отправляем…";

      fetch("/callback-api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ phone: phone, hp: hp.value })
      })
        .then(function (response) { return response.json(); })
        .then(function (result) {
          if (result.error) throw new Error(result.error);
          form.reset();
          formStatus.textContent = "Спасибо! Перезвоним в рабочее время — " + openingText + ".";
          formStatus.classList.add("hours-form__status--ok");
          submit.disabled = true;
        })
        .catch(function (error) {
          formStatus.textContent = error.message || "Не получилось отправить. Позвоните сами.";
          formStatus.classList.add("hours-form__status--err");
          submit.disabled = false;
        });
    });

    panel.appendChild(form);

    wrap.appendChild(panel);
    wrap.addEventListener("click", function (event) {
      if (event.target === wrap) {
        hide();
      }
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        hide();
      }
    });

    document.body.appendChild(wrap);
    return { wrap: wrap, text: text, note: note, previous: null };
  }

  function show(phoneHref) {
    if (!modal) {
      modal = buildModal();
    }
    var now = msk();
    var when = nextOpening(now);
    openingText = when;
    modal.text.textContent = "Сейчас " + now.time + " — офис закрыт. Откроемся " + when + ".";
    modal.note.textContent = "В чате ответим " + when + ". Если вопрос срочный — " +
      "оставьте в сообщении номер телефона, перезвоним первым делом.";
    if (phoneHref) {
      modal.previous = phoneHref;
    }
    modal.wrap.classList.add("is-open");
  }

  function hide() {
    if (modal) {
      modal.wrap.classList.remove("is-open");
    }
  }

  // ---------- перехват звонков ----------
  document.addEventListener("click", function (event) {
    var link = event.target.closest ? event.target.closest('a[href^="tel:"]') : null;
    if (!link) {
      return;
    }
    if (link.getAttribute("data-hours-bypass") === "1") {
      return;
    }

    var now = msk();
    if (isOpen(now)) {
      return;
    }

    event.preventDefault();
    show(link.getAttribute("href"));
  });

  // ---------- пометка на странице ----------
  function markClosed(now) {
    var badge = document.createElement("span");
    badge.className = "hours-badge";
    // пишем не «закрыто», а когда откроемся — чтобы не читалось как «мы закрылись»
    var prefix = WORKDAYS.indexOf(now.weekday) === -1 ? "выходной" : "закрыто";
    badge.textContent = prefix + ", откроемся " + shortOpening(now);

    var label = document.querySelector(".hero-card__label");
    if (label && !label.querySelector(".hours-badge")) {
      label.appendChild(badge);
    }
  }

  function init() {
    var now = msk();
    if (!isOpen(now)) {
      markClosed(now);
    }
    // статус обновим, если человек сидит на странице до 18:00
    window.setInterval(function () {
      var current = msk();
      if (!isOpen(current) && !document.querySelector(".hours-badge")) {
        markClosed(current);
      }
    }, 60000);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
