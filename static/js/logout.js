/* LOGOUT HANDLER

Handles logout functionality across all pages. */

document.addEventListener("DOMContentLoaded", function () {
  //Handle logout links
  const logoutLinks = document.querySelectorAll("a[data-logout-link]");
  logoutLinks.forEach((link) => {
    link.addEventListener("click", async function (e) {
      e.preventDefault();

      try {
        /* Send logout message via WebSocket to mark user as logging out.
                This ensures the server knows it's a logout (not just disconnection). */
        if (
          window.wsClient &&
          window.wsClient.conn &&
          window.wsClient.conn.readyState === WebSocket.OPEN
        ) {
          try {
            window.wsClient.conn.send(
              JSON.stringify({
                action: "logout",
                type: "single",
              }),
            );
            //Give the server a moment to process the logout message
            await new Promise((resolve) => setTimeout(resolve, 100));
          } catch (wsError) {}
          //Close connection after sending logout message
          window.wsClient.conn.close(1000, "User logout");
        }

        //Show loading indicator with custom message
        if (window.loadingIndicator) {
          window.loadingIndicator.show("Logging out...");
        }

        //Use ApiClient.post() without its own loading indicator
        await ApiClient.post(ApiUrls.authLogout(), null, false);

        //Store success message to show after redirect
        sessionStorage.setItem("toastMessage", "Logged out successfully");
        sessionStorage.setItem("toastType", "success");

        //Redirect immediately (keep loading visible)
        window.location.href = "login.php";
      } catch (error) {
        //Store error message to show after redirect
        sessionStorage.setItem(
          "toastMessage",
          error.message || "Logout failed",
        );
        sessionStorage.setItem("toastType", "error");

        //Still redirect even if API fails (session cleanup on server side)
        window.location.href = "login.php";
      }
    });
  });
});
