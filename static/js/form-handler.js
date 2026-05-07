/* API CLIENT UTILITY

Handles AJAX requests to the JSON API endpoints with proper error handling. */

class ApiClient {
  //MAKE AN API REQUEST
  static async request(endpoint, options = {}) {
    const {
      method = "POST",
      data = null,
      headers = {},
      isFormData = false,
      showLoading = false, //Option to show global loading indicator
      loadingMessage = "Loading...",
    } = options;

    const config = {
      method,
      headers: {
        ...headers,
      },
    };

    //Add body if data provided
    if (data) {
      if (isFormData) {
        config.body = data; //FormData for file uploads
      } else {
        config.headers["Content-Type"] = "application/json";
        config.body = JSON.stringify(data);
      }
    }

    //Show loading indicator if requested
    if (showLoading && window.loadingIndicator) {
      window.loadingIndicator.show(loadingMessage);
    }

    try {
      const response = await fetch(endpoint, config);
      const result = await response.json();

      if (
        response.status === 401 &&
        !window.location.pathname.includes("login.php")
      ) {
        //Store current page for redirect after login
        sessionStorage.setItem(
          "redirect_after_login",
          window.location.pathname + window.location.search,
        );

        //Store message to show after redirect
        sessionStorage.setItem(
          "toastMessage",
          "Your session has expired. Please login again.",
        );
        sessionStorage.setItem("toastType", "error");

        //Redirect to login
        window.location.href = "login.php";
        return;
      }

      if (!response.ok) {
        throw new ApiError(
          result.message || "Request failed",
          response.status,
          result.errors,
        );
      }

      return result;
    } catch (error) {
      if (error instanceof ApiError) {
        throw error;
      }
      throw new ApiError("Network error. Please check your connection.", 0);
    } finally {
      //Hide loading indicator if it was shown
      if (showLoading && window.loadingIndicator) {
        window.loadingIndicator.hide();
      }
    }
  }

  //GET REQUEST (for fetching data/no loading by default)
  static get(endpoint, params = null, showLoading = false) {
    let url = endpoint;

    //Only add parameters if they exist and are not null/empty
    if (params && Object.keys(params).length > 0) {
      //Check if URL already has query parameters
      const separator = endpoint.includes("?") ? "&" : "?";
      url = `${endpoint}${separator}${new URLSearchParams(params)}`;
    }

    return this.request(url, { method: "GET", showLoading });
  }

  //POST REQUEST
  static post(endpoint, data, showLoading = false) {
    return this.request(endpoint, { method: "POST", data, showLoading });
  }

  //PUT REQUEST
  static put(endpoint, data, showLoading = false) {
    return this.request(endpoint, { method: "PUT", data, showLoading });
  }

  //DELETE REQUEST
  static delete(endpoint, data = null, showLoading = false) {
    return this.request(endpoint, { method: "DELETE", data, showLoading });
  }

  //UPLOAD FILE REQUEST
  static upload(endpoint, formData, showLoading = true) {
    return this.request(endpoint, {
      method: "POST",
      data: formData,
      isFormData: true,
      showLoading,
    }); //Shows loading by default since uploads are user initiated
  }

  /* Helper method for actions that reload/redirect after success.
  Stores toast message in sessionStorage and reloads/redirects. */
  static async actionWithReload(apiCall, options = {}) {
    const {
      loadingMessage = "Processing...",
      successMessage = "Operation completed successfully",
      errorMessage = "Operation failed",
      redirectUrl = null,
      onError = null,
    } = options;

    if (window.loadingIndicator) {
      window.loadingIndicator.show(loadingMessage);
    }

    try {
      const result = await apiCall();

      sessionStorage.setItem("toastMessage", result.message || successMessage);
      sessionStorage.setItem("toastType", "success");

      //Reload or redirect (keep loading visible)
      if (redirectUrl) {
        window.location.href = redirectUrl;
      } else {
        window.location.reload();
      }
    } catch (error) {
      if (onError) {
        onError(error); //Call custom error handler if provided
      } else {
        //Show error toast immediately
        if (window.showToast) {
          window.showToast(error.message || errorMessage, "error");
        }
      }

      //Hide loading on error
      if (window.loadingIndicator) {
        window.loadingIndicator.hide();
      }
    }
  }
}

//CUSTOM API ERROR CLASS
class ApiError extends Error {
  constructor(message, statusCode = 0, fieldErrors = null) {
    super(message);
    this.name = "ApiError";
    this.statusCode = statusCode;
    this.fieldErrors = fieldErrors || {};
  }
}

/* FORM HANDLER UTILITY

Handles form submission with API integration and error display. */
class FormHandler {
  constructor(formElement, options = {}) {
    this.form = formElement;
    this.submitButton = formElement.querySelector('[type="submit"]');
    this.useToast = options.useToast || true; //Option to use toast instead of inline messages
    this.messageContainer = !this.useToast
      ? options.messageContainer || this.createMessageContainer()
      : null;
    this.onSuccess = options.onSuccess || this.defaultSuccessHandler.bind(this);
    this.onError = options.onError || null;
    this.beforeSubmit = options.beforeSubmit || null; //Validation callback before submission
    this.apiEndpoint = options.apiEndpoint;
    this.method = options.method || "POST";
    this.isFileUpload = options.isFileUpload || false;
    this.keepLoadingOnSuccess = options.keepLoadingOnSuccess || false;

    formElement.formHandler = this; //Store instance on form element for access in callbacks

    this.init();
  }

  init() {
    this.form.addEventListener("submit", this.handleSubmit.bind(this));
  }

  createMessageContainer() {
    let container = this.form.querySelector(".message-container");
    if (!container) {
      container = document.createElement("div");
      container.className = "message-container";
      this.form.insertBefore(container, this.form.firstChild);
    }
    return container;
  }

  async handleSubmit(e) {
    e.preventDefault();

    this.clearMessages(); //Clear previous messages

    const formData = this.isFileUpload
      ? new FormData(this.form)
      : this.getFormData();

    if (this.beforeSubmit) {
      const shouldContinue = this.beforeSubmit(formData);
      if (shouldContinue === false) {
        //Validation failed, don't proceed
        return;
      }
    }

    this.setLoading(true); //Show loading indicator

    if (window.loadingIndicator) {
      window.loadingIndicator.show("Processing...");
    }

    try {
      let response;
      if (this.isFileUpload) {
        response = await ApiClient.upload(this.apiEndpoint, formData);
      } else {
        response = await ApiClient.request(this.apiEndpoint, {
          method: this.method,
          data: formData,
        });
      }

      this.onSuccess(response); //Call success handler

      /* If keepLoadingOnSuccess is true, loading doesn't hide (useful for redirects).
      Skip block cleanup. */
      if (this.keepLoadingOnSuccess) {
        return;
      }
    } catch (error) {
      if (this.onError) {
        this.onError(error);
      } else {
        this.displayError(error);
      }

      //Hide loading on error, even if keepLoadingOnSuccess is true
      this.setLoading(false);
      if (window.loadingIndicator) {
        window.loadingIndicator.hide();
      }
    } finally {
      //Hide loading if not keeping it visible for redirect AND no error occurred
      if (!this.keepLoadingOnSuccess) {
        this.setLoading(false);

        if (window.loadingIndicator) {
          window.loadingIndicator.hide();
        }
      }
    }
  }

  getFormData() {
    const formData = new FormData(this.form);
    const data = {};
    for (let [key, value] of formData.entries()) {
      data[key] = value;
    }
    return data;
  }

  setLoading(isLoading) {
    if (this.submitButton) {
      if (isLoading) {
        //Use loadingIndicator if available, otherwise fallback to simple text
        if (window.loadingIndicator) {
          window.loadingIndicator.showOnElement(
            this.submitButton,
            "Processing...",
          );
        } else {
          this.submitButton.disabled = true;
          this.submitButton.dataset.originalText =
            this.submitButton.textContent;
          this.submitButton.innerHTML = `
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Loading...
                    `;
        }
      } else {
        //Restore button state
        if (window.loadingIndicator) {
          window.loadingIndicator.hideOnElement(this.submitButton);
        } else {
          this.submitButton.disabled = false;
          this.submitButton.textContent =
            this.submitButton.dataset.originalText || "Submit";
        }
      }
    }
  }

  displaySuccess(message) {
    if (this.useToast) {
      if (typeof showToast === "function") {
        showToast(message, "success");
      } else if (typeof window.showToast === "function") {
        window.showToast(message, "success");
      }
    } else if (this.messageContainer) {
      this.messageContainer.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
    }
  }

  displayError(error) {
    //Highlight fields with errors
    if (error.fieldErrors && Object.keys(error.fieldErrors).length > 0) {
      for (let [field, message] of Object.entries(error.fieldErrors)) {
        const input = this.form.querySelector(`[name="${field}"]`);
        if (input) {
          input.classList.add("is-invalid");

          //Display error message next to the field if span exists
          const errorSpan = this.form.querySelector(`#span_error_${field}`);
          if (errorSpan) {
            errorSpan.textContent = message;
            errorSpan.style.display = "block";
          }

          //Auto-clear invalid state when user starts editing the field
          input.addEventListener("input", () => {
            input.classList.remove("is-invalid");
            if (errorSpan) {
              errorSpan.textContent = "";
              errorSpan.style.display = "none";
            }
          }, { once: true });
        }
      }
    }

    if (this.useToast) {
      //Check if error message contains HTML
      const containsHtml = /<[^>]+>/.test(error.message);

      let errorMessage = error.message;

      if (
        error.fieldErrors &&
        Object.keys(error.fieldErrors).length > 0 &&
        !containsHtml
      ) {
        //Create a detailed error message with all field errors
        const fieldErrorList = Object.entries(error.fieldErrors)
          .filter(([, msg]) => msg && msg.trim() !== "")
          .map(([field, msg]) => {
            const fieldLabel = this.getFieldLabel(field);
            return `${fieldLabel}: ${msg}`;
          })
          .join("\n");

        if (fieldErrorList) {
          errorMessage = `${error.message}<br>\n\n${fieldErrorList}`;
        }
      }

      //HTML messages have longer duration, so user can interact with buttons
      const duration = containsHtml ? 15000 : 5000;

      if (typeof showToast === "function") {
        showToast(errorMessage, "error", duration);
      } else if (typeof window.showToast === "function") {
        window.showToast(errorMessage, "error", duration);
      }
    } else if (this.messageContainer) {
      let errorHtml = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error:</strong> ${error.message} <br>
            `;

      //Display field specific errors
      if (error.fieldErrors && Object.keys(error.fieldErrors).length > 0) {
        errorHtml += '<br><ul class="mb-0 mt-2">';
        for (let [field, message] of Object.entries(error.fieldErrors)) {
          const fieldLabel = this.getFieldLabel(field);
          errorHtml += `<li><strong>${fieldLabel}:</strong> ${message}</li>`;
        }
        errorHtml += "</ul>";
      }

      errorHtml += `
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            `;

      this.messageContainer.innerHTML = errorHtml;
    }
  }

  getFieldLabel(fieldName) {
    //Convert field names to readable labels
    const labels = {
      user_username: "Username",
      user_email: "Email",
      user_password: "Password",
      confirm_password: "Confirm Password",
      currentPassword: "Current Password",
      newPassword: "New Password",
      group_name: "Group Name",
      member_guids: "Members",
      member_ids: "Members",
    };

    return (
      labels[fieldName] ||
      fieldName.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase())
    );
  }

  clearMessages() {
    if (this.messageContainer) {
      this.messageContainer.innerHTML = "";
    }

    //Remove error highlighting from inputs
    this.form.querySelectorAll(".is-invalid").forEach((input) => {
      input.classList.remove("is-invalid");
    });

    //Clear error spans
    this.form.querySelectorAll('[id^="span_error_"]').forEach((span) => {
      span.textContent = "";
      span.style.display = "none";
    });
  }

  defaultSuccessHandler(response) {
    this.displaySuccess(response.message || "Operation completed successfully");
    this.form.reset();
  }
}

//Export for use in other scripts
window.ApiClient = ApiClient;
window.ApiError = ApiError;
window.FormHandler = FormHandler;
window.handleActionWithReload = ApiClient.actionWithReload.bind(ApiClient);
