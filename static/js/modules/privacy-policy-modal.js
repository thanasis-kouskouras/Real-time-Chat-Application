//PRIVACY POLICY MODAL

(function () {
  "use strict";

  if (window.__privacyPolicyModalLoaded) return;
  window.__privacyPolicyModalLoaded = true;

  let overlayEl = null;
  let dialogEl = null;
  let bodyEl = null;
  let closeBtn = null;
  let lastFocusedEl = null;
  let cachedHtml = null;

  function buildModal() {
    const overlay = document.createElement("div");
    overlay.className = "privacy-modal-overlay";
    overlay.setAttribute("role", "presentation");
    overlay.innerHTML =
      '<div class="privacy-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="privacyModalTitle">' +
      '<div class="privacy-modal-header">' +
      '<h3 class="privacy-modal-title" id="privacyModalTitle">Privacy Policy</h3>' +
      '<button type="button" class="privacy-modal-close" aria-label="Close">' +
      '<i class="fa-solid fa-xmark" aria-hidden="true"></i>' +
      "</button>" +
      "</div>" +
      '<div class="privacy-modal-body">' +
      '<div class="privacy-modal-loading">' +
      '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Loading&hellip;' +
      "</div>" +
      "</div>" +
      "</div>";

    document.body.appendChild(overlay);

    overlayEl = overlay;
    dialogEl = overlay.querySelector(".privacy-modal-dialog");
    bodyEl = overlay.querySelector(".privacy-modal-body");
    closeBtn = overlay.querySelector(".privacy-modal-close");

    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) closeModal();
    });
    closeBtn.addEventListener("click", closeModal);
  }

  function ensureModal() {
    if (!overlayEl) buildModal();
  }

  async function loadContent() {
    if (cachedHtml !== null) {
      bodyEl.innerHTML = cachedHtml;
      return;
    }

    bodyEl.innerHTML =
      '<div class="privacy-modal-loading">' +
      '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Loading&hellip;' +
      "</div>";

    try {
      const res = await fetch("privacy-policy.php?fragment=1", {
        credentials: "same-origin",
        headers: { Accept: "text/html" },
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      const html = await res.text();
      cachedHtml = html;
      bodyEl.innerHTML = html;
    } catch (err) {
      bodyEl.innerHTML =
        '<div class="privacy-modal-error">' +
        "<p>Could not load the Privacy Policy. " +
        'Please <a href="privacy-policy.php">open it on its own page</a> instead.</p>' +
        "</div>";
    }
  }

  function openModal() {
    ensureModal();
    lastFocusedEl = document.activeElement;
    document.body.classList.add("privacy-modal-open");

    //Force a reflow so the transition animates from opacity 0
    overlayEl.offsetHeight;
    overlayEl.classList.add("is-open");

    document.addEventListener("keydown", onKeyDown);

    loadContent().then(() => {
      //Focus the close button after content loads, so screen readers land on a sensible place inside the dialog
      if (closeBtn) closeBtn.focus();
    });
  }

  function closeModal() {
    if (!overlayEl) return;
    overlayEl.classList.remove("is-open");
    document.body.classList.remove("privacy-modal-open");
    document.removeEventListener("keydown", onKeyDown);

    //Restore focus to whatever the user was on before opening
    if (lastFocusedEl && typeof lastFocusedEl.focus === "function") {
      lastFocusedEl.focus();
    }
  }

  function onKeyDown(e) {
    if (e.key === "Escape") {
      e.preventDefault();
      closeModal();
      return;
    }
    if (e.key === "Tab") trapFocus(e);
  }

  function trapFocus(e) {
    if (!dialogEl) return;
    const focusables = dialogEl.querySelectorAll(
      'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );
    if (focusables.length === 0) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  document.addEventListener("click", (e) => {
    const link = e.target.closest("[data-privacy-link]");
    if (!link) return;
    e.preventDefault();
    openModal();
  });
})();
