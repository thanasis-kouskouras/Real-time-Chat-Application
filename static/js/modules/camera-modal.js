/* CAMERA MODAL MODULE

Provides a fullscreen modal for camera/video/audio capture. */

import { showToast } from "./toast-notifications.js";
import { RECORDING_TIME_MS } from "./config.js";

class CameraModal {
  constructor() {
    this.modal = null;
    this.isOpen = false;
    this.captureType = null; //'photo', 'video', 'audio'
    this.onCapture = null;
    this.onCancel = null;
    this.stream = null;

    //Element references
    this.previewVideo = null;
    this.recordedVideo = null;
    this.audioElement = null;
    this.recordedAudio = null;
    this.canvas = null;
    this.capturedImage = null;

    //Buttons
    this.captureBtn = null;
    this.stopBtn = null;
    this.retakeBtn = null;
    this.useBtn = null;
    this.cancelBtn = null;

    //Recording state
    this.mediaRecorder = null;
    this.recordedChunks = [];
    this.recordedBlob = null;
    this.isRecording = false;
  }

  initialize() {
    //Only initialize on chatbox page
    if (!window.location.href.includes("chatbox.php")) {
      return;
    }

    this.createModalElement();
    this.cacheElements();
    this.setupEventListeners();
  }

  createModalElement() {
    //Check if modal already exists
    if (document.getElementById("cameraModal")) {
      this.modal = document.getElementById("cameraModal");
      return;
    }

    const modalHTML = `
            <div id="cameraModal" class="camera-modal" role="dialog" aria-modal="true" aria-labelledby="cameraModalTitle">
                <div class="camera-modal-overlay"></div>
                <div class="camera-modal-container">
                    <div class="camera-modal-header">
                        <h3 id="cameraModalTitle" class="camera-modal-title">
                            <i class="fa fa-camera"></i>
                            <span id="cameraModalTitleText">Capture</span>
                        </h3>
                        <button type="button" class="camera-modal-close" id="cameraModalClose" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>

                    <div class="camera-modal-body">
                        <!-- Preview Area -->
                        <div class="camera-preview-area">
                            <!-- Video Preview (for photo/video capture) -->
                            <video id="cameraPreviewVideo" class="camera-preview" autoplay playsinline muted></video>

                            <!-- Recorded Video Preview -->
                            <video id="cameraRecordedVideo" class="camera-preview camera-recorded d-none" controls></video>

                            <!-- Audio Recording UI -->
                            <div id="cameraAudioUI" class="camera-audio-ui d-none">
                                <div class="audio-visualizer">
                                    <i class="fa fa-microphone audio-icon"></i>
                                    <div class="audio-waves">
                                        <span></span><span></span><span></span><span></span><span></span>
                                    </div>
                                </div>
                                <audio id="cameraAudioElement" hidden></audio>
                            </div>

                            <!-- Recorded Audio Preview -->
                            <div id="cameraRecordedAudioContainer" class="camera-recorded-audio d-none">
                                <div class="camera-audio-review">
                                    <i class="fa fa-microphone audio-review-icon"></i>
                                    <span id="cameraAudioDuration" class="audio-review-duration">00:00</span>
                                </div>
                                <audio id="cameraRecordedAudio" controls></audio>
                            </div>

                            <!-- Captured Image Preview -->
                            <img id="cameraCapturedImage" class="camera-preview camera-captured-image d-none" alt="Captured photo">

                            <!-- Canvas for photo capture -->
                            <canvas id="cameraCanvas" class="d-none"></canvas>

                            <!-- Recording indicator -->
                            <div id="cameraRecordingIndicator" class="camera-recording-indicator d-none">
                                <span class="recording-dot"></span>
                                <span class="recording-time">00:00</span>
                            </div>

                            <!-- Loading state -->
                            <div id="cameraLoading" class="camera-loading">
                                <i class="fa fa-spinner fa-spin"></i>
                                <p>Accessing camera...</p>
                            </div>

                            <!-- Error state -->
                            <div id="cameraError" class="camera-error d-none">
                                <i class="fa fa-exclamation-triangle"></i>
                                <p id="cameraErrorMessage">Unable to access camera</p>
                            </div>
                        </div>
                    </div>

                    <div class="camera-modal-footer">
                        <!-- Initial capture controls -->
                        <div id="cameraInitialControls" class="camera-controls">
                            <button type="button" id="cameraCaptureBtn" class="camera-btn camera-btn-capture">
                                <i class="fa fa-camera"></i>
                                <span>Capture</span>
                            </button>
                            <button type="button" id="cameraStopBtn" class="camera-btn camera-btn-stop d-none">
                                <i class="fa fa-stop"></i>
                                <span>Stop</span>
                            </button>
                            <button type="button" id="cameraCancelBtn" class="camera-btn camera-btn-cancel">
                                <i class="fa fa-times"></i>
                                <span>Cancel</span>
                            </button>
                        </div>

                        <!-- After capture controls -->
                        <div id="cameraAfterControls" class="camera-controls d-none">
                            <button type="button" id="cameraRetakeBtn" class="camera-btn camera-btn-retake">
                                <i class="fa fa-redo"></i>
                                <span>Retake</span>
                            </button>
                            <button type="button" id="cameraUseBtn" class="camera-btn camera-btn-use">
                                <i class="fa fa-check"></i>
                                <span>Use</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

    document.body.insertAdjacentHTML("beforeend", modalHTML);
    this.modal = document.getElementById("cameraModal");
  }

  cacheElements() {
    this.previewVideo = document.getElementById("cameraPreviewVideo");
    this.recordedVideo = document.getElementById("cameraRecordedVideo");
    this.audioUI = document.getElementById("cameraAudioUI");
    this.audioElement = document.getElementById("cameraAudioElement");
    this.recordedAudioContainer = document.getElementById(
      "cameraRecordedAudioContainer",
    );
    this.recordedAudio = document.getElementById("cameraRecordedAudio");
    this.capturedImage = document.getElementById("cameraCapturedImage");
    this.canvas = document.getElementById("cameraCanvas");
    this.recordingIndicator = document.getElementById(
      "cameraRecordingIndicator",
    );
    this.loadingElement = document.getElementById("cameraLoading");
    this.errorElement = document.getElementById("cameraError");
    this.errorMessage = document.getElementById("cameraErrorMessage");

    this.initialControls = document.getElementById("cameraInitialControls");
    this.afterControls = document.getElementById("cameraAfterControls");

    this.captureBtn = document.getElementById("cameraCaptureBtn");
    this.stopBtn = document.getElementById("cameraStopBtn");
    this.retakeBtn = document.getElementById("cameraRetakeBtn");
    this.useBtn = document.getElementById("cameraUseBtn");
    this.cancelBtn = document.getElementById("cameraCancelBtn");
    this.closeBtn = document.getElementById("cameraModalClose");

    this.titleText = document.getElementById("cameraModalTitleText");
    this.titleIcon = this.modal.querySelector(".camera-modal-title i");
  }

  setupEventListeners() {
    //Close button
    this.closeBtn?.addEventListener("click", () => this.close());

    //Cancel button
    this.cancelBtn?.addEventListener("click", () => this.close());

    //Overlay click to close
    this.modal
      ?.querySelector(".camera-modal-overlay")
      ?.addEventListener("click", () => this.close());

    //Capture button (for photo) / Start Recording (for video/audio)
    if (this.captureBtn) {
      this.captureBtn.addEventListener("click", () => this.handleCapture());
    }

    //Stop recording button
    if (this.stopBtn) {
      this.stopBtn.addEventListener("click", () => this.stopRecording());
    }

    //Retake button
    this.retakeBtn?.addEventListener("click", () => this.handleRetake());

    //Use button
    this.useBtn?.addEventListener("click", () => this.handleUse());

    //Escape key to close
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.isOpen) {
        this.close();
      }
    });
  }

  /* Open the modal for a specific capture type ('photo', 'video', or 'audio').
    onCapture: callback receiving (Blob, mimeType) when capture is confirmed.
    onCancel: callback when the modal is closed without capturing. */
  async open(type, onCapture, onCancel) {
    this.captureType = type;
    this.onCapture = onCapture;
    this.onCancel = onCancel;
    this.recordedBlob = null;
    this.recordedChunks = [];

    //Reset UI
    this.resetUI();

    //Update title based on type
    this.updateTitle(type);

    //Show modal
    this.modal.classList.add("open");
    this.isOpen = true;
    document.body.style.overflow = "hidden";

    //Start camera/microphone
    await this.startMedia(type);
  }

  close() {
    //Stop all media tracks
    this.stopAllMedia();

    //Stop recording if active
    if (this.mediaRecorder && this.mediaRecorder.state !== "inactive") {
      this.mediaRecorder.onstop = null; //Prevent onRecordingStop from firing after cancel
      this.mediaRecorder.stop();
    }
    //Reset recording state so the next open() starts cleanly
    this.isRecording = false;
    this.mediaRecorder = null;
    this.recordedChunks = [];
    this.audioUI?.classList.remove("recording");

    //Hide modal
    this.modal.classList.remove("open");
    this.isOpen = false;
    document.body.style.overflow = "";

    //Clear recording timer
    if (this.recordingTimer) {
      clearInterval(this.recordingTimer);
      this.recordingTimer = null;
    }

    //Call cancel callback
    if (this.onCancel) {
      this.onCancel();
    }
  }

  resetUI() {
    //Hide all preview elements
    this.previewVideo.classList.add("d-none");
    this.recordedVideo.classList.add("d-none");
    this.audioUI.classList.add("d-none");
    this.recordedAudioContainer.classList.add("d-none");
    this.capturedImage.classList.add("d-none");
    this.recordingIndicator.classList.add("d-none");
    this.errorElement.classList.add("d-none");

    //Show loading
    this.loadingElement.classList.remove("d-none");

    //Reset controls
    this.initialControls.classList.remove("d-none");
    this.afterControls.classList.add("d-none");
    this.captureBtn.classList.remove("d-none");
    this.stopBtn.classList.add("d-none");

    //Clear sources
    this.previewVideo.srcObject = null;
    this.recordedVideo.src = "";
    this.recordedAudio.src = "";
    this.capturedImage.src = "";
  }

  updateTitle(type) {
    const titles = {
      photo: { text: "Take Photo", icon: "fa-camera" },
      video: { text: "Record Video", icon: "fa-video-camera" },
      audio: { text: "Record Audio", icon: "fa-microphone" },
    };

    const config = titles[type] || titles.photo;
    this.titleText.textContent = config.text;
    this.titleIcon.className = `fa ${config.icon}`;

    //Update capture button based on type
    if (type === "photo") {
      this.captureBtn.innerHTML =
        '<i class="fa fa-camera"></i><span>Capture</span>';
    } else {
      this.captureBtn.innerHTML =
        '<i class="fa fa-circle"></i><span>Start Recording</span>';
    }
  }

  async startMedia(type) {
    try {
      const constraints = this.getConstraints(type);
      this.stream = await navigator.mediaDevices.getUserMedia(constraints);

      //Hide loading
      this.loadingElement.classList.add("d-none");

      if (type === "audio") {
        //Show audio UI
        this.audioUI.classList.remove("d-none");
        this.audioElement.srcObject = this.stream;
      } else {
        //Show video preview
        this.previewVideo.classList.remove("d-none");
        this.previewVideo.srcObject = this.stream;
        await this.previewVideo.play();
      }
    } catch (error) {
      this.showError(error, type);
    }
  }

  getConstraints(type) {
    switch (type) {
      case "photo":
        return {
          video: {
            facingMode: "user",
            width: { ideal: 1280 },
            height: { ideal: 720 },
          },
          audio: false,
        };
      case "video":
        return {
          video: {
            facingMode: "user",
            width: { ideal: 1280 },
            height: { ideal: 720 },
          },
          audio: true,
        };
      case "audio":
        return { video: false, audio: true };
      default:
        return { video: true, audio: false };
    }
  }

  showError(error, type) {
    this.loadingElement.classList.add("d-none");
    this.errorElement.classList.remove("d-none");

    let message = "Unable to access ";
    if (type === "audio") {
      message += "microphone";
    } else if (type === "video") {
      message += "camera and microphone";
    } else {
      message += "camera";
    }

    if (
      error.name === "NotAllowedError" ||
      error.name === "PermissionDeniedError"
    ) {
      message += ". Permission denied. Please allow access and try again.";
    } else if (
      error.name === "NotFoundError" ||
      error.name === "DevicesNotFoundError"
    ) {
      message += ". No device found.";
    } else {
      message += ". Please check your device settings.";
    }

    this.errorMessage.textContent = message;
  }

  handleCapture() {
    if (this.captureType === "photo") {
      this.capturePhoto();
    } else {
      this.startRecording();
    }
  }

  capturePhoto() {
    if (!this.stream) return;

    const video = this.previewVideo;
    const canvas = this.canvas;

    //Set canvas size to match video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    //Draw video frame to canvas
    const ctx = canvas.getContext("2d");
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    //Convert to blob
    canvas.toBlob(
      (blob) => {
        this.recordedBlob = blob;

        //Show captured image
        this.capturedImage.src = URL.createObjectURL(blob);
        this.capturedImage.classList.remove("d-none");
        this.previewVideo.classList.add("d-none");

        //Show after-capture controls
        this.initialControls.classList.add("d-none");
        this.afterControls.classList.remove("d-none");
      },
      "image/jpeg",
      0.9,
    );
  }

  startRecording() {
    if (!this.stream) {
      return;
    }

    if (this.isRecording) {
      return;
    }

    this.recordedChunks = [];
    this.isRecording = true;

    //Setup MediaRecorder
    const options = this.getRecorderOptions();

    try {
      this.mediaRecorder = new MediaRecorder(this.stream, options);
    } catch (e) {
      try {
        this.mediaRecorder = new MediaRecorder(this.stream);
      } catch {
        return;
      }
    }

    this.mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) {
        this.recordedChunks.push(e.data);
      }
    };

    this.mediaRecorder.onstop = () => {
      this.onRecordingStop();
    };

    this.mediaRecorder.start(100); //Collect data every 100ms

    //Update UI
    this.captureBtn.classList.add("d-none");
    this.stopBtn.classList.remove("d-none");
    this.recordingIndicator.classList.remove("d-none");

    //Add recording class for audio waves animation
    if (this.captureType === "audio") {
      this.audioUI.classList.add("recording");
    }

    //Start timer
    this.startRecordingTimer();
  }

  stopRecording() {
    if (!this.isRecording || !this.mediaRecorder) return;

    this.isRecording = false;
    this.mediaRecorder.stop();

    //Stop timer
    if (this.recordingTimer) {
      clearInterval(this.recordingTimer);
      this.recordingTimer = null;
    }

    //Remove recording class
    if (this.captureType === "audio") {
      this.audioUI.classList.remove("recording");
    }
  }

  onRecordingStop() {
    const actualMimeType =
      this.mediaRecorder && this.mediaRecorder.mimeType
        ? this.mediaRecorder.mimeType.split(";")[0]
        : this.captureType === "audio"
          ? "audio/webm"
          : "video/webm";
    const rawBlob = new Blob(this.recordedChunks, { type: actualMimeType });

    //Fix WebM duration metadata so Firefox can play Chrome-recorded audio/video
    if (
      (actualMimeType === "audio/webm" || actualMimeType === "video/webm") &&
      this.recordingSeconds > 0
    ) {
      this._fixWebmDuration(rawBlob, this.recordingSeconds, (fixedBlob) => {
        this.recordedBlob = fixedBlob;
        this._applyRecordingResult();
      });
    } else {
      this.recordedBlob = rawBlob;
      this._applyRecordingResult();
    }
  }

  _applyRecordingResult() {
    //Update UI based on type
    if (this.captureType === "audio") {
      this.audioUI.classList.add("d-none");
      this.recordedAudioContainer.classList.remove("d-none");
      this.recordedAudio.src = URL.createObjectURL(this.recordedBlob);

      //Show recorded duration
      const mins = Math.floor(this.recordingSeconds / 60);
      const secs = this.recordingSeconds % 60;
      const durationEl = document.getElementById("cameraAudioDuration");
      if (durationEl) {
        durationEl.textContent = `${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;
      }
    } else {
      this.previewVideo.classList.add("d-none");
      this.recordedVideo.classList.remove("d-none");
      this.recordedVideo.src = URL.createObjectURL(this.recordedBlob);
    }

    //Hide recording indicator
    this.recordingIndicator.classList.add("d-none");

    //Show after-capture controls
    this.initialControls.classList.add("d-none");
    this.afterControls.classList.remove("d-none");
  }

  /* Inject correct duration into a Chrome MediaRecorder WebM blob.
    Chrome sets Duration = 0.0 in the EBML Info block. Firefox rejects such files.
    Patched the float64 Duration value in-place using the known recording length. */
  _fixWebmDuration(blob, durationSeconds, callback) {
    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const buffer = e.target.result;
        const arr = new Uint8Array(buffer);

        //Locate EBML Info element
        for (let i = 0; i < arr.length - 12; i++) {
          if (
            arr[i] === 0x15 &&
            arr[i + 1] === 0x49 &&
            arr[i + 2] === 0xa9 &&
            arr[i + 3] === 0x66
          ) {
            //Search for Duration element within Info
            for (let j = i + 4; j < Math.min(arr.length - 10, i + 300); j++) {
              if (
                arr[j] === 0x44 &&
                arr[j + 1] === 0x89 &&
                arr[j + 2] === 0x88
              ) {
                //Overwrite float64 with duration in ms
                const view = new DataView(buffer);
                view.setFloat64(j + 3, durationSeconds * 1000, false); // big-endian
                callback(new Blob([buffer], { type: blob.type }));
                return;
              }
            }
            break;
          }
        }
      } catch {}
      callback(blob); //Return original blob if patch fails
    };
    reader.readAsArrayBuffer(blob);
  }

  getRecorderOptions() {
    const isAudio = this.captureType === "audio";

    const options = {
      videoBitsPerSecond: 500000,
      audioBitsPerSecond: isAudio ? 96000 : 128000,
    };

    const codecs = isAudio
      ? ["audio/ogg;codecs=opus", "audio/webm;codecs=opus", "audio/webm"]
      : [
          "video/webm;codecs=vp9,opus",
          "video/webm;codecs=vp8,opus",
          "video/webm",
          "video/mp4",
        ];

    for (const codec of codecs) {
      if (MediaRecorder.isTypeSupported(codec)) {
        options.mimeType = codec;
        break;
      }
    }

    return options;
  }

  startRecordingTimer() {
    this.recordingSeconds = 0;
    const maxSeconds = RECORDING_TIME_MS / 1000;

    const timerElement =
      this.recordingIndicator.querySelector(".recording-time");
    timerElement.textContent = "00:00";

    this.recordingTimer = setInterval(() => {
      this.recordingSeconds++;
      const mins = Math.floor(this.recordingSeconds / 60);
      const secs = this.recordingSeconds % 60;
      timerElement.textContent = `${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;

      //Auto-stop at max time
      if (this.recordingSeconds >= maxSeconds) {
        this.stopRecording();
        showToast(
          `Maximum recording time reached (${maxSeconds} seconds)`,
          "info",
        );
      }
    }, 1000);
  }

  handleRetake() {
    //Reset for new capture
    this.recordedBlob = null;
    this.recordedChunks = [];

    //Reset UI
    this.capturedImage.classList.add("d-none");
    this.recordedVideo.classList.add("d-none");
    this.recordedAudioContainer.classList.add("d-none");

    //Show initial controls
    this.initialControls.classList.remove("d-none");
    this.afterControls.classList.add("d-none");
    this.captureBtn.classList.remove("d-none");
    this.stopBtn.classList.add("d-none");

    //Show preview based on type
    if (this.captureType === "audio") {
      this.audioUI.classList.remove("d-none");
    } else {
      this.previewVideo.classList.remove("d-none");
    }
  }

  handleUse() {
    if (!this.recordedBlob) return;

    //Call the capture callback with the blob
    if (this.onCapture) {
      const mimeType = this.getMimeType();
      this.onCapture(this.recordedBlob, mimeType);
    }

    //Stop media and close
    this.stopAllMedia();
    this.modal.classList.remove("open");
    this.isOpen = false;
    document.body.style.overflow = "";

    //Clear recording timer
    if (this.recordingTimer) {
      clearInterval(this.recordingTimer);
      this.recordingTimer = null;
    }
  }

  getMimeType() {
    if (this.recordedBlob && this.recordedBlob.type) {
      return this.recordedBlob.type;
    }
    switch (this.captureType) {
      case "photo":
        return "image/jpeg";
      case "video":
        return "video/webm";
      case "audio":
        return "audio/webm";
      default:
        return "application/octet-stream";
    }
  }

  stopAllMedia() {
    if (this.stream) {
      this.stream.getTracks().forEach((track) => track.stop());
      this.stream = null;
    }
  }
}

//Create and export singleton instance
export const cameraModal = new CameraModal();
