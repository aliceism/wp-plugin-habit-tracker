(() => {
  const OPEN_ATTR = "data-ht-open-modal";
  const MODAL_ATTR = "data-ht-modal";
  const CLOSE_ATTR = "data-ht-close-modal";
  const HEADER_CLASS = "habit-tracker-page-header";
  const DASHBOARD_HEADER_CLASS = "habit-tracker-dashboard-header";
  const DASHBOARD_TOGGLE_FORM_SELECTOR = ".habit-tracker-month-toggle-form";
  const DASHBOARD_TOGGLE_ACTION_FALLBACK = "habit_tracker_toggle_checkin";
  const APP_NAV_LINK_SELECTOR = ".app-nav a[href]";
  const PAGE_LOADING_CLASS = "habit-tracker-page-loading";
  const HABITS_STACK_SELECTOR = ".habit-tracker-block--stack";
  const HABITS_SHARED_SELECTOR = ".habit-tracker-block--shared";
  const STACK_CONTROL_ATTR = "data-ht-stack-control";
  const STACK_CONTROL_SEARCH = "search";
  const STACK_CONTROL_CATEGORY = "category";
  const STACK_CONTROL_SORT = "sort";
  const STACK_SORT_DEFAULT = "default";
  const STACK_SORT_NAME_ASC = "name-asc";
  const STACK_SORT_NAME_DESC = "name-desc";
  const STACK_SORT_CATEGORY = "category";
  const STACK_EMPTY_MESSAGE_CLASS = "habit-tracker-stack-tools__empty";
  const STACK_CATEGORY_ORDER = ["mind", "body", "productivity", "life", "custom"];
  const HABITS_ACTIONS_FALLBACK = new Set([
    "habit_tracker_add_shared_habit",
    "habit_tracker_add_custom_habit",
    "habit_tracker_update_custom_habit",
    "habit_tracker_update_shared_habit_frequency",
    "habit_tracker_remove_user_habit",
  ]);

  let isSpaNavigationInProgress = false;
  let isStackControlEventsBound = false;
  const habitsStackState = {
    search: "",
    category: "all",
    sort: STACK_SORT_DEFAULT,
  };

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
    initHabitsStackControls();

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

  function resolveAjaxUrl(config, form) {
    if (
      config &&
      typeof config.ajaxUrl === "string" &&
      config.ajaxUrl !== ""
    ) {
      return config.ajaxUrl;
    }

    if (typeof window.ajaxurl === "string" && window.ajaxurl !== "") {
      return window.ajaxurl;
    }

    if (!(form instanceof HTMLFormElement)) {
      return "";
    }

    const actionAttr = (form.getAttribute("action") || "").trim();

    if (actionAttr === "") {
      return "";
    }

    try {
      const actionUrl = new URL(actionAttr, window.location.href);

      if (/admin-post\.php$/i.test(actionUrl.pathname)) {
        actionUrl.pathname = actionUrl.pathname.replace(
          /admin-post\.php$/i,
          "admin-ajax.php"
        );
        actionUrl.search = "";
        actionUrl.hash = "";

        return actionUrl.toString();
      }
    } catch (error) {
      return "";
    }

    return "";
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
    const ajaxUrl = resolveAjaxUrl(config, form);

    if (ajaxUrl === "") {
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
      } else if (!formData.get("action")) {
        formData.set("action", DASHBOARD_TOGGLE_ACTION_FALLBACK);
      }

      const response = await fetch(ajaxUrl, {
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
      typeof config.updateAction === "string" &&
      config.updateAction !== ""
    ) {
      actions.add(config.updateAction);
    }

    if (
      config &&
      typeof config.updateSharedFrequencyAction === "string" &&
      config.updateSharedFrequencyAction !== ""
    ) {
      actions.add(config.updateSharedFrequencyAction);
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

  function getHabitsActionSetWithFallback() {
    const config = getHabitsConfig();
    const configuredActions = getHabitsActionSet(config);

    if (configuredActions.size > 0) {
      return configuredActions;
    }

    return HABITS_ACTIONS_FALLBACK;
  }

  function getHabitsStackControlsConfig() {
    const config = getHabitsConfig();

    if (!config || typeof config !== "object") {
      return null;
    }

    const stackControls = config.stackControls;

    if (!stackControls || typeof stackControls !== "object") {
      return null;
    }

    return stackControls;
  }

  function getHabitsStackControlLabels() {
    const config = getHabitsStackControlsConfig();
    const labels = {
      searchPlaceholder: "Search habits...",
      filterLabel: "Filter",
      filterAll: "All categories",
      sortLabel: "Sort",
      sortDefault: "Default order",
      sortNameAsc: "Name (A-Z)",
      sortNameDesc: "Name (Z-A)",
      sortCategory: "Category",
      noResults: "No habits match your filters.",
      categories: {
        mind: "Mind",
        body: "Body",
        productivity: "Productivity",
        life: "Life",
        custom: "Custom",
      },
    };

    if (!config) {
      return labels;
    }

    const scalarKeys = [
      "searchPlaceholder",
      "filterLabel",
      "filterAll",
      "sortLabel",
      "sortDefault",
      "sortNameAsc",
      "sortNameDesc",
      "sortCategory",
      "noResults",
    ];

    scalarKeys.forEach((key) => {
      if (typeof config[key] === "string" && config[key].trim() !== "") {
        labels[key] = config[key].trim();
      }
    });

    if (config.categories && typeof config.categories === "object") {
      STACK_CATEGORY_ORDER.forEach((categoryKey) => {
        const categoryLabel = config.categories[categoryKey];

        if (typeof categoryLabel === "string" && categoryLabel.trim() !== "") {
          labels.categories[categoryKey] = categoryLabel.trim();
        }
      });
    }

    return labels;
  }

  function normalizeStackText(value) {
    return String(value || "")
      .trim()
      .toLowerCase();
  }

  function getStackListItemCategory(item) {
    if (!(item instanceof HTMLElement)) {
      return "life";
    }

    for (const categoryKey of STACK_CATEGORY_ORDER) {
      if (item.classList.contains(`habit-tracker-stack-item--${categoryKey}`)) {
        return categoryKey;
      }
    }

    return "life";
  }

  function getStackListItemName(item) {
    if (!(item instanceof HTMLElement)) {
      return "";
    }

    const nameNode = item.querySelector(".habit-tracker-stack-item__name");
    const sourceText =
      nameNode && typeof nameNode.textContent === "string"
        ? nameNode.textContent
        : item.textContent || "";

    return normalizeStackText(sourceText);
  }

  function getStackListItemOriginalOrder(item, fallbackIndex) {
    if (!(item instanceof HTMLElement)) {
      return fallbackIndex;
    }

    if (typeof item.dataset.htOriginalIndex !== "string") {
      item.dataset.htOriginalIndex = String(fallbackIndex);
      return fallbackIndex;
    }

    const parsed = Number.parseInt(item.dataset.htOriginalIndex, 10);

    if (Number.isNaN(parsed)) {
      item.dataset.htOriginalIndex = String(fallbackIndex);
      return fallbackIndex;
    }

    return parsed;
  }

  function compareStackItemsByCurrentSort(left, right) {
    const sortMode = habitsStackState.sort;
    const leftName = getStackListItemName(left);
    const rightName = getStackListItemName(right);
    const nameCompare = leftName.localeCompare(rightName, undefined, {
      sensitivity: "base",
      numeric: true,
    });

    if (sortMode === STACK_SORT_NAME_ASC) {
      return nameCompare;
    }

    if (sortMode === STACK_SORT_NAME_DESC) {
      return nameCompare * -1;
    }

    if (sortMode === STACK_SORT_CATEGORY) {
      const leftCategoryIndex = STACK_CATEGORY_ORDER.indexOf(
        getStackListItemCategory(left)
      );
      const rightCategoryIndex = STACK_CATEGORY_ORDER.indexOf(
        getStackListItemCategory(right)
      );
      const normalizedLeftCategoryIndex =
        leftCategoryIndex >= 0 ? leftCategoryIndex : STACK_CATEGORY_ORDER.length;
      const normalizedRightCategoryIndex =
        rightCategoryIndex >= 0 ? rightCategoryIndex : STACK_CATEGORY_ORDER.length;

      if (normalizedLeftCategoryIndex !== normalizedRightCategoryIndex) {
        return normalizedLeftCategoryIndex - normalizedRightCategoryIndex;
      }

      if (nameCompare !== 0) {
        return nameCompare;
      }
    }

    const leftIndex = getStackListItemOriginalOrder(left, 0);
    const rightIndex = getStackListItemOriginalOrder(right, 0);

    return leftIndex - rightIndex;
  }

  function getStackListItems(stackList) {
    if (!(stackList instanceof HTMLElement)) {
      return [];
    }

    return Array.from(stackList.children).filter(
      (item) =>
        item instanceof HTMLElement &&
        item.classList.contains("habit-tracker-stack-item")
    );
  }

  function resolveStackBlockFromTarget(target) {
    if (!(target instanceof Element)) {
      return null;
    }

    const closest = target.closest(HABITS_STACK_SELECTOR);

    if (closest instanceof HTMLElement) {
      return closest;
    }

    const fallback = document.querySelector(HABITS_STACK_SELECTOR);

    return fallback instanceof HTMLElement ? fallback : null;
  }

  function syncStackControlValues(stackBlock) {
    if (!(stackBlock instanceof HTMLElement)) {
      return;
    }

    const searchInput = stackBlock.querySelector(
      `[${STACK_CONTROL_ATTR}="${STACK_CONTROL_SEARCH}"]`
    );
    const categorySelect = stackBlock.querySelector(
      `[${STACK_CONTROL_ATTR}="${STACK_CONTROL_CATEGORY}"]`
    );
    const sortSelect = stackBlock.querySelector(
      `[${STACK_CONTROL_ATTR}="${STACK_CONTROL_SORT}"]`
    );

    if (searchInput instanceof HTMLInputElement) {
      searchInput.value = habitsStackState.search;
    }

    if (categorySelect instanceof HTMLSelectElement) {
      categorySelect.value = habitsStackState.category;
    }

    if (sortSelect instanceof HTMLSelectElement) {
      sortSelect.value = habitsStackState.sort;
    }
  }

  function ensureStackControlsMarkup(stackBlock) {
    if (!(stackBlock instanceof HTMLElement)) {
      return;
    }

    const stackList = stackBlock.querySelector(".habit-tracker-habits__stack");
    const stackPanel = stackBlock.querySelector(".habit-tracker-stack-panel");
    const controlsHost =
      stackPanel instanceof HTMLElement ? stackPanel : stackBlock;

    if (!(stackList instanceof HTMLElement)) {
      const existingControls = stackBlock.querySelector(".habit-tracker-stack-tools");
      const existingEmptyState = stackBlock.querySelector(`.${STACK_EMPTY_MESSAGE_CLASS}`);

      if (existingControls) {
        existingControls.remove();
      }

      if (existingEmptyState) {
        existingEmptyState.remove();
      }

      return;
    }

    const existingControls = stackBlock.querySelector(".habit-tracker-stack-tools");

    if (!(existingControls instanceof HTMLElement)) {
      const labels = getHabitsStackControlLabels();
      const controls = document.createElement("div");
      controls.className = "habit-tracker-stack-tools";
      const search = document.createElement("input");
      search.type = "search";
      search.className = "habit-tracker-stack-tools__search";
      search.setAttribute(STACK_CONTROL_ATTR, STACK_CONTROL_SEARCH);
      search.placeholder = labels.searchPlaceholder;
      search.setAttribute("aria-label", labels.searchPlaceholder);

      const category = document.createElement("select");
      category.className = "habit-tracker-stack-tools__category";
      category.setAttribute(STACK_CONTROL_ATTR, STACK_CONTROL_CATEGORY);
      category.setAttribute("aria-label", labels.filterLabel);

      const allCategoryOption = document.createElement("option");
      allCategoryOption.value = "all";
      allCategoryOption.textContent = labels.filterAll;
      category.appendChild(allCategoryOption);

      STACK_CATEGORY_ORDER.forEach((categoryKey) => {
        const option = document.createElement("option");
        option.value = categoryKey;
        option.textContent = labels.categories[categoryKey] || categoryKey;
        category.appendChild(option);
      });

      const sort = document.createElement("select");
      sort.className = "habit-tracker-stack-tools__sort";
      sort.setAttribute(STACK_CONTROL_ATTR, STACK_CONTROL_SORT);
      sort.setAttribute("aria-label", labels.sortLabel);

      [
        [STACK_SORT_DEFAULT, labels.sortDefault],
        [STACK_SORT_NAME_ASC, labels.sortNameAsc],
        [STACK_SORT_NAME_DESC, labels.sortNameDesc],
        [STACK_SORT_CATEGORY, labels.sortCategory],
      ].forEach(([value, label]) => {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = label;
        sort.appendChild(option);
      });

      controls.appendChild(search);
      controls.appendChild(category);
      controls.appendChild(sort);

      controlsHost.insertBefore(controls, stackList);
    } else if (existingControls.parentElement !== controlsHost) {
      controlsHost.insertBefore(existingControls, stackList);
    }

    const existingEmptyState = stackBlock.querySelector(`.${STACK_EMPTY_MESSAGE_CLASS}`);

    if (!(existingEmptyState instanceof HTMLElement)) {
      const emptyState = document.createElement("p");
      emptyState.className = STACK_EMPTY_MESSAGE_CLASS;
      emptyState.hidden = true;
      stackList.insertAdjacentElement("afterend", emptyState);
    } else if (existingEmptyState.previousElementSibling !== stackList) {
      stackList.insertAdjacentElement("afterend", existingEmptyState);
    }

    syncStackControlValues(stackBlock);
  }

  function applyStackControls(stackBlock) {
    if (!(stackBlock instanceof HTMLElement)) {
      return;
    }

    const stackList = stackBlock.querySelector(".habit-tracker-habits__stack");
    const emptyMessage = stackBlock.querySelector(`.${STACK_EMPTY_MESSAGE_CLASS}`);

    if (!(stackList instanceof HTMLElement)) {
      if (emptyMessage instanceof HTMLElement) {
        emptyMessage.hidden = true;
      }
      return;
    }

    const items = getStackListItems(stackList);

    items.forEach((item, index) => {
      getStackListItemOriginalOrder(item, index);
    });

    const sortedItems = [...items].sort(compareStackItemsByCurrentSort);
    sortedItems.forEach((item) => {
      stackList.appendChild(item);
    });

    const searchNeedle = normalizeStackText(habitsStackState.search);
    const selectedCategory = normalizeStackText(habitsStackState.category);
    let visibleCount = 0;

    sortedItems.forEach((item) => {
      const itemCategory = getStackListItemCategory(item);
      const itemName = getStackListItemName(item);
      const matchesCategory =
        selectedCategory === "" ||
        selectedCategory === "all" ||
        itemCategory === selectedCategory;
      const matchesSearch =
        searchNeedle === "" || itemName.includes(searchNeedle);
      const isVisible = matchesCategory && matchesSearch;

      item.hidden = !isVisible;
      item.style.display = isVisible ? "" : "none";

      if (isVisible) {
        visibleCount += 1;
      }
    });

    if (!(emptyMessage instanceof HTMLElement)) {
      return;
    }

    if (items.length > 0 && visibleCount === 0) {
      const labels = getHabitsStackControlLabels();
      emptyMessage.textContent = labels.noResults;
      emptyMessage.hidden = false;
    } else {
      emptyMessage.hidden = true;
    }
  }

  function initHabitsStackControls() {
    const stackBlock = document.querySelector(HABITS_STACK_SELECTOR);

    if (!(stackBlock instanceof HTMLElement)) {
      return;
    }

    ensureStackControlsMarkup(stackBlock);
    applyStackControls(stackBlock);
  }

  function initHabitsStackControlEvents() {
    if (isStackControlEventsBound) {
      return;
    }

    isStackControlEventsBound = true;

    document.addEventListener("input", (event) => {
      const target = event.target;

      if (
        !(target instanceof HTMLInputElement) ||
        target.getAttribute(STACK_CONTROL_ATTR) !== STACK_CONTROL_SEARCH
      ) {
        return;
      }

      const stackBlock = resolveStackBlockFromTarget(target);

      if (!(stackBlock instanceof HTMLElement)) {
        return;
      }

      habitsStackState.search = target.value || "";
      applyStackControls(stackBlock);
    });

    document.addEventListener("search", (event) => {
      const target = event.target;

      if (
        !(target instanceof HTMLInputElement) ||
        target.getAttribute(STACK_CONTROL_ATTR) !== STACK_CONTROL_SEARCH
      ) {
        return;
      }

      const stackBlock = resolveStackBlockFromTarget(target);

      if (!(stackBlock instanceof HTMLElement)) {
        return;
      }

      habitsStackState.search = target.value || "";
      applyStackControls(stackBlock);
    });

    document.addEventListener("change", (event) => {
      const target = event.target;

      if (!(target instanceof HTMLSelectElement)) {
        return;
      }

      const stackBlock = resolveStackBlockFromTarget(target);

      if (!(stackBlock instanceof HTMLElement)) {
        return;
      }

      const controlType = target.getAttribute(STACK_CONTROL_ATTR);

      if (controlType === STACK_CONTROL_CATEGORY) {
        habitsStackState.category = target.value || "all";
        applyStackControls(stackBlock);
        return;
      }

      if (controlType === STACK_CONTROL_SORT) {
        habitsStackState.sort = target.value || STACK_SORT_DEFAULT;
        applyStackControls(stackBlock);
      }
    });
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

  function applyHabitsMutationResponse(payload, options = {}) {
    if (!payload || typeof payload !== "object") {
      return;
    }

    const openModalId =
      options && typeof options.openModalId === "string"
        ? options.openModalId.trim()
        : "";

    replaceHabitsFragment(HABITS_STACK_SELECTOR, payload.stack_html || "");
    replaceHabitsFragment(HABITS_SHARED_SELECTOR, payload.shared_html || "");
    upsertHabitsNotice(payload);
    initHabitsStackControls();

    if (payload.close_modals === true) {
      closeAllModals();
      return;
    }

    if (openModalId !== "") {
      const modalToRestore = getModalById(openModalId);

      if (modalToRestore) {
        openModal(modalToRestore);
      }
    }
  }

  async function submitHabitsMutationForm(form) {
    const config = getHabitsConfig();
    const ajaxUrl = resolveAjaxUrl(config, form);
    const parentModal = form.closest(`[${MODAL_ATTR}]`);
    const openModalId =
      parentModal instanceof HTMLElement
        ? (parentModal.getAttribute(MODAL_ATTR) || "").trim()
        : "";

    if (ajaxUrl === "") {
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
      const response = await fetch(ajaxUrl, {
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
          applyHabitsMutationResponse(responseData, { openModalId });
          return;
        }

        throw new Error("habit-tracker-habits-ajax-failed");
      }

      applyHabitsMutationResponse(responseData, { openModalId });
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
    const actionSet = getHabitsActionSetWithFallback();

    if (actionSet.size === 0) {
      return;
    }

    document.addEventListener("submit", (event) => {
      const target = event.target;

      if (!(target instanceof HTMLFormElement)) {
        return;
      }

      if (event.defaultPrevented) {
        return;
      }

      const actionName = getFormActionName(target);

      if (!actionSet.has(actionName)) {
        return;
      }

      const confirmMessage = (target.getAttribute("data-ht-confirm") || "").trim();

      if (confirmMessage !== "" && !window.confirm(confirmMessage)) {
        event.preventDefault();
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
  initHabitsStackControlEvents();
  initHabitsStackControls();

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
