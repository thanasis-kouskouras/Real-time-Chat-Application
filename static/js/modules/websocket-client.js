/* WEBSOCKET CLIENT MODULE

Handles WebSocket connection, reconnection, and message routing. */

import {
  getWsUrl,
  WS_MAX_RECONNECT_ATTEMPTS,
  WS_RECONNECT_DELAY,
  WS_GRACE_PERIOD,
} from "./config.js";
import { showToast } from "./toast-notifications.js";

class WebSocketClient {
  constructor() {
    this.conn = null;
    this.wsServer = getWsUrl();
    this.reconnectAttempts = 0;
    this.reconnectDelay = WS_RECONNECT_DELAY;
    this.reconnectTimeout = null;
    this.connectionFailed = false;
    this.pageLoadTime = Date.now();
    this.token = "";
    this.messageHandlers = new Map();

    //Enhanced state tracking for better grace period logic
    this.isPageUnloading = false;
    this.connectionContext = "initial"; //"initial", "reconnecting", "navigating"
    this.lastConnectionAttempt = 0;
    this.gracePeriodActive = true;
    this.hasEverConnected = false;

    this.setupPageLifecycleHandlers();
  }

  setToken(token) {
    this.token = token;
  }

  setupPageLifecycleHandlers() {
    //Handle page unload events to prevent reconnection toasts during navigation
    window.addEventListener("beforeunload", () => {
      this.isPageUnloading = true;
      this.connectionContext = "navigating";
    });

    window.addEventListener("pagehide", () => {
      this.isPageUnloading = true;
      this.connectionContext = "navigating";
    });

    //Handle visibility changes
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "hidden") {
        this.connectionContext = "navigating";
      } else if (
        document.visibilityState === "visible" &&
        this.hasEverConnected
      ) {
        //Page became visible again, reset grace period for potential reconnection
        this.resetGracePeriod();
      }
    });
  }

  resetGracePeriod() {
    this.pageLoadTime = Date.now();
    this.gracePeriodActive = true;
    this.connectionContext = "initial";
  }

  isWithinGracePeriod() {
    const timeSincePageLoad = Date.now() - this.pageLoadTime;
    const withinTimeWindow = timeSincePageLoad < WS_GRACE_PERIOD;

    return (
      withinTimeWindow &&
      (this.connectionContext === "initial" ||
        this.connectionContext === "navigating")
    );
  }

  shouldShowReconnectionToast() {
    //Never show toasts if page is unloading
    if (this.isPageUnloading) {
      return false;
    }

    //Never show toasts during initial connection attempts
    if (this.connectionContext === "initial" && !this.hasEverConnected) {
      return false;
    }

    //Show toasts for genuine reconnection attempts outside grace period
    return (
      !this.isWithinGracePeriod() && this.connectionContext === "reconnecting"
    );
  }

  setConnectionContext(context) {
    this.connectionContext = context;
  }

  addMessageHandler(type, handler) {
    if (!this.messageHandlers.has(type)) {
      this.messageHandlers.set(type, []);
    }
    this.messageHandlers.get(type).push(handler);
  }

  //Counterpart to addMessageHandler (not currently used in the codebase)
  removeMessageHandler(type, handler) {
    if (this.messageHandlers.has(type)) {
      const handlers = this.messageHandlers.get(type);
      const index = handlers.indexOf(handler);
      if (index > -1) {
        handlers.splice(index, 1);
      }
    }
  }

  getConnection() {
    if (this.conn && this.conn.readyState === WebSocket.OPEN) {
      return this.conn;
    }

    if (this.conn && this.conn.readyState === WebSocket.CONNECTING) {
      return this.conn;
    }

    if (this.connectionFailed) {
      return null;
    }

    if (
      !this.conn ||
      this.conn.readyState === WebSocket.CLOSED ||
      this.conn.readyState === WebSocket.CLOSING
    ) {
      this.connect();
    }

    return this.conn;
  }

  connect() {
    try {
      this.lastConnectionAttempt = Date.now();

      //Set appropriate connection context
      if (this.hasEverConnected && this.reconnectAttempts > 0) {
        this.setConnectionContext("reconnecting");
      } else if (!this.hasEverConnected) {
        this.setConnectionContext("initial");
      }

      this.conn = new WebSocket(this.wsServer + "?token=" + this.token);

      this.conn.onopen = () => {
        const wasReconnecting =
          this.reconnectAttempts > 0 && this.hasEverConnected;
        this.reconnectAttempts = 0;
        this.reconnectDelay = WS_RECONNECT_DELAY;
        this.connectionFailed = false;
        this.hasEverConnected = true;

        if (wasReconnecting && this.shouldShowReconnectionToast()) {
          showToast("Reconnected to server", "success");
        }

        //After successful connection, no longer in initial state
        if (this.connectionContext === "initial") {
          this.gracePeriodActive = false;
        }
      };

      this.conn.onerror = (e) => {
        this.connectionFailed = true;
      };

      this.conn.onclose = (e) => {
        //Handle authentication failures
        if (e.code === 404 || e.code === 1008) {
          showToast("Session expired. Please login again.", "error", 3000);

          setTimeout(() => {
            window.location.href = "login.php";
          }, 3000);
          return;
        }

        //Handle clean disconnections or max attempts reached
        if (
          e.code === 1000 ||
          this.reconnectAttempts >= WS_MAX_RECONNECT_ATTEMPTS
        ) {
          if (this.reconnectAttempts >= WS_MAX_RECONNECT_ATTEMPTS) {
            showToast(
              "Unable to connect to server. Please refresh the page.",
              "error",
              5000,
            );
            this.connectionFailed = true;
          }
          return;
        }

        //Don't attempt reconnection if page is unloading
        if (this.isPageUnloading) {
          return;
        }

        this.reconnectAttempts++;

        if (this.hasEverConnected) {
          this.setConnectionContext("reconnecting");
        }

        const delay = Math.min(
          this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1),
          30000,
        );

        //Show reconnection toast only when appropriate
        if (this.shouldShowReconnectionToast()) {
          showToast(
            `Connection lost. Reconnecting... (${this.reconnectAttempts}/${WS_MAX_RECONNECT_ATTEMPTS})`,
            "warning",
            delay,
          );
        }

        this.reconnectTimeout = setTimeout(() => {
          if (
            this.conn.readyState === WebSocket.CLOSED ||
            this.conn.readyState === WebSocket.CLOSING
          ) {
            this.connect();
          }
        }, delay);
      };

      this.conn.onmessage = (e) => {
        this.handleMessage(e);
      };
    } catch (error) {
      this.connectionFailed = true;
      showToast(
        "Failed to connect to server. Please refresh the page.",
        "error",
        5000,
      );
    }
  }

  handleMessage(e) {
    let data;
    try {
      data = JSON.parse(e.data);
    } catch (error) {
      return;
    }

    if (data.error === "Unauthorized") {
      showToast("Session expired. Please login again.", "error", 3000);

      setTimeout(() => {
        window.location.href = "login.php";
      }, 3000);
      return;
    }

    this.routeMessage(data);
  }

  routeMessage(data) {
    if (data.type && this.messageHandlers.has(data.type)) {
      const handlers = this.messageHandlers.get(data.type);
      handlers.forEach((handler) => handler(data));
      return;
    }

    if (data.action && this.messageHandlers.has(data.action)) {
      const handlers = this.messageHandlers.get(data.action);
      handlers.forEach((handler) => handler(data));
      return;
    }

    if (this.messageHandlers.has("default")) {
      const handlers = this.messageHandlers.get("default");
      handlers.forEach((handler) => handler(data));
    }
  }

  send(data) {
    const conn = this.getConnection();
    if (conn && conn.readyState === WebSocket.OPEN) {
      if (typeof data === "string") {
        conn.send(data);
      } else {
        conn.send(JSON.stringify(data));
      }
      return true;
    }
    return false;
  }

  sendBinary(data) {
    const conn = this.getConnection();
    if (conn && conn.readyState === WebSocket.OPEN) {
      conn.send(data);
      return true;
    }
    return false;
  }

  disconnect() {
    //Set context to indicate clean disconnection
    this.setConnectionContext("navigating");

    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout);
      this.reconnectTimeout = null;
    }

    if (this.conn) {
      this.conn.close(1000, "Client disconnect");
      this.conn = null;
    }
  }

  cleanDisconnect() {
    //Enhanced clean disconnect for page navigation
    this.isPageUnloading = true;
    this.setConnectionContext("navigating");
    this.disconnect();
  }

  isConnected() {
    return this.conn && this.conn.readyState === WebSocket.OPEN;
  }
}

//Create singleton instance
export const wsClient = new WebSocketClient();

//Make wsClient globally available for non-module scripts
window.wsClient = wsClient;

//Legacy global functions for backward compatibility
window.getWebSocket = () => wsClient.getConnection();
