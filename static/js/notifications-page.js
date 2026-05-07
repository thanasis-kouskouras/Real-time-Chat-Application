/* NOTIFICATIONS PAGE

Handles friend request and group chat notifications display via API. */

//Format datetime string in European format (DD/MM/YYYY, HH:MM)
function formatDateTime(datetime) {
  if (!datetime) return "";
  try {
    const date = new Date(datetime);
    const options = {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    };
    return date.toLocaleString("en-GB", options);
  } catch (e) {
    return datetime;
  }
}

//Load friend request notifications from API
async function loadNotifications() {
  const notificationCountDiv = document.querySelector(".notification-count");
  const notificationListDiv = document.getElementById("card-search");

  try {
    //Fetch notifications from API
    const response = await ApiClient.get(
      ApiUrls.friendsGetPendingNotifications(),
    );

    if (!response.notifications) {
      throw new Error("Invalid response format");
    }

    const notifications = response.notifications;
    const notificationCount = notifications.length;

    //Update notification count badge
    const badge =
      notificationCount > 0
        ? `<span class='badge bg-danger rounded-pill ms-2'>${notificationCount}</span>`
        : "";

    if (notificationCountDiv) {
      notificationCountDiv.innerHTML = `
                <i class="fas fa-user-plus"></i> Friend Requests ${badge}
            `;
    }

    //Show message if no notifications
    if (notificationCount === 0) {
      notificationListDiv.innerHTML =
        '<p class="text-center text-muted mb-2">No pending friend requests.</p>';
      return;
    }

    //Render notification cards
    renderNotifications(notifications);
  } catch (error) {
    notificationListDiv.innerHTML =
      '<p class="text-center text-danger">Error loading notifications. Please try again.</p>';
    if (window.showToast) {
      window.showToast(
        error.message || "Failed to load notifications",
        "error",
      );
    }
  }
}

//Render friend request notification cards
function renderNotifications(notifications) {
  const notificationListDiv = document.getElementById("card-search");
  notificationListDiv.innerHTML = "";

  notifications.forEach((notification) => {
    const userIdentifier = notification.fromUserGuid;
    const usernameRaw = notification.fromUsername;
    const username = escapeHtml(notification.fromUsername);
    const datetime = notification.datetime;
    const canRespond = notification.canRespond;

    //Use profile image URL from API
    const imgUrl = notification.profileImageUrl || "img/profiledefault.jpg";

    //Format datetime in European format
    const formattedDate = formatDateTime(datetime);

    //Create notification card
    const notificationCard = document.createElement("div");
    notificationCard.className = "notification-item mb-3";

    notificationCard.innerHTML = `
            <h6 class='alert-primary p-2 rounded'>
                <p class='text-center mb-0'>Friend request from ${username}.</p>
                <p class='text-center text-muted small mb-0'>${formattedDate}</p>
            </h6>
            <div class='form-group mb-0 d-flex justify-content-between align-items-center'>
                <div id='left' class='d-flex align-items-center friend-card-left'>
                    <img id='profileImage' src='${imgUrl}' alt='Profile Image' class='friend-img-margin'>
                    <strong id='black' class='friend-name-truncate'></strong>
                </div>
                <div id='right' class='d-flex gap-2'>
                    ${
                      canRespond
                        ? `
                        <button class='app-btn app-btn-success app-btn-fixed'
                                onclick="friendManager('accept','${userIdentifier}',false,'','notifications.php')"
                                name='acceptrequest'><i class="fa-solid fa-check"></i>Accept</button>
                        <button class='app-btn app-btn-outline-danger app-btn-fixed'
                                onclick="friendManager('reject','${userIdentifier}',false,'','notifications.php')"
                                name='rejectrequest'><i class="fa-solid fa-xmark"></i>Decline</button>
                    `
                        : ""
                    }
                </div>
            </div>
            <br>
        `;

    //Set username and tooltip using DOM methods (safer for special characters)
    const strongElement = notificationCard.querySelector("#black");
    strongElement.textContent = usernameRaw;
    strongElement.title = usernameRaw;

    notificationListDiv.appendChild(notificationCard);
  });
}

//Load group chat notifications from API
async function loadGroupNotifications() {
  const groupNotificationCountDiv = document.querySelector(
    ".group-notification-count",
  );
  const groupNotificationListDiv = document.getElementById(
    "group-notifications-list",
  );

  try {
    const response = await ApiClient.get(ApiUrls.groupsGetGroupNotifications());

    if (!response.notifications) {
      throw new Error("Invalid response format");
    }

    const notifications = response.notifications;
    const notificationCount = notifications.length;

    //Update notification count badge
    const badge =
      notificationCount > 0
        ? `<span class='badge bg-danger rounded-pill ms-2'>${notificationCount}</span>`
        : "";

    if (groupNotificationCountDiv) {
      groupNotificationCountDiv.innerHTML = `
                <i class="fas fa-users"></i> Group Chat Notifications ${badge}
            `;
    }

    //Show message if no notifications
    if (notificationCount === 0) {
      groupNotificationListDiv.innerHTML =
        '<p class="text-center text-muted mb-2">No pending group notifications.</p>';
      return;
    }

    //Render group notification cards
    renderGroupNotifications(notifications);
  } catch (error) {
    groupNotificationListDiv.innerHTML =
      '<p class="text-center text-danger">Error loading group notifications. Please try again.</p>';
    if (window.showToast) {
      window.showToast(
        error.message || "Failed to load group notifications",
        "error",
      );
    }
  }
}

//Render group notification cards
function renderGroupNotifications(notifications) {
  const groupNotificationListDiv = document.getElementById(
    "group-notifications-list",
  );
  groupNotificationListDiv.innerHTML = "";

  notifications.forEach((notification) => {
    const notificationGuid = notification.notificationGuid;
    const groupGuid = notification.groupGuid;
    const groupName = escapeHtml(notification.groupName);
    const type = notification.type;
    const datetime = notification.datetime;
    const canChat = notification.canChat;

    //Create notification card
    const notificationCard = document.createElement("div");
    notificationCard.className = "notification-item mb-3";
    notificationCard.setAttribute("data-notification-guid", notificationGuid);

    //Determine message and alert class based on type
    let message = "";
    let alertClass = "alert-primary";

    switch (type) {
      case "added_to_group":
        message = `You were added to "${groupName}".`;
        alertClass = "alert-success";
        break;
      case "group_deleted":
        message = `Group "${groupName}" has been deleted.`;
        alertClass = "alert-warning";
        break;
      case "group_deleted_account_removed":
        message = `Group "${groupName}" has been deleted because its admin deleted their account.`;
        alertClass = "alert-warning";
        break;
      case "group_deactivated":
        message = `Group "${groupName}" has been deactivated.`;
        alertClass = "alert-warning";
        break;
      case "group_reactivated":
        message = `Group "${groupName}" has been reactivated.`;
        alertClass = "alert-warning";
        break;
      case "removed_from_group":
        message = `You were removed from "${groupName}".`;
        alertClass = "alert-danger";
        break;
      case "became_admin":
        message = `You are now the admin of "${groupName}".`;
        alertClass = "alert-warning";
        break;
      default:
        message = `Notification from group "${groupName}".`;
    }

    //Format datetime in European format
    const formattedDate = formatDateTime(datetime);

    // Use default group image
    const groupImgUrl = "img/groupdefault.png";

    //Build buttons
    let buttonsHtml = "";
    if ((type === "added_to_group" || type === "became_admin") && canChat) {
      buttonsHtml = `
                <button class='app-btn app-btn-secondary app-btn-fixed' onclick="acknowledgeGroupNotification('${notificationGuid}', null)"><i class="fa-solid fa-thumbs-up"></i>Got it</button>
                <button class='app-btn app-btn-outline-primary app-btn-fixed' onclick="acknowledgeGroupNotification('${notificationGuid}', '${groupGuid}')"><i class="fa-solid fa-comments"></i>Chat</button>
            `;
    } else {
      buttonsHtml = `
                <button class='app-btn app-btn-secondary app-btn-fixed' onclick="acknowledgeGroupNotification('${notificationGuid}', null)"><i class="fa-solid fa-thumbs-up"></i>Got it</button>
            `;
    }

    notificationCard.innerHTML = `
            <h6 class='${alertClass} p-2 rounded'>
                <p class='text-center mb-0'>${message}</p>
                <p class='text-center text-muted small mb-0'>${formattedDate}</p>
            </h6>
            <div class='form-group mb-0 d-flex justify-content-between align-items-center'>
                <div id='left' class='d-flex align-items-center friend-card-left'>
                    <img id='profileImage' src='${groupImgUrl}' alt='Group Image' class='friend-img-margin'>
                    <strong id='black' class='friend-name-truncate'></strong>
                </div>
                <div id='right' class='d-flex gap-2'>
                    ${buttonsHtml}
                </div>
            </div>
            <br>
        `;

    //Set group name and tooltip using DOM methods
    const strongElement = notificationCard.querySelector("#black");
    strongElement.textContent = notification.groupName;
    strongElement.title = notification.groupName;

    groupNotificationListDiv.appendChild(notificationCard);
  });
}

//Acknowledge (dismiss) a group notification
async function acknowledgeGroupNotification(
  notificationGuid,
  redirectToGroupGuid,
) {
  try {
    await ApiClient.post(ApiUrls.groupsAcknowledgeGroupNotification(), {
      notification_guid: notificationGuid,
    });

    //Remove the notification card from the UI
    const notificationCard = document.querySelector(
      `[data-notification-guid="${notificationGuid}"]`,
    );
    if (notificationCard) {
      notificationCard.remove();
    }

    //Update the count badge
    updateGroupNotificationCount();

    //Check if there are no more notifications
    const remainingNotifications = document.querySelectorAll(
      "#group-notifications-list .notification-item",
    );
    if (remainingNotifications.length === 0) {
      document.getElementById("group-notifications-list").innerHTML =
        '<p class="text-center text-muted mb-2">No pending group notifications</p>';
    }

    //Redirect to chat if requested
    if (redirectToGroupGuid) {
      window.location.href = `chatbox.php?guid=${redirectToGroupGuid}&type=group`;
    }
  } catch (error) {
    if (window.showToast) {
      window.showToast(
        error.message || "Failed to acknowledge notification",
        "error",
      );
    }
  }
}

//Update the group notification count badge
function updateGroupNotificationCount() {
  const groupNotificationCountDiv = document.querySelector(
    ".group-notification-count",
  );
  const remainingNotifications = document.querySelectorAll(
    "#group-notifications-list .notification-item",
  );
  const count = remainingNotifications.length;

  const badge =
    count > 0
      ? `<span class='badge bg-danger rounded-pill ms-2'>${count}</span>`
      : "";

  if (groupNotificationCountDiv) {
    groupNotificationCountDiv.innerHTML = `
            <i class="fas fa-users"></i> Group Chat Notifications ${badge}
        `;
  }
}

//Handle real-time group notification created event
function handleGroupNotificationCreated(_data) {
  loadGroupNotifications();
}

//Handle real-time group notification acknowledged event
function handleGroupNotificationAcknowledged(data) {
  //Remove the notification card from the UI if it exists
  const notificationCard = document.querySelector(
    `[data-notification-guid="${data.notification_guid}"]`,
  );
  if (notificationCard) {
    notificationCard.remove();
    updateGroupNotificationCount();

    //Check if there are no more notifications
    const remainingNotifications = document.querySelectorAll(
      "#group-notifications-list .notification-item",
    );
    if (remainingNotifications.length === 0) {
      document.getElementById("group-notifications-list").innerHTML =
        '<p class="text-center text-muted mb-2">No pending group notifications</p>';
    }
  }
}

//Handle real-time friend request event
function handleFriendRequestEvent(_data) {
  loadNotifications();
}

//Setup WebSocket handlers for real-time notifications
function setupWebSocketHandlers() {
  //Check if wsClient is available (might need to wait for module to load)
  if (typeof window.wsClient !== "undefined" && window.wsClient) {
    //Handle new group notification
    window.wsClient.addMessageHandler(
      "group_notification_created",
      handleGroupNotificationCreated,
    );

    //Handle notification acknowledged (from other tabs)
    window.wsClient.addMessageHandler(
      "group_notification_acknowledged",
      handleGroupNotificationAcknowledged,
    );

    //Handle friend request events
    window.wsClient.addMessageHandler(
      "friend_request",
      handleFriendRequestEvent,
    );
    window.wsClient.addMessageHandler(
      "friend_accepted",
      handleFriendRequestEvent,
    );
    window.wsClient.addMessageHandler(
      "friend_rejected",
      handleFriendRequestEvent,
    );
    window.wsClient.addMessageHandler(
      "friend_request_cancelled",
      handleFriendRequestEvent,
    );
  } else {
    //Retry after a short delay to wait for wsClient to be initialized
    setTimeout(setupWebSocketHandlers, 500);
  }
}

//Make acknowledgeGroupNotification available globally
window.acknowledgeGroupNotification = acknowledgeGroupNotification;

//Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  //Load friend request notifications
  loadNotifications();

  //Load group chat notifications
  loadGroupNotifications();

  //Setup WebSocket handlers for real-time updates
  setupWebSocketHandlers();
});
