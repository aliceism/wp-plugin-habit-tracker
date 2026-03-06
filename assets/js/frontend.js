(() => {
  const OPEN_ATTR = "data-ht-open-modal";
  const MODAL_ATTR = "data-ht-modal";
  const CLOSE_ATTR = "data-ht-close-modal";
  const HEADER_CLASS = "habit-tracker-page-header";

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
