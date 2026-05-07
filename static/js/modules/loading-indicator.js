/* LOADING INDICATOR MODULE

Provides centralized loading state management for API calls. */

class LoadingIndicator {
  constructor() {
    this.activeRequests = 0;
    this.overlay = null;
    this.initialized = false;
    this.init();
  }

  init() {
    //Wait for DOM to be ready before creating overlay
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => this.createOverlay());
    } else {
      this.createOverlay();
    }
  }

  createOverlay() {
    //Check if overlay already exists
    if (document.getElementById("loading-overlay")) {
      this.overlay = document.getElementById("loading-overlay");
      this.initialized = true;
      return;
    }

    //Ensure document.body exists
    if (!document.body) {
      return;
    }

    //Create overlay
    this.overlay = document.createElement("div");
    this.overlay.id = "loading-overlay";
    this.overlay.className = "loading-overlay";
    this.overlay.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="loading-text mt-3">Loading...</p>
            </div>
        `;

    document.body.appendChild(this.overlay);
    this.initialized = true;
  }

  //Show loading indicator
  show(message = "Loading...") {
    this.activeRequests++;

    //Hide any visible toasts when loading shows
    if (typeof window.hideToast === "function") {
      window.hideToast();
    }

    //Ensure overlay is created
    if (!this.initialized) {
      this.createOverlay();
    }

    if (this.overlay) {
      const textElement = this.overlay.querySelector(".loading-text");
      if (textElement) {
        textElement.textContent = message;
      }
      this.overlay.classList.add("active");
      if (document.body) {
        document.body.style.overflow = "hidden"; //Prevent scrolling
      }
    }
  }

  //Hide loading indicator
  hide() {
    this.activeRequests = Math.max(0, this.activeRequests - 1);

    //Only hide if no active requests
    if (this.activeRequests === 0 && this.overlay) {
      this.overlay.classList.remove("active");
      if (document.body) {
        document.body.style.overflow = ""; //Restore scrolling
      }
    }
  }

  //Force hide loading indicator (clear all active requests)
  forceHide() {
    this.activeRequests = 0;
    if (this.overlay) {
      this.overlay.classList.remove("active");
      if (document.body) {
        document.body.style.overflow = "";
      }
    }
  }

  //Show loading on a specific element
  showOnElement(element, message = "Loading...") {
    if (!element) return;

    //Store original state
    element.dataset.originalDisabled = element.disabled;
    element.dataset.originalContent = element.innerHTML;

    //Disable and show loading
    element.disabled = true;

    if (element.tagName === "BUTTON" || element.tagName === "A") {
      element.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                ${message}
            `;
    }
  }

  //Hide loading on a specific element
  hideOnElement(element) {
    if (!element) return;

    if (element.dataset.originalContent) {
      element.innerHTML = element.dataset.originalContent;
      delete element.dataset.originalContent;
    }

    if (element.dataset.originalDisabled !== undefined) {
      element.disabled = element.dataset.originalDisabled === "true";
      delete element.dataset.originalDisabled;
    }
  }
}

//Create singleton instance
const loadingIndicator = new LoadingIndicator();

//Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = loadingIndicator;
} else {
  window.loadingIndicator = loadingIndicator;
}
