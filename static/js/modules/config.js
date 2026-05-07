/* CONFIGURATION CONSTANTS

Shared constants used across the application. */

//Timing constants
export const RECORDING_TIME_MS = 30000;
export const WS_GRACE_PERIOD = 10000; //10 seconds (prevents reconnection toast on page reload/redirect)
export const STATUS_UPDATE_DEBOUNCE_MS = 0;

//WebSocket configuration
export const LOCAL_WS_PORT = window.WS_CONFIG?.port ?? 8082;
export const WS_HOST = window.WS_CONFIG?.host ?? "localhost"; //In fallback put your dev hostname
export const WS_MAX_RECONNECT_ATTEMPTS = 5;
export const WS_RECONNECT_DELAY = 1000;

//Compression configuration
export const COMPRESSION_CONFIG = {
  recording: {
    video: {
      bitrate: 500000,
      codecs: [
        "video/webm;codecs=vp9,opus",
        "video/webm;codecs=vp8,opus",
        "video/webm",
        "video/mp4",
      ],
    },
    audio: {
      bitrate: 128000,
      bitrateAudioOnly: 96000,
      codecs: [
        "audio/webm;codecs=opus",
        "audio/webm",
        "audio/ogg;codecs=opus",
        "audio/mp4",
      ],
    },
  },
};

//URL utilities
export function getWsUrl() {
  const loc = window.location;
  const protocol = loc.protocol;
  const hostname = loc.hostname;
  const isSecure = protocol === "https:";
  const wsProtocol = isSecure ? "wss" : "ws";

  const isLocalhost =
    hostname === "localhost" ||
    hostname === "127.0.0.1" ||
    hostname.startsWith("192.168.") ||
    hostname.startsWith("10.");

  if (isLocalhost) {
    return `${wsProtocol}://${hostname}:${LOCAL_WS_PORT}`;
  }

  return `wss://${WS_HOST}/ws`;
}

//Base URL calculation
const loc = window.location;
export const BASE_URL = loc.origin + loc.pathname.replace(/\/[^/]*$/, "");
export const QUERY_PATH = loc.search || "";

//HTTP URL builder
export function createUrl(target, withQuery = true) {
  const cleanedTarget = target.startsWith("/") ? target : "/" + target;
  return BASE_URL + cleanedTarget + (withQuery ? QUERY_PATH : "");
}

//HTML escaping for security (prevents XSS)
export function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}
