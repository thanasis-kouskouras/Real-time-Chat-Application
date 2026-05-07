/* CHATBOX UI MODULE

Handles chatbox-specific UI functionality. */

import { setupFileDragAndDrop } from "./file-handler.js";
import { mediaCapture } from "./media-capture.js";
import {
  add_to_message,
  add_group_to_message,
  add_from_message,
  deleteLocalMessage,
} from "../chat/chat-helpers.js";
import { deleteNoMessageYetDiv } from "./ui-updates.js";

class ChatboxUI {
  constructor() {
    this.actualBtn = null;
    this.fileChosen = null;
    this.bodyMsg = null;
    this.chat_message = null;
    this.sendButton = null;
    this.chatForm = null;
  }

  initialize() {
    if (!window.location.href.includes("chatbox.php")) {
      return;
    }

    //Initialize media capture first
    mediaCapture.initialize();

    this.actualBtn = document.getElementById("actual-btn");
    this.fileChosen = document.getElementById("file-chosen");
    this.bodyMsg = document.getElementById("bodyMsg");
    this.chat_message = document.getElementById("chat_message");
    this.sendButton = document.getElementById("send");
    this.chatForm = document.getElementById("chat_form");

    if (!this.actualBtn || !this.fileChosen) {
      return;
    }

    this.setupFileInput();
    this.setupScrolling();
    this.setupSendButton();
    this.setupFormHandlers();
    this.setupMarkReadOnClick();
    setupFileDragAndDrop();

    //Automatically mark messages as read when entering chatbox
    setTimeout(() => {
      this.markMessagesAsRead();
    }, 500); //Small delay to ensure page is fully loaded
  }

  setupMarkReadOnClick() {
    if (!this.chat_message) return;

    //Add click handler to mark messages as read
    this.chat_message.addEventListener("click", () => {
      this.markMessagesAsRead();
    });
  }

  markMessagesAsRead() {
    const isGroupChat = document.getElementById("isGroupChat")?.value === "1";

    if (isGroupChat) {
      //Mark group messages as read via WebSocket
      const groupGuid = document.getElementById("groupGuid")?.value;
      if (groupGuid && window.send_message) {
        this.markGroupMessagesAsReadWebSocket(groupGuid);
      }
    } else {
      //Use existing readMessage function for one-to-one chats
      if (window.readMessage) {
        window.readMessage();

        //Also refresh header counters for one-to-one chats
        setTimeout(() => {
          this.refreshHeaderCounters();
        }, 100); //Small delay to ensure the readMessage WebSocket call completes
      }
    }
  }

  markGroupMessagesAsReadWebSocket() {
    //Hide unread indicators immediately for better UX
    this.hideUnreadIndicators();

    //Send WebSocket message - send_message will automatically add group_guid
    if (window.send_message) {
      window.send_message("readGroupChat");
    }

    //Refresh header counters after a short delay
    setTimeout(() => {
      this.refreshHeaderCounters();
    }, 500); //Delay to allow WebSocket response to complete
  }

  refreshHeaderCounters() {
    //Refresh unread message count in header
    if (typeof window.loadCombinedUnreadCount === "function") {
      window.loadCombinedUnreadCount();
    }

    //Also refresh notification count in case there were group invitations
    if (typeof window.loadCombinedNotificationCount === "function") {
      window.loadCombinedNotificationCount();
    }
  }

  hideUnreadIndicators() {
    //Hide unread dots similar to readMessage() function
    const dots = document.getElementsByClassName("dotBlock");
    for (let i = 0; i < dots.length; i++) {
      if (dots[i].style.display !== "none") {
        dots[i].style.display = "none";
      }
    }
  }

  setupSendButton() {
    if (!this.sendButton || !this.chat_message) return;

    //Function to update send button state
    const updateSendButtonState = () => {
      const messageText = this.chat_message.value?.trim() || "";
      const hasAttachment = window.haveAttachment && window.haveAttachment();

      if (this.sendButton) {
        if (messageText.length > 0 || hasAttachment) {
          this.sendButton.disabled = false;
          this.sendButton.removeAttribute("disabled");
          this.sendButton.classList.remove("ui-disabled");
        } else {
          this.sendButton.disabled = true;
          this.sendButton.setAttribute("disabled", "disabled");
          this.sendButton.classList.add("ui-disabled");
        }
      }
    };

    //Initial state
    updateSendButtonState();

    //Update send button state on textarea input
    this.chat_message.addEventListener("input", updateSendButtonState);

    //Update send button state when file is selected/cleared
    this.actualBtn.addEventListener("change", updateSendButtonState);

    //Store the update function for external access
    this.updateSendButtonState = updateSendButtonState;
  }

  setupFormHandlers() {
    if (!this.chatForm) return;

    //Handle send button click directly
    const sendButton = document.getElementById("send");
    if (sendButton) {
      sendButton.addEventListener("click", (event) => {
        event.preventDefault();

        //Validate before sending
        const messageText = this.chat_message?.value?.trim() || "";
        const hasAttachment = window.haveAttachment && window.haveAttachment();

        if (messageText.length === 0 && !hasAttachment) {
          return;
        }

        let action = "sendTextMessage";
        if (hasAttachment) {
          action = "sendAttachment";
          mediaCapture.hideCaptureContent(mediaCapture.captureType);
        }

        //Call the global send_message function
        if (window.send_message) {
          window.send_message(action);
        }

        //Update button state after sending
        setTimeout(() => {
          if (this.updateSendButtonState) {
            this.updateSendButtonState();
          }
        }, 100);
      });
    }

    //Handle media buttons as click events (not form submission)
    const audioButton = document.getElementById("audioCallButton");
    const videoButton = document.getElementById("videoCallButton");
    const photoButton = document.getElementById("photoButton");

    if (audioButton) {
      audioButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (!window.isButtonDisabled("audioCallButton")) {
          mediaCapture.startAudioCapture();
        }
      });
    }

    if (videoButton) {
      videoButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (!window.isButtonDisabled("videoCallButton")) {
          mediaCapture.startVideoCapture();
        }
      });
    }

    if (photoButton) {
      photoButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (!window.isButtonDisabled("photoButton")) {
          mediaCapture.startPhotoCapture();
        }
      });
    }
  }

  handleLiveChatMessage(data) {
    const isGroupChat = document.getElementById("isGroupChat")?.value === "1";

    //Check if initial messages are still loading
    if (window.chatboxInitialized === false && data.fromName !== "Me") {
      window.pendingInstantMessages.push({
        handler: this.handleLiveChatMessage.bind(this),
        data: data,
      });
      return;
    }

    //If a peer's text/attachment message arrives in a 1-on-1 chat, hide the typing indicator immediately (sending a message implies they stopped typing, so we shouldn't keep showing the indicator)
    if (
      !isGroupChat &&
      data.fromName !== "Me" &&
      (data.action === "sendTextMessage" || data.action === "sendAttachment") &&
      window.chatManager
    ) {
      window.chatManager.updateTypingIndicator(false, null);
    }

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
              window.getToUserGuid(),
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
              window.getToUserGuid(),
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
        //Wait for media to load before scrolling
        this.waitForMediaAndScroll();
        break;
    }

    //Scroll to bottom after new message (unless it's an attachment which handles its own scrolling)
    if (data.action !== "sendAttachment") {
      setTimeout(() => this.scrollToBottomEnhanced(), 50);
    }
  }

  setupFileInput() {
    const clearFileBtn = document.getElementById("clear-file-btn");

    this.actualBtn.addEventListener("change", () => {
      this.fileChosen.textContent = "";
      if (clearFileBtn) {
        clearFileBtn.classList.add("d-none");
      }

      if (this.actualBtn.files.length) {
        this.fileChosen.textContent =
          "Chosen File: " + this.actualBtn.files[0].name;
        if (clearFileBtn) {
          clearFileBtn.classList.remove("d-none");
        }
      }
    });

    this.actualBtn.addEventListener("cancel", () => {
      this.fileChosen.textContent = "";
      if (clearFileBtn) {
        clearFileBtn.classList.add("d-none");
      }
    });

    //Setup clear button click handler
    if (clearFileBtn) {
      clearFileBtn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();

        //Call the clearFiles function from file-handler.js
        if (window.clearFiles) {
          window.clearFiles();
        }

        //Hide the clear button
        clearFileBtn.classList.add("d-none");

        //Update send button state
        if (this.updateSendButtonState) {
          this.updateSendButtonState();
        }
      });
    }
  }

  setupScrolling() {
    //Initial scroll attempts using enhanced method
    this.scrollToBottomEnhanced();

    //Additional fallback scrolls for page load
    setTimeout(() => this.scrollToBottomEnhanced(), 500);
    setTimeout(() => this.scrollToBottomEnhanced(), 1000);
    setTimeout(() => this.scrollToBottomEnhanced(), 2000);
  }

  scrollToBottom() {
    if (this.bodyMsg) {
      //Use scrollHeight to ensure we scroll to the absolute bottom
      this.bodyMsg.scrollTop = this.bodyMsg.scrollHeight + 1000;
    }
  }

  //Enhanced scroll to bottom that waits for media and handles edge cases
  scrollToBottomEnhanced() {
    if (!this.bodyMsg) return;

    //Check if user has scrolled up manually (don't force scroll if they're reading old messages)
    const isNearBottom = this.isUserNearBottom();
    if (!isNearBottom) {
      return;
    }

    const scrollToBottom = () => {
      //Force scroll to absolute bottom
      this.bodyMsg.scrollTop = this.bodyMsg.scrollHeight + 1000;
    };

    //Immediate scroll
    scrollToBottom();

    //Wait for images and videos to load
    const mediaElements = this.bodyMsg.querySelectorAll("img, video, audio");
    let loadedCount = 0;
    const totalMedia = mediaElements.length;

    if (totalMedia === 0) {
      //No media, just scroll with small delays for DOM updates
      setTimeout(scrollToBottom, 50);
      setTimeout(scrollToBottom, 150);
      setTimeout(scrollToBottom, 300);
      return;
    }

    const checkAllLoaded = () => {
      loadedCount++;
      if (loadedCount >= totalMedia) {
        //All media loaded, scroll to bottom
        scrollToBottom();
        //Additional scrolls to handle any layout shifts
        setTimeout(scrollToBottom, 100);
        setTimeout(scrollToBottom, 300);
      }
    };

    //Set up load listeners for all media
    mediaElements.forEach((element) => {
      if (element.tagName === "IMG") {
        if (element.complete) {
          checkAllLoaded();
        } else {
          element.addEventListener("load", checkAllLoaded, { once: true });
          element.addEventListener("error", checkAllLoaded, { once: true });
        }
      } else if (element.tagName === "VIDEO" || element.tagName === "AUDIO") {
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

    //Fallback timeout in case some media never loads
    setTimeout(() => {
      scrollToBottom();
    }, 2000);
  }

  //Check if user is near the bottom of the chat
  isUserNearBottom() {
    if (!this.bodyMsg) return true;

    const scrollTop = this.bodyMsg.scrollTop;
    const scrollHeight = this.bodyMsg.scrollHeight;
    const clientHeight = this.bodyMsg.clientHeight;

    //Consider "near bottom" if within 100px of the bottom
    return scrollTop + clientHeight + 100 >= scrollHeight;
  }

  waitForMediaAndScroll() {
    if (!this.bodyMsg) return;

    //Get only the most recently added media elements
    const allMessages = this.bodyMsg.querySelectorAll(
      '[id^="message-"], [data-message-id]',
    );
    const recentMessages = Array.from(allMessages).slice(-5); //Last 5 messages

    const images = [];
    const videos = [];
    const audios = [];

    recentMessages.forEach((msg) => {
      images.push(...msg.querySelectorAll("img"));
      videos.push(...msg.querySelectorAll("video"));
      audios.push(...msg.querySelectorAll("audio"));
    });

    let totalMedia = images.length + videos.length + audios.length;
    let loadedMedia = 0;

    const checkAllLoaded = () => {
      loadedMedia++;
      if (loadedMedia >= totalMedia) {
        this.scrollToBottom(true);
      }
    };

    //Handle images
    images.forEach((img) => {
      if (img.complete) {
        checkAllLoaded();
      } else {
        img.addEventListener("load", checkAllLoaded, { once: true });
        img.addEventListener("error", checkAllLoaded, { once: true });
      }
    });

    //Handle videos
    videos.forEach((video) => {
      //Force video to load metadata
      if (video.readyState >= 2) {
        checkAllLoaded();
      } else {
        //Try multiple events to catch video loading
        const onVideoReady = () => {
          video.style.display = "block";
          checkAllLoaded();
        };

        video.addEventListener("loadedmetadata", onVideoReady, { once: true });
        video.addEventListener("loadeddata", onVideoReady, { once: true });
        video.addEventListener("canplay", onVideoReady, { once: true });
        video.addEventListener("error", checkAllLoaded, { once: true });

        //Force load if not already loading
        if (video.readyState === 0) {
          video.load();
        }
      }
    });

    //Handle audio
    audios.forEach((audio) => {
      if (audio.readyState >= 2) {
        checkAllLoaded();
      } else {
        audio.addEventListener("loadeddata", checkAllLoaded, { once: true });
        audio.addEventListener("error", checkAllLoaded, { once: true });
      }
    });

    //If no media, scroll immediately
    if (totalMedia === 0) {
      this.scrollToBottom(true);
    } else {
      //Fallback timeout (scroll even if media doesn't load)
      setTimeout(() => {
        this.scrollToBottom(true);
      }, 2000);
    }
  }

  setCursorToStart(element) {
    //Only force cursor to the start if the field is empty
    if (!element.value || element.value.trim().length === 0) {
      element.focus();
      element.setSelectionRange(0, 0);
    } else {
      //Let the browser keep the caret at the click position
      element.focus();
    }
  }
}

//Create singleton instance
export const chatboxUI = new ChatboxUI();

//Global exports for non-module scripts
window.setCursorToStart = (element) => chatboxUI.setCursorToStart(element);
