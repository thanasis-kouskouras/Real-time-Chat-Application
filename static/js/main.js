/* MAIN APPLICATION ENTRY POINT

Imports all modules and initializes the application. */

//Import all modules
import { escapeHtml } from "./modules/config.js";
import { showToast, showMessageStatus } from "./modules/toast-notifications.js";
import { wsClient } from "./modules/websocket-client.js";
import {
  hideNotifyCounters,
  updateUserStatus,
  hideMessageSending,
  setFooterMessage,
  deleteNoMessageYetDiv,
} from "./modules/ui-updates.js";
import { chatboxUI } from "./modules/chatbox-ui.js";
import {
  send_message,
  isLiveChattingTo,
  add_to_message,
  add_group_to_message,
  add_from_message,
  deleteLocalMessage,
} from "./chat/chat-helpers.js";
import "./modules/friend-manager.js";

//Global state
let token = "";

//Initialize application
$(document).ready(function () {
  hideNotifyCounters();
  token = $("#token").val();

  //Set token for modules
  wsClient.setToken(token);

  const url = window.location.href;

  //Connect WebSocket if not on login/signup pages
  if (!url.includes("login") && !url.includes("sign")) {
    wsClient.connect();
    setupWebSocketHandlers();
  }

  //Setup chatbox page
  if (url.includes("chatbox.php")) {
    setupChatboxPage();
  }

  displayInitialMessages();
});

//SETUP WEBSOCKET MESSAGE HANDLERS
function setupWebSocketHandlers() {
  //Friend notifications
  wsClient.addMessageHandler("friend_request", (data) => {
    if (
      window.location.href.includes("notifications.php") ||
      window.location.href.includes("search.php")
    ) {
      setTimeout(() => window.location.reload(), 1000);
    }
  });

  wsClient.addMessageHandler("friend_accepted", (data) => {
    if (
      window.location.href.includes("friends.php") ||
      window.location.href.includes("messages.php") ||
      window.location.href.includes("search.php")
    ) {
      setTimeout(() => window.location.reload(), 1000);
    }
  });

  wsClient.addMessageHandler("friend_rejected", (data) => {
    if (
      window.location.href.includes("notifications.php") ||
      window.location.href.includes("search.php")
    ) {
      setTimeout(() => window.location.reload(), 1000);
    }
  });

  wsClient.addMessageHandler("friend_request_cancelled", (data) => {
    if (
      window.location.href.includes("notifications.php") ||
      window.location.href.includes("search.php")
    ) {
      setTimeout(() => window.location.reload(), 1000);
    }
  });

  //Group notifications (On groups.php, groups-realtime.js handles all group events via silent DOM updates)
  wsClient.addMessageHandler("member_joined", (data) => {
    const currentUserGuid =
      window.CURRENT_USER_GUID || $("#fromUserGuid").val();
    const joinedUserGuid = data.user_guid;
    const isOnGroupsPage = window.location.href.includes("groups.php");

    if (isOnGroupsPage) return;

    if (joinedUserGuid === currentUserGuid) {
      if (window.location.href.includes("messages.php")) {
        setTimeout(() => window.location.reload(), 1500);
      }
    }
  });

  wsClient.addMessageHandler("member_left", (data) => {
    const currentUserGuid =
      window.CURRENT_USER_GUID || $("#fromUserGuid").val();
    const isOnGroupChat =
      window.location.href.includes("chatbox.php") &&
      window.location.href.includes("guid=" + data.group_guid);

    //Check if this is about the current user being removed
    if (data.user_guid === currentUserGuid) {
      if (isOnGroupChat) {
        setTimeout(() => (window.location.href = "groups.php"), 1500);
      }
    }

    //If group was deactivated, redirect silently (no toast)
    if (data.group_deactivated && isOnGroupChat) {
      setTimeout(() => (window.location.href = "groups.php"), 1500);
    }
  });

  /* Direct notification when current user is removed from a group.
  This is sent directly to the removed user for realtime UI updates. */
  wsClient.addMessageHandler("removed_from_group", (data) => {
    const groupName = data.group_name || "a group";

    //On groups page, let groups-realtime.js handle everything
    if (window.location.href.includes("groups.php")) return;

    //If on messages page, reload to update the list
    if (window.location.href.includes("messages.php")) {
      if (typeof window.loadGroupMessages === "function") {
        window.loadGroupMessages();
      } else {
        setTimeout(() => window.location.reload(), 1500);
      }
    }

    //If on the removed group's chat page, redirect to groups page
    if (
      window.location.href.includes("chatbox.php") &&
      window.location.href.includes("guid=" + data.group_guid)
    ) {
      setTimeout(() => (window.location.href = "groups.php"), 1500);
    }
  });

  wsClient.addMessageHandler("friend_deleted", (data) => {
    const url = window.location.href;

    //Update the message count badge in header (removes unread count from deleted friend)
    if (typeof window.loadCombinedUnreadCount === "function") {
      window.loadCombinedUnreadCount();
    }

    if (url.includes("chatbox.php")) {
      const currentChatUserGuid =
        document.getElementById("toUserGuid")?.value ||
        document.getElementById("toUser")?.value;
      if (currentChatUserGuid && currentChatUserGuid === data.from_user_guid) {
        setTimeout(() => {
          window.location.href = "messages.php";
        }, 1500);
        return;
      }
    }

    //Reload messages page to update the direct messages list
    if (url.includes("messages.php")) {
      if (typeof window.loadUnreadMessages === "function") {
        window.loadUnreadMessages();
      } else {
        setTimeout(() => window.location.reload(), 1000);
      }
    } else if (url.includes("friends.php") || url.includes("search.php")) {
      setTimeout(() => window.location.reload(), 1000);
    }
  });

  //Group notifications
  wsClient.addMessageHandler("group_invitation", (data) => {
    if (window.location.href.includes("notifications.php")) {
      setTimeout(() => window.location.reload(), 1000);
    }
  });

  wsClient.addMessageHandler("group_deleted", (data) => {
    const groupName = data.group_name || "A group";

    //On groups page, let groups-realtime.js handle everything
    if (window.location.href.includes("groups.php")) return;

    //If on messages page, reload to update the list
    if (window.location.href.includes("messages.php")) {
      setTimeout(() => window.location.reload(), 1500);
    }

    //If on the deleted group's chat page, redirect to groups page
    if (
      window.location.href.includes("chatbox.php") &&
      window.location.href.includes("guid=" + data.group_guid)
    ) {
      setTimeout(() => (window.location.href = "groups.php"), 1500);
    }
  });

  wsClient.addMessageHandler("group_deactivated", (data) => {
    //If on messages page, reload to update the list (group messages should disappear)
    if (window.location.href.includes("messages.php")) {
      if (typeof window.loadGroupMessages === "function") {
        window.loadGroupMessages();
      } else {
        setTimeout(() => window.location.reload(), 1500);
      }
    }

    //If on the deactivated group's chat page, redirect to groups page
    if (
      window.location.href.includes("chatbox.php") &&
      window.location.href.includes("guid=" + data.group_guid)
    ) {
      setTimeout(() => (window.location.href = "groups.php"), 1500);
    }
  });

  wsClient.addMessageHandler("group_reactivated", (data) => {
    //If on messages page, reload to update the list
    if (window.location.href.includes("messages.php")) {
      if (typeof window.loadGroupMessages === "function") {
        window.loadGroupMessages();
      } else {
        setTimeout(() => window.location.reload(), 1500);
      }
    }
  });

  wsClient.addMessageHandler("group_settings_updated", (data) => {
    const currentGroupGuid = document.getElementById("groupGuid")?.value;
    if (currentGroupGuid && currentGroupGuid === data.group_guid) {
      setTimeout(() => window.location.reload(), 1000);
    }
  });

  wsClient.addMessageHandler("role_updated", (data) => {
    const currentGroupGuid = document.getElementById("groupGuid")?.value;
    if (currentGroupGuid && currentGroupGuid === data.group_guid) {
      setTimeout(() => window.location.reload(), 1000);
    }
  });

  //Chat message handlers
  wsClient.addMessageHandler("default", handleWebSocketMessage);
}

//HANDLE WEBSOCKET MESSAGES
function handleWebSocketMessage(data) {
  //Handle status updates
  if (data.action === "updateUserStatus") {
    updateUserStatus(data.friend_user_guid, data.userStatus, data.color);

    //Also update admin user management page if open
    if (
      window.location.pathname.includes("user-management.php") &&
      window.updateAdminUserStatus
    ) {
      window.updateAdminUserStatus(data.friend_user_guid, data.userStatus);
    }
    return;
  }

  let chatUserGuid =
    data.fromName === "Me" ? data.to : data.fromGuid || data.fromId;
  if (data.friend_user_guid !== undefined) {
    chatUserGuid = data.friend_user_guid;
  }

  //Handle user banned notification (force disconnect and redirect)
  if (data.type === "user_banned" || data.action === "user_banned") {
    alert(data.message || "Your account has been banned.");
    //Clear cookies and redirect to login
    document.cookie = "jwt=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    document.cookie =
      "remember_me=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    window.location.href = "login.php";
    return;
  }

  //Hide sending status when a response is recieved
  if (
    data.action === "sendTextMessage" ||
    (data.action === "sendAttachment" && data.attachment)
  ) {
    hideMessageSending();

    if (data.status === false) {
      showMessageStatus(data.statusMessage || "Failed to send message", false);
    } else if (data.fromName === "Me") {
      showMessageStatus("Message sent successfully", true);
      $("#chat_message").val("");
    }
  }

  //Handle group messages
  if (data.type === "group_message" || data.action === "group_message") {
    //Check if this message is for the current group chat
    const groupGuid = data.group_guid;
    if (!isLiveChattingTo(groupGuid)) {
      return;
    }

    // Check if initial messages are still loading
    if (
      window.chatboxInitialized === false &&
      window.location.href.includes("chatbox.php") &&
      data.fromName !== "Me"
    ) {
      window.pendingInstantMessages.push({
        handler: handleWebSocketMessage,
        data: data,
      });
      return;
    }

    deleteNoMessageYetDiv();
    const messageText = data.message || data.msg;
    const messageId = data.message_guid || data.chatId;
    const senderGuid = data.sender_guid;
    const senderName = data.sender_name || data.senderName;
    const messageDate = data.sent_at || data.date;

    //Sending a message implies the sender stopped typing (drop their entry from the typing indicator immediately instead of waiting for the timeout)
    if (data.fromName !== "Me" && window.groupChatManager && senderGuid) {
      window.groupChatManager.clearUserTyping(senderGuid);
    }

    if (data.fromName === "Me") {
      add_from_message(
        messageText,
        messageId,
        data.attachmentType,
        data.attachment,
        data.filename,
        messageDate,
      );
      hideMessageSending();
    } else {
      add_group_to_message(
        senderGuid,
        senderName,
        messageText,
        messageId,
        null,
        null,
        null,
        messageDate,
        data.sender_profile_url,
      );
    }
    return;
  }

  if (data.type === "group" && data.action === "sendAttachment") {
    //Check if this message is for the current group chat
    const groupGuid = data.group_guid;
    if (!isLiveChattingTo(groupGuid)) {
      return;
    }

    //Check if initial messages are still loading
    if (
      window.chatboxInitialized === false &&
      window.location.href.includes("chatbox.php") &&
      data.fromName !== "Me"
    ) {
      window.pendingInstantMessages.push({
        handler: handleWebSocketMessage,
        data: data,
      });
      return;
    }

    deleteNoMessageYetDiv();
    const messageText = data.msg || data.message || "";
    const messageId = data.message_guid || data.chatId;
    const senderGuid = data.senderGuid || data.sender_guid;
    const senderName = data.senderName || data.sender_name;
    const messageDate = data.date || data.sent_at;

    //Sending an attachment implies the sender stopped typing (drop their entry from the typing indicator immediately)
    if (data.fromName !== "Me" && window.groupChatManager && senderGuid) {
      window.groupChatManager.clearUserTyping(senderGuid);
    }

    if (data.fromName === "Me") {
      add_from_message(
        messageText,
        messageId,
        data.filetype,
        data.attachment,
        data.filename,
        messageDate,
      );
      hideMessageSending();
    } else {
      add_group_to_message(
        senderGuid,
        senderName,
        messageText,
        messageId,
        data.filetype,
        data.attachment,
        data.filename,
        messageDate,
        data.sender_profile_url,
      );
    }

    //Wait for media to load before scrolling
    if (
      window.chatboxUI &&
      typeof window.chatboxUI.waitForMediaAndScroll === "function"
    ) {
      setTimeout(() => window.chatboxUI.waitForMediaAndScroll(), 100);
    }
    return;
  }

  if (data.type === "group" && data.action === "deleteMessage") {
    //Check if this message is for the current group chat
    const groupGuid = data.group_guid;
    if (!isLiveChattingTo(groupGuid)) {
      return;
    }

    if (data.fromName === "Me") {
      deleteLocalMessage(data.chatId, "right");
    } else {
      deleteLocalMessage(data.chatId, "left");
    }
    //Scroll after deletion to ensure UI is updated
    if (
      window.chatboxUI &&
      typeof window.chatboxUI.scrollToBottom === "function"
    ) {
      setTimeout(() => window.chatboxUI.scrollToBottom(true), 100);
    }
    return;
  }

  //Background operation (errors should not show toast to the user)
  if (data.action === "readGroupChat") {
    return;
  }

  if (data.status === false) {
    if (!data.loggedIn) {
      window.location.href = "login.php";
    } else {
      setFooterMessage(data.statusMessage, false);
    }
  } else if (isLiveChattingTo(chatUserGuid)) {
    handleLiveChatMessage(data);
  } else {
    handleBackgroundMessage(data);
  }
}

//LIVE CHAT MESSAGE HANDLERS
function handleLiveChatMessage(data) {
  //Delegate to chatbox UI module if on chatbox page
  if (window.location.href.includes("chatbox.php")) {
    chatboxUI.handleLiveChatMessage(data);
    return;
  }

  //Fallback for other pages
  const isGroupChat = document.getElementById("isGroupChat")?.value === "1";

  switch (data.action) {
    case "sendTextMessage":
      deleteNoMessageYetDiv();
      if (data.fromName === "Me") {
        add_from_message(
          data.msg,
          data.chatId,
          data.attachmentType,
          data.attachment,
          data.filename,
          data.date,
        );
      } else {
        if (isGroupChat) {
          const senderGuid = data.senderGuid || data.sender_guid;
          const senderName = data.senderName || data.sender_name;
          const messageDate = data.date || data.sent_at;
          add_group_to_message(
            senderGuid,
            senderName,
            data.msg,
            data.chatId,
            null,
            null,
            data.filename,
            messageDate,
            data.sender_profile_url,
          );
        } else {
          add_to_message(
            window.getToUserGuid
              ? window.getToUserGuid()
              : window.getToUserGuid(),
            data.msg,
            data.chatId,
            null,
            null,
            data.filename,
            data.date,
            data.sender_profile_url,
          );
        }
      }
      break;

    case "deleteMessage":
      if (data.fromName === "Me") {
        deleteLocalMessage(data.chatId, "right");
      } else {
        deleteLocalMessage(data.chatId, "left");
      }
      //Scroll after deletion to ensure UI is updated
      if (
        window.chatboxUI &&
        typeof window.chatboxUI.scrollToBottom === "function"
      ) {
        setTimeout(() => window.chatboxUI.scrollToBottom(true), 100);
      }
      break;

    case "sendAttachment":
      deleteNoMessageYetDiv();
      if (data.fromName === "Me") {
        add_from_message(
          data.msg,
          data.chatId,
          data.filetype,
          data.attachment,
          data.filename,
          data.date,
        );
      } else {
        if (isGroupChat) {
          const senderGuid = data.senderGuid || data.sender_guid;
          const senderName = data.senderName || data.sender_name;
          const messageDate = data.date || data.sent_at;
          add_group_to_message(
            senderGuid,
            senderName,
            data.msg,
            data.chatId,
            data.filetype,
            data.attachment,
            data.filename,
            messageDate,
            data.sender_profile_url,
          );
        } else {
          add_to_message(
            window.getToUserGuid
              ? window.getToUserGuid()
              : window.getToUserGuid(),
            data.msg,
            data.chatId,
            data.filetype,
            data.attachment,
            data.filename,
            data.date,
            data.sender_profile_url,
          );
        }
      }
      break;
  }
}

function handleBackgroundMessage(data) {
  switch (data.action) {
    case "updateUserStatus":
      // This should not be reached since we handle it earlier in handleWebSocketMessage
      // But keep as fallback
      updateUserStatus(data.friend_user_guid, data.userStatus, data.color);

      // Also update admin user management page if open
      if (
        window.location.pathname.includes("user-management.php") &&
        window.updateAdminUserStatus
      ) {
        window.updateAdminUserStatus(data.friend_user_guid, data.userStatus);
      }
      break;
  }
}

//Setup chatbox page
function setupChatboxPage() {
  chatboxUI.initialize();
}

//Display initial error/success messages
function displayInitialMessages() {
  let msg = "";
  let messageType = null;

  if ($("#errorMessage").length) {
    msg = $("#errorMessage").val();
    messageType = false;
  }
  if ($("#successMessage").length) {
    msg = $("#successMessage").val();
    messageType = true;
  }
  if (msg) {
    setFooterMessage(msg, messageType);
  }
}

//Export to global scope
window.escapeHtml = escapeHtml;
window.send_message = (action, chatId, live) => {
  send_message(action, chatId, live, wsClient, token);
};
window.showToast = showToast;
window.websocketClient = wsClient;
