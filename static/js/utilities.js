const errorTimeOutSeconds = 8000;
const recordingTimeMS = 30000;
const secure = "";
let http = "http" + secure + "://";
let conn = "";
let getUrl = window.location;
let last = getUrl.pathname.split("/").pop();
let base = getUrl.pathname.replace(last, "");
let baseUrl;
let cacheMessageDeleteId = undefined;
let errorMessage = undefined;
let baseHost = getUrl.host;
if (baseHost.includes(":")) {
  baseHost = baseHost.split(":")[0];
}
let wsServer = "ws://" + baseHost + ":8081";
let localAudioStream = null;
let localAudioStreamElement = null;
let localAudioRecordingStreamElement = null;
let canvas = null;
let capturePhotoButton = null;
let cancelCaptureButton = null;
let captureCancelled = false;
let callStatusp = null;
let localStreamElement = null;
let localStream = null;
let connectedContainer = null;
let captureType = "videoCall";
let liveConnectionStatus = "";
let preview = null;
let recording = null;
let stopButton = null;
const constraintByType = {
  videoCall: { audio: true, video: true },
  photo: { audio: false, video: true },
  audioCall: { audio: true, video: false },
};

console.log("base", base, wsServer);
if (base !== "/") {
  baseUrl = getUrl.protocol + "//" + getUrl.host + base;
} else {
  baseUrl = getUrl.protocol + "//" + getUrl.host;
}
if (baseUrl[-1] === "/") baseUrl[-1] = "";
let queryPath = "";
if (getUrl.pathname.indexOf("?") > -1)
  queryPath = "?" + getUrl.pathname.split("?")[1];
console.log("Base path is ", baseUrl);
console.log("Query path is ", queryPath);
let token = "";

function create_url(target) {
  return baseUrl + target + queryPath;
}

function getWebSocket() {
  if (
    conn === undefined ||
    conn.readyState === WebSocket.CLOSED ||
    conn.readyState === WebSocket.CLOSING
  ) {
    wsOpen();
    // getWebSocket()
  } else if (conn.readyState === WebSocket.OPEN) return conn;
  else return undefined;
}

function haveAttachment() {
  const fileInput = document.getElementById("actual-btn");
  return (
    fileInput !== undefined &&
    fileInput.files !== undefined &&
    fileInput.files.length
  );
}

function isMessageDeleted() {
  return cacheMessageDeleteId !== undefined;
}

function friendManager(
  action = "add",
  toId,
  get = false,
  input = "",
  redirectUrl = "search.php"
) {
  // init
  // confirm deletion of a friend
  if (input.from !== token) input.msg = "";
  if (action === "delete") {
    const msg = "Are you sure you want to delete this friend?";
    const ret = confirm(msg);
    if (ret === false) {
      return;
    }
  }
  getWebSocket();
  if (action === "chat") {
    postRedirect("chatbox.php", { rqstFromId: toId, chat: true });
  } else if (!get) {
    let data = {
      action: "friend",
      type: "single",
      to: toId,
      subAction: action,
      redirectUrl: redirectUrl,
    };

    getWebSocket().send(JSON.stringify(data));
    console.log(data);
  } else {
    // get == true
    if (input.from !== token) input.msg = "";
    // request side
    console.log(input);
    // other side
    let params;
    if (!input.reqStatus) {
      params.error = "fail";
    }
    const url = getUrl.href;
    if (url.includes("search.php")) {
      params = {
        "search-submit": $("#searchString").val(),
        search: $("#searchString").val(),
        message: input.msg,
        token: token,
      };
      postRedirect("search.php", params);
    } else if (url.includes("notifications.php")) {
      params = { message: input.msg, token: token };
      postRedirect("notifications.php", params);
    } else if (url.includes("messages.php")) {
      params = { message: input.msg, token: token };
      postRedirect("messages.php", params);
    } else if (url.includes("friends.php")) {
      params = { message: input.msg, token: token };
      postRedirect("friends.php", params);
    } else send_message("updateNotificationsCounter");
  }
}

function send_message(action = "sendTextMessage", chatId = null, live = null) {
  // init
  getWebSocket();
  const to = getToUser();
  let data = {
    action: action,
    type: "single",
    from: token,
    to: to,
    msg: "",
    filename: "",
    category: "chat",
    live: live,
  };

  if (action === "sendAttachment" && haveAttachment()) {
    showMessageSending();
    const fileInput = document.getElementById("actual-btn");
    sendAttachment(fileInput.files[0], data);
    fileInput.type = "text";
    fileInput.type = "file";
    document.getElementById("file-chosen").innerText = "";
  } else if (action === "deleteMessage" && isMessageDeleted()) {
    data.chatId = chatId;
    getWebSocket().send(JSON.stringify(data));
    cacheMessageDeleteId = undefined;
  } else if (action === "getActiveFriends") {
    data.category = "friends";
    data.chatId = chatId;
    getWebSocket().send(JSON.stringify(data));
  } else if (action === "acceptRequest") {
    data.category = "friends";
    data.chatId = chatId;
    getWebSocket().send(JSON.stringify(data));
  } else if (action === "readChat") {
    data.chatId = chatId;
    getWebSocket().send(JSON.stringify(data));
  } else if (action === "deleteAccount") {
    data.category = "friends";
    getWebSocket().send(JSON.stringify(data));
  } else if (action === "updateNotificationsCounter") {
    data.category = "chat";
    getWebSocket().send(JSON.stringify(data));
  } else if (action === "syncfrom") {
    data.category = "chat";
    data.lastdate = "2022-11-11 01:00";
    getWebSocket().send(JSON.stringify(data));
  } else {
    // send simple message
    data.msg = $("#chatMessage").val().trim();
    if (data.msg !== "") {
      showMessageSending();
      getWebSocket().send(JSON.stringify(data));
    }
  }
  console.log("sending", data);
}

function getToUser() {
  return $("#toUser").val();
}

function getToUsername() {
  return $("#toUsername").val();
}

function getFromUser() {
  return $("#fromUser").val();
}

function isLiveChattingTo(toId) {
  const cwd = getUrl.href;
  if (cwd.endsWith("chatbox.php")) {
    const to = Number(getToUser()) === Number(toId);
    const from = Number(getFromUser()) === Number(toId);
    const result = to || from;
    return result;
  }
  return false;
}

function isLiveNotifications() {
  const cwd = getUrl.href;
  return cwd.endsWith("notifications.php");
}

function isLiveMessages() {
  const cwd = getUrl.href;
  return cwd.endsWith("messages.php");
}

function deleteNoMessageYetDiv() {
  const div = $("#noMessageYet");
  if (div) div.remove();
}

function wsReceive() {
  conn.onmessage = function (e) {
    // console.log(e.data);
    let data = JSON.parse(e.data);
    // console.log('Message Received from ', data)
    let to = data.friendUserId !== undefined ? data.friendUserId : data.to;
    console.log("Message Received from ", data);
    console.log("to: ", to);

    // Hide sending status when we receive any response
    if (data.action === "sendTextMessage" || data.action === "sendAttachment") {
      hideMessageSending();

      if (data.status === false) {
        showMessageStatus(
          data.statusMessage || "Failed to send message",
          false
        );
      } else if (data.fromName === "Me") {
        showMessageStatus("Message sent successfully", true);
        $("#chatMessage").val(""); // Clear the message input
      }
    }

    if (data.status === false) {
      // alert("Error: " + data.statusMessage)
      if (!data.loggedIn) redirect("login.php");
      else {
        setFooterMessage(data.statusMessage, fail);
      }
      // window.location
    } else if (isLiveChattingTo(to)) {
      switch (data.action) {
        case "sendTextMessage": {
          deleteNoMessageYetDiv();
          if (data.fromName === "Me") {
            console.log(`data: ${data}`);
            add_from_message(
              data.msg,
              data.chatId,
              data.attachmentType,
              data.attachment,
              data.filename,
              data.date
            );
          } else {
            add_to_message(
              getToUser(),
              data.msg,
              data.chatId,
              null,
              null,
              data.filename,
              data.date
            );
          }
          break;
        }
        case "deleteMessage": {
          if (data.fromName === "Me") {
            deleteLocalMessage(data.chatId, "right");
          } else {
            deleteLocalMessage(data.chatId, "left");
          }
          break;
        }
        case "sendAttachment": {
          if (data.fromName === "Me") {
            add_from_message(
              data.msg,
              data.chatId,
              data.filetype,
              data.attachment,
              data.filename,
              data.date
            );
          } else {
            add_to_message(
              getToUser(),
              data.msg,
              data.chatId,
              data.filetype,
              data.attachment,
              data.filename,
              data.date
            );
          }
          break;
        }
        case "deleteAccount": {
          alert("Your Friend has requested to delete his account!");
          redirect("friends.php");
          break;
        }
        case "friend": {
          if (data.subAction === "delete") {
            alert("Your Friend has requested to delete his account!");
            redirect("friends.php");
            break;
          }
        }
        case "updateUserStatus": {
          updateUserStatus(data.friendUserId, data.userStatus, data.color);
          break;
        }
        default: {
          break;
        }
      }
    } else {
      switch (data.action) {
        case "updateUserStatus": {
          updateUserStatus(data.friendUserId, data.userStatus, data.color);
          break;
        }
        case "updateNotificationsCounter": {
          if (isLiveNotifications() || isLiveMessages()) {
            window.location.reload();
            //refresh
          } else {
            updateNotificationsCounter(data.counter);
            updateMessagesCounter(data.chatCounter);
          }
          break;
        }
        case "initializeNotificationsCounter": {
          updateNotificationsCounter(data.counter);
          updateMessagesCounter(data.chatCounter);
          break;
        }
        case "friend": {
          friendManager(data.action, data.to, true, data);
          break;
        }
        case "deleteAccount": {
          alert("Your Account has been deleted!");
          redirect("login.php");
          break;
        }
        default:
          // ask server  to sum up notifications and send the correct number back
          send_message("updateNotificationsCounter");
          break;
      }
    }
  };
}

function wsOpen() {
  conn = new WebSocket(wsServer + "?token=" + token);
  conn.onopen = function (e) {
    console.log("Connection established to webSocket!");
  };
  conn.onerror = function (e) {
    console.log("Connection failed to webSocket!");
    redirect("login.php");
  };
  wsReceive();
}

function setFooterMessage(message, type = false) {
  let msg = "";
  let htmlMessage = "";
  if (type === false) {
    htmlMessage = "<h6><p class='alert-danger'>" + message + "</p></h6>";
  } else if (type === true) {
    htmlMessage = "<h6><p class='alert-success'>" + message + "</p></h6>";
  } else {
    return;
  }
  displayErrorMessage(htmlMessage);
}

function displayErrorMessage(htmlMessage) {
  const message = $("#footerMessage");
  message.append(htmlMessage);
  setTimeout(function () {
    console.log("timeout ended");
    const myNode = document.getElementById("footerMessage");
    console.log(myNode);
    while (myNode.lastElementChild) {
      myNode.removeChild(myNode.lastElementChild);
    }
    errorMessage = undefined;
  }, errorTimeOutSeconds);
}

$(document).ready(function () {
  hideNotifyCounters();
  token = $("#token").val();

  const url = getUrl.href;

  if (
    !url.includes("login") &&
    !url.includes("sign") &&
    !url.includes("chatbox")
  ) {
  }

  if (!url.includes("login") && !url.includes("sign")) {
    wsOpen();
    // Get the modal
  }
  if (url.includes("chatbox.php")) {
    localAudioStreamElement = document.getElementById("localAudioStream");
    localAudioRecordingStreamElement = document.getElementById(
      "localAudioRecordingStream"
    );
    canvas = document.getElementById("canvas");
    preview = document.getElementById("localVideoStream");
    recording = document.getElementById("recording");
    capturePhotoButton = document.getElementById("capture");
    cancelCaptureButton = document.getElementById("captureCancel");
    stopButton = document.getElementById("stopButton");

    localStreamElement = document.getElementById("localStream");
    connectedContainer = document.getElementById("connectedContainer");
    callStatusp = document.getElementById("callStatus");
    stopButton.addEventListener(
      "click",
      () => {
        if (!captureType.startsWith("audio"))
          stopMediaRecording(preview.srcObject);
        else {
          stopMediaRecording(localAudioStreamElement.srcObject);
        }
      },
      false
    );
    hideConnectedContent();

    const captureButton = document.getElementById("capture");
    const context = canvas.getContext("2d");
    captureButton.addEventListener("click", () => {
      // Draw the video frame to the canvas.
      let mimetype = "image/jpeg";
      context.drawImage(preview, 0, 0, canvas.width, canvas.height);
      //add the img for upload
      canvas.toBlob((blob) => {
        loadURLToInputFiled(blob, mimetype);
      }, mimetype);
      // Stop all video streams.
      preview.srcObject.getVideoTracks().forEach((track) => track.stop());
    });
  }
  // Add event listeners here after the dom has loaded.
  $("#chat_form").on("submit", function (event) {
    getWebSocket();
    event.preventDefault();
    clearMediaErrors();
    let tmp = event.originalEvent.submitter.id;

    if (tmp === "audioCallButton") {
      if (!isButtonDisabled("audioCallButton")) {
        startAudioCapture();
      }
    } else if (tmp === "videoCallButton") {
      if (!isButtonDisabled("videoCallButton")) {
        startVideoCapture();
      }
    } else if (tmp === "photoButton") {
      if (!isButtonDisabled("photoButton")) {
        startPhotoCapture();
      }
    } else if (tmp === "send") {
      // submit text or attachment
      let action = "sendTextMessage";
      if (haveAttachment()) {
        action = "sendAttachment";
        hideCaptureContent(captureType);
      }
      send_message(action);
    } else {
      //nothing
    }
  });

  let msg = "";
  let messageType = null;
  if ($("#errorMessage")) {
    msg = $("#errorMessage").val();
    messageType = false;
  }
  if ($("#successMessage")) {
    msg = $("#successMessage").val();
    messageType = true;
  }
  if (msg) {
    setFooterMessage(msg, messageType);
  }
});

function isButtonDisabled(buttonId) {
  const button = document.getElementById(buttonId);
  return button && button.disabled;
}

function deleteLocalMessage(messageId, direction = "right") {
  const div2 = $("#" + messageId);
  const divMedia = $("#" + messageId + "_media");
  let p;
  const chatMessage = "<i>Deleted Message</i>";
  if (direction === "right") p = "<p class=pFrom> ";
  else p = "<p class=pTo> ";
  p += chatMessage + "</p>";
  if (direction === "right") {
    // from = me
    div2.children().remove();
    if (divMedia) {
      divMedia.children().remove();
    }
  } else {
    // from other
    // length-1 // delete it is either media or text
    div2.children()[div2.children().length - 1].remove();
    if (divMedia) {
      divMedia.remove();
    }
  }
  div2.append(p);
}

function delete_message(messageId, direction = "right") {
  let ret = true; // delete to message without asking if direction == left
  const msg = "Delete this message for all?";
  if (direction === "right")
    // delete from message
    ret = confirm(msg);
  if (ret === true) {
    console.log("deleting message id", messageId);
    cacheMessageDeleteId = messageId;
    send_message("deleteMessage", messageId);
    // send via server to both sides
  }
}

function redirect(url) {
  // simple redirect
  window.location.href = url;
}

let cache = {};
const request = (url, params = {}, method = "GET") => {
  // used by all code to make requests
  // Quick return from cache.
  let cacheKey = JSON.stringify({ url, params, method });
  if (cache[cacheKey]) {
    return cache[cacheKey];
  }

  let options = {
    method,
  };
  if ("GET" === method) {
    url += "?" + new URLSearchParams(params).toString();
  } else {
    options.body = JSON.stringify(params);
  }

  const result = fetch(url, options).then((response) => response.json());
  cache[cacheKey] = result;

  return result;
};

function getProfileImagePath(id) {
  return request(
    create_url("/api.php"),
    {
      key1: "123456",
      data_type: "json_g_profile",
      token: id,
    },
    "POST"
  );
}

function add_to_message(
  chatToId,
  chatMessage,
  chatId,
  type = null,
  url = null,
  filename = null,
  date = null
) {
  getProfileImagePath(chatToId).then((response) => {
    let profileImageUrl = response.url;
    profileImageUrl = baseUrl + "/" + profileImageUrl;

    let html_text = "<div id=" + chatId + " class='divTo'>";
    if (type === null || type === undefined) {
      html_text = "<div id=" + chatId + " class='divToText'>";
    }
    const spanId = "unread_".chatId;
    html_text += "<span id= " + spanId + " class='dotBlock'></span>";
    html_text += "<img id='profileImgInMessage' src=" + profileImageUrl + ">";
    html_text += getMediaHtml(
      type,
      chatMessage,
      url,
      date,
      "left",
      chatId,
      filename
    );
    const body = $("#bodyMsg");
    body.append(html_text);
    if (body.length) {
      body.scrollTop(body[0].scrollHeight);
    }
  });
}

function getMediaHtml(
  type,
  chatMessage,
  url,
  date,
  side = "right",
  chatId,
  filename
) {
  let html_text = "";
  let dateMedia = "";

  // default to
  let divClass = "divTo2";
  let p = "<p class=pTo> ";
  let spanColor = "small_span";
  // if from
  if (side === "right") {
    p = "<p class=pFrom> ";
    divClass = "divFrom2";
    spanColor = "small_span_dark";
    if (type === null || type === undefined) {
      spanColor = "small_span_white";
    }
  }
  if (type === null || type === undefined) {
    p +=
      chatMessage +
      "<br> <span class='" +
      spanColor +
      "'>" +
      date +
      "</span> </p>";
    html_text += p;
  } else {
    let a; // the link for the media to open in new window
    let media = ""; // the actual media to host in html tag
    dateMedia = "<span class='" + spanColor + "'>" + date + "</span> <br>";
    if (type.startsWith("image")) {
      //add image
      media = "<img class='imgChat' src=" + url + " alt='image' />";
    } else if (type.startsWith("audio")) {
      //add audio
      media =
        " <audio controls='controls'> <source src=" +
        url +
        " type=" +
        type +
        "> </audio>";
    } else if (type.startsWith("video")) {
      //add video
      media = "<video src=" + url + " controls='controls'> </video>";
    } else if (type.startsWith("application/") || type === "text/plain") {
      //add document link with appropriate icon
      let icon = "📄"; // Default document icon

      // Determine the right icon based on MIME type
      if (type === "application/pdf") {
        icon = "📄"; // PDF document icon
      } else if (
        type === "application/msword" ||
        type ===
          "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
      ) {
        icon = "📄"; // Word document icon
      } else if (
        type === "application/vnd.ms-excel" ||
        type ===
          "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
      ) {
        icon = "📊"; // Excel spreadsheet icon
      } else if (
        type === "application/vnd.ms-powerpoint" ||
        type ===
          "application/vnd.openxmlformats-officedocument.presentationml.presentation"
      ) {
        icon = "📑"; // PowerPoint presentation icon
      } else if (type === "text/plain") {
        icon = "📝"; // Text file icon
      }

      // Extract filename from URL or use a default
      // Create a styled document link
      media = `<div class="document-link">
                      <span class="doc-icon">${icon}</span>
                      <span class="doc-name">${filename}</span>
                    </div>`;
    } else if (
      type === "document" ||
      type === "spreadsheet" ||
      type === "presentation"
    ) {
      //add document link with appropriate icon
      let icon = "";
      if (type === "document") {
        icon = "📄"; // Document icon
      } else if (type === "spreadsheet") {
        icon = "📊"; // Spreadsheet icon
      } else if (type === "presentation") {
        icon = "📑"; // Presentation icon
      }

      // Extract filename from URL or use a default
      let filename = url.split("/").pop() || "document";

      // Create a styled document link
      media = `<div class="document-link">
                      <span class="doc-icon">${icon}</span>
                      <span class="doc-name">${filename}</span>
                    </div>`;
    }

    a =
      "<a href=" +
      url +
      " role='link'  target='_blank' rel='noopener noreferrer'>" +
      media +
      " </a>";
    html_text += a;
  }

  const idMedia = chatId + "_media";
  html_text +=
    "</div><div id='" +
    idMedia +
    "' class=" +
    divClass +
    ">" +
    dateMedia +
    "</div>";
  return html_text;
}

function add_from_message(
  chatMessage,
  ChatboxId,
  type = null,
  url = null,
  filename = null,
  date = null
) {
  let html_text = "<div id=" + ChatboxId + " class='divFrom'>";
  if (type === null || type === undefined) {
    html_text = "<div id=" + ChatboxId + " class='divFromText'>";
  }
  let button =
    "<button id='buttonDelete' onclick='delete_message(" +
    ChatboxId +
    ")' class='alert-white btn-danger' type='submit'>x</button>";
  if (type !== null && type.startsWith("audio"))
    button =
      "<button id='buttonDeleteSound' onclick='delete_message(" +
      ChatboxId +
      ")' class='alert-white btn-danger' type='submit'>x</button>";
  html_text += button;
  html_text += getMediaHtml(
    type,
    chatMessage,
    url,
    date,
    "right",
    ChatboxId,
    filename
  );
  const body = $("#bodyMsg");
  body.append(html_text);
  $("#chatMessage").val("");
  if (body.length) {
    body.scrollTop(body[0].scrollHeight);
  }
}

function sendAttachment(file, data) {
  if (!file) {
    return;
  }
  if (file.size > MAX_FILE_SIZE) {
    showFileError(
      "This file size is not supported. Maximum size is " +
        formatFileSize(MAX_FILE_SIZE)
    );
    return false;
  }
  const blob = file;
  const reader = new FileReader();
  reader.onload = function (event) {
    data.filename = file.name;
    data.filetype = file.type;
    data.filesize = file.size;
    getWebSocket().send(JSON.stringify(data));
    getWebSocket().send(blob);
  };
  reader.readAsDataURL(blob);
}

function updateFriendIcon(friendUserId, userStatus) {
  // Find the friend's profile image by user ID
  const friendImages = document.querySelectorAll(
    `img[data-friend-id="${friendUserId}"]`
  );

  friendImages.forEach((img) => {
    const isActive = userStatus === "Active";
    const newId = isActive
      ? "profileImgInFriendsActive"
      : "profileImgInFriendsInactive";

    // Update the image ID to change the styling
    img.id = newId;

    // Animation to indicate the change
    img.style.transition = "all 0.3s ease";
    if (isActive) {
      img.style.transform = "scale(1.05)";
      setTimeout(() => {
        img.style.transform = "scale(1)";
      }, 300);
    }
  });
}

function updateUserStatus(userId, status, color) {
  // Check if we're on the friends page
  const url = getUrl.href;
  if (url.includes("friends.php") || url.includes("friends-search.php")) {
    updateFriendIcon(userId, status);
  }

  // Update status in chat if applicable
  if (url.includes("chatbox.php")) {
    updateChatStatus(userId, status, color);
  }
}

function updateChatStatus(userId, status, color) {
  // if we are in chatbox
  const chatBoxStatusElement = document.getElementById(userId + "_status");
  if (chatBoxStatusElement !== undefined && chatBoxStatusElement !== null) {
    if (status === "Active") {
      setLiveButtons(true);
    } else {
      setLiveButtons(false);
    }
    chatBoxStatusElement.innerText = status;
    chatBoxStatusElement.style.color = color;
  }
}

function getToUserStatus() {
  // if we are in chatbox
  const to = getToUser();
  const chatBoxStatusElement = document.getElementById(to + "_status");
  if (chatBoxStatusElement !== undefined && chatBoxStatusElement !== null) {
    const status = chatBoxStatusElement.innerText;
    if (status !== "") {
      if (status === "Active") {
        return true;
      }
    }
  }
  return false;
  // if we are in friend List
}

function setLiveButtons(enable) {
  const audioButton = document.getElementById("audioCallButton");
  const videoButton = document.getElementById("videoCallButton");
  const photoButton = document.getElementById("photoButton");
  if (audioButton && videoButton && photoButton) {
    if (enable) {
      audioButton.removeAttribute("disabled");
      videoButton.removeAttribute("disabled");
      photoButton.removeAttribute("disabled");
    } else {
      audioButton.setAttribute("disabled", "disabled");
      videoButton.setAttribute("disabled", "disabled");
      photoButton.setAttribute("disabled", "disabled");
    }
  }
}

function setLiveButtonsExclude(enable) {
  const audioButton = document.getElementById("audioCallButton");
  const videoButton = document.getElementById("videoCallButton");
  const photoButton = document.getElementById("photoButton");
  if (captureType.startsWith("photo")) {
    if (enable) {
      audioButton.removeAttribute("disabled");
      videoButton.removeAttribute("disabled");
      photoButton.removeAttribute("disabled");
    } else {
      audioButton.setAttribute("disabled", "disabled");
      videoButton.setAttribute("disabled", "disabled");
    }
  } else if (captureType.startsWith("video")) {
    if (enable) {
      audioButton.removeAttribute("disabled");
      videoButton.removeAttribute("disabled");
      photoButton.removeAttribute("disabled");
    } else {
      audioButton.setAttribute("disabled", "disabled");
      photoButton.setAttribute("disabled", "disabled");
    }
  } else if (captureType.startsWith("audio")) {
    if (enable) {
      audioButton.removeAttribute("disabled");
      videoButton.removeAttribute("disabled");
      photoButton.removeAttribute("disabled");
    } else {
      videoButton.setAttribute("disabled", "disabled");
      photoButton.setAttribute("disabled", "disabled");
    }
  }
}

function hideNotifyCounters() {
  const spanElement = document.getElementById("notify_header");
  let cv = spanElement.innerText.trim();
  if (Number(cv) === 0) spanElement.style.display = "none";
  const spanElementM = document.getElementById("message_header");
  cv = spanElementM.innerText.trim();
  if (Number(cv) === 0) spanElementM.style.display = "none";
}

function incrementNotificationsCounter(counter) {
  const spanElement = document.getElementById("notify_header");
  if (spanElement !== undefined && spanElement !== null) {
    let cv = spanElement.innerText.trim();
    let result = Number(cv) + Number(counter);
    spanElement.innerText = result.toString();
  }
}

function updateNotificationsCounter(counter) {
  const spanElement = document.getElementById("notify_header");
  if (spanElement !== undefined && spanElement !== null) {
    if (counter === 0) {
      spanElement.style.display = "none";
    } else {
      spanElement.style.display = "inline-block";
      spanElement.innerText = counter;
    }
  }
}

function updateMessagesCounter(counter) {
  const spanElement = document.getElementById("message_header");
  if (spanElement !== undefined && spanElement !== null) {
    if (counter === 0) {
      spanElement.style.display = "none";
    } else {
      spanElement.style.display = "inline-block";
      spanElement.innerText = counter;
    }
  }
}

function postRedirect(redirectUrl, obj) {
  let input_part = "";
  for (let id in obj) {
    input_part +=
      '<input type="hidden" name="' + id + '" value="' + obj[id] + '">';
  }
  const form_part =
    '<form action="' +
    redirectUrl +
    '" method="post">' +
    input_part +
    "</form>";

  const form = $(form_part);
  $("body").append(form);
  $(form).submit();
}

function readMessage() {
  let hasUnReadMessage = false;
  // Edit form and remove the unread status from messages
  const dots = document.getElementsByClassName("dotBlock");
  for (let i = 0; i < dots.length; i++) {
    hasUnReadMessage = true;
    if (dots[i].style.display !== "none") {
      dots[i].style.display = "none";
    }
  }
  console.log("hasUnreadMessages?=", hasUnReadMessage);
  if (hasUnReadMessage) send_message("readChat");
}

function deleteAccount() {
  const msg = "Are you sure you want to delete your account?";
  const ret = confirm(msg);
  if (ret === true) {
    send_message("deleteAccount");
    postRedirect("login.php", {});
  }
}

function clearLog() {
  callStatusp.innerText = "";
}

function hideConnectedContent() {
  clearLog();
  liveConnectionStatus = "";
  if (captureType.startsWith("video") && localStream !== null)
    stopBothVideoAndAudio(localStream);
  cancelCaptureButton.hidden = true;
  connectedContainer.hidden = true;
}

// stop both mic and camera
function stopBothVideoAndAudio(stream) {
  stream.getTracks().forEach((track) => {
    console.log("track.readystate on closing: " + track.readyState);
    if (track.readyState === "live") {
      track.stop();
      if (localStreamElement !== null) {
        localStreamElement.srcObject?.removeTrack(track);
      } else if (localAudioStreamElement !== null) {
        localAudioStreamElement.srcObject?.removeTrack(track);
      } else if (preview !== null) {
        preview.srcObject?.removeTrack(track);
      }
    }
  });
}

function startRecording(stream, lengthInMS) {
  let recorder = new MediaRecorder(stream);
  let data = [];

  recorder.ondataavailable = (event) => data.push(event.data);
  recorder.start();

  let stopped = new Promise((resolve, reject) => {
    recorder.onstop = resolve;
    recorder.onerror = (event) => reject(event.name);
  });

  return Promise.all([stopped]).then(() => data);
}

function stopMediaRecording(stream) {
  stream.getTracks().forEach((track) => {
    track.stop();
  });
}

function startVideoCapture() {
  const videoButton = document.getElementById("videoCallButton");
  const stopBtn = document.getElementById("stopButton");

  // Reset stop button state
  if (stopBtn) {
    stopBtn.disabled = false;
    stopBtn.style.opacity = "1";
    stopBtn.textContent = "Stop Recording";
  }

  captureCancelled = false;

  // Disable button immediately to prevent multiple clicks
  if (videoButton) {
    videoButton.disabled = true;
    videoButton.style.opacity = "0.5";
  }
  try {
    captureType = "videoCall";
    setLiveButtonsExclude(false);
    showCaptureContent(captureType);
    navigator.mediaDevices
      .getUserMedia(constraintByType[captureType])
      .then((stream) => {
        localStream = stream;
        preview.srcObject = localStream;
        preview.captureStream =
          preview.captureStream || preview.mozCaptureStream;
        return new Promise((resolve) => (preview.onplaying = resolve));
      })
      .then(() => startRecording(preview.captureStream(), recordingTimeMS))
      .then((recordedChunks) => {
        if (stopBtn) {
          stopBtn.disabled = false;
          stopBtn.style.opacity = "1";
          stopBtn.textContent = "Stop Recording";
        }
        if (!captureCancelled && recordedChunks && recordedChunks.length > 0) {
          let recordedBlob = new Blob(recordedChunks, { type: "video/mp4" });
          recording.src = URL.createObjectURL(recordedBlob);
          loadURLToInputFiled(recordedBlob);
        }
        localStream = null;
      })
      .catch((error) => {
        if (stopBtn) {
          stopBtn.disabled = false;
          stopBtn.style.opacity = "1";
          stopBtn.textContent = "Stop Recording";
        }
        hideCaptureContent(captureType);
        if (error.name === "NotFoundError") {
          log("Camera or microphone not found. Can't record.");
        } else {
          log(error);
        }
      })
      .finally(() => {
        // Re-enable button after capture is done (success or error)
        if (videoButton) {
          videoButton.disabled = false;
          videoButton.style.opacity = "1";
        }
      });
  } catch (error) {
    handleMediaError(error, "camera and microphone");
    hideCaptureContent(captureType);
    if (stopBtn) {
      stopBtn.disabled = false;
      stopBtn.style.opacity = "1";
      stopBtn.textContent = "Stop Recording";
    }
    // Re-enable button on error
    if (videoButton) {
      videoButton.disabled = false;
      videoButton.style.opacity = "1";
    }
  }
}

function startPhotoCapture() {
  const photoButton = document.getElementById("photoButton");
  const stopBtn = document.getElementById("stopButton");

  // Reset stop button state
  if (stopBtn) {
    stopBtn.disabled = false;
    stopBtn.style.opacity = "1";
    stopBtn.textContent = "Stop Recording";
  }

  captureCancelled = false;
  // Disable button immediately to prevent multiple clicks
  if (photoButton) {
    photoButton.disabled = true;
    photoButton.style.opacity = "0.5";
  }

  try {
    captureType = "photo";
    setLiveButtonsExclude(false);
    showCaptureContent(captureType);

    navigator.mediaDevices
      .getUserMedia(constraintByType[captureType])
      .then((stream) => {
        localStream = stream;
        preview.srcObject = localStream;
        preview.captureStream =
          preview.captureStream || preview.mozCaptureStream;
        return new Promise((resolve) => (preview.onplaying = resolve));
      })
      .then(() => startRecording(preview.captureStream(), recordingTimeMS))
      .then((recordedChunks) => {})
      .catch((error) => {
        hideCaptureContent(captureType);
        if (error.name === "NotFoundError") {
          log("Camera or microphone not found. Can't record.");
        } else {
          log(error);
        }
      })
      .finally(() => {
        // Re-enable button after capture is done (success or error)
        if (photoButton) {
          photoButton.disabled = false;
          photoButton.style.opacity = "1";
        }
      });
  } catch (error) {
    handleMediaError(error, "camera");
    hideCaptureContent(captureType);
    // Re-enable button on error
    if (photoButton) {
      photoButton.disabled = false;
      photoButton.style.opacity = "1";
    }
  }
}
function showRecordingIndicator() {
  const recordingIndicator = document.getElementById("recording-indicator");
  if (recordingIndicator) {
    // Clear any existing content
    recordingIndicator.innerHTML = "";

    // Create the circle and text elements
    const circle = document.createElement("div");
    circle.className = "recording-circle";

    const text = document.createElement("span");
    text.className = "recording-text";
    text.textContent = "Recording...";

    // Add elements to the indicator
    recordingIndicator.appendChild(circle);
    recordingIndicator.appendChild(text);

    // Show the indicator with the CSS class
    recordingIndicator.classList.add("show");

    console.log("Recording indicator shown");
  } else {
    console.log("Recording indicator element not found!");
  }
}

function hideRecordingIndicator() {
  const recordingIndicator = document.getElementById("recording-indicator");
  if (recordingIndicator) {
    // Remove the show class instead of setting display none
    recordingIndicator.classList.remove("show");

    // Clear content after a small delay to allow any CSS transitions
    setTimeout(() => {
      recordingIndicator.innerHTML = "";
    }, 100);

    console.log("Recording indicator hidden");
  } else {
    console.log("Recording indicator element not found when trying to hide!");
  }
}

function startAudioCapture() {
  const audioButton = document.getElementById("audioCallButton");
  const stopBtn = document.getElementById("stopButton");

  // Reset stop button state
  if (stopBtn) {
    stopBtn.disabled = false;
    stopBtn.style.opacity = "1";
    stopBtn.textContent = "Stop Recording";
  }

  captureCancelled = false;
  // Disable button immediately to prevent multiple clicks
  if (audioButton) {
    audioButton.disabled = true;
    audioButton.style.opacity = "0.5";
  }

  try {
    showCaptureContent("audio");
    captureType = "audioCall";
    setLiveButtonsExclude(false);
    navigator.mediaDevices
      .getUserMedia(constraintByType[captureType])
      .then((stream) => {
        localAudioStream = stream;
        localAudioStreamElement.srcObject = localAudioStream;
        localAudioStreamElement.captureStream =
          localAudioStreamElement.captureStream ||
          localAudioStreamElement.mozCaptureStream;
        showRecordingIndicator();
        // Show recording indicator when audio starts playing
        return new Promise((resolve) => {
          localAudioStreamElement.onplaying = () => {
            resolve();
          };
        });
      })
      .then(() =>
        startRecording(localAudioStreamElement.captureStream(), recordingTimeMS)
      )
      .then((recordedChunks) => {
        hideRecordingIndicator();
        if (stopBtn) {
          stopBtn.disabled = false;
          stopBtn.style.opacity = "1";
          stopBtn.textContent = "Stop Recording";
        }

        // Only load file if we have actual recorded content
        if (!captureCancelled && recordedChunks && recordedChunks.length > 0) {
          let recordedBlob = new Blob(recordedChunks, { type: "audio/mp3" });
          loadURLToInputFiled(recordedBlob, "audio/mp3");
          localAudioRecordingStreamElement.src =
            URL.createObjectURL(recordedBlob);
        }
        stopBothVideoAndAudio(localAudioStreamElement.srcObject);
        localAudioStream = null;
      })
      .catch((error) => {
        hideRecordingIndicator();
        if (stopBtn) {
          stopBtn.disabled = false;
          stopBtn.style.opacity = "1";
          stopBtn.textContent = "Stop Recording";
        }

        hideCaptureContent("audio");
        if (error.name === "NotFoundError") {
          log("Camera or microphone not found. Can't record.");
        } else {
          log(error);
        }
      })
      .finally(() => {
        // Re-enable button after capture is done (success or error)
        if (audioButton) {
          audioButton.disabled = false;
          audioButton.style.opacity = "1";
        }
      });
  } catch (error) {
    hideRecordingIndicator();
    handleMediaError(error, "microphone");
    hideCaptureContent("audio");
    if (stopBtn) {
      stopBtn.disabled = false;
      stopBtn.style.opacity = "1";
      stopBtn.textContent = "Stop Recording";
    }

    // Re-enable button on error
    if (audioButton) {
      audioButton.disabled = false;
      audioButton.style.opacity = "1";
    }
  }
}

function handleMediaError(error, mediaType) {
  console.log(`Media access error for ${mediaType}:`, error);

  clearMediaErrors();

  let errorMessage = "";

  if (
    error.name === "NotAllowedError" ||
    error.name === "PermissionDeniedError"
  ) {
    errorMessage = `${mediaType} access was denied. Please allow access and try again.`;
  } else if (
    error.name === "NotFoundError" ||
    error.name === "DevicesNotFoundError"
  ) {
    errorMessage = `No ${mediaType} found. Please check your device.`;
  } else {
    errorMessage = `Unable to access ${mediaType}. Please check your device settings.`;
  }

  showMediaError(errorMessage);
}

function showMediaError(message) {
  let errorDiv = document.getElementById("media-error-message");
  if (!errorDiv) {
    errorDiv = document.createElement("div");
    errorDiv.id = "media-error-message";
    errorDiv.className = "alert-danger";
    errorDiv.style.cssText =
      "margin: 10px; padding: 10px; border-radius: 5px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;";

    const callStatus = document.getElementById("callStatus");
    if (callStatus) {
      callStatus.parentNode.insertBefore(errorDiv, callStatus.nextSibling);
    }
  }

  errorDiv.innerHTML = message;
  errorDiv.style.display = "block";

  // Auto-hide after 3 seconds
  setTimeout(() => {
    if (errorDiv) {
      errorDiv.style.display = "none";
    }
    clearLog();
  }, 3000);
}

function clearMediaErrors() {
  const existingError = document.getElementById("media-error-message");
  clearLog();
  if (existingError) {
    existingError.remove();
  }
}

function log(msg) {
  callStatusp.innerHTML += `${msg}\n`;
}

function showCaptureContent(type) {
  if (type.startsWith("audio")) {
    localAudioStreamElement.hidden = false;
    localAudioRecordingStreamElement.hidden = false;
  } else if (type.startsWith("photo")) {
    canvas.hidden = false;
    capturePhotoButton.hidden = false;
    preview.hidden = false;
  } else {
    preview.hidden = false;
    recording.hidden = false;
  }
  cancelCaptureButton.hidden = false;
  connectedContainer.hidden = false;
}

function hideCaptureContent(type) {
  hideRecordingIndicator();
  callStatusp.innerText = "";
  setLiveButtonsExclude(true);
  if (type.startsWith("audio")) {
    localAudioStreamElement.hidden = true;
    localAudioRecordingStreamElement.hidden = true;
    localAudioRecordingStreamElement.src = "";
  } else if (type.startsWith("photo")) {
    canvas.hidden = true;
    capturePhotoButton.hidden = true;
    preview.hidden = true;
  } else {
    recording.src = "";
    preview.hidden = true;
    recording.hidden = true;
  }
  cancelCaptureButton.hidden = true;
  connectedContainer.hidden = true;
}

function loadURLToInputFiled(blob, mimetype = "video/mp4") {
  // Load img blob to input
  // WIP: UTF8 character error
  const fileChosen = document.getElementById("file-chosen");
  let fileName = "UserCapturedFile." + mimetype.split("/")[1];
  fileChosen.textContent = "Chosen File: " + fileName;
  let file = new File([blob], fileName, {
    type: mimetype,
    lastModified: new Date().getTime(),
  });
  let container = new DataTransfer();
  container.items.add(file);
  document.querySelector("#actual-btn").files = container.files;
}

function clearFiles() {
  const fileChosen = document.getElementById("file-chosen");
  fileChosen.textContent = "";
  document.querySelector("#actual-btn").files = new DataTransfer().files;
}

function onCancelCapture() {
  captureCancelled = true;
  if (captureType.startsWith("photo") || captureType.startsWith("video")) {
    stopBothVideoAndAudio(preview.srcObject);
  } else {
    localAudioRecordingStreamElement.src = "";
    stopBothVideoAndAudio(localAudioStreamElement.srcObject);
  }
  hideCaptureContent(captureType);
  clearFiles();
  // Reset stop button
  const stopBtn = document.getElementById("stopButton");
  if (stopBtn) {
    stopBtn.disabled = false;
    stopBtn.style.opacity = "1";
    stopBtn.textContent = "Stop Recording";
  }
}

function setupFileDragAndDrop() {
  const chatMessageTextarea = document.getElementById("chatMessage");
  if (!chatMessageTextarea) return;

  // Prevent default drag behaviors
  ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
    chatMessageTextarea.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  // Highlight drop area when item is dragged over
  ["dragenter", "dragover"].forEach((eventName) => {
    chatMessageTextarea.addEventListener(eventName, highlight, false);
  });

  ["dragleave", "drop"].forEach((eventName) => {
    chatMessageTextarea.addEventListener(eventName, unhighlight, false);
  });

  function highlight() {
    chatMessageTextarea.classList.add("highlight-drop-area");
  }

  function unhighlight() {
    chatMessageTextarea.classList.remove("highlight-drop-area");
  }

  // Handle dropped files
  chatMessageTextarea.addEventListener("drop", handleDrop, false);

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;

    if (files.length > 0) {
      // Handle the first file (you can modify to handle multiple files if needed)
      handleFile(files[0]);
    }
  }

  function isFileAllowed(file) {
    const filename = file.name;
    const fileExt = filename.split(".").pop().toLowerCase();
    return ALLOWED_EXTENSIONS.includes(fileExt);
  }

  function handleFile(file) {
    // Check if a file is allowed
    if (!isFileAllowed(file)) {
      // Show an error message
      showFileError(
        "This file type is not supported. Allowed types: " +
          ALLOWED_EXTENSIONS.join(", ")
      );
      return;
    }

    // Check file size using the shared MAX_FILE_SIZE constant
    if (file.size > MAX_FILE_SIZE) {
      showFileError(
        "This file size is not supported. Maximum size is " +
          formatFileSize(MAX_FILE_SIZE)
      );
      return;
    }

    // This will trigger the same flow as if the file was selected via file input
    const fileInput = document.getElementById("actual-btn");

    // Create a new FileList containing the file
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);

    // Set the file to the file input
    if (fileInput) {
      fileInput.files = dataTransfer.files;

      // Trigger the change event manually
      const event = new Event("change", { bubbles: true });
      fileInput.dispatchEvent(event);
    } else {
      console.error("File input element not found");
    }
  }
}

function showFileError(message) {
  // Create a toast notification
  const toast = document.createElement("div");
  toast.className = "file-upload-toast";
  toast.style.position = "fixed";
  toast.style.bottom = "20px";
  toast.style.right = "20px";
  toast.style.backgroundColor = "#ff4d4f";
  toast.style.color = "white";
  toast.style.padding = "10px 20px";
  toast.style.borderRadius = "4px";
  toast.style.boxShadow = "0 2px 8px rgba(0, 0, 0, 0.15)";
  toast.style.zIndex = "1000";
  toast.textContent = message;

  document.body.appendChild(toast);

  // Remove after 3 seconds
  setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transition = "opacity 0.5s ease";

    // Remove from DOM after fade out
    setTimeout(() => {
      document.body.removeChild(toast);
    }, 500);
  }, 3000);
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + " bytes";
  else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
  else return (bytes / 1048576).toFixed(1) + " MB";
}
