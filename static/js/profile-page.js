/* PROFILE PAGE MANAGER Profile

Handles client-side profile data loading and operations. */

class ProfilePageLoader {
  constructor() {
    this.profileData = null;
    this.initialized = false;
  }

  //Initialize the profile page
  async init() {
    if (this.initialized) return;

    this.showInitialLoading(true);

    try {
      await this.loadProfileData(); //Load profile data from API
      this.renderProfileData(); //Render the profile data
      this.initializeFormHandlers();
      this.initialized = true;
    } catch {
      this.handleLoadingError();
    } finally {
      this.showInitialLoading(false); //Hide initial loading indicator
    }
  }

  //Show/hide initial loading indicator
  showInitialLoading(show) {
    const loadingDiv = document.getElementById("profile-loading");
    if (loadingDiv) {
      if (show) {
        loadingDiv.classList.remove("d-none");
      } else {
        loadingDiv.classList.add("d-none");
      }
    }
  }

  //Load profile data from API
  async loadProfileData() {
    try {
      const response = await ApiClient.get(ApiUrls.profileGet());

      if (response.success && response.profile) {
        this.profileData = response.profile;
      } else {
        throw new Error("Failed to load profile data");
      }
    } catch (error) {
      throw error;
    }
  }

  //Render profile data in the UI
  renderProfileData() {
    if (!this.profileData) return;

    //Update username display with truncation and tooltip
    const usernameSpan = document.getElementById("username-display");
    if (usernameSpan) {
      usernameSpan.textContent = this.profileData.user_username;
      usernameSpan.title = this.profileData.user_username;
      usernameSpan.classList.add("profile-field-truncate");
    }

    //Update email display with truncation and tooltip
    const emailSpan = document.getElementById("email-display");
    if (emailSpan) {
      emailSpan.textContent = this.profileData.user_email;
      emailSpan.title = this.profileData.user_email;
      emailSpan.classList.add("profile-field-truncate");
    }

    //Update profile image if needed (handled by existing header scripts)
    if (this.profileData.profile_image_url && window.updateProfileImage) {
      window.updateProfileImage(this.profileData.profile_image_url);
    }
  }

  //Initialize form handlers for profile operations
  initializeFormHandlers() {
    this.initUsernameForm();
    this.initPasswordForm();
    this.initImageUploadForm();
    this.initImageDeleteForm();
    this.initAccountDeleteButton();
  }

  //Initialize username change form
  initUsernameForm() {
    const usernameForm = document.getElementById("username-form");
    if (!usernameForm) return;

    new FormHandler(usernameForm, {
      apiEndpoint: ApiUrls.profileUpdateUsername(),
      method: "POST",
      useToast: true,
      beforeSubmit: (formData) => {
        const newUsername = formData.new_username || "";

        //Use FormUtilities for required field validation
        if (
          !FormUtilities.validateRequired(formData, ["new_username"], {
            new_username: "New Username",
          })
        ) {
          return false;
        }

        if (newUsername.trim() === this.profileData.user_username) {
          FormUtilities.showToast("No changes to save", "info");
          return false;
        }

        return true;
      },
      onSuccess: (response) => {
        FormUtilities.showToast(
          response.message || "Username updated successfully!",
          "success",
        );

        //Get the new username from response (support both formats)
        const newUsername = response.user_username || response.username;

        if (newUsername) {
          //Update profile data
          this.profileData.user_username = newUsername;

          //Update username display on profile page
          const usernameDisplay = document.getElementById("username-display");
          if (usernameDisplay) {
            usernameDisplay.textContent = newUsername;
            usernameDisplay.title = newUsername; // Update tooltip too
          }

          //Update username in header navbar (real-time update)
          const headerUsername = document.querySelector(
            ".navbar-nav .nav-item:first-child strong",
          );
          if (headerUsername) {
            headerUsername.textContent = newUsername;
          } else {
            //Try alternative selector in case structure is different
            const altHeaderUsername = document.querySelector(
              "header .navbar-nav strong",
            );
            if (altHeaderUsername) {
              altHeaderUsername.textContent = newUsername;
            }
          }
        }

        //Clear the form input field after successful update
        const usernameInput = document.getElementById("new_username");
        if (usernameInput) {
          usernameInput.value = "";
          //Remove any validation classes
          usernameInput.classList.remove("is-invalid", "is-valid");
        }

        //Reset the form
        if (usernameForm) {
          usernameForm.reset();
        }
      },
    });
  }

  //Initialize password change form
  initPasswordForm() {
    const passwordForm = document.getElementById("password-form");
    if (!passwordForm) return;

    new FormHandler(passwordForm, {
      apiEndpoint: ApiUrls.profileUpdatePassword(),
      method: "POST",
      useToast: true,
      beforeSubmit: (formData) => {
        const currentPassword = formData.current_password || "";
        const newPassword = formData.new_password || "";
        const confirmPassword = formData.confirm_password || "";

        //Use FormUtilities for required field validation
        if (
          !FormUtilities.validateRequired(
            formData,
            ["current_password", "new_password", "confirm_password"],
            {
              current_password: "Current Password",
              new_password: "New Password",
              confirm_password: "Confirm Password",
            },
          )
        ) {
          return false;
        }

        //Validate password length
        if (newPassword.length < 8) {
          FormUtilities.showToast(
            "New password must be at least 8 characters",
            "error",
          );
          return false;
        }

        //Validate passwords match
        if (newPassword !== confirmPassword) {
          FormUtilities.showToast("New passwords do not match", "error");
          return false;
        }

        //Validate if new password is different
        if (currentPassword === newPassword) {
          FormUtilities.showToast(
            "New password must be different from current password",
            "error",
          );
          return false;
        }

        return true;
      },
    });
  }

  //Initialize image upload form
  initImageUploadForm() {
    const uploadForm = document.getElementById("upload-image-form");
    if (!uploadForm) return;

    new FormHandler(uploadForm, {
      apiEndpoint: ApiUrls.profileUploadImage(),
      isFileUpload: true,
      useToast: true,
      beforeSubmit: (formData) => {
        const fileInput = uploadForm.querySelector('input[name="file"]');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
          FormUtilities.showToast("Please select an image file", "info");
          return false;
        }
        const file = fileInput.files[0];
        return FormUtilities.validateImageFile(file);
      },
      onSuccess: (response) => {
        //Get the image URL from response
        const imageUrl = response.imageUrl || response.data?.url;
        const message =
          response.message || "Profile image uploaded successfully!";

        FormUtilities.showToast(message, "success");

        //Update the header profile image immediately with cache buster
        if (imageUrl && window.updateProfileImage) {
          //Add timestamp to force browser to reload the image
          const urlWithTimestamp = imageUrl.includes("?")
            ? imageUrl + "&t=" + Date.now()
            : imageUrl + "?t=" + Date.now();
          window.updateProfileImage(urlWithTimestamp);
        }

        //Update profile image in the profile page if it exists
        const profileImg = document.querySelector(
          "#profile-image, .profile-image",
        );
        if (profileImg && imageUrl) {
          //Add timestamp to force reload here too
          const urlWithTimestamp = imageUrl.includes("?")
            ? imageUrl + "&t=" + Date.now()
            : imageUrl + "?t=" + Date.now();
          profileImg.src = urlWithTimestamp;
        }
      },
    });
  }

  //Initialize image delete form
  initImageDeleteForm() {
    const deleteForm = document.getElementById("delete-image-form");
    if (!deleteForm) return;

    deleteForm.addEventListener("submit", async (e) => {
      e.preventDefault();

      if (!confirm("Are you sure you want to delete your profile image?")) {
        return;
      }

      const submitBtn = deleteForm.querySelector('[type="submit"]');

      try {
        //Show consistent loading indicator
        if (window.loadingIndicator) {
          window.loadingIndicator.showOnElement(submitBtn, "Deleting...");
          window.loadingIndicator.show("Deleting profile image...");
        }

        const response = await ApiClient.delete(ApiUrls.profileDeleteImage());

        FormUtilities.showToast(
          response.message || "Profile image deleted successfully!",
          "success",
        );

        //Update the header profile image to default immediately
        if (window.updateProfileImage) {
          const defaultImageUrl = "img/profiledefault.jpg";
          window.updateProfileImage(defaultImageUrl);
        }

        //Clear any file input if present
        const fileInput = document.querySelector('input[name="file"]');
        if (fileInput) {
          fileInput.value = "";
        }
      } catch (error) {
        FormUtilities.showToast("Error: " + error.message, "error");
      } finally {
        //Hide loading indicators
        if (window.loadingIndicator) {
          window.loadingIndicator.hideOnElement(submitBtn);
          window.loadingIndicator.hide();
        }
      }
    });
  }

  //Initialize account delete button
  initAccountDeleteButton() {
    const deleteBtn = document.getElementById("button-delete");
    if (!deleteBtn) return;

    deleteBtn.addEventListener("click", async (e) => {
      e.preventDefault();

      const confirmation = prompt(
        'Are you sure you want to delete your account? Type "DELETE" to confirm:',
      );
      if (confirmation !== "DELETE") {
        return;
      }

      try {
        if (window.loadingIndicator) {
          window.loadingIndicator.show("Deleting account...");
        }
        await ApiClient.delete(ApiUrls.profileDeleteAccount());
        FormUtilities.showToast("Account deleted successfully", "success");
        //Redirect after a brief delay to show the toast
        setTimeout(() => {
          window.location.href = "login.php";
        }, 1500);
      } catch (error) {
        FormUtilities.showToast("Error: " + error.message, "error");
        //Hide loading on error
        if (window.loadingIndicator) {
          window.loadingIndicator.hide();
        }
      }
    });
  }

  //Handle loading errors with consistent error handling
  handleLoadingError() {
    if (window.showToast) {
      window.showToast(
        "Failed to load profile data. Please refresh the page.",
        "error",
      );
    }
    //Show fallback content with consistent styling
    const cardBody = document.getElementById("card-profile-outer");
    if (cardBody) {
      const errorDiv = document.createElement("div");
      errorDiv.className = "alert alert-danger";
      errorDiv.innerHTML = `
                <h5>Error Loading Profile</h5>
                <p>Unable to load profile data. Please refresh the page or try again later.</p>
                <button class="btn btn-primary" onclick="window.location.reload()">
                    <i class="fas fa-redo"></i> Refresh Page
                </button>
            `;
      cardBody.insertBefore(errorDiv, cardBody.firstChild);
    }
  }

}

//Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", async function () {
  //Create profile page loader instance
  const profileLoader = new ProfilePageLoader();

  //Make it globally accessible for debugging
  window.profileLoader = profileLoader;

  //Initialize the profile page
  await profileLoader.init();
});

//Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = ProfilePageLoader;
}
