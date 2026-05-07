/* API CONFIGURATION

Centralized API endpoint definitions for the entire application. */

const API_CONFIG = {
  //Base API paths
  ENDPOINTS: {
    AUTH: "api/auth-api.php",
    PROFILE: "api/account-api.php",
    SETTINGS: "api/settings-api.php",
    FRIENDS: "api/friend-api.php",
    GROUP_CHAT: "api/group-chat-api.php",
    CHAT: "api/chat-api.php",
    SEARCH: "api/search-api.php",
    CONTACT: "api/contact-api.php",
    ADMIN: "api/admin-api.php",
    ACCOUNT: "api/account-api.php",
    UPLOAD: "api/upload-api.php",
  },

  //Actions for each API endpoint
  ACTIONS: {
    AUTH: {
      LOGIN: "login",
      REGISTER: "register",
      LOGOUT: "logout",
      FORGOT_PASSWORD: "forgot-password",
      RESET_PASSWORD: "reset-password",
      EMAIL_REDIRECT: "email-redirect",
    },

    PROFILE: {
      GET: "get",
      UPDATE_USERNAME: "update-username",
      UPDATE_PASSWORD: "update-password",
      UPLOAD_IMAGE: "upload-image",
      DELETE_IMAGE: "delete-image",
      DELETE_ACCOUNT: "delete-account",
    },

    UPLOAD: {
      PROFILE_IMAGE: "profile-image",
      PROFILE_IMAGE_DELETE: "profile-image-delete",
      CHAT_MEDIA: "chat-media",
      FILE_STATUS: "file-status",
      DELETE_FILE: "delete-file",
    },

    SETTINGS: {
      GET: "get",
      UPDATE: "update",
    },

    FRIENDS: {
      GET_FRIENDS: "get-friends",
      GET_PENDING_NOTIFICATIONS: "get-pending-notifications",
      VERIFY: "verify",
      VALIDATE_MEMBERS_FOR_GROUP: "validate-members-for-group",
      SEND_REQUEST: "send-request",
      ACCEPT_REQUEST: "accept-request",
      REJECT_REQUEST: "reject-request",
      CANCEL_REQUEST: "cancel-request",
      DELETE_FRIEND: "delete-friend",
    },

    GROUP_CHAT: {
      LIST: "list",
      DETAILS: "details",
      MESSAGES: "messages",
      CREATE: "create",
      UPDATE: "update",
      UPDATE_NAME: "update_name",
      UPDATE_IMAGE: "update_image",
      DELETE_IMAGE: "delete_image",
      ADD_MEMBERS: "add_members",
      REMOVE_MEMBER: "remove_member",
      LEAVE: "leave",
      DELETE: "delete",
      MARK_READ: "mark_read",
      GET_COMBINED_UNREAD_COUNT: "get_combined_unread_count",
      GET_COMBINED_NOTIFICATION_COUNT: "get_combined_notification_count",
      INVITE: "invite",
      UPDATE_SETTINGS: "update_settings",
      UPDATE_ROLE: "update_role",
      GET_GROUP_NOTIFICATIONS: "get_group_notifications",
      ACKNOWLEDGE_GROUP_NOTIFICATION: "acknowledge_group_notification",
      ACKNOWLEDGE_GROUP_NOTIFICATIONS_BY_GROUP:
        "acknowledge_group_notifications_by_group",
      ADMIN_LEAVE: "admin_leave",
    },

    CHAT: {
      GET_MESSAGES: "get-messages",
      GET_UNREAD_MESSAGES: "get-unread-messages",
    },

    SEARCH: {
      SEARCH_USERS: "search-users",
    },

    CONTACT: {
      SEND: "send",
    },

    ADMIN: {
      LIST_USERS: "list-users",
      SEARCH_USERS: "search-users",
      BAN_USER: "ban-user",
      UNBAN_USER: "unban-user",
    },

    ACCOUNT: {
      GET_IMAGE: "get-image",
    },
  },
};

//Helper functions for building API URLs with parameters
const ApiUrls = {
  //Build URL with query parameters
  buildUrl(baseUrl, params = {}) {
    if (Object.keys(params).length === 0) {
      return baseUrl;
    }

    //Build query string manually for better control
    const queryParams = [];
    Object.entries(params).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        queryParams.push(
          `${encodeURIComponent(key)}=${encodeURIComponent(value)}`,
        );
      }
    });

    const queryString = queryParams.join("&");
    return queryString ? `${baseUrl}?${queryString}` : baseUrl;
  },

  //Build API URL with action
  build(endpoint, action, params = {}) {
    return this.buildUrl(endpoint, { action, ...params });
  },

  //AUTH API
  authLogin() {
    return this.build(API_CONFIG.ENDPOINTS.AUTH, API_CONFIG.ACTIONS.AUTH.LOGIN);
  },

  authRegister() {
    return this.build(
      API_CONFIG.ENDPOINTS.AUTH,
      API_CONFIG.ACTIONS.AUTH.REGISTER,
    );
  },

  authLogout() {
    return this.build(
      API_CONFIG.ENDPOINTS.AUTH,
      API_CONFIG.ACTIONS.AUTH.LOGOUT,
    );
  },

  authForgotPassword() {
    return this.build(
      API_CONFIG.ENDPOINTS.AUTH,
      API_CONFIG.ACTIONS.AUTH.FORGOT_PASSWORD,
    );
  },

  authResetPassword() {
    return this.build(
      API_CONFIG.ENDPOINTS.AUTH,
      API_CONFIG.ACTIONS.AUTH.RESET_PASSWORD,
    );
  },

  authEmailRedirect(target = "messages") {
    return this.build(
      API_CONFIG.ENDPOINTS.AUTH,
      API_CONFIG.ACTIONS.AUTH.EMAIL_REDIRECT,
      { target },
    );
  },

  //PROFILE API
  profileGet() {
    return this.build(
      API_CONFIG.ENDPOINTS.PROFILE,
      API_CONFIG.ACTIONS.PROFILE.GET,
    );
  },

  profileUpdateUsername() {
    return this.build(
      API_CONFIG.ENDPOINTS.PROFILE,
      API_CONFIG.ACTIONS.PROFILE.UPDATE_USERNAME,
    );
  },

  profileUpdatePassword() {
    return this.build(
      API_CONFIG.ENDPOINTS.PROFILE,
      API_CONFIG.ACTIONS.PROFILE.UPDATE_PASSWORD,
    );
  },

  profileUploadImage() {
    return this.build(
      API_CONFIG.ENDPOINTS.PROFILE,
      API_CONFIG.ACTIONS.PROFILE.UPLOAD_IMAGE,
    );
  },

  profileDeleteImage() {
    return this.build(
      API_CONFIG.ENDPOINTS.PROFILE,
      API_CONFIG.ACTIONS.PROFILE.DELETE_IMAGE,
    );
  },

  profileDeleteAccount() {
    return this.build(
      API_CONFIG.ENDPOINTS.PROFILE,
      API_CONFIG.ACTIONS.PROFILE.DELETE_ACCOUNT,
    );
  },

  //SETTINGS API
  settingsGet() {
    return this.build(
      API_CONFIG.ENDPOINTS.SETTINGS,
      API_CONFIG.ACTIONS.SETTINGS.GET,
    );
  },

  settingsUpdate() {
    return this.build(
      API_CONFIG.ENDPOINTS.SETTINGS,
      API_CONFIG.ACTIONS.SETTINGS.UPDATE,
    );
  },

  //FRIENDS API
  friendsGet() {
    return this.build(
      API_CONFIG.ENDPOINTS.FRIENDS,
      API_CONFIG.ACTIONS.FRIENDS.GET_FRIENDS,
    );
  },

  friendsGetPendingNotifications() {
    return this.build(
      API_CONFIG.ENDPOINTS.FRIENDS,
      API_CONFIG.ACTIONS.FRIENDS.GET_PENDING_NOTIFICATIONS,
    );
  },

  friendsVerify(friendGuid) {
    return this.build(
      API_CONFIG.ENDPOINTS.FRIENDS,
      API_CONFIG.ACTIONS.FRIENDS.VERIFY,
      { friend_guid: friendGuid },
    );
  },

  friendsSendRequest() {
    return this.build(
      API_CONFIG.ENDPOINTS.FRIENDS,
      API_CONFIG.ACTIONS.FRIENDS.SEND_REQUEST,
    );
  },

  friendsAcceptRequest() {
    return this.build(
      API_CONFIG.ENDPOINTS.FRIENDS,
      API_CONFIG.ACTIONS.FRIENDS.ACCEPT_REQUEST,
    );
  },

  friendsRejectRequest() {
    return this.build(
      API_CONFIG.ENDPOINTS.FRIENDS,
      API_CONFIG.ACTIONS.FRIENDS.REJECT_REQUEST,
    );
  },

  friendsCancelRequest() {
    return this.build(
      API_CONFIG.ENDPOINTS.FRIENDS,
      API_CONFIG.ACTIONS.FRIENDS.CANCEL_REQUEST,
    );
  },

  friendsDeleteFriend() {
    return this.build(
      API_CONFIG.ENDPOINTS.FRIENDS,
      API_CONFIG.ACTIONS.FRIENDS.DELETE_FRIEND,
    );
  },

  friendsAction(action) {
    return this.build(API_CONFIG.ENDPOINTS.FRIENDS, action);
  },

  friendsValidateMembersForGroup() {
    return this.build(
      API_CONFIG.ENDPOINTS.FRIENDS,
      API_CONFIG.ACTIONS.FRIENDS.VALIDATE_MEMBERS_FOR_GROUP,
    );
  },

  //GROUP CHAT API
  groupsList() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.LIST,
    );
  },

  groupsDetails(groupGuid) {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.DETAILS,
      { group_guid: groupGuid },
    );
  },

  groupsMessages(groupGuid, limit = 50, offset = 0, additionalParams = {}) {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.MESSAGES,
      {
        group_guid: groupGuid,
        limit,
        offset,
        ...additionalParams,
      },
    );
  },

  groupsCreate() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.CREATE,
    );
  },

  groupsUpdate() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.UPDATE,
    );
  },

  groupsUpdateName(groupGuid) {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.UPDATE_NAME,
      { group_guid: groupGuid },
    );
  },

  groupsUpdateImage(groupGuid) {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.UPDATE_IMAGE,
      { group_guid: groupGuid },
    );
  },

  groupsDeleteImage(groupGuid) {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.DELETE_IMAGE,
      { group_guid: groupGuid },
    );
  },

  groupsAddMembers() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.ADD_MEMBERS,
    );
  },

  groupsRemoveMember() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.REMOVE_MEMBER,
    );
  },

  groupsLeave(groupGuid) {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.LEAVE,
      { group_guid: groupGuid },
    );
  },

  groupsDelete(groupGuid) {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.DELETE,
      { group_guid: groupGuid },
    );
  },

  groupsUnreadCount() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.GET_COMBINED_UNREAD_COUNT,
    );
  },

  groupsNotificationCount() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.GET_COMBINED_NOTIFICATION_COUNT,
    );
  },

  groupsMarkRead(groupGuid, messageId = null) {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.MARK_READ,
    );
  },

  groupsInvite() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.INVITE,
    );
  },

  groupsUpdateSettings() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.UPDATE_SETTINGS,
    );
  },

  groupsUpdateRole() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.UPDATE_ROLE,
    );
  },

  groupsGetGroupNotifications() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.GET_GROUP_NOTIFICATIONS,
    );
  },

  groupsAcknowledgeGroupNotification() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.ACKNOWLEDGE_GROUP_NOTIFICATION,
    );
  },

  groupsAcknowledgeGroupNotificationsByGroup() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.ACKNOWLEDGE_GROUP_NOTIFICATIONS_BY_GROUP,
    );
  },

  groupsAdminLeave() {
    return this.build(
      API_CONFIG.ENDPOINTS.GROUP_CHAT,
      API_CONFIG.ACTIONS.GROUP_CHAT.ADMIN_LEAVE,
    );
  },

  //CHAT API
  chat_messages(friendGuid, limit = 50, before = null) {
    const params = {
      friend_guid: friendGuid,
      limit,
    };
    if (before) {
      params.before = before;
    }
    return this.build(
      API_CONFIG.ENDPOINTS.CHAT,
      API_CONFIG.ACTIONS.CHAT.GET_MESSAGES,
      params,
    );
  },

  chatUnreadMessages() {
    return this.build(
      API_CONFIG.ENDPOINTS.CHAT,
      API_CONFIG.ACTIONS.CHAT.GET_UNREAD_MESSAGES,
    );
  },

  //SEARCH API
  searchUsers(query) {
    return this.build(
      API_CONFIG.ENDPOINTS.SEARCH,
      API_CONFIG.ACTIONS.SEARCH.SEARCH_USERS,
      { q: query },
    );
  },

  //CONTACT API
  contactSend() {
    return this.build(
      API_CONFIG.ENDPOINTS.CONTACT,
      API_CONFIG.ACTIONS.CONTACT.SEND,
    );
  },

  //ADMIN API
  adminListUsers() {
    return this.build(
      API_CONFIG.ENDPOINTS.ADMIN,
      API_CONFIG.ACTIONS.ADMIN.LIST_USERS,
    );
  },

  adminSearchUsers(query) {
    return this.build(
      API_CONFIG.ENDPOINTS.ADMIN,
      API_CONFIG.ACTIONS.ADMIN.SEARCH_USERS,
      { q: query },
    );
  },

  adminBanUser() {
    return this.build(
      API_CONFIG.ENDPOINTS.ADMIN,
      API_CONFIG.ACTIONS.ADMIN.BAN_USER,
    );
  },

  adminUnbanUser() {
    return this.build(
      API_CONFIG.ENDPOINTS.ADMIN,
      API_CONFIG.ACTIONS.ADMIN.UNBAN_USER,
    );
  },

  //ACCOUNT API
  accountUserImage(userGuid) {
    return this.build(
      API_CONFIG.ENDPOINTS.ACCOUNT,
      API_CONFIG.ACTIONS.ACCOUNT.GET_IMAGE,
      { user_guid: userGuid },
    );
  },
};

//Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = { API_CONFIG, ApiUrls };
} else {
  //Make it available globally
  window.API_CONFIG = API_CONFIG;
  window.ApiUrls = ApiUrls;
}
