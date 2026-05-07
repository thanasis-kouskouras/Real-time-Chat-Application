//SETTINGS PAGE JAVASCRIPT MODULE

class SettingsPageManager {
  constructor() {
    this.form = null;

    this.init();
  }

  //Initialize the settings page manager
  init() {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => this.setupPage());
    } else {
      this.setupPage();
    }
  }

  //Set up the page after DOM is ready
  setupPage() {
    this.form = document.querySelector("#settings-form");

    if (!this.form) {
      return;
    }

    //Set up standard FormHandler pattern
    this.setupFormHandler();

    //Load current settings
    this.loadSettings();
  }

  //Set up FormHandler with standard patterns
  setupFormHandler() {
    //Create custom success handler that updates the form with returned settings
    const customSuccessHandler = (response) => {
      //1. Show success toast notification (standard pattern)
      FormUtilities.showToast(
        response.message || "Settings saved successfully!",
        "success",
      );

      //2. Update form with returned settings (no page reload)
      if (response.settings) {
        this.populateForm(response.settings);
      }
      //3. Form reset is not needed for settings
    };

    //Create custom validation for checkbox data
    const customValidation = (data) => {
      //Convert checkbox presence to boolean values
      data.hide_account_from_search = "hide_account_from_search" in data;
      data.email_notifications = "email_notifications" in data;
      return true;
    };

    //Initialize FormHandler with standard pattern
    new FormHandler(this.form, {
      apiEndpoint: ApiUrls.settingsUpdate(),
      method: "POST",
      useToast: true, //Standard pattern (use toast notifications, no page reloads)
      onSuccess: customSuccessHandler,
      beforeSubmit: customValidation,
    });
  }

  //Load user settings from API
  async loadSettings() {
    try {
      //Show loading indicator
      if (window.loadingIndicator) {
        window.loadingIndicator.show("Loading settings...");
      }

      //Check if ApiClient is available
      if (typeof window.ApiClient === "undefined") {
        throw new Error("ApiClient is not available. Please refresh the page.");
      }

      const data = await ApiClient.get(ApiUrls.settingsGet());

      if (data.success && data.settings) {
        this.populateForm(data.settings);
      } else {
        throw new Error(data.message || "Invalid response format");
      }
    } catch (error) {
      FormUtilities.showToast(
        "Failed to load settings. Please refresh the page.",
        "error",
      );
    } finally {
      //Hide loading indicator
      if (window.loadingIndicator) {
        window.loadingIndicator.hide();
      }
    }
  }

  //Populate form with settings data
  populateForm(settings) {
    const hideAccountCheckbox = this.form.querySelector(
      "#hide_account_from_search",
    );
    if (hideAccountCheckbox) {
      hideAccountCheckbox.checked = settings.hide_account_from_search || false;
    }
    const emailNotificationsCheckbox = this.form.querySelector(
      "#email_notifications",
    );
    if (emailNotificationsCheckbox) {
      emailNotificationsCheckbox.checked =
        settings.email_notifications || false;
    }
  }
}

//Initialize settings page manager when script loads
const settingsPageManager = new SettingsPageManager();

//Export for external use
if (typeof module !== "undefined" && module.exports) {
  module.exports = SettingsPageManager;
} else {
  window.SettingsPageManager = SettingsPageManager;
  window.settingsPageManager = settingsPageManager;
}
