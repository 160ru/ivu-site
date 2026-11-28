/**
 * Заказ товара: окно с телефоном.
 *
 * Вызывается из карточек и со страницы товара:
 *   window.ivuOrder("Netcraze Giga (NC-1013)", 18880)
 *
 * Заявка уходит в /callback-api.php: попадает в базу и на почту.
 */
(function () {
  "use strict";

  var CHAT = "https://yandex.ru/maps/org/1043257440/";
  var PHONE = "+7 4932 353070";
  var modal = null;
  var productName = "";

  function money(value) {
    if (!value) {
      return "";
    }
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " ₽";
  }

  function build() {
    var wrap = document.createElement("div");
    wrap.className = "modal order-modal";
    wrap.setAttribute("role", "dialog");
    wrap.setAttribute("aria-modal", "true");
    wrap.setAttribute("aria-labelledby", "order-title");

    var panel = document.createElement("div");
    panel.className = "modal__panel order-modal__panel";

    var close = document.createElement("button");
    close.type = "button";
    close.className = "modal__close";
    close.setAttribute("aria-label", "Закрыть");
    close.textContent = "×";
    close.addEventListener("click", hide);
    panel.appendChild(close);

    var title = document.createElement("h2");
    title.className = "modal__title";
    title.id = "order-title";
    title.textContent = "Заказать";
    panel.appendChild(title);

    var about = document.createElement("p");
    about.className = "order-modal__about";
    panel.appendChild(about);

    var form = document.createElement("form");
    form.className = "order-form";
    form.noValidate = true;

    var hp = document.createElement("input");
    hp.type = "text";
    hp.name = "hp";
    hp.className = "hours-form__hp";
    hp.tabIndex = -1;
    hp.autocomplete = "off";
    form.appendChild(hp);

    var phoneLabel = document.createElement("label");
    phoneLabel.className = "order-form__label";
    var phoneText = document.createElement("span");
    phoneText.textContent = "Ваш телефон";
    phoneLabel.appendChild(phoneText);

    var phoneInput = document.createElement("input");
    phoneInput.type = "tel";
    phoneInput.name = "phone";
    phoneInput.className = "hours-form__input";
    phoneInput.placeholder = "+7 900 000-00-00";
    phoneInput.autocomplete = "tel";
    phoneInput.inputMode = "tel";
    phoneInput.required = true;
    phoneLabel.appendChild(phoneInput);
    form.appendChild(phoneLabel);

    var emailLabel = document.createElement("label");
    emailLabel.className = "order-form__label";
    var emailText = document.createElement("span");
    emailText.textContent = "Почта для подтверждения (необязательно)";
    emailLabel.appendChild(emailText);

    var emailInput = document.createElement("input");
    emailInput.type = "email";
    emailInput.name = "email";
    emailInput.className = "hours-form__input";
    emailInput.placeholder = "you@example.ru";
    emailInput.autocomplete = "email";
    emailLabel.appendChild(emailInput);
    form.appendChild(emailLabel);

    var hint = document.createElement("p");
    hint.className = "order-form__hint";
    hint.textContent = "Перезвоним, подтвердим наличие и срок. Работаем Пн–Пт с 11:00 до 18:00.";
    form.appendChild(hint);

    var submit = document.createElement("button");
    submit.type = "submit";
    submit.className = "btn";
    submit.textContent = "Жду звонка";
    form.appendChild(submit);

    var status = document.createElement("p");
    status.className = "order-form__status";
    status.setAttribute("role", "status");
    form.appendChild(status);

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var phone = phoneInput.value.trim();
      var digits = phone.replace(/\D+/g, "");

      status.className = "order-form__status";
      if (digits.length < 10) {
        status.textContent = "Проверьте номер — нужно не меньше 10 цифр";
        status.classList.add("order-form__status--err");
        phoneInput.focus();
        return;
      }

      submit.disabled = true;
      status.textContent = "Отправляем…";

      fetch("/callback-api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          phone: phone,
          email: emailInput.value.trim(),
          note: productName ? "Заказ: " + productName : "Заказ с сайта",
          hp: hp.value
        })
      })
        .then(function (response) { return response.json(); })
        .then(function (result) {
          if (result.error) throw new Error(result.error);
          var hadEmail = emailInput.value.trim() !== "";
          form.reset();
          status.textContent = hadEmail
            ? "Заявка принята. Перезвоним в рабочее время, подтверждение отправили на почту."
            : "Заявка принята. Перезвоним в рабочее время.";
          status.classList.add("order-form__status--ok");
          submit.disabled = true;
        })
        .catch(function (error) {
          status.textContent = error.message || "Не получилось отправить. Позвоните сами.";
          status.classList.add("order-form__status--err");
          submit.disabled = false;
        });
    });

    panel.appendChild(form);

    var alt = document.createElement("p");
    alt.className = "order-modal__alt";
    var callLink = document.createElement("a");
    callLink.className = "product__link";
    callLink.href = "tel:+74932353070";
    callLink.textContent = "Или позвоните: " + PHONE;
    alt.appendChild(callLink);
    panel.appendChild(alt);

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
    return { wrap: wrap, about: about, input: phoneInput };
  }

  function show(name, price) {
    if (!modal) {
      modal = build();
    }
    productName = name || "";
    modal.about.textContent = productName + (price ? " — " + money(price) : "");
    modal.wrap.classList.add("is-open");
    window.setTimeout(function () {
      if (modal.input) {
        modal.input.focus();
      }
    }, 60);
  }

  function hide() {
    if (modal) {
      modal.wrap.classList.remove("is-open");
    }
  }

  window.ivuOrder = show;
})();
