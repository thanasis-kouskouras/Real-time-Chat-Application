/* MESSAGE RENDERER MODULE

Unified message rendering for both initial load and real-time messages. */

import { escapeHtml } from './config.js';

class MessageRenderer {
  constructor(currentUserGuid, options = {}) {
    this.currentUserGuid = currentUserGuid;
    this.autoScroll = options.autoScroll !== false;
    this.waitForMedia = options.waitForMedia !== false;
  }

  //Main render method (handles all message types)
  renderMessage(message, container, context = {}) {
    const isMyMessage = this.isMyMessage(message);
    const isGroup = context.isGroup || false;

    if (isMyMessage) {
      this.renderMyMessage(message, container);
    } else if (isGroup) {
      this.renderGroupMessage(message, container);
    } else {
      this.renderFriendMessage(message, container, context.friendGuid);
    }
  }

  //Render instant message from WebSocket (with auto-scroll)
  renderInstantMessage(messageData, context = {}) {
    const container = document.getElementById("bodyMsg");
    if (!container) {
      return;
    }

    //Convert WebSocket data format to standard format
    const message = this.normalizeMessageData(messageData, context);

    this.renderMessage(message, container, context);

    if (this.autoScroll) {
      this.scrollToBottom(this.waitForMedia);
    }
  }

  //Normalize message data from different sources (API vs WebSocket)
  normalizeMessageData(data, context = {}) {
    //Determine if this is an incoming message (should be marked as unread)
    const isIncoming =
      data.fromName !== "Me" &&
      data.from_guid !== this.currentUserGuid &&
      data.sender_guid !== this.currentUserGuid;

    return {
      message_guid:
        data.chatId || data.message_guid || data.message_id || data.id,
      message_content:
        data.msg || data.message || data.message_content || data.chat_message,
      sent_at: data.date || data.sent_at || data.created_at,
      from_guid: data.from || data.from_guid || data.sender_guid,
      sender_guid: data.sender_guid || data.from_guid,
      sender_name: data.senderName || data.sender_name,
      sender_profile_url: data.sender_profile_url || data.profile_image_url, //Support both field names
      mime_type: data.filetype || data.mime_type || data.attachmentType,
      url: data.attachment || data.url,
      attachment: data.attachment,
      attachment_guid: data.attachment_guid,
      filename: data.filename,
      status: isIncoming ? 1 : data.status || data.chat_status || 0, //Mark incoming instant messages as unread (status = 1)
      chat_status: isIncoming ? 1 : data.chat_status || data.status || 0,
      isGroup: context.isGroup,
      friendGuid: context.friendGuid,
    };
  }

  //Scroll to bottom of message container
  scrollToBottom(waitForMedia = false) {
    const body = document.getElementById("bodyMsg");
    if (!body) return;

    const doScroll = () => {
      body.scrollTop = body.scrollHeight + 1000;
    };

    if (waitForMedia) {
      //Wait for media elements to load
      const newMessage = body.lastElementChild;
      if (!newMessage) {
        doScroll();
        return;
      }

      const mediaElements = newMessage.querySelectorAll("img, video, audio");

      if (mediaElements.length > 0) {
        let loadedCount = 0;
        const totalMedia = mediaElements.length;

        const checkAllLoaded = () => {
          loadedCount++;
          if (loadedCount >= totalMedia) {
            doScroll();
            setTimeout(doScroll, 100);
          }
        };

        mediaElements.forEach((element) => {
          if (element.tagName === "IMG") {
            if (element.complete) {
              checkAllLoaded();
            } else {
              element.addEventListener("load", checkAllLoaded, { once: true });
              element.addEventListener("error", checkAllLoaded, { once: true });
            }
          } else if (
            element.tagName === "VIDEO" ||
            element.tagName === "AUDIO"
          ) {
            if (element.readyState >= 2) {
              checkAllLoaded();
            } else {
              element.addEventListener("loadeddata", checkAllLoaded, {
                once: true,
              });
              element.addEventListener("error", checkAllLoaded, { once: true });
            }
          }
        });

        //Fallback timeout
        setTimeout(doScroll, 1000);
      } else {
        //No media, just scroll with small delays
        doScroll();
        setTimeout(doScroll, 50);
        setTimeout(doScroll, 200);
      }
    } else {
      //Immediate scroll
      doScroll();
      setTimeout(doScroll, 100);
      setTimeout(doScroll, 300);
    }
  }

  //Check if message is from current user
  isMyMessage(message) {
    const fromGuid = message.from_guid || message.sender_guid;
    return fromGuid === this.currentUserGuid;
  }

  //Render message (sent by current user)
  renderMyMessage(message, container) {
    const messageId =
      message.message_guid || message.chatId || message.message_id;
    const messageContent =
      message.message_content || message.chat_message || message.msg || "";
    const messageDate = this.formatDate(
      message.sent_at || message.created_at || message.date,
    );
    const hasAttachment = this.hasAttachment(message);
    //Check multiple ways a message can be deleted
    const isDeleted =
      messageContent === "<i>Deleted Message</i>" ||
      messageContent.includes("Deleted Message") ||
      message.is_deleted === true ||
      message.is_deleted === 1 ||
      message.chat_is_deleted === true ||
      message.chat_is_deleted === 1;

    //Validate messageId (if undefined, skip delete button)
    if (!messageId) {
      return;
    }

    const messageDiv = document.createElement("div");
    messageDiv.id = messageId;
    messageDiv.setAttribute("data-message-id", messageId);
    messageDiv.className = hasAttachment ? "divFrom" : "divFromText";

    let content = "";

    //Wrap delete button and content together for proper positioning
    const mimeType = message.mime_type || message.filetype || "";
    const isAudio = hasAttachment && !isDeleted && mimeType.startsWith("audio");
    const isImage = hasAttachment && !isDeleted && mimeType.startsWith("image");
    const isFile =
      hasAttachment &&
      !isDeleted &&
      !isAudio &&
      !isImage &&
      !mimeType.startsWith("video");
    content += `<div class='message-content-wrapper${hasAttachment && !isDeleted && !isFile ? " has-media" : ""}${isAudio ? " has-audio" : ""}${isImage ? " has-image" : ""}${isFile ? " has-file" : ""}'>`;

    //Delete button (if not deleted and messageId is valid)
    if (!isDeleted && messageId) {
      const buttonId =
        hasAttachment && message.mime_type?.startsWith("audio")
          ? "buttonDeleteSound"
          : "buttonDelete";
      content += `<button id='${buttonId}' onclick='delete_message("${escapeHtml(messageId)}")' class='btn-delete-message' type='button'>X</button>`;
    }

    //Message content
    if (hasAttachment && !isDeleted) {
      content += `<div class='attachment-wrapper'>`;
      content += this.getMediaHtml(
        message.mime_type || message.filetype,
        message.url || message.attachment,
        message.filename,
      );
      content += `<div class='media-timestamp'><span class='small_span_dark'>${messageDate}</span></div>`;
      content += `</div>`;
    } else {
      const spanClass = "small_span_white";
      content += `<p class='pFrom'>${isDeleted ? "<i>Deleted Message</i>" : escapeHtml(messageContent)}<br><span class='${spanClass}'>${messageDate}</span></p>`;
    }

    content += `</div>`; //Close message-content-wrapper

    messageDiv.innerHTML = content;
    container.appendChild(messageDiv);

    //Initialize audio players for this message if it has audio
    if (
      hasAttachment &&
      !isDeleted &&
      (message.mime_type || message.filetype || "").startsWith("audio")
    ) {
      if (typeof initializeNewAudioPlayers === "function") {
        initializeNewAudioPlayers(messageDiv);
      }
    }

    //Initialize video players for this message if it has video
    if (
      hasAttachment &&
      !isDeleted &&
      (message.mime_type || message.filetype || "").startsWith("video")
    ) {
      if (typeof initializeNewAudioPlayers === "function") {
        initializeNewAudioPlayers(messageDiv);
      }
    }
  }

  //Render friend message (one-on-one chat)
  renderFriendMessage(message, container, friendGuid = null) {
    const messageId =
      message.message_guid || message.chatId || message.message_id;
    const messageContent =
      message.message_content || message.chat_message || message.msg || "";
    const messageDate = this.formatDate(
      message.sent_at || message.created_at || message.date,
    );
    const hasAttachment = this.hasAttachment(message);
    //Check multiple ways a message can be deleted
    const isDeleted =
      messageContent === "<i>Deleted Message</i>" ||
      messageContent.includes("Deleted Message") ||
      message.is_deleted === true ||
      message.is_deleted === 1 ||
      message.chat_is_deleted === true ||
      message.chat_is_deleted === 1;

    //Validate messageId (if undefined, skip rendering)
    if (!messageId) {
      return;
    }

    const messageDiv = document.createElement("div");
    messageDiv.id = messageId;
    messageDiv.setAttribute("data-message-id", messageId);
    messageDiv.className = hasAttachment ? "divTo" : "divToText";

    let content = "";

    //Unread indicator
    if (message.status === 1 || message.chat_status === 1) {
      content += `<span id='unread_${messageId}' class='dotBlock'></span>`;
    }

    //Profile image (use provided URL or construct API URL)
    const toUserGuid = friendGuid || message.from_guid || message.sender_guid;
    const profileImageUrl =
      message.sender_profile_url ||
      (toUserGuid
        ? this.getProfileImageUrl(toUserGuid)
        : "img/profiledefault.jpg");
    content += `<img id='profileImgInMessage' src='${profileImageUrl}' onerror="this.src='img/profiledefault.jpg';">`;

    //Message content
    const mimeTypeFriend = message.mime_type || message.filetype || "";
    const isImageFriend =
      hasAttachment && !isDeleted && mimeTypeFriend.startsWith("image");
    if (hasAttachment && !isDeleted) {
      content += `<div class='attachment-wrapper${isImageFriend ? " is-image" : ""}'>`;
      content += this.getMediaHtml(
        message.mime_type || message.filetype,
        message.url || message.attachment,
        message.filename,
      );
      content += `<div class='media-timestamp'><span class='small_span'>${messageDate}</span></div>`;
      content += `</div>`;
    } else {
      content += `<p class='pTo'>${isDeleted ? "<i>Deleted Message</i>" : escapeHtml(messageContent)}<br><span class='small_span'>${messageDate}</span></p>`;
    }

    messageDiv.innerHTML = content;
    container.appendChild(messageDiv);

    //Initialize audio players for this message if it has audio
    if (
      hasAttachment &&
      !isDeleted &&
      (message.mime_type || message.filetype || "").startsWith("audio")
    ) {
      if (typeof initializeNewAudioPlayers === "function") {
        initializeNewAudioPlayers(messageDiv);
      }
    }

    //Initialize video players for this message if it has video
    if (
      hasAttachment &&
      !isDeleted &&
      (message.mime_type || message.filetype || "").startsWith("video")
    ) {
      if (typeof initializeNewAudioPlayers === "function") {
        initializeNewAudioPlayers(messageDiv);
      }
    }
  }

  //Render group message
  renderGroupMessage(message, container) {
    const messageId =
      message.message_guid || message.chatId || message.message_id;
    const messageContent =
      message.message_content || message.chat_message || message.msg || "";
    const messageDate = this.formatDate(
      message.sent_at || message.created_at || message.date,
    );
    const senderName =
      message.sender_name || message.senderName || "Unknown User";
    const senderGuid = message.sender_guid || message.senderGuid;
    const hasAttachment = this.hasAttachment(message);
    //Check multiple ways a message can be deleted
    const isDeleted =
      messageContent === "<i>Deleted Message</i>" ||
      messageContent.includes("Deleted Message") ||
      message.is_deleted === true ||
      message.is_deleted === 1;
    const isUnread =
      message.isRead === false ||
      message.is_read === false ||
      message.status === 1 ||
      message.chat_status === 1;

    //Validate messageId (if undefined, skip rendering)
    if (!messageId) {
      return;
    }

    const messageDiv = document.createElement("div");
    messageDiv.className = "group-message-container";

    //Profile image (use provided URL or construct API URL)
    const profileImageUrl =
      message.sender_profile_url ||
      (senderGuid
        ? this.getProfileImageUrl(senderGuid)
        : "img/profiledefault.jpg");

    let content = `
            <div class='group-message-header'>
                <img class='group-profile-img' src='${profileImageUrl}' onerror="this.src='img/profiledefault.jpg';">
                <span class='sender-name-label'>${escapeHtml(senderName)}</span>
            </div>
            <div id='${messageId}' data-message-id='${messageId}' class='${hasAttachment ? "divTo" : "divToText"}'>
        `;

    //Add unread indicator for group messages
    if (isUnread) {
      content += `<span id='unread_${messageId}' class='dotBlock'></span>`;
    }

    const mimeTypeGroup = message.mime_type || message.filetype || "";
    const isImageGroup =
      hasAttachment && !isDeleted && mimeTypeGroup.startsWith("image");
    if (hasAttachment && !isDeleted) {
      content += `<div class='attachment-wrapper${isImageGroup ? " is-image" : ""}'>`;
      content += this.getMediaHtml(
        message.mime_type || message.filetype,
        message.url || message.attachment,
        message.filename,
      );
      content += `<div class='media-timestamp'><span class='small_span'>${messageDate}</span></div>`;
      content += `</div>`;
    } else {
      content += `<p class='pTo'>${isDeleted ? "<i>Deleted Message</i>" : escapeHtml(messageContent)}<br><span class='small_span'>${messageDate}</span></p>`;
    }

    content += `</div>`;

    messageDiv.innerHTML = content;
    container.appendChild(messageDiv);

    //Initialize audio players for this message if it has audio
    if (
      hasAttachment &&
      !isDeleted &&
      (message.mime_type || message.filetype || "").startsWith("audio")
    ) {
      if (typeof initializeNewAudioPlayers === "function") {
        initializeNewAudioPlayers(messageDiv);
      }
    }

    //Initialize video players for this message if it has video
    if (
      hasAttachment &&
      !isDeleted &&
      (message.mime_type || message.filetype || "").startsWith("video")
    ) {
      if (typeof initializeNewAudioPlayers === "function") {
        initializeNewAudioPlayers(messageDiv);
      }
    }
  }

  //Check if message has attachment
  hasAttachment(message) {
    return !!(
      message.attachment_guid ||
      message.url ||
      message.attachment ||
      message.mime_type ||
      message.filetype
    );
  }

  //Get media HTML for attachments
  getMediaHtml(mimeType, url, filename) {
    //Extract filename from URL if not provided
    let displayFilename = filename;
    if (!displayFilename && url) {
      try {
        //Try to extract from URL path or query params
        const urlMatch = url.match(/[?&]filename=([^&]+)/);
        if (urlMatch) {
          displayFilename = decodeURIComponent(urlMatch[1]);
        } else {
          //Try to get from path
          const urlObj = new URL(url, window.location.origin);
          const pathParts = urlObj.pathname.split("/");
          const lastPart = pathParts[pathParts.length - 1];
          if (
            lastPart &&
            lastPart !== "download.php" &&
            lastPart.includes(".")
          ) {
            displayFilename = decodeURIComponent(lastPart);
          }
        }
      } catch (e) {
        //URL parsing failed (keep displayFilename as null)
      }
    }

    //Fallback if still no filename
    if (!displayFilename) {
      displayFilename = "Attachment";
    }

    if (!mimeType || !url) {
      //Return filename if available, otherwise empty (prevents empty space)
      if (displayFilename && displayFilename !== "Attachment") {
        return `<a href='#' role='link' class='attachment-link' onclick='return false;'>
                          <div class="document-link">
                            <span class="doc-icon">📎</span>
                            <span class="doc-name" title="${escapeHtml(displayFilename)}">${escapeHtml(displayFilename)}</span>
                          </div>
                        </a>`;
      }
      return "";
    }

    //Backend returns complete URLs
    const mediaUrl = url;

    let media = "";

    if (mimeType.startsWith("image")) {
      //For images, show image only (date will be added by renderer)
      media = `<div class="image-attachment-wrapper">
                      <img class='imgChat' src='${mediaUrl}' alt='${escapeHtml(displayFilename)}' onerror="this.classList.add('d-none'); this.parentElement.innerHTML='<span class=text-danger>❌ Image not available</span>';" />
                    </div>`;
    } else if (mimeType.startsWith("audio")) {
      //Custom audio player for consistency across browsers
      const streamUrl = mediaUrl.includes("?")
        ? `${mediaUrl}&mode=inline`
        : `${mediaUrl}?mode=inline`;
      const downloadUrl = mediaUrl.includes("?")
        ? `${mediaUrl}&mode=attachment`
        : `${mediaUrl}?mode=attachment`;
      const audioId = "audio-" + Math.random().toString(36).substr(2, 9);

      //Use actual mimeType or fallback for browser compatibility
      const audioType = mimeType || "audio/mpeg";
      media = `<div class='custom-audio-player' data-audio-id='${audioId}'>
                        <audio id='${audioId}' preload='metadata'>
                            <source src='${streamUrl}' type='${audioType}'>
                            <!-- Fallback sources for cross-browser compatibility -->
                            <source src='${streamUrl}' type='audio/webm'>
                            <source src='${streamUrl}' type='audio/mpeg'>
                            <source src='${streamUrl}' type='audio/ogg'>
                        </audio>
                        <button class='play-pause-btn' data-audio='${audioId}' title='Play/Pause'>
                            <svg class='play-icon' viewBox='0 0 24 24'>
                                <path d='M8 5v14l11-7z'/>
                            </svg>
                            <svg class='pause-icon d-none' viewBox='0 0 24 24'>
                                <path d='M6 4h4v16H6V4zm8 0h4v16h-4V4z'/>
                            </svg>
                        </button>
                        <div class='audio-controls'>
                            <div class='progress-bar' data-audio='${audioId}'>
                                <div class='progress-bar-fill'></div>
                            </div>
                            <div class='time-display'>
                                <span class='current-time'>0:00</span>
                                <span class='time-separator'>/</span>
                                <span class='duration'>0:00</span>
                            </div>
                        </div>
                        <a href='${downloadUrl}' download='${escapeHtml(displayFilename) || "audio.mp3"}' class='download-btn' title='Download'>
                            <svg viewBox='0 0 24 24'>
                                <path d='M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z'/>
                            </svg>
                        </a>
                    </div>`;
      return media;
    } else if (mimeType.startsWith("video")) {
      //Custom video player for consistency and better browser support
      const streamUrl = mediaUrl.includes("?")
        ? `${mediaUrl}&mode=inline`
        : `${mediaUrl}?mode=inline`;
      const downloadUrl = mediaUrl.includes("?")
        ? `${mediaUrl}&mode=attachment`
        : `${mediaUrl}?mode=attachment`;
      const videoId = "video-" + Math.random().toString(36).substr(2, 9);

      media = `<div class='custom-video-player' data-video-id='${videoId}'>
                        <video id='${videoId}' preload='auto'>
                            <source src='${streamUrl}' type='${mimeType}'>
                            Your browser does not support the video element.
                        </video>
                        <div class='video-controls'>
                            <button class='video-play-btn' data-video='${videoId}' title='Play/Pause'>
                                <svg class='video-play-icon' viewBox='0 0 24 24' width='24' height='24'>
                                    <path fill='white' d='M8 5v14l11-7z'/>
                                </svg>
                                <svg class='video-pause-icon d-none' viewBox='0 0 24 24' width='24' height='24'>
                                    <path fill='white' d='M6 4h4v16H6V4zm8 0h4v16h-4V4z'/>
                                </svg>
                            </button>
                            <div class='video-progress-container'>
                                <div class='video-progress-bar' data-video='${videoId}'>
                                    <div class='video-progress-fill'></div>
                                </div>
                                <span class='video-time'>0:00 / 0:00</span>
                            </div>
                            <a href='${downloadUrl}' download='${escapeHtml(displayFilename) || "video.mp4"}' class='video-download-btn' title='Download'>
                                <svg viewBox='0 0 24 24' width='20' height='20'>
                                    <path fill='white' d='M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z'/>
                                </svg>
                            </a>
                        </div>
                    </div>`;
      return media;
    } else {
      const icon = this.getDocumentIcon(mimeType);
      media = `<div class="document-link">
                      <span class="doc-icon">${icon}</span>
                      <span class="doc-name" title="${escapeHtml(displayFilename)}">${escapeHtml(displayFilename)}</span>
                    </div>`;
    }

    return `<a href='${mediaUrl}' role='link' target='_blank' rel='noopener noreferrer' class='attachment-link' download="${escapeHtml(displayFilename)}">${media}</a>`;
  }

  //Get document icon based on MIME type
  getDocumentIcon(mimeType) {
    if (mimeType === "application/pdf") return "📄";
    if (
      mimeType === "application/msword" ||
      mimeType ===
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
    )
      return "📄";
    if (
      mimeType === "application/vnd.ms-excel" ||
      mimeType ===
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    )
      return "📊";
    if (
      mimeType === "application/vnd.ms-powerpoint" ||
      mimeType ===
        "application/vnd.openxmlformats-officedocument.presentationml.presentation"
    )
      return "📑";
    if (mimeType === "text/plain") return "📝";
    return "📄";
  }

  //Get profile image URL (with caching)
  getProfileImageUrl(userGuid) {
    //Convert GUID to hex format (remove dashes) for download.php
    const guidHex = userGuid.replace(/-/g, "");
    return `download.php?guid=${guidHex}&type=user`;
  }

  //Format date
  formatDate(dateString) {
    if (!dateString) return "";

    //If it's already formatted, return as is
    if (
      typeof dateString === "string" &&
      dateString.includes("/") &&
      dateString.includes(",")
    ) {
      return dateString;
    }

    try {
      const date = new Date(dateString);
      if (isNaN(date.getTime())) return dateString;

      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      const year = date.getFullYear();
      const hours = String(date.getHours()).padStart(2, "0");
      const minutes = String(date.getMinutes()).padStart(2, "0");

      return `${day}/${month}/${year}, ${hours}:${minutes}`;
    } catch (e) {
      return dateString;
    }
  }

}

// Export for use in other modules
export { MessageRenderer };

// Global export for non-module scripts
window.MessageRenderer = MessageRenderer;
