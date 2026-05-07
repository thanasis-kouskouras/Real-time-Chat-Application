/* CHAT MANAGER

Handles one-on-one chat functionality, WebSocket communication, and message display. */

class ChatManager {
  constructor() {
    this.toUserGuid = null;
    this.token = null;
    this.messageQueue = [];
  }

  //Initialize the chat manager
  init(token, toUserGuid = null) {
    this.token = token;
    this.toUserGuid = toUserGuid;

    //Process any queued messages
    this.processMessageQueue();

    //Setup typing indicator input listeners
    this.setupTypingIndicatorInput();
  }

  //Get WebSocket connection (always returns fresh shared instance from websocket-client.js)
  getWebSocket() {
    if (typeof getWebSocket === "function") {
      return getWebSocket();
    }

    return null;
  }

  //Send text message
  sendTextMessage(message, toUserGuid = null) {
    const to = toUserGuid || this.toUserGuid || getToUserGuid();

    if (!message || message.trim() === "") {
      hideMessageSending();
      return false;
    }

    const messageData = {
      action: "sendTextMessage",
      type: "single",
      from: this.token,
      to: to,
      msg: message,
      filename: "",
      category: "chat",
    };

    const success = this.sendViaWebSocket(messageData);

    //If send failed immediately, re-enable send button
    if (!success) {
      hideMessageSending();
    } else {
      //Set timeout to re-enable button if no response within 10 seconds
      setTimeout(() => {
        hideMessageSending();
      }, 10000);
    }

    return success;
  }

  //Send attachment
  sendAttachment(file, toUserGuid = null) {
    const to = toUserGuid || this.toUserGuid || getToUserGuid();

    if (!file) {
      hideMessageSending();
      return false;
    }

    const messageData = {
      action: "sendAttachment",
      type: "single",
      from: this.token,
      to: to,
      msg: "",
      filename: file.name,
      filetype: file.type,
      filesize: file.size,
      category: "chat",
    };

    //Send metadata first
    const success = this.sendViaWebSocket(messageData);

    if (!success) {
      hideMessageSending();
      return false;
    }

    //Send file as ArrayBuffer
    file
      .arrayBuffer()
      .then((arrayBuffer) => {
        const ws = this.getWebSocket();
        if (ws && ws.readyState === WebSocket.OPEN) {
          ws.send(arrayBuffer);
        } else {
          hideMessageSending();
          showToast("Failed to send file. Connection lost.", "error");
        }
      })
      .catch((error) => {
        hideMessageSending();
        showToast("Failed to read file", "error");
      });

    //Set timeout to re-enable button if no response within 15 seconds (files take longer)
    setTimeout(() => {
      hideMessageSending();
    }, 15000);

    return true;
  }

  //Delete message
  deleteMessage(messageId) {
    const messageData = {
      action: "deleteMessage",
      type: "single",
      from: this.token,
      to: this.toUserGuid || getToUserGuid(),
      msg: "",
      filename: "",
      category: "chat",
      chatId: messageId,
    };

    return this.sendViaWebSocket(messageData);
  }

  //Mark chat as read
  markAsRead(chatId = null) {
    const messageData = {
      action: "readChat",
      type: "single",
      from: this.token,
      to: this.toUserGuid || getToUserGuid(),
      msg: "",
      filename: "",
      category: "chat",
      chatId: chatId,
    };

    return this.sendViaWebSocket(messageData);
  }

  //Send message via WebSocket
  sendViaWebSocket(messageData) {
    const ws = this.getWebSocket();

    //Check if WebSocket is null (connection failed)
    if (!ws) {
      showToast("Unable to send message. Connection failed.", "error");
      return false;
    }

    if (ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify(messageData));
      return true;
    } else if (ws.readyState === WebSocket.CONNECTING) {
      //Queue message if WebSocket is still connecting
      this.messageQueue.push(messageData);
      showToast("Connecting... Message will be sent shortly.", "info", 2000);
      return false;
    } else {
      //WebSocket is closed or closing
      showToast("Connection lost. Please try again.", "error");
      return false;
    }
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

  //Show message sending status
  showSending() {
    const sendButton =
      document.getElementById("send-button") || document.getElementById("send");
    if (sendButton) {
      sendButton.disabled = true;
      const originalHTML = sendButton.innerHTML;
      sendButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
      sendButton.dataset.originalHTML = originalHTML;
    }
  }

  //Hide message sending status
  hideSending() {
    const sendButton =
      document.getElementById("send-button") || document.getElementById("send");
    if (sendButton) {
      sendButton.disabled = false;
      const originalHTML =
        sendButton.dataset.originalHTML || '<i class="fa fa-paper-plane"></i>';
      sendButton.innerHTML = originalHTML;
    }
  }

  //Send typing indicator event to recipient
  sendTypingIndicator(isTyping) {
    if (!this.toUserGuid) return;
    const ws = this.getWebSocket();
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(
        JSON.stringify({
          action: "direct_typing",
          type: "single",
          from: this.token,
          to: this.toUserGuid,
          is_typing: isTyping,
        }),
      );
    }
  }

  //Attach input/submit listeners to trigger typing events
  setupTypingIndicatorInput() {
    const messageInput = document.getElementById("chat_message");
    if (!messageInput) return;

    let typingTimer = null;
    let isTyping = false;

    messageInput.addEventListener("input", () => {
      if (typingTimer) clearTimeout(typingTimer);
      if (!isTyping) {
        isTyping = true;
        this.sendTypingIndicator(true);
      }
      typingTimer = setTimeout(() => {
        isTyping = false;
        this.sendTypingIndicator(false);
      }, 2000);
    });

    const chatForm = document.getElementById("chat_form");
    if (chatForm) {
      chatForm.addEventListener("submit", () => {
        if (typingTimer) clearTimeout(typingTimer);
        if (isTyping) {
          isTyping = false;
          this.sendTypingIndicator(false);
        }
      });
    }
  }

  //Show or hide the typing indicator inside #bodyMsg
  updateTypingIndicator(isTyping, username) {
    const url = window.location.href;
    if (!url.includes("chatbox.php")) return;

    let indicator = document.getElementById("typing-indicator");
    if (!indicator) {
      indicator = document.createElement("div");
      indicator.id = "typing-indicator";
    }

    /* Re-append to keep the indicator pinned at the bottom of #bodyMsg even
    after new messages have been inserted between calls. */
    const bodyMsg = document.getElementById("bodyMsg");
    if (bodyMsg) bodyMsg.appendChild(indicator);

    if (!isTyping) {
      indicator.style.visibility = "hidden";
      indicator.textContent = "";
    } else {
      indicator.innerHTML = `${escapeHtml(username)} is typing<span class="typing-dots">...</span>`;
      indicator.style.visibility = "visible";
      if (bodyMsg) {
        const nearBottom =
          bodyMsg.scrollHeight - bodyMsg.scrollTop - bodyMsg.clientHeight < 80;
        if (nearBottom) bodyMsg.scrollTop = bodyMsg.scrollHeight;
      }
    }
  }
}

//Create global instance
window.chatManager = new ChatManager();
