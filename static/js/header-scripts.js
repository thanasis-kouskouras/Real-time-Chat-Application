/* HEADER SCRIPTS

Handles navbar functionality, badge updates, and WebSocket listeners. */

//Load combined unread message count (one-on-one & group chats)
function loadCombinedUnreadCount() {
  ApiClient.get(ApiUrls.groupsUnreadCount(), null, false)
    .then((data) => {
      if (data.success) {
        updateMessageBadge(data.unread_count);
      }
    })
    .catch((error) => {
      //Handle errors silently
    });
}

//Update message badge
function updateMessageBadge(count) {
  const link = document.querySelector('a[href="messages.php"]');

  if (!link) {
    return;
  }

  //Remove existing badge if present
  const existingBadge = link.querySelector("#message_header");
  if (existingBadge) {
    existingBadge.remove();
  }

  if (count > 0) {
    //Create and append new badge
    const badge = document.createElement("span");
    badge.id = "message_header";
    badge.className = "alert-notifications";
    badge.textContent = count;
    link.appendChild(badge);
  }
}

//Load combined notification count (friend requests & group invitations)
function loadCombinedNotificationCount() {
  ApiClient.get(ApiUrls.groupsNotificationCount(), null, false)
    .then((data) => {
      if (data.success) {
        updateNotificationBadge(data.notification_count);
      }
    })
    .catch((error) => {
      //Handle errors silently
    });
}

//Update notification badge
function updateNotificationBadge(count) {
  const link = document.querySelector('a[href="notifications.php"]');

  if (!link) {
    return;
  }

  //Remove existing badge if present
  const existingBadge = link.querySelector("#notify_header");
  if (existingBadge) {
    existingBadge.remove();
  }

  if (count > 0) {
    //Create and append new badge
    const badge = document.createElement("span");
    badge.id = "notify_header";
    badge.className = "alert-notifications";
    badge.textContent = count;
    link.appendChild(badge);
  }
}

//Listen for WebSocket updates to message count
function setupMessageCountWebSocket() {
  //Check if WebSocket is available from websocket-client.js
  if (typeof getWebSocket === "function") {
    const ws = getWebSocket();
    if (ws) {
      //Store original onmessage handler
      const originalOnMessage = ws.onmessage;

      ws.onmessage = function (event) {
        try {
          const data = JSON.parse(event.data);

          //Update message count on relevant events
          if (
            (data.type === "group_message" ||
              data.action === "group_message" ||
              data.type === "message" ||
              data.action === "message" ||
              data.action === "sendTextMessage" ||
              data.action === "sendAttachment") &&
            data.fromName !== "Me"
          ) {
            loadCombinedUnreadCount(); //Reload combined unread count

            //If on messages.php, reload the page to show new messages
            if (window.location.pathname.includes("messages.php")) {
              setTimeout(() => window.location.reload(), 500);
            }
          }

          //Update counter when messages are marked as read
          if (data.action === "readChat" || data.action === "readGroupChat") {
            loadCombinedUnreadCount();
          }

          //Update notification count on friend request events
          if (
            data.type === "friend_request" ||
            data.action === "friend_request" ||
            data.type === "friend_accepted" ||
            data.action === "friend_accepted" ||
            data.type === "friend_rejected" ||
            data.action === "friend_rejected" ||
            data.type === "friend_request_cancelled" ||
            data.action === "friend_request_cancelled"
          ) {
            loadCombinedNotificationCount();

            //If on notifications.php, reload the page to show new notifications
            if (window.location.pathname.includes("notifications.php")) {
              setTimeout(() => window.location.reload(), 500);
            }
          }

          //Update notification count on invitation events
          if (
            data.type === "group_invitation" ||
            data.action === "group_invitation"
          ) {
            loadCombinedNotificationCount();

            //If on notifications.php, reload the page to show new invitations
            if (window.location.pathname.includes("notifications.php")) {
              setTimeout(() => window.location.reload(), 500);
            }
          }

          //Update notification count when invitation is accepted/declined
          if (
            data.type === "member_joined" ||
            data.action === "member_joined"
          ) {
            loadCombinedNotificationCount();
          }

          //Update notification count on group notification events (real-time)
          if (
            data.type === "group_notification_created" ||
            data.action === "group_notification_created" ||
            data.type === "added_to_group" ||
            data.action === "added_to_group" ||
            data.type === "removed_from_group" ||
            data.action === "removed_from_group" ||
            data.type === "group_deleted" ||
            data.action === "group_deleted" ||
            data.type === "group_deactivated" ||
            data.action === "group_deactivated" ||
            data.type === "group_reactivated" ||
            data.action === "group_reactivated"
          ) {
            loadCombinedNotificationCount();
          }

          //Update message count when group status changes or friend is deleted
          if (
            data.type === "group_deactivated" ||
            data.action === "group_deactivated" ||
            data.type === "group_deleted" ||
            data.action === "group_deleted" ||
            data.type === "removed_from_group" ||
            data.action === "removed_from_group" ||
            data.type === "friend_deleted" ||
            data.action === "friend"
          ) {
            loadCombinedUnreadCount();
          }

          //Update notification count when a notification is acknowledged
          if (
            data.type === "group_notification_acknowledged" ||
            data.action === "group_notification_acknowledged"
          ) {
            loadCombinedNotificationCount();
          }
        } catch (e) {
          //Ignore WebSocket messages
        }

        //Call original handler if it exists
        if (originalOnMessage) {
          originalOnMessage.call(ws, event);
        }
      };
    }
  }
}

//Update profile image in header
function updateProfileImage(imageUrl) {
  const profileImg = document.getElementById("headerProfileImage");
  if (profileImg && imageUrl) {
    profileImg.src = imageUrl;
  }
}

//Load profile image on page load
async function loadProfileImage() {
  const userGuid =
    window.CURRENT_USER_GUID || document.getElementById("token")?.value;
  if (!userGuid) {
    return;
  }

  const profileImg = document.getElementById("headerProfileImage");
  if (!profileImg) {
    return;
  }

  try {
    //Fetch the profile image URL from the API
    const response = await ApiClient.get(
      ApiUrls.accountUserImage(userGuid),
      null,
      false,
    );

    if (response.success && response.url) {
      profileImg.src = response.url;
    } else {
      profileImg.src = "img/profiledefault.jpg";
    }
  } catch (error) {
    profileImg.src = "img/profiledefault.jpg";
  }
}

//Make functions globally available
window.updateProfileImage = updateProfileImage;
window.loadCombinedUnreadCount = loadCombinedUnreadCount;
window.loadCombinedNotificationCount = loadCombinedNotificationCount;

//Navbar functionality (Menu)
document.addEventListener("DOMContentLoaded", function () {
  //Only load counts if user is authenticated
  if (window.CURRENT_USER_GUID && window.CURRENT_USER_GUID !== "") {
    loadProfileImage();
    loadCombinedUnreadCount();
    loadCombinedNotificationCount();
    setTimeout(setupMessageCountWebSocket, 1000); //Setup WebSocket listener for real-time updates
  }

  var navbarCollapse = document.getElementById("navbarSupportedContent");
  var navbarToggler = document.querySelector(".navbar-toggler");
  if (!navbarCollapse || !navbarToggler) return;

  //Remove Bootstrap's data-bs-toggle to prevent conflicts (handle toggle manually)
  navbarToggler.removeAttribute("data-bs-toggle");
  navbarToggler.removeAttribute("data-bs-target");

  //Create Bootstrap Collapse instance with toggle disabled
  var bsCollapseInstance = new bootstrap.Collapse(navbarCollapse, {
    toggle: false,
  });

  //Get the container-fluid for adding menu-open class
  var containerFluid = document.querySelector(".navbar .container-fluid");

  //Update button state and container class when collapse state changes
  navbarCollapse.addEventListener("show.bs.collapse", function () {
    navbarToggler.setAttribute("aria-expanded", "true");
    navbarToggler.classList.remove("collapsed");
    if (containerFluid) {
      containerFluid.classList.add("menu-open");
    }
  });

  navbarCollapse.addEventListener("hide.bs.collapse", function () {
    navbarToggler.setAttribute("aria-expanded", "false");
    navbarToggler.classList.add("collapsed");
    if (containerFluid) {
      containerFluid.classList.remove("menu-open");
    }
  });

  //Handle toggler click manually (toggle open/close)
  navbarToggler.addEventListener("click", function (e) {
    e.preventDefault();
    e.stopPropagation();

    if (navbarCollapse.classList.contains("show")) {
      bsCollapseInstance.hide();
    } else {
      bsCollapseInstance.show();
    }
  });

  //Close navbar when clicking outside
  document.addEventListener("click", function (e) {
    var isOpen = navbarCollapse.classList.contains("show");
    if (!isOpen) return;

    var navbar = document.querySelector(".navbar");
    var clickedInside = navbar && navbar.contains(e.target);

    if (!clickedInside) {
      bsCollapseInstance.hide();
    }
  });

  //Close when clicking on nav links
  var navLinks = navbarCollapse.querySelectorAll(".nav-link");
  navLinks.forEach(function (link) {
    link.addEventListener("click", function () {
      if (navbarCollapse.classList.contains("show")) {
        bsCollapseInstance.hide();
      }
    });
  });
});
