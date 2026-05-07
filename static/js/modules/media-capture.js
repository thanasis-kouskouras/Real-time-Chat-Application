/* MEDIA CAPTURE MODULE

Handles audio, video, and photo capture functionality using a modal interface. */

import { showToast } from "./toast-notifications.js";
import { setMediaButtonsState } from "./ui-updates.js";
import {
  loadURLToInputField,
  clearFiles,
  hasAttachmentReady,
} from "./file-handler.js";
import { cameraModal } from "./camera-modal.js";

class MediaCapture {
  constructor() {
    this.localAudioStreamElement = null;
    this.localAudioRecordingStreamElement = null;
    this.cancelCaptureButton = null;
    this.captureCancelled = false;
    this.callStatusp = null;
    this.localStreamElement = null;
    this.localStream = null;
    this.connectedContainer = null;
    this.captureType = "videoCall";
    this.preview = null;
    this.recording = null;
    this.stopButton = null;
  }

  initialize() {
    if (!window.location.href.includes("chatbox.php")) {
      return;
    }

    //Legacy element references (kept for backward compatibility)
    this.localAudioStreamElement = document.getElementById("localAudioStream");
    this.localAudioRecordingStreamElement = document.getElementById(
      "localAudioRecordingStream",
    );
    this.preview = document.getElementById("localVideoStream");
    this.recording = document.getElementById("recording");
    this.cancelCaptureButton = document.getElementById("captureCancel");
    this.stopButton = document.getElementById("stopButton");
    this.localStreamElement = document.getElementById("localStream");
    this.connectedContainer = document.getElementById("connectedContainer");
    this.callStatusp = document.getElementById("callStatus");

    //Initialize the camera modal
    cameraModal.initialize();

    //Hide legacy capture container
    this.hideConnectedContent();
  }

  //Start video capture using the modal
  startVideoCapture() {
    if (hasAttachmentReady()) {
      showToast(
        "Please send or clear the current attachment before recording a new one",
        "warning",
      );
      return;
    }

    this.captureCancelled = false;
    this.captureType = "videoCall";
    setMediaButtonsState(true);

    cameraModal.open(
      "video",
      (blob, mimeType) => {
        //onCapture callback
        loadURLToInputField(blob, mimeType);
        setMediaButtonsState(false);
        showToast("Video recorded successfully!", "success");
      },
      () => {
        //onCancel callback
        setMediaButtonsState(false);
        this.captureCancelled = true;
      },
    );
  }

  //Start photo capture using the modal
  startPhotoCapture() {
    if (hasAttachmentReady()) {
      showToast(
        "Please send or clear the current attachment before taking a new photo",
        "warning",
      );
      return;
    }

    this.captureCancelled = false;
    this.captureType = "photo";
    setMediaButtonsState(true);

    cameraModal.open(
      "photo",
      (blob, mimeType) => {
        //onCapture callback
        loadURLToInputField(blob, mimeType);
        setMediaButtonsState(false);
        showToast("Photo captured successfully!", "success");
      },
      () => {
        //onCancel callback
        setMediaButtonsState(false);
        this.captureCancelled = true;
      },
    );
  }

  //Start audio capture using the modal
  startAudioCapture() {
    if (hasAttachmentReady()) {
      showToast(
        "Please send or clear the current attachment before recording audio",
        "warning",
      );
      return;
    }

    this.captureCancelled = false;
    this.captureType = "audioCall";
    setMediaButtonsState(true);

    cameraModal.open(
      "audio",
      (blob, mimeType) => {
        //onCapture callback, use actual recorded MIME type for cross-browser compatibility
        loadURLToInputField(blob, blob.type || mimeType || "audio/webm");
        setMediaButtonsState(false);
        showToast("Audio recorded successfully!", "success");
      },
      () => {
        //onCancel callback
        setMediaButtonsState(false);
        this.captureCancelled = true;
      },
    );
  }

  //LEGACY METHODS (kept for backward compatibility)

  hideRecordingIndicator() {
    const recordingIndicator = document.getElementById("recording-indicator");
    if (recordingIndicator) {
      recordingIndicator.classList.remove("show");
      setTimeout(() => {
        recordingIndicator.innerHTML = "";
      }, 100);
    }
  }

  clearLog() {
    if (this.callStatusp) {
      this.callStatusp.innerText = "";
    }
  }

  hideCaptureContent(type) {
    //Legacy method (modal handles this now)
    this.hideRecordingIndicator();
  }

  hideConnectedContent() {
    this.clearLog();
    if (this.captureType.startsWith("video") && this.localStream !== null) {
      this.stopBothVideoAndAudio(this.localStream);
    }
    if (this.cancelCaptureButton) this.cancelCaptureButton.hidden = true;
    if (this.connectedContainer) this.connectedContainer.hidden = true;
  }

  stopBothVideoAndAudio(stream) {
    if (stream) {
      stream.getTracks().forEach((track) => {
        if (track.readyState === "live") {
          track.stop();
          if (this.localStreamElement !== null) {
            this.localStreamElement.srcObject?.removeTrack(track);
          } else if (this.localAudioStreamElement !== null) {
            this.localAudioStreamElement.srcObject?.removeTrack(track);
          } else if (this.preview !== null) {
            this.preview.srcObject?.removeTrack(track);
          }
        }
      });
    }
  }

  onCancelCapture() {
    //Close the modal if it's open
    if (cameraModal.isOpen) {
      cameraModal.close();
    }

    //Legacy cleanup
    this.captureCancelled = true;
    if (
      this.captureType.startsWith("photo") ||
      this.captureType.startsWith("video")
    ) {
      this.stopBothVideoAndAudio(this.preview?.srcObject);
      if (this.recording) {
        this.recording.removeAttribute("src");
        this.recording.style.display = "none";
      }
    } else {
      if (this.localAudioRecordingStreamElement) {
        this.localAudioRecordingStreamElement.removeAttribute("src");
      }
      this.stopBothVideoAndAudio(this.localAudioStreamElement?.srcObject);
    }
    this.hideCaptureContent(this.captureType);
    clearFiles();

    if (this.stopButton) {
      this.stopButton.hidden = true;
      this.stopButton.disabled = true;
    }

    setMediaButtonsState(false);
  }
}

//Create singleton instance
export const mediaCapture = new MediaCapture();

//Legacy global functions for backward compatibility
window.startVideoCapture = () => mediaCapture.startVideoCapture();
window.startPhotoCapture = () => mediaCapture.startPhotoCapture();
window.startAudioCapture = () => mediaCapture.startAudioCapture();
window.onCancelCapture = () => mediaCapture.onCancelCapture();
