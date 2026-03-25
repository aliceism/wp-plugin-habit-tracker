(() => {
  const OPEN_ATTR = "data-ht-open-modal";
  const MODAL_ATTR = "data-ht-modal";
  const CLOSE_ATTR = "data-ht-close-modal";
  const HEADER_CLASS = "habit-tracker-page-header";
  const DASHBOARD_HEADER_CLASS = "habit-tracker-dashboard-header";
  const DASHBOARD_TOGGLE_FORM_SELECTOR = ".habit-tracker-month-toggle-form";
  const APP_NAV_LINK_SELECTOR = ".app-nav a[href]";
  const PAGE_LOADING_CLASS = "habit-tracker-page-loading";
  const HABITS_STACK_SELECTOR = ".habit-tracker-block--stack";
  const HABITS_SHARED_SELECTOR = ".habit-tracker-block--shared";

  let isSpaNavigationInProgress = false;

  function hasHabitsUi() {
    return Boolean(
      document.querySelector(
        ".habit-tracker-habits, .habit-tracker-block, .habit-tracker-progress, .habit-tracker-progress-metrics, .habit-tracker-progress-grid, [data-ht-open-modal], [data-ht-modal]"
      )
    );
  }

  function hasDashboardUi() {
    return Boolean(
      document.querySelector(
        ".habit-tracker-dashboard, .habit-tracker-dashboard-metrics, .habit-tracker-dashboard-panels"
      )
    );
  }

  function markHabitsPageHeader() {
    const pageHeader = document.querySelector(".app-page-header");

    if (pageHeader) {
      pageHeader.classList.toggle(HEADER_CLASS, hasHabitsUi());
    }
  }

  function markDashboardPageHeader() {
    const pageHeader = document.querySelector(".app-page-header");

    if (pageHeader) {
      pageHeader.classList.toggle(DASHBOARD_HEADER_CLASS, hasDashboardUi());
    }
  }

  function isEligibleAppNavLink(event, link) {
    if (!(link instanceof HTMLAnchorElement)) {
      return false;
    }

    if (event.defaultPrevented || event.button !== 0) {
      return false;
    }

    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return false;
    }

    const target = (link.getAttribute("target") || "").trim();

    if (target !== "" && target !== "_self") {
      return false;
    }

    if (link.hasAttribute("download")) {
      return false;
    }

    const href = link.getAttribute("href") || "";

    if (href === "" || href.startsWith("#")) {
      return false;
    }

    let url;

    try {
      url = new URL(href, window.location.href);
    } catch (error) {
      return false;
    }

    if (url.origin !== window.location.origin) {
      return false;
    }

    if (url.hash !== "") {
      return false;
    }

    if (url.searchParams.get("action") === "logout") {
      return false;
    }

    if (
      url.pathname === window.location.pathname &&
      url.search === window.location.search
    ) {
      return false;
    }

    return true;
  }

  function syncAppNavigation(nextDocument) {
    const currentAppNav = document.querySelector(".app-nav");
    const nextAppNav = nextDocument.querySelector(".app-nav");

    if (!currentAppNav || !nextAppNav) {
      return;
    }

    currentAppNav.className = nextAppNav.className;
    currentAppNav.innerHTML = nextAppNav.innerHTML;

    const nextAria = nextAppNav.getAttribute("aria-label");

    if (nextAria) {
      currentAppNav.setAttribute("aria-label", nextAria);
    }
  }

  function applyFetchedPage(nextDocument) {
    const nextMain = nextDocument.querySelector("#primary");
    const currentMain = document.querySelector("#primary");

    if (!nextMain || !currentMain) {
      return false;
    }

    currentMain.innerHTML = nextMain.innerHTML;

    if (nextDocument.body && typeof nextDocument.body.className === "string") {
      document.body.className = nextDocument.body.className;
    }

    if (typeof nextDocument.title === "string" && nextDocument.title !== "") {
      document.title = nextDocument.title;
    }

    syncAppNavigation(nextDocument);
    closeAllModals();
    markHabitsPageHeader();
    markDashboardPageHeader();

    return true;
  }

  async function navigateAppWithoutReload(url, pushHistory) {
    if (isSpaNavigationInProgress) {
      return;
    }

    isSpaNavigationInProgress = true;
    document.body.classList.add(PAGE_LOADING_CLASS);

    try {
      const response = await fetch(url, {
        method: "GET",
        credentials: "same-origin",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const html = await response.text();

      if (!response.ok || typeof html !== "string" || html === "") {
        throw new Error("habit-tracker-spa-fetch-failed");
      }

      const parsed = new DOMParser().parseFromString(html, "text/html");
      const applied = applyFetchedPage(parsed);

      if (!applied) {
        throw new Error("habit-tracker-spa-apply-failed");
      }

      const finalUrl =
        typeof response.url === "string" && response.url !== ""
          ? response.url
          : url;

      if (pushHistory) {
        window.history.pushState({ habitTrackerSpa: true }, "", finalUrl);
      }

      window.scrollTo({ top: 0, left: 0, behavior: "auto" });
    } catch (error) {
      window.location.href = url;
    } finally {
      isSpaNavigationInProgress = false;
      document.body.classList.remove(PAGE_LOADING_CLASS);
    }
  }

  function initAppNavigation() {
    document.addEventListener("click", (event) => {
      const link = event.target.closest(APP_NAV_LINK_SELECTOR);

      if (!isEligibleAppNavLink(event, link)) {
        return;
      }

      event.preventDefault();
      navigateAppWithoutReload(link.href, true);
    });

    window.addEventListener("popstate", () => {
      if (isSpaNavigationInProgress) {
        return;
      }

      navigateAppWithoutReload(window.location.href, false);
    });
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

  function getHabitsConfig() {
    if (
      typeof window.habitTrackerHabits !== "object" ||
      window.habitTrackerHabits === null
    ) {
      return null;
    }

    return window.habitTrackerHabits;
  }

  function getHabitsActionSet(config) {
    const actions = new Set();

    if (
      config &&
      typeof config.addSharedAction === "string" &&
      config.addSharedAction !== ""
    ) {
      actions.add(config.addSharedAction);
    }

    if (
      config &&
      typeof config.addCustomAction === "string" &&
      config.addCustomAction !== ""
    ) {
      actions.add(config.addCustomAction);
    }

    if (
      config &&
      typeof config.removeAction === "string" &&
      config.removeAction !== ""
    ) {
      actions.add(config.removeAction);
    }

    return actions;
  }

  function getFormActionName(form) {
    if (!(form instanceof HTMLFormElement)) {
      return "";
    }

    const actionInput = form.querySelector('input[name="action"]');

    if (!(actionInput instanceof HTMLInputElement)) {
      return "";
    }

    return (actionInput.value || "").trim();
  }

  function replaceHabitsFragment(selector, html) {
    if (typeof html !== "string" || html.trim() === "") {
      return;
    }

    const current = document.querySelector(selector);

    if (!current) {
      return;
    }

    current.outerHTML = html;
  }

  function upsertHabitsNotice(payload) {
    const html =
      payload && typeof payload.notice_html === "string"
        ? payload.notice_html.trim()
        : "";

    if (html === "") {
      return;
    }

    const existingNotice = document.querySelector(".habit-tracker-notice");

    if (existingNotice) {
      existingNotice.outerHTML = html;
      return;
    }

    const habitsRoot = document.querySelector(".habit-tracker-habits");

    if (habitsRoot) {
      habitsRoot.insertAdjacentHTML("afterbegin", html);
      return;
    }

    const appGrid = document.querySelector(".app-grid");

    if (appGrid) {
      appGrid.insertAdjacentHTML("beforebegin", html);
      return;
    }

    const stack = document.querySelector(HABITS_STACK_SELECTOR);

    if (stack) {
      stack.insertAdjacentHTML("beforebegin", html);
      return;
    }

    const primary = document.querySelector("#primary");

    if (primary) {
      primary.insertAdjacentHTML("afterbegin", html);
    }
  }

  function applyHabitsMutationResponse(payload) {
    if (!payload || typeof payload !== "object") {
      return;
    }

    replaceHabitsFragment(HABITS_STACK_SELECTOR, payload.stack_html || "");
    replaceHabitsFragment(HABITS_SHARED_SELECTOR, payload.shared_html || "");
    upsertHabitsNotice(payload);

    if (payload.close_modals === true) {
      closeAllModals();
    }
  }

  async function submitHabitsMutationForm(form) {
    const config = getHabitsConfig();

    if (
      !config ||
      typeof config.ajaxUrl !== "string" ||
      config.ajaxUrl === ""
    ) {
      form.submit();
      return;
    }

    const button = form.querySelector('button[type="submit"]');

    if (button instanceof HTMLButtonElement) {
      button.disabled = true;
      button.classList.add("is-loading");
    }

    try {
      const formData = new FormData(form);
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

        if (responseData && typeof responseData === "object") {
          applyHabitsMutationResponse(responseData);
          return;
        }

        throw new Error("habit-tracker-habits-ajax-failed");
      }

      applyHabitsMutationResponse(responseData);
    } catch (error) {
      form.submit();
    } finally {
      if (button instanceof HTMLButtonElement) {
        button.disabled = false;
        button.classList.remove("is-loading");
      }
    }
  }

  function initHabitsMutationsAjax() {
    const config = getHabitsConfig();
    const actionSet = getHabitsActionSet(config);

    if (
      !config ||
      typeof config.ajaxUrl !== "string" ||
      config.ajaxUrl === "" ||
      actionSet.size === 0
    ) {
      return;
    }

    document.addEventListener("submit", (event) => {
      const target = event.target;

      if (!(target instanceof HTMLFormElement)) {
        return;
      }

      const actionName = getFormActionName(target);

      if (!actionSet.has(actionName)) {
        return;
      }

      event.preventDefault();

      if (target.dataset.htSubmitting === "1") {
        return;
      }

      target.dataset.htSubmitting = "1";

      submitHabitsMutationForm(target).finally(() => {
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
  initAppNavigation();
  initDashboardCheckinAjax();
  initHabitsMutationsAjax();

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
