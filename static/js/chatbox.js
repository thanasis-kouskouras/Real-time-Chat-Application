$(document).ready(function () {
  const actualBtn = document.getElementById("actual-btn");

  const fileChosen = document.getElementById("file-chosen");

  actualBtn.addEventListener("change", function () {
    fileChosen.textContent = "";
    if (this.files.length)
      fileChosen.textContent = "Chosen File: " + this.files[0].name;
  });
  actualBtn.addEventListener("cancel", function () {
    fileChosen.textContent = "";
  });

  const body = $("#bodyMsg");
  body.scrollTop(body[0].scrollHeight);

  setTimeout(function (e = null) {
    // a delay for videos
    const body = $("#bodyMsg");
    body.scrollTop(body[0].scrollHeight);
  }, 5000);
});

// Call setupFileDragAndDrop when the document is loaded
document.addEventListener("DOMContentLoaded", function () {
  setupFileDragAndDrop();
});

function showMessageSending() {
  $("#send-button").prop("disabled", true).text("Sending...");
}

function hideMessageSending() {
  $("#send-button").prop("disabled", false).text("Send");
}

function showMessageStatus(message, isSuccess = true) {
  const statusClass = isSuccess ? "alert-success" : "alert-danger";
  const statusHtml = `<div class="message-status ${statusClass}">${message}</div>`;
  $("#chatMessage").after(statusHtml);

  // Remove status after 3 seconds
  setTimeout(() => {
    $(".message-status").fadeOut(300, function () {
      $(this).remove();
    });
  }, 3000);
}

function setCursorToStart(element) {
  element.focus();
  element.setSelectionRange(0, 0);
}
