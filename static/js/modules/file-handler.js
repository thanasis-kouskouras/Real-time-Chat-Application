/* FILE HANDLER MODULE

Handles file uploads, drag-and-drop, validation, and file utilities. */

import { showFileError } from "./toast-notifications.js";

//File validation
export function haveAttachment() {
  const fileInput = document.getElementById("actual-btn");
  return (
    fileInput !== undefined &&
    fileInput.files !== undefined &&
    fileInput.files.length
  );
}

export function hasAttachmentReady() {
  const fileChosen = document.getElementById("file-chosen");
  return (
    fileChosen &&
    fileChosen.textContent &&
    fileChosen.textContent.trim().length > 0
  );
}

//File utilities
export function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + " bytes";
  else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
  else return (bytes / 1048576).toFixed(1) + " MB";
}

export function loadURLToInputField(blob, mimetype = "video/mp4") {
  const fileChosen = document.getElementById("file-chosen");
  const clearFileBtn = document.getElementById("clear-file-btn");
  let fileName = "UserCapturedFile." + mimetype.split("/")[1].split(";")[0];
  fileChosen.textContent = "Chosen File: " + fileName;

  //Show the clear button
  if (clearFileBtn) {
    clearFileBtn.classList.remove("d-none");
  }

  let file = new File([blob], fileName, {
    type: mimetype,
    lastModified: new Date().getTime(),
  });
  let container = new DataTransfer();
  container.items.add(file);
  const fileInput = document.querySelector("#actual-btn");
  fileInput.files = container.files;

  //Trigger change event to update send button state
  fileInput.dispatchEvent(new Event("change", { bubbles: true }));
}

export function clearFiles() {
  const fileChosen = document.getElementById("file-chosen");
  if (fileChosen) {
    fileChosen.textContent = "";
  }

  const clearFileBtn = document.getElementById("clear-file-btn");
  if (clearFileBtn) {
    clearFileBtn.classList.add("d-none");
  }

  const fileInput = document.querySelector("#actual-btn");
  if (fileInput) {
    fileInput.files = new DataTransfer().files;
    //Trigger change event to update send button state
    fileInput.dispatchEvent(new Event("change"));
  }

  //Trigger the Cancel recording button if recording UI is visible
  const captureCancel = document.getElementById("captureCancel");
  if (captureCancel && !captureCancel.hidden) {
    captureCancel.click();
  }
}

//Drag and drop functionality
export function setupFileDragAndDrop() {
  const chat_messageTextarea = document.getElementById("chat_message");
  if (!chat_messageTextarea) return;

  ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
    chat_messageTextarea.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  ["dragenter", "dragover"].forEach((eventName) => {
    chat_messageTextarea.addEventListener(eventName, highlight, false);
  });

  ["dragleave", "drop"].forEach((eventName) => {
    chat_messageTextarea.addEventListener(eventName, unhighlight, false);
  });

  function highlight() {
    chat_messageTextarea.classList.add("highlight-drop-area");
  }

  function unhighlight() {
    chat_messageTextarea.classList.remove("highlight-drop-area");
  }

  chat_messageTextarea.addEventListener("drop", handleDrop, false);

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;

    if (files.length > 0) {
      handleFile(files[0]);
    }
  }

  function isFileAllowed(file) {
    const filename = file.name;
    const fileExt = filename.split(".").pop().toLowerCase();
    return (
      window.ALLOWED_EXTENSIONS && window.ALLOWED_EXTENSIONS.includes(fileExt)
    );
  }

  function handleFile(file) {
    if (!isFileAllowed(file)) {
      const allowedTypes =
        window.ALLOWED_EXTENSIONS && window.ALLOWED_EXTENSIONS.length > 0
          ? window.ALLOWED_EXTENSIONS.join(", ")
          : "images, videos, audio, documents";
      showFileError(
        "This file type is not supported. Allowed types: " + allowedTypes,
      );
      return;
    }

    if (window.MAX_FILE_SIZE && file.size > window.MAX_FILE_SIZE) {
      showFileError(
        "This file size is not supported. Maximum size is " +
          formatFileSize(window.MAX_FILE_SIZE),
      );
      return;
    }

    const fileInput = document.getElementById("actual-btn");
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);

    if (fileInput) {
      fileInput.files = dataTransfer.files;
      const event = new Event("change", { bubbles: true });
      fileInput.dispatchEvent(event);
    }
  }
}

//File sending
export function sendAttachment(file, data, wsClient) {
  if (!file) {
    return false;
  }

  if (window.MAX_FILE_SIZE && file.size > window.MAX_FILE_SIZE) {
    showFileError(
      "This file size is not supported. Maximum size is " +
        formatFileSize(window.MAX_FILE_SIZE),
    );
    return false;
  }

  data.filename = file.name;
  data.filetype = file.type;
  data.filesize = file.size;

  if (wsClient && wsClient.send) {
    wsClient.send(data);

    file.arrayBuffer().then((arrayBuffer) => {
      wsClient.sendBinary(arrayBuffer);
    });
  }

  return true;
}

//Global exports for non-module scripts
window.haveAttachment = haveAttachment;
window.clearFiles = clearFiles;
