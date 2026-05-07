//FORM UTILITIES

class FormUtilities {
  /* Standard success handler factory.
     Creates consistent success handlers that show toast notifications and update UI elements without page reloads. */
  static standardSuccessHandler(updateSelector = null, updateProperty = null) {
    return (response) => {
      //1. Show success toast notification
      FormUtilities.showToast(
        response.message || "Operation completed successfully",
        "success",
      );

      //2. Update UI element immediately (no page reload)
      if (updateSelector && response) {
        let updateValue = null;

        //Use specified property or find first suitable property
        if (updateProperty && response[updateProperty] !== undefined) {
          updateValue = response[updateProperty];
        } else if (response.data && typeof response.data === "object") {
          //Use first property from data object
          const dataKeys = Object.keys(response.data);
          if (dataKeys.length > 0) {
            updateValue = response.data[dataKeys[0]];
          }
        } else if (response.username) {
          updateValue = response.username;
        } else if (response.group_name) {
          updateValue = response.group_name;
        }

        if (updateValue !== null) {
          FormUtilities.updateElement(updateSelector, updateValue);
        }
      }
    };
  }

  /* Validate required fields.
    Provides consistent validation with clear error messages. */
  static validateRequired(formData, requiredFields, fieldLabels = {}) {
    //Convert FormData to object if needed
    const data =
      formData instanceof FormData
        ? FormUtilities.formDataToObject(formData)
        : formData;

    for (const field of requiredFields) {
      const value = data[field];
      const label =
        fieldLabels[field] ||
        field
          .replace(/([A-Z])/g, " $1")
          .replace(/^./, (str) => str.toUpperCase());

      if (!value || (typeof value === "string" && !value.trim())) {
        FormUtilities.showToast(`${label} is required`, "error");
        return false;
      }
    }

    return true;
  }

  /* Validate image file uploads.
    Provides consistent file validation with clear error messages. */
  static validateImageFile(file, options = {}) {
    const {
      allowedTypes = ["image/jpeg", "image/jpg", "image/png"],
      maxSize = 5 * 1024 * 1024, //5MB default
    } = options;

    if (!file) {
      FormUtilities.showToast("Please select an image file", "error");
      return false;
    }

    //Check file type
    if (!allowedTypes.includes(file.type)) {
      const typeList = allowedTypes
        .map((type) => type.split("/")[1].toUpperCase())
        .join(", ");
      FormUtilities.showToast(`Only ${typeList} files are allowed`, "error");
      return false;
    }

    //Check file size
    if (file.size > maxSize) {
      const sizeMB = Math.round(maxSize / (1024 * 1024));
      FormUtilities.showToast(
        `File is too large. Maximum size is ${sizeMB}MB`,
        "error",
      );
      return false;
    }

    return true;
  }

  /* Update DOM element with new value.
    Provides consistent UI updates across all forms. */
  static updateElement(selector, value, property = "textContent") {
    const element = document.querySelector(selector);
    if (element) {
      if (property === "textContent") {
        element.textContent = value;
      } else if (property === "innerHTML") {
        element.innerHTML = value;
      } else if (property === "value") {
        element.value = value;
      } else if (property === "src") {
        element.src = value;
      } else {
        element[property] = value;
      }
    }
  }

  /* Show toast notification.
    Provides consistent toast notifications across all forms. */
  static showToast(message, type = "success", duration = 3000) {
    if (window.showToast && typeof window.showToast === "function") {
      window.showToast(message, type, duration);
    }
  }

  /* Convert FormData to plain object or return object if already converted.
    Helper function for working with FormData in validation. */
  static formDataToObject(formData) {
    //If it's already a plain object, return it as-is
    if (
      formData &&
      typeof formData === "object" &&
      !(formData instanceof FormData)
    ) {
      return formData;
    }

    //If it's FormData, convert it to object
    if (formData instanceof FormData) {
      const obj = {};
      for (let [key, value] of formData.entries()) {
        obj[key] = value;
      }
      return obj;
    }

    //Fallback for unexpected types
    return {};
  }

  /* Create standard FormHandler configuration.
    Factory function for creating consistent FormHandler configurations. */
  static createStandardConfig(config) {
    const {
      apiEndpoint,
      method = "POST",
      isFileUpload = false,
      updateSelector = null,
      updateProperty = null,
      requiredFields = [],
      fieldLabels = {},
      customValidation = null,
      customSuccess = null,
    } = config;

    return {
      apiEndpoint,
      method,
      isFileUpload,
      useToast: true,
      beforeSubmit: (formData) => {
        //Standard required field validation
        if (requiredFields.length > 0) {
          if (
            !FormUtilities.validateRequired(
              formData,
              requiredFields,
              fieldLabels,
            )
          ) {
            return false;
          }
        }

        //File validation for upload forms
        if (isFileUpload) {
          const file =
            formData instanceof FormData ? formData.get("file") : formData.file;
          if (file && !FormUtilities.validateImageFile(file)) {
            return false;
          }
        }

        //Custom validation if provided
        if (customValidation) {
          return customValidation(formData);
        }

        return true;
      },
      onSuccess:
        customSuccess ||
        FormUtilities.standardSuccessHandler(updateSelector, updateProperty),
    };
  }
}

//Export for use in other scripts
window.FormUtilities = FormUtilities;

//Export for ES6 modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = FormUtilities;
}
