/* UI UPDATES MODULE

Handles DOM updates, counters, status indicators, and UI state management. */

import { STATUS_UPDATE_DEBOUNCE_MS } from "./config.js";

//Counter management
export function hideNotifyCounters() {
  const spanElement = document.getElementById("notify_header");
  if (spanElement) {
    let cv = spanElement.innerText.trim();
    if (Number(cv) === 0) {
      spanElement.classList.add("d-none");
    }
  }

  const spanElementM = document.getElementById("message_header");
  if (spanElementM) {
    let cv = spanElementM.innerText.trim();
    if (Number(cv) === 0) {
      spanElementM.classList.add("d-none");
    }
  }
}

export function updateNotificationsCounter(counter) {
  const spanElement = document.getElementById("notify_header");
  if (spanElement) {
    if (counter === 0) {
      spanElement.classList.add("d-none");
    } else {
      spanElement.classList.remove("d-none");
      spanElement.innerText = counter;
    }
  }
}

export function updateMessagesCounter(counter) {
  const spanElement = document.getElementById("message_header");
  if (spanElement) {
    if (counter === 0) {
      spanElement.classList.add("d-none");
    } else {
      spanElement.classList.remove("d-none");
      spanElement.innerText = counter;
    }
  }
}

//Status updates
const statusUpdateTimers = {};

export function updateUserStatus(userGuid, status, color) {
  const url = window.location.href;

  if (url.includes("friends.php") || url.includes("friends-search.php")) {
    updateFriendIcon(userGuid, status);
  }

  if (url.includes("chatbox.php")) {
    updateChatStatus(userGuid, status, color);
    updateGroupMemberStatus(userGuid, status);
  }
}

function updateFriendIcon(friendUserGuid, userStatus) {
  if (window.updateFriendStatusAndRerender) {
    //Use the page's function to update status and re-render with sorting
    window.updateFriendStatusAndRerender(friendUserGuid, userStatus);
  } else {
    //Fallback, just update the image styling without re-sorting
    const friendImages = document.querySelectorAll(
      `img[data-friend-guid="${friendUserGuid}"]`,
    );

    friendImages.forEach((img) => {
      const isActive = userStatus === "Active";
      const newId = isActive
        ? "profileImgInFriendsActive"
        : "profileImgInFriendsInactive";

      img.id = newId;

      img.style.transition = "all 0.3s ease";
      if (isActive) {
        img.style.transform = "scale(1.05)";
        setTimeout(() => {
          img.style.transform = "scale(1)";
        }, 300);
      }
    });
  }
}

function updateGroupMemberStatus(userGuid, status) {
  if (statusUpdateTimers[userGuid]) {
    clearTimeout(statusUpdateTimers[userGuid]);
  }

  statusUpdateTimers[userGuid] = setTimeout(() => {
    const isGroupChat = document.getElementById("isGroupChat")?.value === "1";

    if (
      isGroupChat &&
      window.groupChatManager &&
      window.groupChatManager.members
    ) {
      //Update status and re-sort members
      const memberIndex = window.groupChatManager.members.findIndex(
        (m) => m.user_guid === userGuid,
      );

      if (memberIndex !== -1) {
        //Update the member's status in the data
        window.groupChatManager.members[memberIndex].user_status = status;

        //Re-render the sidebar with proper sorting
        window.groupChatManager.updateMembersSidebar();

        //Update the online count in header
        window.groupChatManager.updateMemberCount();
      }
    } else {
      //Fallback, just update the DOM elements without re-sorting
      const memberRow = document.getElementById("member-" + userGuid);
      if (memberRow) {
        //Check if this member is banned
        const statusLabel = document.getElementById("label-" + userGuid);
        if (statusLabel && statusLabel.textContent.trim() === "Banned") {
          //User is banned, don't update their status
          return;
        }

        const isActive = status === "Active";

        const statusDot = document.getElementById("status-" + userGuid);
        if (statusDot) {
          statusDot.className =
            "status-dot " + (isActive ? "status-online" : "status-offline");
        }

        if (statusLabel) {
          statusLabel.textContent = isActive ? "Active" : "Offline";
          statusLabel.className =
            "status-label " + (isActive ? "active" : "offline");
        }

        memberRow.setAttribute("data-status", status.toLowerCase());
      }
    }

    delete statusUpdateTimers[userGuid];
  }, STATUS_UPDATE_DEBOUNCE_MS);
}

function updateChatStatus(userGuid, status, color) {
  //Check if this is a one-on-one chat
  const isGroupChat = document.getElementById("isGroupChat")?.value === "1";

  if (!isGroupChat) {
    //For one-on-one chat, check if this status update is for the current chat user
    const toUserGuid =
      document.getElementById("toUser")?.value ||
      (window.getToUserGuid && window.getToUserGuid()) ||
      null;

    //Only update if this status is for the user currently chatting with
    if (toUserGuid && toUserGuid === userGuid) {
      const friendStatusDisplay = document.getElementById(
        "friend-status-display",
      );
      if (friendStatusDisplay) {
        friendStatusDisplay.textContent = status;
        friendStatusDisplay.className =
          status === "Active" ? "onlineColor" : "offlineColor";
        friendStatusDisplay.style.color = color;
      }
    }
  }
}

//Button state management
export function setMediaButtonsState(disabled, excludeButton = null) {
  const buttons = ["audioCallButton", "videoCallButton", "photoButton"];
  buttons.forEach((buttonId) => {
    if (buttonId !== excludeButton) {
      const button = document.getElementById(buttonId);
      if (button) {
        button.disabled = disabled;
        button.classList.toggle("ui-disabled", disabled);
      }
    }
  });
}

export function isButtonDisabled(buttonId) {
  const button = document.getElementById(buttonId);
  return button && button.disabled;
}

//Message UI
export function showMessageSending() {
  //Use chat manager if available
  const isGroupChat = document.getElementById("isGroupChat")?.value === "1";

  if (isGroupChat && window.groupChatManager) {
    return;
  } else if (!isGroupChat && window.chatManager) {
    if (window.chatManager.showSending) {
      window.chatManager.showSending();
      return;
    }
  }

  //Fallback to original implementation
  const sendButton = document.getElementById("send");
  if (sendButton) {
    sendButton.disabled = true;
    sendButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
  }
}

export function hideMessageSending() {
  //Use chat manager if available
  const isGroupChat = document.getElementById("isGroupChat")?.value === "1";

  if (
    isGroupChat &&
    window.groupChatManager &&
    window.groupChatManager.hideSending
  ) {
    window.groupChatManager.hideSending();
    return;
  } else if (
    !isGroupChat &&
    window.chatManager &&
    window.chatManager.hideSending
  ) {
    window.chatManager.hideSending();
    return;
  }

  //Fallback to original implementation for both group and one-on-one chats
  const sendButton = document.getElementById("send");
  if (sendButton) {
    sendButton.disabled = false;
    sendButton.innerHTML = '<i class="fa fa-paper-plane"></i>';
    sendButton.removeAttribute("disabled");
    sendButton.classList.remove("ui-disabled");
  }

  //Re-enable other common button IDs
  const sendButton2 = document.getElementById("send-button");
  if (sendButton2) {
    sendButton2.disabled = false;
    sendButton2.innerHTML = '<i class="fa fa-paper-plane"></i>';
    sendButton2.removeAttribute("disabled");
    sendButton2.classList.remove("ui-disabled");
  }

  //Re-enable media buttons after successful send
  setMediaButtonsState(false);
}

//Footer messages (converted to toast notifications)
export function setFooterMessage(message, type = false) {
  if (typeof showToast === "function") {
    if (type === false) {
      showToast(message, "error");
    } else if (type === true) {
      showToast(message, "success");
    }
  }
}

//Utility functions
export function deleteNoMessageYetDiv() {
  const div = $("#noMessageYet");
  if (div) {
    div.remove();
  }
}

export function redirect(url) {
  window.location.href = url;
}

//Legacy global functions for backward compatibility
window.hideNotifyCounters = hideNotifyCounters;
window.updateNotificationsCounter = updateNotificationsCounter;
window.updateMessagesCounter = updateMessagesCounter;
window.updateUserStatus = updateUserStatus;
window.setMediaButtonsState = setMediaButtonsState;
window.isButtonDisabled = isButtonDisabled;
window.showMessageSending = showMessageSending;
window.hideMessageSending = hideMessageSending;
window.setFooterMessage = setFooterMessage;
window.deleteNoMessageYetDiv = deleteNoMessageYetDiv;
window.redirect = redirect;
