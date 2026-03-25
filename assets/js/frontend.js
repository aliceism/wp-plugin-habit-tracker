(() => {
  const OPEN_ATTR = "data-ht-open-modal";
  const MODAL_ATTR = "data-ht-modal";
  const CLOSE_ATTR = "data-ht-close-modal";
  const HEADER_CLASS = "habit-tracker-page-header";
  const DASHBOARD_HEADER_CLASS = "habit-tracker-dashboard-header";
  const DASHBOARD_TOGGLE_FORM_SELECTOR = ".habit-tracker-month-toggle-form";

  function markHabitsPageHeader() {
    const hasHabitsUi = document.querySelector(
      ".habit-tracker-habits, .habit-tracker-block, [data-ht-open-modal], [data-ht-modal]"
    );

    if (!hasHabitsUi) return;

    const pageHeader = document.querySelector(".app-page-header");

    if (pageHeader) {
      pageHeader.classList.add(HEADER_CLASS);
    }
  }

  function markDashboardPageHeader() {
    const hasDashboardUi = document.querySelector(
      ".habit-tracker-dashboard, .habit-tracker-dashboard-metrics, .habit-tracker-dashboard-panels"
    );

    if (!hasDashboardUi) return;

    const pageHeader = document.querySelector(".app-page-header");

    if (pageHeader) {
      pageHeader.classList.add(DASHBOARD_HEADER_CLASS);
    }
  }

  function getDashboardConfig() {
    if (
      typeof window.habitTrackerDashboard !== "object" ||
      window.habitTrackerDashboard === null
    ) {
      return null;
    }

    return window.habitTrackerDashboard;
  }

  function upsertDashboardNotice(notice) {
    if (
      typeof notice !== "object" ||
      notice === null ||
      typeof notice.text !== "string" ||
      notice.text.trim() === ""
    ) {
      return;
    }

    const metricsSection = document.querySelector(".habit-tracker-dashboard-metrics");
    const panelsSection = document.querySelector(".habit-tracker-dashboard-panels");
    const dashboardRoot =
      document.querySelector(".habit-tracker-dashboard") ||
      (metricsSection ? metricsSection.parentElement : null) ||
      (panelsSection ? panelsSection.parentElement : null);

    if (!dashboardRoot) {
      return;
    }

    const existingNotice = dashboardRoot.querySelector(".habit-tracker-notice");
    const noticeElement = existingNotice || document.createElement("div");
    const noticeClass =
      typeof notice.class === "string" && notice.class !== ""
        ? notice.class
        : "habit-tracker-notice habit-tracker-notice--info";
    const message = document.createElement("p");

    message.textContent = notice.text;
    noticeElement.className = noticeClass;
    noticeElement.innerHTML = "";
    noticeElement.appendChild(message);

    if (!existingNotice) {
      if (
        metricsSection &&
        metricsSection.parentElement &&
        metricsSection.parentElement === dashboardRoot
      ) {
        dashboardRoot.insertBefore(noticeElement, metricsSection);
      } else if (
        panelsSection &&
        panelsSection.parentElement &&
        panelsSection.parentElement === dashboardRoot
      ) {
        dashboardRoot.insertBefore(noticeElement, panelsSection);
      } else {
        dashboardRoot.prepend(noticeElement);
      }
    }
  }

  function replaceDashboardFragment(selector, html) {
    if (typeof html !== "string" || html === "") {
      return;
    }

    const container = document.querySelector(selector);

    if (!container) {
      return;
    }

    container.innerHTML = html;
  }

  function updateDashboardRow(form, rowData, checked) {
    const row = form.closest(".habit-tracker-month-row");
    const button = form.querySelector(".habit-tracker-month-cell--action");

    if (button) {
      button.classList.toggle("is-filled", Boolean(checked));
      button.textContent = checked ? "\u2713" : "";
      button.setAttribute("aria-pressed", checked ? "true" : "false");
    }

    if (!row || typeof rowData !== "object" || rowData === null) {
      return;
    }

    const progressFill = row.querySelector(".habit-tracker-progress-bar > span");
    const progressText = row.querySelector(".habit-tracker-month-progress-text");

    if (progressFill && typeof rowData.progress_percent === "number") {
      progressFill.style.width = `${rowData.progress_percent}%`;
    }

    if (progressText && typeof rowData.progress_text === "string") {
      progressText.textContent = rowData.progress_text;
    }
  }

  async function submitDashboardCheckinForm(form) {
    const config = getDashboardConfig();

    if (
      !config ||
      typeof config.ajaxUrl !== "string" ||
      config.ajaxUrl === ""
    ) {
      form.submit();
      return;
    }

    const button = form.querySelector(".habit-tracker-month-cell--action");

    if (button) {
      button.disabled = true;
      button.classList.add("is-loading");
    }

    try {
      const formData = new FormData(form);

      if (
        !formData.get("action") &&
        typeof config.toggleAction === "string" &&
        config.toggleAction !== ""
      ) {
        formData.set("action", config.toggleAction);
      }

      const response = await fetch(config.ajaxUrl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const payload = await response.json();
      const responseData =
        payload && typeof payload === "object" ? payload.data || {} : {};

      if (!response.ok || !payload || payload.success !== true) {
        if (
          responseData &&
          typeof responseData.login_url === "string" &&
          responseData.login_url !== ""
        ) {
          window.location.href = responseData.login_url;
          return;
        }

        if (responseData && responseData.notice) {
          upsertDashboardNotice(responseData.notice);
          return;
        }

        throw new Error("habit-tracker-checkin-ajax-failed");
      }

      updateDashboardRow(form, responseData.row || null, Boolean(responseData.checked));
      replaceDashboardFragment(
        ".habit-tracker-dashboard-metrics",
        responseData.metrics_html
      );
      replaceDashboardFragment(".habit-tracker-month-side", responseData.side_html);

      if (responseData.notice) {
        upsertDashboardNotice(responseData.notice);
      }
    } catch (error) {
      form.submit();
    } finally {
      if (button) {
        button.disabled = false;
        button.classList.remove("is-loading");
      }
    }
  }

  function initDashboardCheckinAjax() {
    const config = getDashboardConfig();

    if (
      !config ||
      typeof config.ajaxUrl !== "string" ||
      config.ajaxUrl === ""
    ) {
      return;
    }

    document.addEventListener("submit", (event) => {
      const target = event.target;

      if (
        !(target instanceof HTMLFormElement) ||
        !target.matches(DASHBOARD_TOGGLE_FORM_SELECTOR)
      ) {
        return;
      }

      event.preventDefault();

      if (target.dataset.htSubmitting === "1") {
        return;
      }

      target.dataset.htSubmitting = "1";

      submitDashboardCheckinForm(target).finally(() => {
        delete target.dataset.htSubmitting;
      });
    });
  }

  function getModalById(modalId) {
    return document.querySelector(`[${MODAL_ATTR}="${modalId}"]`);
  }

  function openModal(modal) {
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add("habit-tracker-modal-open");
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.hidden = true;

    const anyOpen = document.querySelector(
      `[${MODAL_ATTR}]:not([hidden])`
    );

    if (!anyOpen) {
      document.body.classList.remove("habit-tracker-modal-open");
    }
  }

  function closeAllModals() {
    document.querySelectorAll(`[${MODAL_ATTR}]`).forEach((modal) => {
      modal.hidden = true;
    });
    document.body.classList.remove("habit-tracker-modal-open");
  }

  markHabitsPageHeader();
  markDashboardPageHeader();
  initDashboardCheckinAjax();

  document.addEventListener("click", (event) => {
    const openTrigger = event.target.closest(`[${OPEN_ATTR}]`);
    if (openTrigger) {
      const modalId = openTrigger.getAttribute(OPEN_ATTR) || "";
      if (modalId !== "") {
        openModal(getModalById(modalId));
      }
      return;
    }

    const closeTrigger = event.target.closest(`[${CLOSE_ATTR}]`);
    if (closeTrigger) {
      closeModal(closeTrigger.closest(`[${MODAL_ATTR}]`));
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeAllModals();
    }
  });
})();
