//CONTACT US PAGE - JAVASCRIPT MODULE

class ContactPageManager {
  constructor() {
    this.form = null;
    this.formHandler = null;
    this.initialized = false;

    this.init();
  }

  //Initialize the contact page manager
  init() {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => this.setupPage());
    } else {
      this.setupPage();
    }
  }

  //Set up the page after DOM is ready
  setupPage() {
    this.form = document.querySelector("#contact-form");

    if (!this.form) {
      return;
    }

    //Set up standard FormHandler pattern
    this.setupFormHandler();

    //Set up character counter for message textarea
    this.setupMessageCounter();

    this.initialized = true;
  }

  //Set up FormHandler with standard patterns
  setupFormHandler() {
    //Create standard configuration
    const config = FormUtilities.createStandardConfig({
      apiEndpoint: ApiUrls.contactSend(),
      method: "POST",
      requiredFields: ["subject", "message"],
      fieldLabels: {
        subject: "Subject",
        message: "Message",
        nickname: "Nickname",
        surname: "Surname",
      },
      customValidation: this.validateContactForm.bind(this),
      customSuccess: this.handleContactSuccess.bind(this),
    });

    //Initialize FormHandler with standard configuration
    this.formHandler = new FormHandler(this.form, config);
  }

  //Set up live character counter for the message textarea
  setupMessageCounter() {
    const messageField = this.form.querySelector("#message");
    const counter = document.querySelector("#message-counter");

    if (!messageField || !counter) return;

    messageField.addEventListener("input", () => {
      const current = messageField.value.length;
      const max = 1000;
      counter.textContent = `${current} / ${max}`;

      if (current >= max) {
        counter.classList.remove("text-muted");
        counter.classList.add("text-danger");
      } else {
        counter.classList.remove("text-danger");
        counter.classList.add("text-muted");
      }
    });
  }

  /* Custom validation for contact form.
  Validates nickname and surname format if provided. */
  validateContactForm(formData) {
    const data = FormUtilities.formDataToObject(formData);

    //Validate nickname if provided (optional field)
    if (data.nickname && data.nickname.trim()) {
      if (!this.isValidName(data.nickname.trim())) {
        FormUtilities.showToast("Invalid Nickname format", "error");
        return false;
      }
    }

    //Validate surname if provided (optional field)
    if (data.surname && data.surname.trim()) {
      if (!this.isValidName(data.surname.trim())) {
        FormUtilities.showToast("Invalid Surname format", "error");
        return false;
      }
    }

    //Validate subject length
    if (data.subject && data.subject.length > 100) {
      FormUtilities.showToast(
        "Subject must be 100 characters or less",
        "error",
      );
      return false;
    }

    //Validate message length
    if (data.message && data.message.length > 1000) {
      FormUtilities.showToast(
        "Message must be 1000 characters or less",
        "error",
      );
      return false;
    }

    return true; //All validation passed
  }

  /* Validate name format (nickname/surname).
  Allows ASCII letters only. */
  isValidName(name) {
    const namePattern = /^[a-zA-Z]+$/;
    return namePattern.test(name) && name.length >= 2 && name.length <= 50;
  }

  //Custom success handler for contact form (shows success message and resets form)
  handleContactSuccess(response) {
    //1. Show success toast notification
    FormUtilities.showToast(
      response.message ||
        "Message sent successfully! We will reply to you via email.",
      "success",
    );

    // 2. Reset form to clear all fields
    this.form.reset();

    //3. Focus on first field for next message
    const firstField = this.form.querySelector("#nickname");
    if (firstField) {
      firstField.focus();
    }
  }
}

//Initialize contact page manager when script loads
new ContactPageManager();
