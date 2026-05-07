/* FRIEND MANAGER MODULE

Handles friend-related actions using HTTP API. */

import { createUrl } from "./config.js";
import { showToast } from "./toast-notifications.js";

class FriendManager {
  async performAction(action = "add", toId, redirectUrl = "search.php") {
    if (action === "delete") {
      const msg = "Are you sure you want to delete this friend?";
      const ret = confirm(msg);
      if (ret === false) {
        return;
      }
    }

    if (action === "chat") {
      //Use GET redirect with guid parameter for one-on-one chat
      window.location.href = `chatbox.php?guid=${toId}&type=user`;
      return;
    }

    await this.callFriendAPI(action, toId, redirectUrl);
  }

  async callFriendAPI(action, toId, redirectUrl) {
    let requestData = {};

    if (action === "add") {
      requestData = { to_user_guid: toId };
    } else if (action === "accept") {
      requestData = { from_user_guid: toId };
    } else if (action === "reject") {
      requestData = { from_user_guid: toId };
    } else if (action === "delete") {
      requestData = { friend_user_guid: toId };
    } else if (action === "cancel") {
      requestData = { to_user_guid: toId };
    }

    //Show loading indicator
    const loadingMessages = {
      add: "Sending friend request...",
      accept: "Accepting friend request...",
      reject: "Declining friend request...",
      delete: "Removing friend...",
      cancel: "Canceling request...",
    };

    if (window.loadingIndicator) {
      window.loadingIndicator.show(loadingMessages[action] || "Processing...");
    }

    try {
      const response = await $.ajax({
        url: ApiUrls.friendsAction(action),
        method: "POST",
        contentType: "application/json",
        data: JSON.stringify(requestData),
      });

      if (response.success) {
        //Store success message in sessionStorage to show after redirect
        sessionStorage.setItem(
          "toastMessage",
          response.message || "Action completed successfully",
        );
        sessionStorage.setItem("toastType", "success");

        // Use the redirectUrl as-is if it already contains query parameters, otherwise preserve current URL parameters
        let targetUrl;
        if (redirectUrl.includes("?")) {
          //redirectUrl already has parameters, use it directly
          targetUrl = createUrl("/" + redirectUrl, false);
        } else {
          //No parameters in redirectUrl (preserve current URL parameters)
          const currentUrl = new URL(window.location.href);
          targetUrl = createUrl("/" + redirectUrl, false);
          const params = new URLSearchParams(currentUrl.search);
          if (params.toString()) {
            targetUrl += "?" + params.toString();
          }
        }

        //Redirect immediately (loading stays visible during redirect)
        window.location.href = targetUrl;
      } else {
        //Hide loading on error
        if (window.loadingIndicator) {
          window.loadingIndicator.hide();
        }

        //Show error toast after loading is hidden
        setTimeout(() => {
          showToast(
            "Error: " + (response.message || "Failed to perform friend action"),
            "error",
          );
        }, 100);
      }
    } catch (xhr) {
      //Hide loading on error
      if (window.loadingIndicator) {
        window.loadingIndicator.hide();
      }

      //Show error toast after loading is hidden
      setTimeout(() => {
        showToast(
          "Failed to perform friend action. Please try again.",
          "error",
        );
      }, 100);
    }
  }
}

//Create singleton instance
export const friendManager = new FriendManager();

//Legacy global function for backward compatibility
window.friendManager = (action, toId, get, input, redirectUrl) => {
  //Simplified (ignore 'get' and 'input' parameters since we only use HTTP API now)
  return friendManager.performAction(action, toId, redirectUrl);
};
