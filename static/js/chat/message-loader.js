/* MESSAGE LOADER

Optimized cursor-based pagination with lazy loading.
Handles efficient loading of older messages on scroll. */

class MessageLoader {
  constructor(chatGuid, isGroupChat = false) {
    this.chatGuid = chatGuid; //Group GUID for groups, Friend GUID for one-on-one
    this.isGroupChat = isGroupChat;
    this.isLoading = false;
    this.hasMore = true;
    this.nextCursor = null;
    this.messageContainer = null;
    this.scrollThreshold = 100; //pixels from top to trigger load
    this.batchSize = 50;
    this.initialized = false;
  }

  //Initialize the message loader
  init() {
    if (this.initialized) {
      return;
    }

    this.messageContainer = document.getElementById("bodyMsg");
    if (!this.messageContainer) {
      return;
    }

    //Set up scroll event listener
    this.setupScrollListener();

    this.initialized = true;
  }

  //Set up scroll event listener for lazy loading
  setupScrollListener() {
    let scrollTimeout = null;

    this.messageContainer.addEventListener("scroll", () => {
      // Debounce scroll events
      if (scrollTimeout) {
        clearTimeout(scrollTimeout);
      }

      scrollTimeout = setTimeout(() => {
        this.handleScroll();
      }, 150);
    });
  }

  //Handle scroll event
  handleScroll() {
    //Check if user scrolled near the top
    const scrollTop = this.messageContainer.scrollTop;

    if (scrollTop <= this.scrollThreshold && !this.isLoading && this.hasMore) {
      this.loadOlderMessages();
    }
  }

  //Load older messages using cursor-based pagination
  async loadOlderMessages() {
    if (this.isLoading || !this.hasMore) {
      return;
    }

    this.isLoading = true;
    this.showLoadingIndicator();

    try {
      //Get the oldest message ID currently displayed
      if (this.nextCursor === null) {
        this.nextCursor = this.getOldestMessageId();
      }

      let data;

      if (this.isGroupChat) {
        //Group chat (use groupsMessages API)
        const additionalParams = {};
        if (this.nextCursor) {
          additionalParams.before = this.nextCursor;
        }
        data = await window.ApiClient.get(
          window.ApiUrls.groupsMessages(
            this.chatGuid,
            this.batchSize,
            0,
            additionalParams,
          ),
        );
      } else {
        //One-on-one chat (use chat_messages API)
        data = await window.ApiClient.get(
          window.ApiUrls.chat_messages(
            this.chatGuid,
            this.batchSize,
            this.nextCursor,
          ),
        );
      }

      if (data.success) {
        //Store current scroll position and height
        const scrollHeightBefore = this.messageContainer.scrollHeight;
        const scrollTopBefore = this.messageContainer.scrollTop;

        //Prepend messages to the container
        this.prependMessages(data.messages);

        //Update pagination state
        this.hasMore = data.has_more || false;
        this.nextCursor = data.next_cursor || null;

        //Restore scroll position (maintain user's view)
        const scrollHeightAfter = this.messageContainer.scrollHeight;
        const scrollHeightDiff = scrollHeightAfter - scrollHeightBefore;
        this.messageContainer.scrollTop = scrollTopBefore + scrollHeightDiff;
      } else {
        this.showError(data.message || "Failed to load older messages");
      }
    } catch (error) {
      //Display user-friendly error message
      const errorMessage = error.message || "Error loading older messages";
      this.showError(errorMessage);
    } finally {
      this.isLoading = false;
      this.hideLoadingIndicator();
    }
  }

  //Get the oldest message ID currently displayed
  getOldestMessageId() {
    //Find all message elements with data-message-id attribute
    const messageElements =
      this.messageContainer.querySelectorAll("[data-message-id]");

    if (messageElements.length === 0) {
      return null;
    }

    //Get the first message (oldest in chronological order)
    const oldestElement = messageElements[0];
    const messageId = oldestElement.getAttribute("data-message-id");

    return messageId || null;
  }

  //Prepend messages to the container
  prependMessages(messages) {
    if (!messages || messages.length === 0) {
      return;
    }

    if (!window.messageRenderer) {
      return;
    }

    const fragment = document.createDocumentFragment();
    const context = { isGroup: this.isGroupChat };

    messages.forEach((message) => {
      //Render into a temp container, then move result to the fragment
      const tempContainer = document.createElement("div");
      window.messageRenderer.renderMessage(message, tempContainer, context);
      while (tempContainer.firstChild) {
        fragment.appendChild(tempContainer.firstChild);
      }
    });

    //Prepend all messages before the current first child
    this.messageContainer.insertBefore(
      fragment,
      this.messageContainer.firstChild,
    );
  }

  //Show loading indicator
  showLoadingIndicator() {
    let indicator = document.getElementById("message-loading-indicator");

    if (!indicator) {
      indicator = document.createElement("div");
      indicator.id = "message-loading-indicator";
      indicator.className = "msg-loading-indicator";
      indicator.innerHTML =
        '<i class="fa fa-spinner fa-spin"></i> Loading older messages...';

      this.messageContainer.insertBefore(
        indicator,
        this.messageContainer.firstChild,
      );
    } else {
      indicator.classList.remove("d-none");
    }
  }

  //Hide loading indicator
  hideLoadingIndicator() {
    const indicator = document.getElementById("message-loading-indicator");
    if (indicator) {
      indicator.classList.add("d-none");
    }
  }

  //Show error message
  showError(message) {
    const errorDiv = document.createElement("div");
    errorDiv.className = "msg-load-error";
    errorDiv.innerHTML = `<i class="fa fa-exclamation-circle"></i> ${message}`;

    this.messageContainer.insertBefore(
      errorDiv,
      this.messageContainer.firstChild,
    );

    //Remove error after 3 seconds
    setTimeout(() => {
      errorDiv.remove();
    }, 3000);
  }
}

//Export for use in other scripts
if (typeof window !== "undefined") {
  window.MessageLoader = MessageLoader;
}
