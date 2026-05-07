/* MESSAGES PAGE

Handles unread messages display via API. */

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

//Load unread messages from API
async function loadUnreadMessages() {
  const messageCountDiv = document.querySelector(".message-count");
  const messageListDiv = document.getElementById("card-search");

  try {
    //Show loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.show("Loading messages...");
    }

    //Fetch unread messages from API
    const response = await ApiClient.get(ApiUrls.chatUnreadMessages());

    //Hide loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }

    if (!response.messages) {
      throw new Error("Invalid response format");
    }

    const messages = response.messages;
    const messageCount = messages.length;

    //Update message count badge
    const badge =
      messageCount > 0
        ? `<span class='badge bg-danger rounded-pill ms-2'>${messageCount}</span>`
        : "";

    if (messageCountDiv) {
      messageCountDiv.innerHTML = `
                <i class="fas fa-comment"></i> Direct Messages ${badge}
            `;
    }

    //Show message if no unread messages
    if (messageCount === 0) {
      messageListDiv.innerHTML =
        '<p class="text-center text-muted mb-2">No new direct messages</p>';
      return;
    }

    //Render message cards
    renderMessages(messages);
  } catch (error) {
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }
    messageListDiv.innerHTML =
      '<p class="text-center text-danger">Error loading messages. Please try again.</p>';
    if (window.showToast) {
      window.showToast(error.message || "Failed to load messages", "error");
    }
  }
}

//Render message cards
function renderMessages(messages) {
  const messageListDiv = document.getElementById("card-search");
  messageListDiv.innerHTML = "";

  messages.forEach((message) => {
    const username = escapeHtml(message.fromUsername);
    const unreadCount = message.unreadCount;

    //Handle message preview
    let preview;
    if (message.messagePreview === "<i>Deleted Message</i>") {
      //Deleted message
      preview = "<i>Deleted Message</i>";
    } else if (
      !message.messagePreview ||
      message.messagePreview.trim() === ""
    ) {
      //Empty preview means it's likely an attachment
      preview = '<i class="fas fa-paperclip"></i> Attachment';
    } else {
      //Regular text message
      preview = escapeHtml(message.messagePreview);
    }

    //Use profile image URL from API
    const imgUrl = message.profileImageUrl || "img/profiledefault.jpg";

    //Create message card
    const messageCard = document.createElement("div");
    messageCard.className = "message-item mb-2 p-3";

    //Use GUID for chat button
    const chatUrl = `chatbox.php?guid=${message.fromUserGuid}&type=user`;

    messageCard.innerHTML = `
            <div class='d-flex justify-content-between align-items-center'>
                <div class='d-flex align-items-center friend-card-left'>
                    <img id='profileImage' src='${imgUrl}' alt='Profile Image' class='msg-avatar'>
                    <div class='msg-content-wrap'>
                        <strong class='username-text msg-text-truncate' title='${username}'>${username}</strong>
                        <small class='text-muted msg-text-truncate'>${unreadCount} new message${unreadCount > 1 ? "s" : ""} • ${preview}</small>
                        ${message.lastMessageTime ? `<small class='text-muted d-block'>${formatDateTime(message.lastMessageTime)}</small>` : ""}
                    </div>
                </div>
                <div class='msg-right'>
                    <button class='app-btn app-btn-primary app-btn-fixed'
                            onclick="window.location.href='${chatUrl}'"><i class="fa-solid fa-comments"></i>Chat</button>
                </div>
            </div>
        `;

    messageListDiv.appendChild(messageCard);
  });

  //Remove tooltips from elements that aren't actually truncated
  removeUnnecessaryTooltips(messageListDiv);
}

//Remove tooltip (title attribute) from elements that aren't truncated
function removeUnnecessaryTooltips(container) {
  const elementsWithTitle = container.querySelectorAll("[title]");
  elementsWithTitle.forEach((element) => {
    //Check if the element is actually truncated
    if (element.scrollWidth <= element.clientWidth) {
      //Text is not truncated, remove tooltip
      element.removeAttribute("title");
    }
  });
}

//Make functions globally available for realtime updates
window.loadGroupMessages = loadGroupMessages;
window.loadUnreadMessages = loadUnreadMessages;

/*Setup WebSocket handlers for realtime updates on Messages page.
This function adds handlers for events not covered by main.js (group_deactivated, group_reactivated, group_deleted are handled by main.js). */
function setupMessagesPageWebSocket() {
  //Wait for wsClient to be available
  if (!window.wsClient) {
    setTimeout(setupMessagesPageWebSocket, 500);
    return;
  }

  //Handler for being removed from a group (reload group messages section)
  window.wsClient.addMessageHandler("removed_from_group", (_data) => {
    loadGroupMessages();
    if (typeof loadCombinedUnreadCount === "function") {
      loadCombinedUnreadCount();
    }
  });

  //Handler for member left, including when it triggers deactivation
  window.wsClient.addMessageHandler("member_left", (data) => {
    //If the group was deactivated as a result, reload
    if (data.group_deactivated) {
      loadGroupMessages();
      if (typeof loadCombinedUnreadCount === "function") {
        loadCombinedUnreadCount();
      }
    }
  });

  //Handler for new group messages (reload to show new messages)
  window.wsClient.addMessageHandler("group_message", (data) => {
    //Only reload if message is from someone else
    if (data.fromName !== "Me") {
      loadGroupMessages();
    }
  });

  //Handler for new direct messages (reload to show new messages)
  window.wsClient.addMessageHandler("message", (data) => {
    if (data.fromName !== "Me") {
      loadUnreadMessages();
    }
  });

  //Handler for friend deleted (reload direct messages section)
  window.wsClient.addMessageHandler("friend_deleted", (_data) => {
    loadUnreadMessages();
    if (typeof loadCombinedUnreadCount === "function") {
      loadCombinedUnreadCount();
    }
  });
}

//Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  //Load unread messages
  loadUnreadMessages();

  //Load group messages
  loadGroupMessages();

  // Setup WebSocket handlers for realtime updates (wait a bit for wsClient to be initialized)
  setTimeout(setupMessagesPageWebSocket, 1000);
});

//Update Group Messages section header with badge count
function updateGroupMessagesBadge(count) {
  const groupHeader = document.querySelector("#groupChatsSection h5");
  if (groupHeader) {
    const badge =
      count > 0
        ? `<span class='badge bg-danger rounded-pill ms-2'>${count}</span>`
        : "";
    groupHeader.innerHTML = `
            <i class="fas fa-users"></i> Group Messages ${badge}
        `;
  }
}

//Load group messages with unread counts
async function loadGroupMessages() {
  const groupListDiv = document.getElementById("groupChatsList");

  try {
    //Fetch groups from API
    const response = await ApiClient.get(ApiUrls.groupsList());

    if (!response.success || !response.groups) {
      throw new Error(response.message || "Invalid response format");
    }

    const groups = response.groups;

    //Show message if no groups
    if (groups.length === 0) {
      groupListDiv.innerHTML =
        '<p class="text-center text-muted mb-2">No group chats available</p>';
      return;
    }

    //Include groups with unread messages
    const groupsWithMessages = groups.filter(
      (group) => group.unread_count === undefined || group.unread_count > 0,
    );

    if (groupsWithMessages.length === 0) {
      groupListDiv.innerHTML =
        '<p class="text-center text-muted mb-2">No new group messages</p>';
      //Update group messages header with no badge
      updateGroupMessagesBadge(0);
      return;
    }

    //Update group messages header with badge count
    updateGroupMessagesBadge(groupsWithMessages.length);

    //Render group cards
    renderGroupMessages(groupsWithMessages);
  } catch (error) {
    groupListDiv.innerHTML =
      '<p class="text-center text-danger">Error loading group messages. Please try again.</p>';
    if (window.showToast) {
      window.showToast(
        error.message || "Failed to load group messages",
        "error",
      );
    }
  }
}

//Render group message cards
function renderGroupMessages(groups) {
  const groupListDiv = document.getElementById("groupChatsList");
  groupListDiv.innerHTML = "";

  groups.forEach((group) => {
    const groupGuid = group.group_guid;
    const groupName = escapeHtml(group.group_name);
    const unreadCount = group.unread_count || 0;

    //Get last message preview
    const lastMessage = group.last_message;
    let preview;
    if (lastMessage === "<i>Deleted Message</i>") {
      //Deleted message
      preview = "<i>Deleted Message</i>";
    } else if (
      !lastMessage ||
      lastMessage.trim() === "" ||
      lastMessage === "No messages yet"
    ) {
      preview = '<i class="fas fa-paperclip"></i> Attachment';
    } else {
      //Regular text message
      preview = escapeHtml(lastMessage);
    }

    //Generate group image URL
    const imgSrc = group.group_image_url;

    //Create group card
    const groupCard = document.createElement("div");
    groupCard.className = "message-item mb-2 p-3";

    groupCard.innerHTML = `
            <div class='d-flex justify-content-between align-items-center'>
                <div class='d-flex align-items-center friend-card-left'>
                    <img src='${imgSrc}' alt='Group Image' class='msg-avatar'>
                    <div class='msg-content-wrap'>
                        <strong class='groupname-text msg-text-truncate' title='${groupName}'>${groupName}</strong>
                        <small class='text-muted msg-text-truncate'>${unreadCount} new message${unreadCount > 1 ? "s" : ""} • ${preview}</small>
                        ${group.last_message_time ? `<small class='text-muted d-block'>${formatDateTime(group.last_message_time)}</small>` : ""}
                    </div>
                </div>
                <div class='msg-right'>
                    <button class='app-btn app-btn-primary app-btn-fixed'
                            onclick="window.location.href='chatbox.php?guid=${groupGuid}&type=group'"><i class="fa-solid fa-comments"></i>Chat</button>
                </div>
            </div>
        `;

    groupListDiv.appendChild(groupCard);
  });

  //Remove tooltips from elements that aren't actually truncated
  removeUnnecessaryTooltips(groupListDiv);
}
