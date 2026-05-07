/* TOAST NOTIFICATION SYSTEM

Global toast notifications with different types and auto-dismiss. */

let toastCounter = 0;
const activeToasts = new Map();

export function hideToast(toastId = null) {
  if (toastId) {
    //Hide specific toast
    const toast = activeToasts.get(toastId);
    if (toast) {
      dismissToast(toast, toastId);
    }
  } else {
    //Hide all toasts
    activeToasts.forEach((toast, id) => {
      dismissToast(toast, id);
    });
  }
}

function dismissToast(toast, toastId) {
  if (toast.timeout) {
    clearTimeout(toast.timeout);
  }

  toast.element.style.opacity = "0";
  toast.element.style.transform = "translateY(10px)";

  setTimeout(() => {
    if (toast.element && toast.element.parentNode) {
      toast.element.remove();
    }
    activeToasts.delete(toastId);
  }, 300);
}

export function showToast(message, type = "success", duration = 3000) {
  const toastId = ++toastCounter;

  const toastElement = document.createElement("div");
  toastElement.className = "global-toast";

  //Calculate bottom position based on existing toasts
  const existingCount = activeToasts.size;
  const bottomOffset = 20 + existingCount * 70; //Stack toasts

  //Dynamic styles that must stay in JS
  toastElement.style.bottom = `${bottomOffset}px`;
  toastElement.style.transform = "translateY(10px)";
  toastElement.style.opacity = "0";

  //Set type class and icon
  let icon = "";
  if (type === "success") {
    toastElement.classList.add("toast-success");
    icon = "✓";
  } else if (type === "error") {
    toastElement.classList.add("toast-error");
    icon = "✕";
  } else if (type === "info") {
    toastElement.classList.add("toast-info");
    icon = "ℹ";
  } else if (type === "warning") {
    toastElement.classList.add("toast-warning");
    icon = "⚠";
  } else if (type === "loading") {
    toastElement.classList.add("toast-loading");
    icon = "⟳";
    toastElement.style.animation = "toast-spin 1s linear infinite";
  }

  //Check if message contains HTML
  const containsHtml = /<[^>]+>/.test(message);

  if (containsHtml) {
    toastElement.classList.add("toast-html");
    toastElement.innerHTML = `
            <div class="toast-inner">
                <span class="toast-icon-html">${icon}</span>
                <div class="toast-body">${message}</div>
            </div>
        `;
  } else {
    toastElement.innerHTML = `<span class="toast-icon">${icon}</span><span>${message}</span>`;
  }

  document.body.appendChild(toastElement);

  //Store toast reference
  const toastData = {
    element: toastElement,
    timeout: null,
  };
  activeToasts.set(toastId, toastData);

  //Animate in
  requestAnimationFrame(() => {
    toastElement.style.transform = "translateY(0)";
    toastElement.style.opacity = "1";
  });

  //Auto-remove after duration (if duration > 0)
  if (duration > 0) {
    toastData.timeout = setTimeout(() => {
      dismissToast(toastData, toastId);
    }, duration);
  }

  //Return toast ID for manual dismissal
  return toastId;
}

export function showMessageStatus(message, isSuccess) {
  showToast(message, isSuccess ? "success" : "error");
}

export function showFileError(message) {
  showToast(message, "error");
}

//Convert hidden error/success messages from PHP to toast notifications
export function convertHiddenMessagesToToasts() {
  const errorMessage = document.getElementById("errorMessage");
  const successMessage = document.getElementById("successMessage");

  if (errorMessage && errorMessage.value) {
    showToast(errorMessage.value, "error");
    errorMessage.remove();
  }

  if (successMessage && successMessage.value) {
    showToast(successMessage.value, "success");
    successMessage.remove();
  }
}

//Check for toast messages stored in sessionStorage (from redirects)
export function checkStoredToastMessages() {
  const message = sessionStorage.getItem("toastMessage");
  const type = sessionStorage.getItem("toastType");

  if (message) {
    //Clear from storage first
    sessionStorage.removeItem("toastMessage");
    sessionStorage.removeItem("toastType");

    //Hide loading indicator if still visible
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }

    //Show toast after a brief delay to ensure page is ready
    setTimeout(() => {
      showToast(message, type || "success");
    }, 100);
  }
}

//Auto-run conversions on page load
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    checkStoredToastMessages();
    convertHiddenMessagesToToasts();
  });
} else {
  //DOM already loaded
  checkStoredToastMessages();
  convertHiddenMessagesToToasts();
}

showToast.dismiss = hideToast;

//Legacy global functions for backward compatibility
window.showToast = showToast;
window.hideToast = hideToast;
window.showMessageStatus = showMessageStatus;
window.showFileError = showFileError;
window.convertHiddenMessagesToToasts = convertHiddenMessagesToToasts;
