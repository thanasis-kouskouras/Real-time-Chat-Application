/* CHATBOX PAGE - API BASED DATA LOADING

Handles chat initialization, friend verification, and message loading via APIs. */

let groupGuid = null;
let isGroupChat = false;
let friendGuid = null;
let currentUserGuid = null;

//Message queue for instant messages received before initial load completes
window.pendingInstantMessages = [];
window.chatboxInitialized = false;

//Simple cache for group data to avoid duplicate API calls
const groupDataCache = new Map();

//Get cached group data or fetch from API
async function getCachedGroupData(groupGuid) {
  const cacheKey = `group_${groupGuid}`;

  //Check if we have cached data
  if (groupDataCache.has(cacheKey)) {
    return groupDataCache.get(cacheKey);
  }

  //Fetch from API and cache the result
  const response = await ApiClient.get(ApiUrls.groupsDetails(groupGuid));

  if (!response.success) {
    throw new Error(response.message || "Failed to load group data");
  }

  const groupData = {
    type: "group",
    group: response.group,
    members: response.members,
    userRole: response.user_role,
  };

  //Cache the result
  groupDataCache.set(cacheKey, groupData);

  return groupData;
}

//Clear group data cache
function clearGroupDataCache(groupGuid = null) {
  if (groupGuid) {
    const cacheKey = `group_${groupGuid}`;
    groupDataCache.delete(cacheKey);
  } else {
    groupDataCache.clear();
  }
}

//Make cache functions globally available
window.getCachedGroupData = getCachedGroupData;
window.clearGroupDataCache = clearGroupDataCache;

//ChatboxDataLoader - Handles API-based data loading for chatbox
class ChatboxDataLoader {
  constructor() {
    this.loadingStates = new Set();
  }

  //Load group chat data via API
  async loadGroupData(groupGuid) {
    try {
      this.showLoadingState("group-data");

      //Use cached data if available
      const groupData = await getCachedGroupData(groupGuid);

      this.hideLoadingState("group-data");
      return groupData;
    } catch (error) {
      this.hideLoadingState("group-data");
      this.handleLoadingError(error, "Failed to load group data");
      throw error;
    }
  }

  //Load one-on-one chat data via API
  async loadOneOnOneData(friendId) {
    try {
      this.showLoadingState("friend-data");

      //Load messages first (API will verify friendship and return friend info)
      const messagesResponse = await ApiClient.get(
        ApiUrls.chat_messages(friendId, 1),
      );

      if (!messagesResponse.success) {
        throw new Error(messagesResponse.message || "Failed to load chat data");
      }

      //Extract friend information from the response
      const friendInfo = messagesResponse.friend;

      if (!friendInfo) {
        throw new Error("Friend information not available");
      }

      this.hideLoadingState("friend-data");
      return {
        type: "friend",
        friend: {
          id: friendInfo.user_guid,
          username: friendInfo.username,
          status: friendInfo.status,
          profile_url: friendInfo.profile_url,
        },
        isFriend: true,
      };
    } catch (error) {
      this.hideLoadingState("friend-data");
      this.handleLoadingError(error, "Failed to load chat data");
      throw error;
    }
  }

  /* Load messages via API.
  Returns object with messages array and pagination info. */
  async loadMessages(chatId, isGroup, limit = 50) {
    try {
      this.showLoadingState("messages");

      let response;
      if (isGroup) {
        response = await ApiClient.get(ApiUrls.groupsMessages(chatId, limit));
      } else {
        response = await ApiClient.get(ApiUrls.chat_messages(chatId, limit));
      }

      if (!response.success) {
        throw new Error(response.message || "Failed to load messages");
      }

      this.hideLoadingState("messages");

      //Return full response with pagination info
      return {
        messages: response.messages || [],
        has_more: response.has_more || false,
        next_cursor: response.next_cursor || null,
      };
    } catch (error) {
      this.hideLoadingState("messages");
      this.handleLoadingError(error, "Failed to load messages");
      throw error;
    }
  }

  //Show loading state for a specific component with consistent patterns
  showLoadingState(component) {
    this.loadingStates.add(component);

    //Show loading indicators based on component with consistent styling
    switch (component) {
      case "group-data":
        const memberCountSpan = document.querySelector(
          ".member-count-indicator",
        );
        if (memberCountSpan) {
          memberCountSpan.innerHTML =
            '<i class="fa fa-spinner fa-spin"></i> Loading...';
        }
        break;
      case "friend-data":
        const friendStatusDisplay = document.getElementById(
          "friend-status-display",
        );
        if (friendStatusDisplay) {
          friendStatusDisplay.innerHTML =
            '<i class="fa fa-spinner fa-spin"></i> Loading...';
        }
        break;
      case "messages":
        //Don't show spinner (skeleton loader is already visible in HTML)
        break;
    }
  }

  //Hide loading state for a specific component
  hideLoadingState(component) {
    this.loadingStates.delete(component);

    //Clear the loading message from the DOM
    switch (component) {
      case "messages":
        //Remove the loading indicator completely
        const loadingMessages = document.getElementById("loadingMessages");
        if (loadingMessages) {
          loadingMessages.remove();
        }
        break;
      case "group-data":
        //Reset member count display if it was showing loading
        const memberCountSpan = document.querySelector(
          ".member-count-indicator",
        );
        if (
          memberCountSpan &&
          memberCountSpan.innerHTML.includes("Loading...")
        ) {
          memberCountSpan.innerHTML = '<i class="fa fa-user"></i> Loading...';
        }
        break;
      case "friend-data":
        //Reset friend status display if it was showing loading
        const friendStatusDisplay = document.getElementById(
          "friend-status-display",
        );
        if (
          friendStatusDisplay &&
          friendStatusDisplay.innerHTML.includes("Loading...")
        ) {
          friendStatusDisplay.innerHTML = "";
        }
        break;
    }
  }

  //Handle loading errors
  handleLoadingError(error, context) {
    //Remove the loading indicator
    const loadingMessages = document.getElementById("loadingMessages");
    if (loadingMessages) {
      loadingMessages.remove();
    }

    //Show error message in bodyMsg
    const bodyMsg = document.getElementById("bodyMsg");
    if (bodyMsg) {
      bodyMsg.innerHTML =
        '<div class="text-center text-danger"><i class="fa fa-exclamation-circle"></i><p>Failed to load messages. Please try again.</p></div>';
    }

    //Show user-friendly error message
    if (window.showToast) {
      window.showToast(error.message || context, "error");
    }
  }
}

//ChatboxRenderer (Handles rendering of chat data)
class ChatboxRenderer {
  //Update group header with loaded data
  updateGroupHeader(groupData) {
    const groupNameDisplay = document.getElementById("group-name-display");
    if (groupNameDisplay && groupData.group) {
      groupNameDisplay.textContent = groupData.group.group_name;
      //Add tooltip to show full name on hover
      groupNameDisplay.title = groupData.group.group_name;
    }

    const memberCountSpan = document.querySelector(".member-count-indicator");
    if (memberCountSpan && groupData.members) {
      const memberCount = groupData.members.length;
      memberCountSpan.innerHTML = `<i class="fa fa-user"></i> ${memberCount} members`;
    }

    //Update group image
    const groupImage = document.getElementById("groupImage");
    if (groupImage && groupData.group) {
      //Use group_image_url if available, otherwise fall back to default
      const imageUrl =
        groupData.group.group_image_url || "img/profiledefault.jpg";

      //Add loading class
      groupImage.classList.add("loading");

      //Set image source
      groupImage.src = imageUrl;

      //Remove loading class when image loads
      groupImage.onload = function () {
        this.classList.remove("loading");
      };

      //Add error handler to fallback to default
      groupImage.onerror = function () {
        this.src = "img/profiledefault.jpg";
        this.classList.remove("loading");
        this.onerror = null; //Prevent infinite loop
      };
    }
  }

  //Update friend header with loaded data
  async updateFriendHeader(friendData) {
    const friendNameDisplay = document.getElementById("friend-name-display");
    const friendStatusDisplay = document.getElementById(
      "friend-status-display",
    );
    const profileImage = document.getElementById("profileImage");
    const toUsernameInput = document.getElementById("toUsername");

    if (friendNameDisplay && friendData.friend) {
      friendNameDisplay.textContent = friendData.friend.username;
    }

    if (friendStatusDisplay && friendData.friend) {
      const status = friendData.friend.status;
      friendStatusDisplay.textContent = status;
      friendStatusDisplay.className =
        status === "Active" ? "onlineColor" : "offlineColor";
    }

    if (toUsernameInput && friendData.friend) {
      toUsernameInput.value = friendData.friend.username;
    }

    //Profile image (use profile_url from friendData if available)
    if (profileImage && friendData.friend) {
      const imageUrl =
        friendData.friend.profile_url || "img/profiledefault.jpg";

      //Add loading class
      profileImage.classList.add("loading");

      //Set image source
      profileImage.src = imageUrl;

      //Remove loading class when image loads
      profileImage.onload = function () {
        this.classList.remove("loading");
      };

      //Add error handler to fallback to default
      profileImage.onerror = function () {
        this.src = "img/profiledefault.jpg";
        this.classList.remove("loading");
        this.onerror = null; //Prevent infinite loop
      };
    }
  }

  //Render messages in the chat body
  renderMessages(messages, isGroup) {
    const bodyMsg = document.getElementById("bodyMsg");
    if (!bodyMsg) return;

    //Remove the loading indicator
    const loadingMessages = document.getElementById("loadingMessages");
    if (loadingMessages) {
      loadingMessages.remove(); //Remove from DOM instead of just hiding
    }

    if (!messages || messages.length === 0) {
      bodyMsg.innerHTML =
        '<div class="center" id="noMessageYet"><p class="pMessage">There is no message yet..</p></div>';
      return;
    }

    //Clear any remaining content
    bodyMsg.innerHTML = "";

    //Render each message with fade-in animation
    messages.forEach((message, index) => {
      //Use global MessageRenderer (no fallback to obsolete methods)
      const renderer = window.messageRenderer;
      if (!renderer) {
        return;
      }

      renderer.renderMessage(message, bodyMsg, {
        isGroup: isGroup,
        friendGuid: friendGuid,
      });

      //Add fade-in animation with staggered delay
      const lastChild = bodyMsg.lastElementChild;
      if (lastChild) {
        lastChild.classList.add("message-fade-in");
        lastChild.style.animationDelay = `${index * 0.02}s`;
      }
    });

    //Scroll to bottom after messages are rendered
    this.scrollToBottom();
  }

  //Scroll to bottom of message container
  scrollToBottom() {
    const bodyMsg = document.getElementById("bodyMsg");
    if (!bodyMsg) return;

    const doScroll = () => {
      bodyMsg.scrollTop = bodyMsg.scrollHeight + 1000;
    };

    //Immediate scroll
    requestAnimationFrame(doScroll);

    /* Timed fallbacks. Catch layout shifts that have no load event
    (audio/video player DOM, font metrics, border rendering, slow connections) */
    [100, 300, 700, 1500].forEach((delay) => setTimeout(doScroll, delay));

    //Image load listeners (re-scroll as each image expands into its full height)
    bodyMsg.querySelectorAll("img").forEach((img) => {
      if (!img.complete) {
        img.addEventListener("load", doScroll, { once: true });
        img.addEventListener("error", doScroll, { once: true });
      }
    });
  }

  //Render group members sidebar
  renderMembersSidebar(members, groupInfo = null) {
    const membersSidebar = document.getElementById("membersSidebar");
    if (!membersSidebar || !members) return;

    if (members.length === 0) {
      membersSidebar.innerHTML =
        '<div class="text-center text-muted">No members found</div>';
      return;
    }

    //Sort members. Online first (a-z), then offline (a-z), then banned (a-z)
    const sortedMembers = [...members].sort((a, b) => {
      //Check if banned
      const aIsBanned =
        a.user_banned === 1 || a.user_banned === "1" || a.user_banned === true;
      const bIsBanned =
        b.user_banned === 1 || b.user_banned === "1" || b.user_banned === true;

      //Determine sort priority (0 = Active, 1 = Offline, 2 = Banned)
      let aPriority, bPriority;
      if (aIsBanned) {
        aPriority = 2;
      } else if (a.is_online) {
        aPriority = 0;
      } else {
        aPriority = 1;
      }

      if (bIsBanned) {
        bPriority = 2;
      } else if (b.is_online) {
        bPriority = 0;
      } else {
        bPriority = 1;
      }

      if (aPriority !== bPriority) {
        return aPriority - bPriority;
      }

      return a.user_username.localeCompare(b.user_username);
    });

    let html = "";
    sortedMembers.forEach((member) => {
      //Check if member is banned
      const isBanned =
        member.user_banned === 1 ||
        member.user_banned === "1" ||
        member.user_banned === true;

      //If banned, show "Banned" instead of Active/Offline
      const status = isBanned
        ? "Banned"
        : member.is_online
          ? "Active"
          : "Offline";
      const statusClass = isBanned
        ? "status-offline"
        : member.is_online
          ? "status-online"
          : "status-offline";
      const statusLabelClass = isBanned
        ? "banned"
        : member.is_online
          ? "active"
          : "offline";

      //Determine if member is owner
      const isOwner = groupInfo && member.user_guid === groupInfo.creator_guid;
      const adminBadge =
        member.role === "admin"
          ? `<span class="badge bg-primary ms-2">${isOwner ? "Owner" : "Admin"}</span>`
          : "";

      //Get profile image URL for the member
      const profileImageUrl = member.user_guid
        ? ApiUrls.accountUserImage(member.user_guid)
        : "img/profiledefault.jpg";

      html += `
                <div class="member-row" id="member-${member.user_guid}"
                     data-username="${member.user_username.toLowerCase()}"
                     data-status="${status.toLowerCase()}">

                    <div class="left-info">
                        <span class="status-dot ${statusClass}" id="status-${member.user_guid}"></span>
                        <img src="${profileImageUrl}" class="rounded-circle member-avatar-sm"
                             alt="${escapeHtml(member.user_username)}"
                             onerror="this.src='img/profiledefault.jpg';">
                        <strong class="username-label">${escapeHtml(member.user_username)}</strong>
                    </div>

                    ${adminBadge}

                    <span class="status-label ${statusLabelClass}" id="label-${member.user_guid}">
                        ${status}
                    </span>
                </div>
            `;
    });

    membersSidebar.innerHTML = html;
  }

}

//Initialize chatbox page with API-based loading
async function initChatboxPage() {
  //Get chat parameters from hidden inputs
  const groupGuidInput = document.getElementById("groupGuid");
  const isGroupChatInput = document.getElementById("isGroupChat");
  const toUserInput = document.getElementById("toUser");
  const fromUserInput = document.getElementById("fromUser");
  const tokenInput = document.getElementById("token");

  if (groupGuidInput) {
    groupGuid = groupGuidInput.value;
  }

  if (isGroupChatInput) {
    isGroupChat = isGroupChatInput.value === "1";
  }

  if (toUserInput) {
    friendGuid = toUserInput.value;
  }

  if (fromUserInput) {
    currentUserGuid = window.CURRENT_USER_GUID || fromUserInput.value;
  }

  const token = tokenInput?.value;

  //Initialize MessageRenderer first (required by ChatboxRenderer)
  if (!window.messageRenderer && typeof MessageRenderer !== "undefined") {
    window.messageRenderer = new MessageRenderer(currentUserGuid, {
      autoScroll: true,
      waitForMedia: true,
    });
  }

  //Initialize data loader and renderer
  const dataLoader = new ChatboxDataLoader();
  const renderer = new ChatboxRenderer();

  try {
    if (isGroupChat && groupGuid) {
      //Load group details to check user role and show/hide Edit button
      try {
        const groupDetailsResponse = await ApiClient.get(
          ApiUrls.groupsDetails(groupGuid),
        );
        if (groupDetailsResponse && groupDetailsResponse.user_role) {
          const manageButton = document.getElementById("buttonGroupInfo");
          if (manageButton) {
            // Show Manage button ONLY if user is admin
            if (groupDetailsResponse.user_role === "admin") {
              manageButton.classList.remove("d-none");
            } else {
              manageButton.classList.add("d-none");
            }
          }
        }
      } catch (error) {
        //Hide button on error to be safe
        const manageButton = document.getElementById("buttonGroupInfo");
        if (manageButton) {
          manageButton.classList.add("d-none");
        }
      }
      try {
        //Load group data via API
        const groupData = await dataLoader.loadGroupData(groupGuid);

        //Update UI with group data
        renderer.updateGroupHeader(groupData);
        renderer.renderMembersSidebar(groupData.members, groupData.group);

        //Auto-acknowledge any pending group notifications for this group, when the user opens the group chat (regardless of how they got here)
        try {
          if (
            window.ApiClient &&
            window.ApiUrls &&
            window.ApiUrls.groupsAcknowledgeGroupNotificationsByGroup
          ) {
            ApiClient.post(
              ApiUrls.groupsAcknowledgeGroupNotificationsByGroup(),
              {
                group_guid: groupGuid,
                notification_types: [
                  "added_to_group",
                  "group_reactivated",
                  "group_deactivated",
                ],
              },
            ).catch(() => {
              //Non-critical operation, ignore errors
            });
          }
        } catch (_e) {
          //Non-critical operation, ignore errors
        }

        //Load messages via API (with pagination info)
        const messagesData = await dataLoader.loadMessages(groupGuid, true);

        //Render messages
        renderer.renderMessages(messagesData.messages, true);

        //Initialize group chat manager for WebSocket
        if (typeof GroupChatManager !== "undefined") {
          const groupChatManager = new GroupChatManager();
          groupChatManager.init(groupGuid, currentUserGuid);
          window.groupChatManager = groupChatManager;
        }

        //Initialize message loader for lazy loading with pagination state
        if (window.MessageLoader) {
          const messageLoader = new MessageLoader(groupGuid, true);
          messageLoader.hasMore = messagesData.has_more;
          messageLoader.nextCursor = messagesData.next_cursor;
          messageLoader.init();
          window.messageLoader = messageLoader;
        }
      } catch (error) {}
    } else if (friendGuid) {
      //Verify friendship via API
      const friendData = await dataLoader.loadOneOnOneData(friendGuid);

      //Update UI with friend data
      renderer.updateFriendHeader(friendData);

      //Load messages via API (with pagination info)
      const messagesData = await dataLoader.loadMessages(friendGuid, false);

      //Render messages
      renderer.renderMessages(messagesData.messages, false);

      //Initialize one-on-one chat manager for WebSocket
      if (token && window.chatManager) {
        window.chatManager.init(token, friendGuid);
      }

      //Register direct_typing WebSocket handler
      if (window.websocketClient) {
        window.websocketClient.addMessageHandler("direct_typing", (data) => {
          if (window.chatManager) {
            window.chatManager.updateTypingIndicator(
              data.is_typing,
              data.username,
            );
          }
        });
      }

      //Initialize message loader for one-on-one chats with pagination state
      if (window.MessageLoader) {
        const messageLoader = new MessageLoader(friendGuid, false);
        messageLoader.hasMore = messagesData.has_more;
        messageLoader.nextCursor = messagesData.next_cursor;
        messageLoader.init();
        window.messageLoader = messageLoader;
      }
    }

    //Initialize custom audio players
    initializeAudioPlayers();

    //Set flag to indicate initial messages are loaded
    window.chatboxInitialized = true;

    //Process any queued instant messages
    if (
      window.pendingInstantMessages &&
      window.pendingInstantMessages.length > 0
    ) {
      window.pendingInstantMessages.forEach((queuedMessage) => {
        //Re-trigger the message handler with the queued message
        if (queuedMessage.handler) {
          queuedMessage.handler(queuedMessage.data);
        }
      });
      window.pendingInstantMessages = [];
    }
  } catch (error) {
    //Set flag even on error so queued messages can still be processed
    window.chatboxInitialized = true;

    //Redirect to appropriate page on critical errors
    if (
      error.message.includes("not friends") ||
      error.message.includes("not a member")
    ) {
      if (isGroupChat) {
        window.location.href = "messages.php";
      } else {
        window.location.href = "friends.php";
      }
    }
  }
}

//Initialize custom audio players
function initializeAudioPlayers() {
  document.addEventListener("click", function (e) {
    //Handle play/pause button clicks
    if (e.target.closest(".play-pause-btn")) {
      const btn = e.target.closest(".play-pause-btn");
      const audioId = btn.getAttribute("data-audio");
      const audio = document.getElementById(audioId);
      const playIcon = btn.querySelector(".play-icon");
      const pauseIcon = btn.querySelector(".pause-icon");

      if (audio.paused) {
        //Pause all other audio players first
        document
          .querySelectorAll(".custom-audio-player audio")
          .forEach((otherAudio) => {
            if (otherAudio.id !== audioId && !otherAudio.paused) {
              otherAudio.pause();
              const otherBtn = document.querySelector(
                `[data-audio="${otherAudio.id}"].play-pause-btn`,
              );
              if (otherBtn) {
                otherBtn.querySelector(".play-icon").classList.remove("d-none");
                otherBtn.querySelector(".pause-icon").classList.add("d-none");
              }
            }
          });

        audio.play();
        playIcon.classList.add("d-none");
        pauseIcon.classList.remove("d-none");
      } else {
        audio.pause();
        playIcon.classList.remove("d-none");
        pauseIcon.classList.add("d-none");
      }
    }

    //Handle progress bar clicks
    if (e.target.closest(".progress-bar")) {
      const progressBar = e.target.closest(".progress-bar");
      const audioId = progressBar.getAttribute("data-audio");
      const audio = document.getElementById(audioId);

      const rect = progressBar.getBoundingClientRect();
      const clickX = e.clientX - rect.left;
      const percentage = clickX / rect.width;
      audio.currentTime = percentage * audio.duration;
    }

    //Handle video play/pause button clicks
    if (e.target.closest(".video-play-btn")) {
      const btn = e.target.closest(".video-play-btn");
      const videoId = btn.getAttribute("data-video");
      const video = document.getElementById(videoId);
      const playIcon = btn.querySelector(".video-play-icon");
      const pauseIcon = btn.querySelector(".video-pause-icon");

      if (video.paused) {
        //Load metadata on first play, or reload after ended
        if (video.readyState === 0 || video._needsReload) {
          video._needsReload = false;
          video.load();
        }
        const startPlay = () => {
          video.play().catch((err) => {
            //Revert icons
            playIcon.classList.remove("d-none");
            pauseIcon.classList.add("d-none");
          });
        };
        //Wait for any ongoing seek before playing
        if (video.seeking) {
          video.addEventListener("seeked", startPlay, { once: true });
        } else {
          startPlay();
        }
        playIcon.classList.add("d-none");
        pauseIcon.classList.remove("d-none");
      } else {
        video.pause();
        playIcon.classList.remove("d-none");
        pauseIcon.classList.add("d-none");
      }
    }

    //Handle video progress bar clicks
    if (e.target.closest(".video-progress-bar")) {
      const progressBar = e.target.closest(".video-progress-bar");
      const videoId = progressBar.getAttribute("data-video");
      const video = document.getElementById(videoId);

      const rect = progressBar.getBoundingClientRect();
      const clickX = e.clientX - rect.left;
      const percentage = clickX / rect.width;
      video.currentTime = percentage * video.duration;
    }
  });

  //Attach event listeners to all existing audio elements
  attachAudioEventListeners(
    document.querySelectorAll(".custom-audio-player audio"),
  );

  //Attach event listeners to all existing video elements
  attachVideoEventListeners(
    document.querySelectorAll(".custom-video-player video"),
  );
}

//Initialize audio players for newly added messages
function initializeNewAudioPlayers(messageElement) {
  const audioElements = messageElement.querySelectorAll(
    ".custom-audio-player audio",
  );
  attachAudioEventListeners(audioElements);

  const videoElements = messageElement.querySelectorAll(
    ".custom-video-player video",
  );
  attachVideoEventListeners(videoElements);
}

//Make globally available
window.initializeNewAudioPlayers = initializeNewAudioPlayers;

//Attach event listeners to audio elements
function attachAudioEventListeners(audioElements) {
  audioElements.forEach((audio) => {
    audio.addEventListener("loadedmetadata", function () {
      const player = this.closest(".custom-audio-player");
      const duration = player.querySelector(".duration");
      if (isFinite(this.duration) && this.duration > 0) {
        duration.textContent = formatTime(this.duration);
      } else if (!this._durationResolved) {
        /*Chrome WebM: EBML Duration=0
        Seek to end to force recalculation (first load only). */
        this._seekingForDuration = true;
        this.currentTime = 1e9;
      }
    });

    audio.addEventListener("durationchange", function () {
      if (isFinite(this.duration) && this.duration > 0) {
        const player = this.closest(".custom-audio-player");
        const duration = player.querySelector(".duration");
        if (duration) duration.textContent = formatTime(this.duration);
        //Reset playhead after seek-to-end trick (idle state shows 0:00 / 0:04)
        if (this._seekingForDuration) {
          this._durationResolved = true;
          this._seekingForDuration = false;
          if (this.paused) this.currentTime = 0; //Don't interrupt active playback
        }
      }
    });

    audio.addEventListener("timeupdate", function () {
      //Suppress time-update while seeking to end for duration calculation
      if (this._seekingForDuration) return;
      const player = this.closest(".custom-audio-player");
      const progressFill = player.querySelector(".progress-bar-fill");
      const currentTime = player.querySelector(".current-time");

      const percentage = (this.currentTime / this.duration) * 100;
      progressFill.style.width = percentage + "%";
      currentTime.textContent = formatTime(this.currentTime);
    });

    audio.addEventListener("ended", function () {
      const player = this.closest(".custom-audio-player");
      const btn = player.querySelector(".play-pause-btn");
      btn.querySelector(".play-icon").classList.remove("d-none");
      btn.querySelector(".pause-icon").classList.add("d-none");

      //Reset progress
      const progressFill = player.querySelector(".progress-bar-fill");
      progressFill.style.width = "0%";
      this.currentTime = 0;
      //Explicitly reset display (Chrome does not fire time-update at position 0 after ended)
      const currentTimeEl = player.querySelector(".current-time");
      if (currentTimeEl) currentTimeEl.textContent = formatTime(0);
    });

    audio.addEventListener("error", function (e) {
      const player = this.closest(".custom-audio-player");
      player.innerHTML =
        '<span class="media-error-text">❌ Audio not available</span>';
    });
  });
}

//Attach event listeners to video elements
function attachVideoEventListeners(videoElements) {
  videoElements.forEach((video) => {
    video.addEventListener("loadedmetadata", function () {
      const player = this.closest(".custom-video-player");
      const timeDisplay = player.querySelector(".video-time");
      if (isFinite(this.duration) && this.duration > 0) {
        timeDisplay.textContent = `0:00 / ${formatTime(this.duration)}`;
      } else if (!this._durationResolved) {
        /*Chrome WebM: EBML Duration=0
        Seek to end to force recalculation (first load only). */
        this._seekingForDuration = true;
        this.currentTime = 1e9;
      }
    });

    video.addEventListener("durationchange", function () {
      if (isFinite(this.duration) && this.duration > 0) {
        const player = this.closest(".custom-video-player");
        const timeDisplay = player.querySelector(".video-time");
        if (this._seekingForDuration) {
          //Show 0:00 since resetting position
          if (timeDisplay)
            timeDisplay.textContent = `0:00 / ${formatTime(this.duration)}`;
          this._durationResolved = true;
          this._seekingForDuration = false;
          if (this.paused) this.currentTime = 0;
        } else {
          //Use actual currentTime to avoid flashing 0:00 mid-playback
          if (timeDisplay)
            timeDisplay.textContent = `${formatTime(this.currentTime)} / ${formatTime(this.duration)}`;
        }
      }
    });

    video.addEventListener("timeupdate", function () {
      //Suppress time-update while seeking to end for duration calculation
      if (this._seekingForDuration) return;
      const player = this.closest(".custom-video-player");
      const progressFill = player.querySelector(".video-progress-fill");
      const timeDisplay = player.querySelector(".video-time");

      const percentage = (this.currentTime / this.duration) * 100;
      progressFill.style.width = percentage + "%";
      timeDisplay.textContent = `${formatTime(this.currentTime)} / ${formatTime(this.duration)}`;
    });

    video.addEventListener("ended", function () {
      const player = this.closest(".custom-video-player");
      const btn = player.querySelector(".video-play-btn");
      btn.querySelector(".video-play-icon").classList.remove("d-none");
      btn.querySelector(".video-pause-icon").classList.add("d-none");

      //Reset progress
      const progressFill = player.querySelector(".video-progress-fill");
      progressFill.style.width = "0%";
      this.currentTime = 0;
      //Reset display (Chrome does not fire time-update at position 0 after ended)
      const timeDisplay = player.querySelector(".video-time");
      if (timeDisplay)
        timeDisplay.textContent = `0:00 / ${formatTime(this.duration)}`;
      //Mark that Chrome needs a full reload to replay this WebM from position 0
      this._needsReload = true;
    });

    video.addEventListener("error", function (e) {
      const source = this.querySelector("source");
      const errorCode = this.error ? this.error.code : "unknown";
      const errorMessage = this.error ? this.error.message : "unknown";

      /* Only show error if it's a critical error (not during seeking).
      Error code 2 during seeking is often temporary. */
      if (this.seeking || this.readyState > 0) {
        return; //Don't destroy player for temporary errors
      }

      const player = this.closest(".custom-video-player");
      if (player) {
        player.innerHTML = `<span class="media-error-text">❌ Video not available (Error: ${errorCode})</span>`;
      }
    });
  });
}

//Format time in seconds to MM:SS
function formatTime(seconds) {
  if (isNaN(seconds) || !isFinite(seconds) || seconds < 0) {
    return "0:00";
  }
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return mins + ":" + (secs < 10 ? "0" : "") + secs;
}

//Initialize on page load
document.addEventListener("DOMContentLoaded", initChatboxPage);
