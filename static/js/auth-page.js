//AUTHENTICATION PAGE HANDLER

document.addEventListener("DOMContentLoaded", function () {
  //Clear any stale session storage from previous sessions
  sessionStorage.removeItem("toastMessage");
  sessionStorage.removeItem("toastType");
  sessionStorage.removeItem("pendingToast");

  //Login Form
  const loginForm = document.getElementById("login-form");
  if (loginForm) {
    new FormHandler(loginForm, {
      apiEndpoint: ApiUrls.authLogin(),
      beforeSubmit: (formData) => {
        return FormUtilities.validateRequired(
          formData,
          ["user_email", "user_password"],
          {
            user_email: "Email",
            user_password: "Password",
          },
        );
      },
      onSuccess: (response) => {
        //Show success toast notification (no page reload)
        FormUtilities.showToast(
          response.message || "Login successful!",
          "success",
        );

        //Redirect after showing success message
        setTimeout(() => {
          const redirectUrl = response.redirect;
          const appPath = window.APP_PATH ?? "";

          if (redirectUrl) {
            //If redirect is relative, prepend app path
            const fullRedirectUrl = redirectUrl.startsWith("/")
              ? redirectUrl
              : appPath + "/" + redirectUrl;
            window.location.href = fullRedirectUrl;
          } else {
            window.location.href = appPath + "/index.php";
          }
        }, 1000);
      },
    });
  }

  //Signup Form
  const signupForm = document.getElementById("signup-form");
  if (signupForm) {
    new FormHandler(signupForm, {
      apiEndpoint: ApiUrls.authRegister(),
      beforeSubmit: (formData) => {
        const requiredFields = [
          "user_username",
          "user_email",
          "user_password",
          "confirm_password",
        ];
        const fieldLabels = {
          user_username: "Username",
          user_email: "Email",
          user_password: "Password",
          confirm_password: "Confirm Password",
        };

        if (
          !FormUtilities.validateRequired(formData, requiredFields, fieldLabels)
        ) {
          return false;
        }

        //Password confirmation validation
        const data =
          formData instanceof FormData
            ? FormUtilities.formDataToObject(formData)
            : formData;
        if (data.user_password !== data.confirm_password) {
          FormUtilities.showToast("Passwords do not match", "error");
          return false;
        }

        //Password length validation
        if (data.user_password.length < 8) {
          FormUtilities.showToast(
            "Password must be at least 8 characters long",
            "error",
          );
          return false;
        }

        return true;
      },
      onSuccess: (response) => {
        //Show success toast notification (no page reload)
        FormUtilities.showToast(
          response.message ||
            "Registration successful! Please check your email to verify your account.",
          "success",
        );

        //Redirect to login page after showing success message
        setTimeout(() => {
          const appPath = window.APP_PATH ?? "";
          window.location.href = appPath + "/login.php";
        }, 2000);
      },
    });
  }

  //Forgot Password Form
  const forgotPasswordForm = document.getElementById("forgot-password-form");
  if (forgotPasswordForm) {
    new FormHandler(forgotPasswordForm, {
      apiEndpoint: ApiUrls.authForgotPassword(),
      beforeSubmit: (formData) => {
        return FormUtilities.validateRequired(formData, ["user_email"], {
          user_email: "Email",
        });
      },
      onSuccess: (response) => {
        //Show success toast and reset form (no page reload)
        FormUtilities.showToast(
          response.message ||
            "Password reset email sent! Please check your inbox.",
          "success",
        );
        forgotPasswordForm.reset();
      },
    });
  }

  //Reset Password Form
  const resetPasswordForm = document.getElementById("reset-password-form");
  if (resetPasswordForm) {
    new FormHandler(resetPasswordForm, {
      apiEndpoint: ApiUrls.authResetPassword(),
      beforeSubmit: (formData) => {
        const requiredFields = ["user_password", "confirm_password"];
        const fieldLabels = {
          user_password: "Password",
          confirm_password: "Confirm Password",
        };

        if (
          !FormUtilities.validateRequired(formData, requiredFields, fieldLabels)
        ) {
          return false;
        }

        //Password confirmation validation
        const data =
          formData instanceof FormData
            ? FormUtilities.formDataToObject(formData)
            : formData;
        if (data.user_password !== data.confirm_password) {
          FormUtilities.showToast("Passwords do not match", "error");
          return false;
        }

        //Password length validation
        if (data.user_password.length < 8) {
          FormUtilities.showToast(
            "Password must be at least 8 characters long",
            "error",
          );
          return false;
        }

        return true;
      },
      onSuccess: (response) => {
        //Show success toast notification (no page reload)
        FormUtilities.showToast(
          response.message || "Password reset successful!",
          "success",
        );

        //Redirect to login page after showing success message
        setTimeout(() => {
          const appPath = window.APP_PATH ?? "";
          window.location.href = appPath + "/login.php";
        }, 2000);
      },
    });
  }

  /* Logout functionality is handled in logout.js (loaded in header.php).
  This maintains separation of concerns while using consistent patterns. */
});
