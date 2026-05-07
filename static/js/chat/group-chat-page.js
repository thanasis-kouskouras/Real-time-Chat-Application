/* GROUP CHAT PAGE REAL_TIME UPDATES
 
Handles WebSocket notifications for group chat events. */

document.addEventListener("DOMContentLoaded", function () {
  //Only run on chatbox page with group chat
  if (!window.location.pathname.includes("chatbox.php")) {
    return;
  }

  //Check if this is a group chat (has guid parameter)
  const urlParams = new URLSearchParams(window.location.search);
  const currentGroupGuid = urlParams.get("guid");

  if (!currentGroupGuid) {
    //Not a group chat, exit
    return;
  }

  //Wait for WebSocket to be ready
  function initializeGroupChatUpdates() {
    if (!window.websocketClient || !window.websocketClient.isConnected()) {
      setTimeout(initializeGroupChatUpdates, 1000);
      return;
    }

    //Listen for group_deleted events
    window.websocketClient.addMessageHandler("group_deleted", (data) => {
      //Check if it's the current group
      if (data.group_guid && data.group_guid == currentGroupGuid) {
        handleCurrentGroupDeleted();
      }
    });

    //Listen for member_left events (in case you were removed or someone else left)
    window.websocketClient.addMessageHandler("member_left", (data) => {
      //Check if this event is for the current group
      if (data.group_guid && data.group_guid == currentGroupGuid) {
        //Check if you were removed from the current group
        if (data.user_guid && data.user_guid == window.currentUserGuid) {
          handleRemovedFromCurrentGroup();
        } else {
          //Someone else left/was removed (forward to GroupChatManager for real-time sidebar update)
          if (
            window.groupChatManager &&
            typeof window.groupChatManager.handleWebSocketMessage === "function"
          ) {
            window.groupChatManager.handleWebSocketMessage(data);
          }
        }
      }
    });

    //Listen for member_joined events (someone added to current group)
    window.websocketClient.addMessageHandler("member_joined", (data) => {
      //Check if someone was added to the current group
      if (data.group_guid && data.group_guid == currentGroupGuid) {
        //Forward to GroupChatManager for real-time sidebar update
        if (
          window.groupChatManager &&
          typeof window.groupChatManager.handleWebSocketMessage === "function"
        ) {
          window.groupChatManager.handleWebSocketMessage(data);
        }
      }
    });

    //Listen for group_deactivated events
    window.websocketClient.addMessageHandler("group_deactivated", (data) => {
      //Check if it's the current group
      if (data.group_guid && data.group_guid == currentGroupGuid) {
        handleGroupDeactivated();
      }
    });

    //Listen for group_reactivated events
    window.websocketClient.addMessageHandler("group_reactivated", (data) => {
      //Check if it's the current group
      if (data.group_guid && data.group_guid == currentGroupGuid) {
        handleGroupReactivated();
      }
    });

    //Listen for user_banned_broadcast events
    window.websocketClient.addMessageHandler(
      "user_banned_broadcast",
      (data) => {
        //Forward to GroupChatManager if available
        if (
          window.groupChatManager &&
          typeof window.groupChatManager.handleWebSocketMessage === "function"
        ) {
          window.groupChatManager.handleWebSocketMessage(data);
        }
      },
    );

    //Listen for user_unbanned_broadcast events
    window.websocketClient.addMessageHandler(
      "user_unbanned_broadcast",
      (data) => {
        //Forward to GroupChatManager if available
        if (
          window.groupChatManager &&
          typeof window.groupChatManager.handleWebSocketMessage === "function"
        ) {
          window.groupChatManager.handleWebSocketMessage(data);
        }
      },
    );

    //Listen for group_typing events (typing indicator)
    window.websocketClient.addMessageHandler("group_typing", (data) => {
      //Forward to GroupChatManager if available
      if (
        window.groupChatManager &&
        typeof window.groupChatManager.handleWebSocketMessage === "function"
      ) {
        window.groupChatManager.handleWebSocketMessage(data);
      }
    });
  }

  //Handle when the current group is deleted
  function handleCurrentGroupDeleted() {
    //Disable the message input
    const messageInput = document.getElementById("chat_message");
    const sendButton = document.getElementById("send");

    if (messageInput) {
      messageInput.disabled = true;
      messageInput.placeholder = "This group has been deleted";
    }

    if (sendButton) {
      sendButton.disabled = true;
    }

    //Redirect to groups page after a delay
    setTimeout(() => {
      window.location.href = "groups.php";
    }, 3000);
  }

  //Handle when you're removed from the current group
  function handleRemovedFromCurrentGroup() {
    //Disable the message input
    const messageInput = document.getElementById("chat_message");
    const sendButton = document.getElementById("send");

    if (messageInput) {
      messageInput.disabled = true;
      messageInput.placeholder = "You are no longer a member of this group";
    }

    if (sendButton) {
      sendButton.disabled = true;
    }

    //Redirect to groups page after a delay
    setTimeout(() => {
      window.location.href = "groups.php";
    }, 3000);
  }

  //Handle when the current group is deactivated
  function handleGroupDeactivated() {
    //Disable the message input
    const messageInput = document.getElementById("chat_message");
    const sendButton = document.getElementById("send");

    if (messageInput) {
      messageInput.disabled = true;
      messageInput.placeholder =
        "Group is deactivated. Add more members to reactivate.";
      messageInput.classList.add("input-deactivated");
    }

    if (sendButton) {
      sendButton.disabled = true;
      sendButton.classList.add("ui-disabled");
    }

    //Disable other buttons
    ["audioCallButton", "videoCallButton", "photoButton", "actual-btn"].forEach(
      (id) => {
        const btn = document.getElementById(id);
        if (btn) {
          btn.disabled = true;
          btn.classList.add("ui-disabled");
        }
      },
    );

    //Disable the (+) label button visually
    const fileButtonLabel = document.querySelector('label[for="actual-btn"]');
    if (fileButtonLabel) {
      fileButtonLabel.classList.add("ui-disabled");
    }

    //Update GroupChatManager if available
    if (window.groupChatManager) {
      window.groupChatManager.isGroupActive = false;
    }
  }

  //Handle when the current group is reactivated
  function handleGroupReactivated() {
    //Enable the message input
    const messageInput = document.getElementById("chat_message");
    const sendButton = document.getElementById("send");

    if (messageInput) {
      messageInput.disabled = false;
      messageInput.placeholder = "Type a message or drop files here...";
      messageInput.classList.remove("input-deactivated");
    }

    if (sendButton) {
      sendButton.disabled = false;
      sendButton.classList.remove("ui-disabled");
    }

    //Enable other buttons
    ["audioCallButton", "videoCallButton", "photoButton", "actual-btn"].forEach(
      (id) => {
        const btn = document.getElementById(id);
        if (btn) {
          btn.disabled = false;
          btn.classList.remove("ui-disabled");
        }
      },
    );

    //Enable the (+) label button visually
    const fileButtonLabel = document.querySelector('label[for="actual-btn"]');
    if (fileButtonLabel) {
      fileButtonLabel.classList.remove("ui-disabled");
    }

    //Update GroupChatManager if available
    if (window.groupChatManager) {
      window.groupChatManager.isGroupActive = true;
    }
  }

  //Start initialization
  initializeGroupChatUpdates();
});
