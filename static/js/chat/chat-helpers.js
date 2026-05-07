/* CHAT MANAGER MODULE

Handles chat messages, attachments, and message UI. */

import { showMessageSending } from "../modules/ui-updates.js";
import { showToast } from "../modules/toast-notifications.js";
import { haveAttachment, sendAttachment } from "../modules/file-handler.js";
import { MessageRenderer } from "../modules/message-renderer.js";

//Global state
let cacheMessageDeleteId = undefined;

//Helper functions
export function getToUserGuid() {
  return $("#toUser").val();
}

export function getFromUserGuid() {
  return $("#fromUser").val();
}

export function isLiveChattingTo(toGuid) {
  const cwd = window.location.href;
  if (cwd.includes("chatbox.php")) {
    const isGroupChat = document.getElementById("isGroupChat")?.value === "1";
    if (isGroupChat) {
      const currentGroupGuid = document.getElementById("groupGuid")?.value;
      return currentGroupGuid && currentGroupGuid === toGuid;
    }

    const to = getToUserGuid() === toGuid;
    const from = getFromUserGuid() === toGuid;
    return to || from;
  }
  return false;
}

export function isMessageDeleted() {
  return cacheMessageDeleteId !== undefined;
}

//Message sending
export function send_message(
  action = "sendTextMessage",
  chatId = null,
  live = null,
  wsClient,
  token,
) {
  wsClient.getConnection();
  const to = getToUserGuid();
  const isGroupChat = document.getElementById("isGroupChat")?.value === "1";
  const groupGuid = document.getElementById("groupGuid")?.value;

  //Use chat managers if available
  if (action === "sendTextMessage") {
    const message = $("#chat_message").val().trim();
    if (message !== "") {
      if (message.length > 2000) {
        showToast("Message is too long (max 2000 characters).", "error");
        return;
      }
      showMessageSending();

      if (isGroupChat && window.groupChatManager) {
        window.groupChatManager.sendMessage(message);
        $("#chat_message").val("");
        return;
      } else if (!isGroupChat && window.chatManager) {
        window.chatManager.sendTextMessage(message);
        $("#chat_message").val("");
        return;
      }
    }
  } else if (action === "sendAttachment" && haveAttachment()) {
    showMessageSending();
    const fileInput = document.getElementById("actual-btn");
    const file = fileInput.files[0];

    if (isGroupChat && window.groupChatManager) {
      sendAttachment(
        file,
        {
          action: "sendAttachment",
          type: "group",
          from: token,
          group_guid: groupGuid,
          msg: "",
          filename: "",
          category: "chat",
        },
        wsClient,
      );
      fileInput.type = "text";
      fileInput.type = "file";
      document.getElementById("file-chosen").innerText = "";
      const clearFileBtn = document.getElementById("clear-file-btn");
      if (clearFileBtn) clearFileBtn.classList.add("d-none");
      return;
    }

    if (!isGroupChat && window.chatManager) {
      window.chatManager.sendAttachment(file);
    } else {
      sendAttachment(
        file,
        {
          action: "sendAttachment",
          type: isGroupChat ? "group" : "single",
          from: token,
          to: to,
          group_guid: groupGuid,
          msg: "",
          filename: "",
          category: "chat",
        },
        wsClient,
      );
    }

    fileInput.type = "text";
    fileInput.type = "file";
    document.getElementById("file-chosen").innerText = "";
    const clearFileBtn = document.getElementById("clear-file-btn");
    if (clearFileBtn) clearFileBtn.classList.add("d-none");
    return;
  } else if (action === "deleteMessage" && isMessageDeleted()) {
    if (!isGroupChat && window.chatManager) {
      window.chatManager.deleteMessage(chatId);
      cacheMessageDeleteId = undefined;
      return;
    }
  } else if (action === "readChat") {
    if (!isGroupChat && window.chatManager) {
      window.chatManager.markAsRead(chatId);
      return;
    }
  } else if (action === "readGroupChat") {
    /* Group chat mark as read, handled via WebSocket.
        No special handling needed here, will be processed by WebSocket. */
  }

  //Fallback to WebSocket implementation
  let data = {
    action: action,
    type: isGroupChat ? "group" : "single",
    from: token,
    to: to,
    msg: "",
    filename: "",
    category: "chat",
    live: live,
  };

  if (isGroupChat && groupGuid) {
    data.group_guid = groupGuid;
  }

  if (action === "deleteMessage" && isMessageDeleted()) {
    data.chatId = chatId;
    wsClient.send(data);
    cacheMessageDeleteId = undefined;
  } else if (action === "readChat") {
    data.chatId = chatId;
    wsClient.send(data);
  } else if (action === "readGroupChat") {
    wsClient.send(data);
  } else {
    data.msg = $("#chat_message").val().trim();
    if (data.msg !== "") {
      showMessageSending();
      wsClient.send(data);
    }
  }
}

//Message deletion
export function delete_message(messageId, direction = "right") {
  let ret = true;
  const msg = "Delete this message for all?";
  if (direction === "right") {
    ret = confirm(msg);
  }
  if (ret === true) {
    cacheMessageDeleteId = messageId;
    //send_message will be called from global context
    if (window.send_message) {
      window.send_message("deleteMessage", messageId);
    }
  }
}

/* Delete a message locally in the UI.
This function searches the entire DOM for the message, including messages
outside the viewport. */
export function deleteLocalMessage(messageId, direction = "right") {
  if (!messageId) {
    return;
  }
  //Try multiple methods to find the message element

  //1. Try by ID (primary method)
  let messageDiv = document.getElementById(messageId);

  //2. If not found by ID, try by data-message-id attribute (fallback)
  if (!messageDiv) {
    messageDiv = document.querySelector(`[data-message-id="${messageId}"]`);
  }

  //3. If still not found, try jQuery selector (for compatibility)
  if (!messageDiv) {
    const $div = $("#" + messageId);
    if ($div.length > 0) {
      messageDiv = $div[0];
    }
  }

  //If message element doesn't exist in DOM, it hasn't been loaded yet
  if (!messageDiv) {
    return;
  }

  //Convert to jQuery for easier manipulation
  const $div2 = $(messageDiv);
  const divMedia = $("#" + messageId + "_media");
  const chat_message = "<i>Deleted Message</i>";

  const pClass = direction === "right" ? "pFrom" : "pTo";
  const spanClasses =
    direction === "right"
      ? ".small_span_dark, .small_span_white"
      : ".small_span, .small_span_dark, .small_span_white";

  // Get timestamp before removing elements
  let timestamp = $div2.find(".media-timestamp span, " + spanClasses);

  //Filter out any audio/video player time elements
  timestamp = timestamp.filter(function () {
    const $this = $(this);
    //Exclude elements inside audio/video players
    if (
      $this.closest(
        ".custom-audio-player, .custom-video-player, .audio-controls, .video-controls",
      ).length > 0
    ) {
      return false;
    }
    //Exclude elements with player-specific classes
    if (
      $this.hasClass("current-time") ||
      $this.hasClass("duration") ||
      $this.hasClass("video-time")
    ) {
      return false;
    }
    return true;
  });

  if (timestamp.length === 0 && divMedia.length > 0) {
    timestamp = divMedia.find(".media-timestamp span, " + spanClasses);
  }
  const timestampHtml = timestamp.length > 0 ? timestamp[0].outerHTML : "";

  //Remove media div if exists
  if (divMedia && divMedia.length > 0) {
    divMedia.remove();
  }

  //Check if this is a group chat message
  const isGroupMessage =
    $div2.closest(".group-message-container").length > 0 ||
    $div2.siblings(".group-message-header").length > 0 ||
    $div2.parent().find(".group-message-header").length > 0;

  if (direction === "right") {
    //For sent messages (no wrapper needed for deleted messages)

    //Remove all children
    $div2.children().remove();

    //Build content without wrapper (no delete button for deleted messages)
    let content = "<p class='" + pClass + "'>" + chat_message;
    if (timestampHtml) {
      content += "<br>" + timestampHtml;
    }
    content += "</p>";
    $div2.append(content);
  } else if (isGroupMessage) {
    //For group chat messages (profile image and name are in sibling header, not inside message div)

    //Replace the message content
    $div2.children().remove();

    //Build deleted message content
    let content = "<p class='" + pClass + "'>" + chat_message;
    if (timestampHtml) {
      content += "<br>" + timestampHtml;
    }
    content += "</p>";
    $div2.append(content);
  } else {
    //For one-to-one received messages (preserve profile image)

    //Find and save the profile image
    const $profileImg = $div2.find(
      '#profileImgInMessage, img[id="profileImgInMessage"]',
    );
    const profileImgHtml =
      $profileImg.length > 0 ? $profileImg[0].outerHTML : "";

    //Find and save the unread dot if exists
    const $unreadDot = $div2.find(".dotBlock, .dot");
    const unreadDotHtml = $unreadDot.length > 0 ? $unreadDot[0].outerHTML : "";

    //Remove all children
    $div2.children().remove();

    //Rebuild with profile image preserved
    let content = "";

    //Add unread dot if it existed
    if (unreadDotHtml) {
      content += unreadDotHtml;
    }

    //Add profile image back
    if (profileImgHtml) {
      content += profileImgHtml;
    } else {
      //Fallback (create default profile image)
      content +=
        "<img id='profileImgInMessage' src='img/profiledefault.jpg' onerror=\"this.src='img/profiledefault.jpg';\">";
    }

    //Add the deleted message text
    content += "<p class='" + pClass + "'>" + chat_message;
    if (timestampHtml) {
      content += "<br>" + timestampHtml;
    }
    content += "</p>";

    $div2.append(content);
  }
}

//Read messages
export function readMessage() {
  let hasUnReadMessage = false;
  const dots = document.getElementsByClassName("dotBlock");
  for (let i = 0; i < dots.length; i++) {
    if (dots[i].style.display !== "none") {
      hasUnReadMessage = true;
      dots[i].style.display = "none";
    }
  }

  //Send readChat to mark messages as read in the database
  if (window.send_message) {
    window.send_message("readChat");
  }
}

//Message UI functions
export function add_to_message(
  chatToId,
  chat_message,
  chatId,
  type = null,
  url = null,
  filename = null,
  date = null,
  senderProfileUrl = null,
) {
  const renderer =
    window.messageRenderer ||
    new MessageRenderer(window.CURRENT_USER_GUID || getFromUserGuid(), {
      autoScroll: true,
      waitForMedia: true,
    });

  const messageData = {
    chatId: chatId,
    msg: chat_message,
    filetype: type,
    attachment: url,
    filename: filename,
    date: date,
    from_guid: chatToId,
    sender_guid: chatToId,
    sender_profile_url: senderProfileUrl,
  };

  renderer.renderInstantMessage(messageData, {
    isGroup: false,
    friendGuid: chatToId,
  });
}

export function add_group_to_message(
  senderId,
  senderName,
  chat_message,
  chatId,
  type = null,
  url = null,
  filename = null,
  date = null,
  senderProfileUrl = null,
) {
  const renderer =
    window.messageRenderer ||
    new MessageRenderer(window.CURRENT_USER_GUID || getFromUserGuid(), {
      autoScroll: true,
      waitForMedia: true,
    });

  const messageData = {
    chatId: chatId,
    msg: chat_message,
    filetype: type,
    attachment: url,
    filename: filename,
    date: date,
    sender_guid: senderId,
    senderName: senderName,
    sender_profile_url: senderProfileUrl,
  };

  renderer.renderInstantMessage(messageData, {
    isGroup: true,
  });
}

export function add_from_message(
  chat_message,
  chat_id,
  type = null,
  url = null,
  filename = null,
  date = null,
) {
  const renderer =
    window.messageRenderer ||
    new MessageRenderer(window.CURRENT_USER_GUID || getFromUserGuid(), {
      autoScroll: true,
      waitForMedia: true,
    });

  const messageData = {
    chatId: chat_id,
    msg: chat_message,
    filetype: type,
    attachment: url,
    filename: filename,
    date: date,
    from_guid: window.CURRENT_USER_GUID || getFromUserGuid(),
  };

  renderer.renderInstantMessage(messageData, {
    isGroup: false,
  });
}

//Global exports
window.getToUserGuid = getToUserGuid;
window.getFromUserGuid = getFromUserGuid;
window.isLiveChattingTo = isLiveChattingTo;
window.delete_message = delete_message;
window.deleteLocalMessage = deleteLocalMessage;
window.readMessage = readMessage;
window.add_to_message = add_to_message;
window.add_group_to_message = add_group_to_message;
window.add_from_message = add_from_message;
