/* GROUP CHAT MANAGER

Handles group info modal, member management, group settings, and WebSocket integration. */

class GroupChatManager {
  constructor() {
    this.currentGroupGuid = null;
    this.currentUserGuid = null;
    this.isAdmin = false;
    this.groupData = null;
    this.members = [];
    this.friends = [];
    this.typingTimeouts = new Map(); //Per-user safety timeouts
    this.typingUsers = new Map(); //Track who is typing
    this.messageQueue = []; //Queue messages if WebSocket is not ready
    this.isGroupActive = true;
  }

  //Initialize the group chat manager
  init(groupGuid, userGuid) {
    this.currentGroupGuid = groupGuid;
    this.currentUserGuid = userGuid;

    //Bind event listeners
    this.bindEvents();

    //Load initial data
    this.loadGroupDetails();

    //Setup typing indicator for message input
    this.setupTypingIndicatorInput();

    //Process any queued messages
    this.processMessageQueue();
  }

  //Get WebSocket connection
  getWebSocket() {
    if (typeof getWebSocket === "function") {
      return getWebSocket();
    }

    return null;
  }

  //Handle incoming WebSocket messages
  handleWebSocketMessage(data) {
    switch (data.type || data.action) {
      case "group_message":
        this.handleGroupMessage(data);
        break;
      case "group_typing":
        this.handleTypingIndicator(data);
        break;
      case "member_joined":
        this.handleMemberJoined(data);
        break;
      case "member_left":
        this.handleMemberLeft(data);
        break;
      case "group_invitation":
        this.handleGroupInvitation(data);
        break;
      case "group_settings_updated":
        this.handleSettingsUpdated(data);
        break;
      case "role_updated":
        this.handleRoleUpdated(data);
        break;
      case "group_deactivated":
        this.handleGroupDeactivated(data);
        break;
      case "group_reactivated":
        this.handleGroupReactivated(data);
        break;
      case "updateUserStatus":
        this.handleMemberStatusUpdate(data);
        break;
      case "user_banned_broadcast":
      case "userBanned":
        this.handleUserBanned(data);
        break;
      case "user_unbanned_broadcast":
      case "userUnbanned":
        this.handleUserUnbanned(data);
        break;
    }
  }

  //Send message via WebSocket (only method for messages)
  sendMessage(message) {
    if (!this.currentGroupGuid) {
      return false;
    }

    //Check if group is active before sending
    if (!this.isGroupActive) {
      alert(
        "Cannot send messages to a deactivated group. The group needs at least 3 members to be active.",
      );
      return false;
    }

    //Get fresh WebSocket reference
    const ws = this.getWebSocket();

    //Check WebSocket connection
    if (!ws || ws.readyState !== WebSocket.OPEN) {
      alert("Connection lost. Please refresh the page to reconnect.");
      return false;
    }

    const messageData = {
      action: "group_message",
      type: "group",
      group_guid: this.currentGroupGuid,
      msg: message,
      from: $("#token").val(),
      timestamp: new Date().toISOString(),
    };

    //Send via WebSocket (direct client-to-server-to-clients)
    ws.send(JSON.stringify(messageData));
    return true;
  }

  //Process queued messages
  processMessageQueue() {
    if (this.messageQueue.length === 0) {
      return;
    }

    const ws = this.getWebSocket();
    while (
      this.messageQueue.length > 0 &&
      ws &&
      ws.readyState === WebSocket.OPEN
    ) {
      const messageData = this.messageQueue.shift();
      ws.send(JSON.stringify(messageData));
    }
  }

  //Send typing indicator
  sendTypingIndicator(isTyping) {
    if (!this.currentGroupGuid) {
      return;
    }

    const typingData = {
      action: "group_typing",
      type: "group",
      group_guid: this.currentGroupGuid,
      is_typing: isTyping,
      from: $("#token").val(),
    };

    const ws = this.getWebSocket();
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify(typingData));
    }
  }

  //Handle incoming group message
  handleGroupMessage(data) {
    //Only process if this message is for the current group
    if (data.group_guid != this.currentGroupGuid) {
      return;
    }

    // Check if initial messages are still loading
    if (window.chatboxInitialized === false && data.fromName !== "Me") {
      window.pendingInstantMessages.push({
        handler: this.handleGroupMessage.bind(this),
        data: data,
      });
      return;
    }

    //Display the message immediately
    this.displayNewMessage(data);

    //Update unread counts if not from current user
    if (data.sender_guid != this.currentUserGuid) {
      this.updateUnreadCount(1);
    }
  }

  //Display new message in the chat
  displayNewMessage(data) {
    //Check if we're on the chatbox page
    const url = window.location.href;
    if (!url.includes("chatbox.php")) {
      return;
    }

    //Use existing message display functions from utilities.js
    if (data.sender_guid == this.currentUserGuid || data.fromName === "Me") {
      //Message from current user
      add_from_message(
        data.message || data.msg,
        data.message_guid || data.chatId,
        data.attachmentType || null,
        data.attachment || null,
        data.filename || null,
        data.date || data.sent_at,
      );
    } else {
      //Message from other user
      add_group_to_message(
        data.sender_guid,
        data.sender_name,
        data.message || data.msg,
        data.message_guid || data.chatId,
        data.attachmentType || null,
        data.attachment || null,
        data.filename || null,
        data.date || data.sent_at,
        data.sender_profile_url || null,
      );
    }
  }

  //Update unread count
  updateUnreadCount(increment) {
    //Update the unread badge in the header
    const messageHeader = document.getElementById("message_header");
    if (messageHeader) {
      let currentCount = parseInt(messageHeader.textContent) || 0;
      currentCount += increment;

      if (currentCount > 0) {
        messageHeader.textContent = currentCount;
        messageHeader.classList.remove("d-none");
      } else {
        messageHeader.classList.add("d-none");
      }
    }

    //Update group-specific unread count if on messages page
    const groupUnreadBadge = document.querySelector(
      `[data-group-guid="${this.currentGroupGuid}"] .unread-badge`,
    );
    if (groupUnreadBadge) {
      let groupCount = parseInt(groupUnreadBadge.textContent) || 0;
      groupCount += increment;

      if (groupCount > 0) {
        groupUnreadBadge.textContent = groupCount;
        groupUnreadBadge.classList.remove("d-none");
      } else {
        groupUnreadBadge.classList.add("d-none");
      }
    }
  }

  //Handle typing indicator
  handleTypingIndicator(data) {
    //Only process if this event is for the current group
    if (data.group_guid != this.currentGroupGuid) {
      return;
    }

    //Don't show typing indicator for current user
    if (data.user_guid == this.currentUserGuid) {
      return;
    }

    const userGuid = data.user_guid;

    if (data.is_typing) {
      //Add user to typing users map
      this.typingUsers.set(userGuid, data.username);

      //Clear existing per-user safety timeout
      if (this.typingTimeouts.has(userGuid)) {
        clearTimeout(this.typingTimeouts.get(userGuid));
      }

      //Safety net (3s)
      this.typingTimeouts.set(
        userGuid,
        setTimeout(() => {
          this.typingTimeouts.delete(userGuid);
          this.typingUsers.delete(userGuid);
          this.updateTypingIndicator();
        }, 3000),
      );
    } else {
      //Explicit stop (clear safety timeout and remove user)
      if (this.typingTimeouts.has(userGuid)) {
        clearTimeout(this.typingTimeouts.get(userGuid));
        this.typingTimeouts.delete(userGuid);
      }
      this.typingUsers.delete(userGuid);
    }

    //Update the typing indicator display
    this.updateTypingIndicator();
  }

  //Clear typing state for a user (called when their message arrives, sending a message implies they stopped typing, so we shouldn't keep showing the indicator)
  clearUserTyping(userGuid) {
    if (userGuid && this.typingUsers.has(userGuid)) {
      this.typingUsers.delete(userGuid);
      this.updateTypingIndicator();
    }
  }

  //Update typing indicator display
  updateTypingIndicator() {
    const url = window.location.href;
    if (!url.includes("chatbox.php")) {
      return;
    }

    let typingIndicator = document.getElementById("typing-indicator");

    //Create typing indicator element if it doesn't exist
    if (!typingIndicator) {
      typingIndicator = document.createElement("div");
      typingIndicator.id = "typing-indicator";
    }

    /* Re-append to keep the indicator pinned at the bottom of #bodyMsg even
    after new messages have been inserted between calls. appendChild on an
    existing node moves it; it does not duplicate. */
    const bodyMsg = document.getElementById("bodyMsg");
    if (bodyMsg) {
      bodyMsg.appendChild(typingIndicator);
    }
    if (this.typingUsers.size === 0) {
      typingIndicator.textContent = "";
      typingIndicator.style.visibility = "hidden";
    } else if (this.typingUsers.size === 1) {
      const username = Array.from(this.typingUsers.values())[0];
      typingIndicator.innerHTML = `${escapeHtml(username)} is typing...`;
      typingIndicator.style.visibility = "visible";
      if (bodyMsg) {
        const nearBottom =
          bodyMsg.scrollHeight - bodyMsg.scrollTop - bodyMsg.clientHeight < 80;
        if (nearBottom) bodyMsg.scrollTop = bodyMsg.scrollHeight;
      }
    } else if (this.typingUsers.size === 2) {
      const usernames = Array.from(this.typingUsers.values());
      typingIndicator.innerHTML = `${escapeHtml(usernames[0])} and ${escapeHtml(usernames[1])} are typing...`;
      typingIndicator.style.visibility = "visible";
      if (bodyMsg) {
        const nearBottom =
          bodyMsg.scrollHeight - bodyMsg.scrollTop - bodyMsg.clientHeight < 80;
        if (nearBottom) bodyMsg.scrollTop = bodyMsg.scrollHeight;
      }
    } else {
      typingIndicator.innerHTML = `${this.typingUsers.size} people are typing...`;
      typingIndicator.style.visibility = "visible";
      if (bodyMsg) {
        const nearBottom =
          bodyMsg.scrollHeight - bodyMsg.scrollTop - bodyMsg.clientHeight < 80;
        if (nearBottom) bodyMsg.scrollTop = bodyMsg.scrollHeight;
      }
    }
  }

  //Setup typing indicator for message input
  setupTypingIndicatorInput() {
    const messageInput = document.getElementById("chat_message");
    if (!messageInput) {
      return;
    }

    let typingTimer = null;
    let isTyping = false;
    let lastTypingSentAt = 0;

    messageInput.addEventListener("input", () => {
      if (typingTimer) {
        clearTimeout(typingTimer);
      }

      const now = Date.now();
      //Send true on start, or every 2s while still typing
      if (!isTyping || now - lastTypingSentAt >= 2000) {
        isTyping = true;
        lastTypingSentAt = now;
        this.sendTypingIndicator(true);
      }

      //Stop after 2 seconds of inactivity
      typingTimer = setTimeout(() => {
        isTyping = false;
        this.sendTypingIndicator(false);
      }, 2000);
    });

    //Stop typing indicator when user submits message
    const chatForm = document.getElementById("chat_form");
    if (chatForm) {
      chatForm.addEventListener("submit", () => {
        if (typingTimer) {
          clearTimeout(typingTimer);
        }
        if (isTyping) {
          isTyping = false;
          this.sendTypingIndicator(false);
        }
      });
    }
  }

  //Handle member joined event
  handleMemberJoined(data) {
    //Only process if this event is for the current group
    if (data.group_guid != this.currentGroupGuid) {
      return;
    }

    //Clear cache since member list changed
    if (window.clearGroupDataCache) {
      window.clearGroupDataCache(this.currentGroupGuid);
    }

    //Show system message in chat
    this.showSystemMessage(`${data.username} joined the group`);

    //Reload group details to get updated member list
    this.loadGroupDetails();
  }

  //Handle member left event
  handleMemberLeft(data) {
    //Only process if this event is for the current group
    if (data.group_guid != this.currentGroupGuid) {
      return;
    }

    //Clear cache since member list changed
    if (window.clearGroupDataCache) {
      window.clearGroupDataCache(this.currentGroupGuid);
    }

    //Check if current user was removed
    if (data.user_guid == this.currentUserGuid) {
      window.location.href = "messages.php";
      return;
    }

    //Show system message in chat
    const action = data.reason === "removed" ? "was removed from" : "left";
    this.showSystemMessage(`${data.username} ${action} the group`);

    //Reload group details to get updated member list
    this.loadGroupDetails();
  }

  //Handle group invitation event
  handleGroupInvitation(data) {
    this.updateNotificationCounter(1);
  }

  //Handle settings updated event
  handleSettingsUpdated(data) {
    //Only process if this event is for the current group
    if (data.group_guid != this.currentGroupGuid) {
      return;
    }

    //Clear cache since group settings changed
    if (window.clearGroupDataCache) {
      window.clearGroupDataCache(this.currentGroupGuid);
    }

    //Update group name if changed
    if (data.changes && data.changes.group_name) {
      const newName = data.changes.group_name;

      //Update header
      const groupNameDisplay = document.getElementById("group-name-display");
      if (groupNameDisplay) {
        groupNameDisplay.textContent = newName;
        groupNameDisplay.title = newName; //Tooltip for truncated names
      }

      //Update local group data
      if (this.groupData) {
        this.groupData.group_name = newName;
      }

      //Show system message
      this.showSystemMessage(`Group name changed to "${newName}"`);
    }

    //Update group image if changed (including deletion)
    if (data.changes && "group_image" in data.changes) {
      const groupImage = document.getElementById("groupImage");
      if (groupImage) {
        if (data.changes.group_image === null) {
          //Image was deleted (use default)
          groupImage.src = "img/groupdefault.png";
          this.showSystemMessage("Group image was removed");
        } else {
          //Image was updated (reload from API to get the new URL)
          this.updateGroupImage();
          this.showSystemMessage("Group image was updated");
        }
      }
    }

    this.loadGroupDetails();
  }

  //Update group image from API
  async updateGroupImage() {
    try {
      const apiUrl = ApiUrls.groupsDetails(this.currentGroupGuid);
      const response = await fetch(apiUrl);
      const data = await response.json();

      if (data.success && data.group && data.group.group_image_url) {
        const groupImage = document.getElementById("groupImage");
        if (groupImage) {
          //Add loading class
          groupImage.classList.add("loading");

          //Set new image URL with cache buster
          const imageUrl =
            data.group.group_image_url +
            (data.group.group_image_url.includes("?") ? "&" : "?") +
            "t=" +
            Date.now();
          groupImage.src = imageUrl;

          //Remove loading class when done
          groupImage.onload = function () {
            this.classList.remove("loading");
          };
          groupImage.onerror = function () {
            this.src = "img/groupdefault.png";
            this.classList.remove("loading");
          };
        }
      }
    } catch (error) {}
  }

  //Handle role updated event
  handleRoleUpdated(data) {
    //Only process if this event is for the current group
    if (data.group_guid != this.currentGroupGuid) {
      return;
    }

    //Check if current user's role was updated
    if (data.user_guid == this.currentUserGuid) {
      this.isAdmin = data.new_role === "admin";

      const roleText =
        data.new_role === "admin" ? "promoted to admin" : "demoted to member";
      this.showSystemMessage(`You have been ${roleText}`);

      //Reload page to update UI with new permissions
      setTimeout(() => {
        window.location.reload();
      }, 2000);
    } else {
      //Another user's role was updated
      const roleText =
        data.new_role === "admin" ? "promoted to admin" : "demoted to member";
      this.showSystemMessage(`${data.username} has been ${roleText}`);
    }

    //Reload group details to get updated member list
    this.loadGroupDetails();
  }

  //Handle group deactivated event
  handleGroupDeactivated(data) {
    //Only process if this event is for the current group
    if (data.group_guid != this.currentGroupGuid) {
      return;
    }

    //Mark group as inactive
    this.isGroupActive = false;

    //Clear cache since group state changed
    if (window.clearGroupDataCache) {
      window.clearGroupDataCache(this.currentGroupGuid);
    }

    //Show system message
    this.showSystemMessage(
      "Group has been deactivated - it now has fewer than 3 members",
    );

    //Disable message input
    this.disableMessageInput(
      "Group is deactivated. Add more members to reactivate.",
    );

    //Reload group details (also updates member count)
    this.loadGroupDetails();
  }

  //Handle group reactivated event
  handleGroupReactivated(data) {
    //Only process if this event is for the current group
    if (data.group_guid != this.currentGroupGuid) {
      return;
    }

    //Mark group as active
    this.isGroupActive = true;

    //Clear cache since group state changed
    if (window.clearGroupDataCache) {
      window.clearGroupDataCache(this.currentGroupGuid);
    }

    //Show system message
    this.showSystemMessage(
      "Group has been reactivated - it now has 3 or more members",
    );

    //Enable message input
    this.enableMessageInput();

    //Reload group details (also updates member count)
    this.loadGroupDetails();
  }

  //Handle member status update (real-time online/offline updates)
  handleMemberStatusUpdate(data) {
    //Get the user GUID from the message
    const userGuid = data.friend_user_guid;

    if (!userGuid) {
      return;
    }

    //Find the member in our members list
    const memberIndex = this.members.findIndex((m) => m.user_guid === userGuid);

    if (memberIndex === -1) {
      return;
    }

    //Update the member's status
    const newStatus = data.userStatus || (data.loggedIn ? "Active" : "Offline");
    this.members[memberIndex].user_status = newStatus;

    //Re-render the members sidebar (which includes sorting)
    this.updateMembersSidebar();

    //Update the online count in header
    this.updateMemberCount();
  }

  //Handle user banned event (real-time ban status updates)
  handleUserBanned(data) {
    const userGuid = data.user_guid;

    if (!userGuid) {
      return;
    }

    //Check if members list is loaded
    if (!this.members || this.members.length === 0) {
      return;
    }

    //Find the member in our members list
    const memberIndex = this.members.findIndex((m) => m.user_guid === userGuid);

    if (memberIndex === -1) {
      return;
    }

    //Update the member's banned status
    this.members[memberIndex].user_banned = 1;
    this.members[memberIndex].user_status = "Offline";

    //Re-render the members sidebar (which includes sorting)
    this.updateMembersSidebar();
  }

  //Handle user unbanned event (real-time unban status updates)
  handleUserUnbanned(data) {
    const userGuid = data.user_guid;

    if (!userGuid) {
      return;
    }

    //Check if members list is loaded
    if (!this.members || this.members.length === 0) {
      return;
    }

    //Find the member in our members list
    const memberIndex = this.members.findIndex((m) => m.user_guid === userGuid);

    if (memberIndex === -1) {
      return;
    }

    //Update the member's banned status
    this.members[memberIndex].user_banned = 0;

    //Re-render the members sidebar (which includes sorting)
    this.updateMembersSidebar();
  }

  //Disable message input (for deactivated groups)
  disableMessageInput(placeholderText) {
    const messageInput = document.getElementById("chat_message");
    const sendButton = document.getElementById("send");
    const fileButton = document.getElementById("actual-btn");
    const fileButtonLabel = document.querySelector('label[for="actual-btn"]');

    if (messageInput) {
      messageInput.disabled = true;
      messageInput.placeholder = placeholderText || "Messages disabled";
      messageInput.classList.add("input-deactivated");
    }

    if (sendButton) {
      sendButton.disabled = true;
      sendButton.classList.add("ui-disabled");
    }

    if (fileButton) {
      fileButton.disabled = true;
    }

    //Disable the (+) label button visually
    if (fileButtonLabel) {
      fileButtonLabel.classList.add("ui-disabled");
    }

    //Disable other buttons
    const audioButton = document.getElementById("audioCallButton");
    const videoButton = document.getElementById("videoCallButton");
    const photoButton = document.getElementById("photoButton");

    [audioButton, videoButton, photoButton].forEach((btn) => {
      if (btn) {
        btn.disabled = true;
        btn.classList.add("ui-disabled");
      }
    });
  }

  //Enable message input (for reactivated groups)
  enableMessageInput() {
    const messageInput = document.getElementById("chat_message");
    const sendButton = document.getElementById("send");
    const fileButton = document.getElementById("actual-btn");
    const fileButtonLabel = document.querySelector('label[for="actual-btn"]');

    if (messageInput) {
      messageInput.disabled = false;
      messageInput.placeholder = "Type a message or drop files here...";
      messageInput.classList.remove("input-deactivated");
    }

    if (sendButton) {
      sendButton.disabled = false;
      sendButton.classList.remove("ui-disabled");
    }

    if (fileButton) {
      fileButton.disabled = false;
    }

    //Enable the (+) label button visually
    if (fileButtonLabel) {
      fileButtonLabel.classList.remove("ui-disabled");
    }

    //Enable other buttons
    const audioButton = document.getElementById("audioCallButton");
    const videoButton = document.getElementById("videoCallButton");
    const photoButton = document.getElementById("photoButton");

    [audioButton, videoButton, photoButton].forEach((btn) => {
      if (btn) {
        btn.disabled = false;
        btn.classList.remove("ui-disabled");
      }
    });
  }

  //Check if group is active and update UI accordingly
  checkGroupActiveStatus() {
    if (!this.groupData) {
      return;
    }

    // Check is_active field (can be 0, 1, "0", "1", false, true)
    const isActive =
      this.groupData.is_active == 1 || this.groupData.is_active === true;

    if (isActive !== this.isGroupActive) {
      this.isGroupActive = isActive;

      if (!isActive) {
        this.disableMessageInput(
          "Group is deactivated. Add more members to reactivate.",
        );
      } else {
        this.enableMessageInput();
      }
    }
  }

  //Show system message in chat
  showSystemMessage(message) {
    const url = window.location.href;
    if (!url.includes("chatbox.php")) {
      return;
    }

    const body = $("#bodyMsg");
    if (!body.length) {
      return;
    }

    const timestamp = new Date().toLocaleString("en-GB");
    const systemMessageHtml = `
            <div class="system-message">
                <span>
                    ${escapeHtml(message)} • ${timestamp}
                </span>
            </div>
        `;

    body.append(systemMessageHtml);

    //Scroll to bottom
    if (body.length) {
      body.scrollTop(body[0].scrollHeight);
    }
  }

  //Update notification counter
  updateNotificationCounter(increment) {
    const notifyHeader = document.getElementById("notify_header");
    if (notifyHeader) {
      let currentCount = parseInt(notifyHeader.textContent) || 0;
      currentCount += increment;

      if (currentCount > 0) {
        notifyHeader.textContent = currentCount;
        notifyHeader.classList.remove("d-none");
      } else {
        notifyHeader.classList.add("d-none");
      }
    }
  }

  //Bind event listeners
  bindEvents() {
    const self = this;

    //Group info button
    $("#buttonGroupInfo").on("click", function () {
      self.showGroupInfo();
    });

    //Send invitations button
    $("#sendInvitationsBtn").on("click", function () {
      self.sendInvitations();
    });

    //Invite modal close handlers
    $(
      '#inviteMembersModal .close, #inviteMembersModal [data-dismiss="modal"]',
    ).on("click", function () {
      $("#inviteMembersModal").modal("hide");
    });

    //Save group settings button
    $("#saveGroupSettingsBtn").on("click", function () {
      self.saveGroupSettings();
    });
  }

  //Load group details from cache or API
  async loadGroupDetails() {
    try {
      //Use cached data
      if (window.getCachedGroupData) {
        const cachedData = await window.getCachedGroupData(
          this.currentGroupGuid,
        );

        this.groupData = cachedData.group;
        this.members = cachedData.members;
        this.isAdmin = cachedData.userRole === "admin";

        //Check if group is active
        this.checkGroupActiveStatus();

        //Update the members sidebar
        this.updateMembersSidebar();

        //Update member count in header
        this.updateMemberCount();

        return;
      }
    } catch (_e) {
      //Cache failed, fall through to API
    }

    //Fallback to original API call if cache fails
    this.loadGroupDetailsFromAPI();
  }

  //Load group details directly from API (fallback method)
  loadGroupDetailsFromAPI() {
    const self = this;
    const apiUrl = ApiUrls.groupsDetails(this.currentGroupGuid);

    $.ajax({
      url: apiUrl,
      method: "GET",
      dataType: "json",
      success: function (response) {
        if (response.success) {
          self.groupData = response.group;
          self.members = response.members;
          self.isAdmin = response.user_role === "admin";

          //Check if group is active
          self.checkGroupActiveStatus();

          //Update the members sidebar
          self.updateMembersSidebar();

          //Update member count in header
          self.updateMemberCount();
        } else {
        }
      },
      error: function (xhr, status, error) {},
    });
  }

  //Update the members sidebar with current member data
  updateMembersSidebar() {
    const sidebar = $("#membersSidebar");

    if (!sidebar.length) {
      return; //Sidebar not found
    }

    sidebar.empty();
    sidebar.append('<h5 class="text-center mb-3">Members</h5>');

    if (!this.members || this.members.length === 0) {
      sidebar.append(
        '<div class="text-center text-muted">No members found</div>',
      );
      return;
    }

    //Sort members (online first (a-z), then offline (a-z), then banned (a-z))
    const sortedMembers = [...this.members].sort((a, b) => {
      //Check if banned
      const aIsBanned =
        a.user_banned === 1 || a.user_banned === "1" || a.user_banned === true;
      const bIsBanned =
        b.user_banned === 1 || b.user_banned === "1" || b.user_banned === true;

      //Determine sort priority (0 = Active, 1 = Offline, 2 = Banned)
      let aPriority, bPriority;
      if (aIsBanned) {
        aPriority = 2;
      } else if (a.user_status === "Active") {
        aPriority = 0;
      } else {
        aPriority = 1;
      }

      if (bIsBanned) {
        bPriority = 2;
      } else if (b.user_status === "Active") {
        bPriority = 0;
      } else {
        bPriority = 1;
      }

      if (aPriority !== bPriority) {
        return aPriority - bPriority;
      }

      //Use the correct username field from API response
      const aUsername = a.user_username || "";
      const bUsername = b.user_username || "";
      return aUsername.localeCompare(bUsername);
    });

    sortedMembers.forEach((member) => {
      //Check if member is banned
      const isBanned =
        member.user_banned === 1 ||
        member.user_banned === "1" ||
        member.user_banned === true;

      //If banned, show "Banned" instead of Active/Offline
      const isOnline = !isBanned && member.user_status === "Active";
      const statusClass = isOnline ? "status-online" : "status-offline";
      const statusLabel = isBanned ? "Banned" : isOnline ? "Active" : "Offline";
      const statusLabelClass = isBanned
        ? "banned"
        : isOnline
          ? "active"
          : "offline";

      const isOwner = member.user_guid == this.groupData.creator_guid;
      const isAdmin = member.role === "admin";

      //Use the correct username field from API response
      const memberUsername = member.user_username || "Unknown";

      //Use profile image URL from API response, fallback to default
      const profileImg =
        member.profile_image_url || this.getProfileImageUrl(member.user_guid);

      const adminBadge = isAdmin
        ? `<span class="badge bg-primary ms-2">${isOwner ? "Owner" : "Admin"}</span>`
        : "";

      const memberGuid = member.user_guid;

      const memberHtml = `
                <div class="member-row" id="member-${memberGuid}"
                     data-username="${memberUsername.toLowerCase()}"
                     data-status="${statusLabel.toLowerCase()}">

                    <div class="left-info">
                        <span class="status-dot ${statusClass}" id="status-${memberGuid}"></span>

                        <img src="${profileImg}" class="rounded-circle member-avatar-sm"
                             alt="${escapeHtml(memberUsername)}"
                             onerror="this.src='img/profiledefault.jpg';">

                        <strong class="username-label" title="${escapeHtml(memberUsername)}">${escapeHtml(memberUsername)}</strong>
                        ${adminBadge}
                    </div>

                    <span class="status-label ${statusLabelClass}" id="label-${memberGuid}">
                        ${statusLabel}
                    </span>

                </div>
            `;

      sidebar.append(memberHtml);
    });
  }

  //Update member count in header
  updateMemberCount() {
    const memberCountElement = $(".member-count-indicator");
    if (memberCountElement.length && this.groupData) {
      const onlineCount = this.members.filter(
        (m) => m.user_status === "Active",
      ).length;
      memberCountElement.html(
        `<i class="fa fa-user"></i> ${this.groupData.member_count} (${onlineCount} online)`,
      );
    }
  }

  //Get profile image URL for a user
  getProfileImageUrl(userGuid) {
    return `img/profile/${userGuid}.jpg`;
  }

  //Show group info modal
  async showGroupInfo() {
    try {
      //Try to use cached data first, but allow fresh fetch for modal
      let groupData;

      if (window.getCachedGroupData) {
        groupData = await window.getCachedGroupData(this.currentGroupGuid);
        this.groupData = groupData.group;
        this.members = groupData.members;
        this.isAdmin = groupData.userRole === "admin";
      } else {
        //Fallback to direct API call if cache not available
        await this.loadGroupDetailsFromAPI();
      }

      this.populateGroupInfo();
      $("#groupInfoModal").modal("show");
    } catch (error) {
      alert(
        "Failed to load group information: " +
          (error.message || "Unknown error"),
      );
    }
  }

  //Populate group info modal with data
  populateGroupInfo() {
    //Populate group details
    $("#groupInfoName").text(this.groupData.group_name);
    $("#groupInfoCreated").text(this.formatDate(this.groupData.created_at));
    $("#groupInfoMemberCount").text(this.groupData.member_count);
    $("#groupInfoMessageCount").text(this.groupData.message_count);

    //Populate members list
    this.populateMembersList();

    //Populate admin actions
    this.populateAdminActions();
  }

  //Populate members list
  populateMembersList() {
    const membersList = $("#groupMembersList");
    membersList.empty();

    this.members.forEach((member) => {
      const isOnline = member.user_status === "Active";
      const statusClass = isOnline ? "online" : "offline";
      const adminBadge =
        member.role === "admin"
          ? '<span class="badge bg-primary">Admin</span>'
          : "";
      const memberGuid = member.user_guid;

      const memberHtml = `
                <div class="member-item" data-user-guid="${memberGuid}">
                    <div class="member-info">
                        <span class="online-status ${statusClass}"></span>
                        <span class="member-name">${escapeHtml(member.user_username)}</span>
                        ${adminBadge}
                    </div>
                    ${this.isAdmin && memberGuid !== this.currentUserGuid ? this.getMemberActions(member) : ""}
                </div>
            `;

      membersList.append(memberHtml);
    });

    //Bind member action buttons
    this.bindMemberActions();
  }

  //Get member action buttons HTML
  getMemberActions(member) {
    const memberGuid = member.user_guid;
    const isAdmin = member.role === "admin";
    const roleButton = isAdmin
      ? `<button class="btn btn-sm btn-warning demote-btn" data-user-guid="${memberGuid}">Demote</button>`
      : `<button class="btn btn-sm btn-success promote-btn" data-user-guid="${memberGuid}">Promote</button>`;

    return `
            <div class="member-actions">
                ${roleButton}
                <button class="btn btn-sm btn-danger remove-btn" data-user-guid="${memberGuid}">Remove</button>
            </div>
        `;
  }

  //Bind member action buttons
  bindMemberActions() {
    const self = this;

    //Promote button
    $(".promote-btn")
      .off("click")
      .on("click", function () {
        const userGuid = $(this).data("user-guid");
        self.updateMemberRole(userGuid, "admin");
      });

    //Demote button
    $(".demote-btn")
      .off("click")
      .on("click", function () {
        const userGuid = $(this).data("user-guid");
        self.updateMemberRole(userGuid, "member");
      });

    //Remove button
    $(".remove-btn")
      .off("click")
      .on("click", function () {
        const userGuid = $(this).data("user-guid");
        const member = self.members.find((m) => m.user_guid == userGuid);
        self.confirmRemoveMember(userGuid, member.user_username);
      });
  }

  //Populate admin actions
  populateAdminActions() {
    const actionsDiv = $("#groupInfoActions");
    actionsDiv.empty();

    if (this.isAdmin) {
      actionsDiv.append(`
                <button type="button" class="btn btn-primary mr-2" id="inviteMembersBtn">
                    <i class="fa fa-user-plus"></i> Invite Members
                </button>
                <button type="button" class="btn btn-secondary mr-2" id="editGroupBtn">
                    <i class="fa fa-edit"></i> Edit Group
                </button>
            `);

      //Bind admin action buttons
      $("#inviteMembersBtn").on("click", () => this.showInviteMembersModal());
      $("#editGroupBtn").on("click", () => this.showEditGroupModal());
    }

    //Leave group button
    actionsDiv.append(`
            <button type="button" class="btn btn-danger" id="leaveGroupBtn">
                <i class="fa fa-sign-out"></i> Leave Group
            </button>
        `);

    $("#leaveGroupBtn").on("click", () => this.confirmLeaveGroup());
  }

  //Show invite members modal
  showInviteMembersModal() {
    const self = this;

    //Load friends list
    $.ajax({
      url: ApiUrls.friendsGet(),
      method: "GET",
      dataType: "json",
      success: function (response) {
        if (response.success) {
          self.friends = response.friends || [];
          self.populateInviteFriendsList();
          $("#groupInfoModal").modal("hide");
          $("#inviteMembersModal").modal("show");
        } else {
          alert(
            "Failed to load friends list: " +
              (response.message || "Unknown error"),
          );
        }
      },
      error: function (xhr, status, error) {
        alert("Failed to load friends list. Please try again.");
      },
    });
  }

  //Populate invite friends list
  populateInviteFriendsList() {
    const friendsList = $("#inviteFriendsList");
    friendsList.empty();

    //Filter out existing members
    const memberGuids = this.members.map((m) => m.user_guid);
    const availableFriends = this.friends.filter(
      (f) => !memberGuids.includes(f.friend_guid),
    );

    if (availableFriends.length === 0) {
      friendsList.append(
        '<p class="text-muted">No friends available to invite.</p>',
      );
      return;
    }

    availableFriends.forEach((friend) => {
      const friendHtml = `
                <div class="form-check">
                    <input class="form-check-input invite-friend-checkbox" type="checkbox" 
                           value="${friend.friend_guid}" id="friend-${friend.friend_guid}">
                    <label class="form-check-label" for="friend-${friend.friend_guid}">
                        ${escapeHtml(friend.username)}
                    </label>
                </div>
            `;
      friendsList.append(friendHtml);
    });
  }

  //Send invitations to selected friends
  sendInvitations() {
    const self = this;
    const selectedFriends = [];

    $(".invite-friend-checkbox:checked").each(function () {
      selectedFriends.push($(this).val());
    });

    if (selectedFriends.length === 0) {
      this.showInviteError("Please select at least one friend to invite.");
      return;
    }

    //Disable button and show loading
    $("#sendInvitationsBtn").prop("disabled", true).text("Sending...");

    $.ajax({
      url: ApiUrls.groupsInvite(),
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        group_guid: this.currentGroupGuid,
        user_guids: selectedFriends,
      }),
      dataType: "json",
      success: function (response) {
        if (response.success) {
          self.showInviteSuccess(
            `Successfully sent ${response.invitations_sent} invitation(s).`,
          );
          setTimeout(() => {
            $("#inviteMembersModal").modal("hide");
            self.loadGroupDetails();
          }, 2000);
        } else {
          self.showInviteError(
            response.message || "Failed to send invitations.",
          );
        }
      },
      error: function (xhr, status, error) {
        self.showInviteError("Failed to send invitations. Please try again.");
      },
      complete: function () {
        $("#sendInvitationsBtn")
          .prop("disabled", false)
          .text("Send Invitations");
      },
    });
  }

  //Show edit group modal
  showEditGroupModal() {
    $("#editGroupName").val(this.groupData.group_name);
    $("#editGroupError").hide();
    $("#editGroupSuccess").hide();
    $("#groupInfoModal").modal("hide");
    $("#editGroupModal").modal("show");
  }

  //Save group settings
  saveGroupSettings() {
    const self = this;
    const newGroupName = $("#editGroupName").val().trim();

    //Validate
    if (newGroupName.length < 3 || newGroupName.length > 50) {
      this.showEditGroupError(
        "Group name must be between 3 and 50 characters.",
      );
      return;
    }

    //Disable button and show loading
    $("#saveGroupSettingsBtn").prop("disabled", true).text("Saving...");

    $.ajax({
      url: ApiUrls.groupsUpdateSettings(),
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        group_guid: this.currentGroupGuid,
        group_name: newGroupName,
      }),
      dataType: "json",
      success: function (response) {
        if (response.success) {
          self.showEditGroupSuccess("Group settings updated successfully.");
          self.groupData.group_name = newGroupName;

          //Update header
          $("#white").text(newGroupName);

          setTimeout(() => {
            $("#editGroupModal").modal("hide");
            self.loadGroupDetails();
          }, 2000);
        } else {
          self.showEditGroupError(
            response.message || "Failed to update group settings.",
          );
        }
      },
      error: function (xhr, status, error) {
        self.showEditGroupError(
          "Failed to update group settings. Please try again.",
        );
      },
      complete: function () {
        $("#saveGroupSettingsBtn").prop("disabled", false).text("Save Changes");
      },
    });
  }

  //Update member role
  updateMemberRole(userGuid, newRole) {
    const self = this;
    const member = this.members.find((m) => m.user_guid == userGuid);
    const action = newRole === "admin" ? "promote" : "demote";

    this.showConfirmDialog(
      `Are you sure you want to ${action} ${member.user_username}?`,
      function () {
        $.ajax({
          url: ApiUrls.groupsUpdateRole(),
          method: "POST",
          contentType: "application/json",
          data: JSON.stringify({
            group_guid: self.currentGroupGuid,
            user_guid: userGuid,
            role: newRole,
          }),
          dataType: "json",
          success: function (response) {
            if (response.success) {
              self.loadGroupDetails();
              setTimeout(() => self.showGroupInfo(), 500);
            } else {
              alert(
                "Failed to update member role: " +
                  (response.message || "Unknown error"),
              );
            }
          },
          error: function (xhr, status, error) {
            alert("Failed to update member role. Please try again.");
          },
        });
      },
    );
  }

  //Confirm remove member
  confirmRemoveMember(userGuid, username) {
    const self = this;

    this.showConfirmDialog(
      `Are you sure you want to remove ${username} from the group?`,
      function () {
        self.removeMember(userGuid);
      },
    );
  }

  //Remove member from group
  removeMember(userGuid) {
    const self = this;

    $.ajax({
      url: ApiUrls.groupsRemoveMember(),
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        group_guid: this.currentGroupGuid,
        user_guid: userGuid,
      }),
      dataType: "json",
      success: function (response) {
        if (response.success) {
          self.loadGroupDetails();
          setTimeout(() => self.showGroupInfo(), 500);
        } else {
          alert(
            "Failed to remove member: " + (response.message || "Unknown error"),
          );
        }
      },
      error: function (xhr, status, error) {
        alert("Failed to remove member. Please try again.");
      },
    });
  }

  //Confirm leave group
  confirmLeaveGroup() {
    const self = this;

    let message = "Are you sure you want to leave this group?";
    if (this.isAdmin) {
      const adminCount = this.members.filter((m) => m.role === "admin").length;
      if (adminCount === 1) {
        message +=
          " As the last admin, the longest-standing member will be promoted to admin.";
      }
    }

    this.showConfirmDialog(message, function () {
      self.leaveGroup();
    });
  }

  //Leave group
  leaveGroup() {
    $.ajax({
      url: ApiUrls.groupsLeave(this.currentGroupGuid),
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        group_guid: this.currentGroupGuid,
      }),
      dataType: "json",
      success: function (response) {
        if (response.success) {
          window.location.href = "messages.php";
        } else {
          alert(
            "Failed to leave group: " + (response.message || "Unknown error"),
          );
        }
      },
      error: function (xhr, status, error) {
        alert("Failed to leave group. Please try again.");
      },
    });
  }

  //Show confirm dialog
  showConfirmDialog(message, onConfirm) {
    $("#confirmModalMessage").text(message);
    $("#confirmModal").modal("show");

    $("#confirmModalBtn")
      .off("click")
      .on("click", function () {
        $("#confirmModal").modal("hide");
        onConfirm();
      });
  }

  //Show invite error
  showInviteError(message) {
    $("#inviteError").text(message).show();
    $("#inviteSuccess").hide();
  }

  //Show invite success
  showInviteSuccess(message) {
    $("#inviteSuccess").text(message).show();
    $("#inviteError").hide();
  }

  //Show edit group error
  showEditGroupError(message) {
    $("#editGroupError").text(message).show();
    $("#editGroupSuccess").hide();
  }

  //Show edit group success
  showEditGroupSuccess(message) {
    $("#editGroupSuccess").text(message).show();
    $("#editGroupError").hide();
  }

  //Format date
  formatDate(dateString) {
    const date = new Date(dateString);
    return (
      date.toLocaleDateString("en-GB") + " " + date.toLocaleTimeString("en-GB")
    );
  }
}

//Initialize group chat manager when document is ready
$(document).ready(function () {
  const isGroupChat = $("#isGroupChat").val() === "1";

  if (isGroupChat) {
    const groupGuid = $("#groupId").val();
    const userGuid = $("#fromUser").val();

    if (groupGuid && userGuid) {
      window.groupChatManager = new GroupChatManager();
      window.groupChatManager.init(groupGuid, userGuid);
    }
  }
});
