/* LEAVE GROUP HANDLER

Handles the leave group confirmation and API call for the Groups page. */

async function leaveGroup(groupGuid, groupName) {
  if (!confirm(`Are you sure you want to leave "${groupName}"?`)) {
    return;
  }

  await ApiClient.actionWithReload(
    () =>
      ApiClient.post(
        ApiUrls.build(
          API_CONFIG.ENDPOINTS.GROUP_CHAT,
          API_CONFIG.ACTIONS.GROUP_CHAT.LEAVE,
        ),
        { group_guid: groupGuid },
      ),
    {
      loadingMessage: "Leaving group...",
      successMessage: "Left group successfully!",
      errorMessage: "Failed to leave group",
    },
  );
}

//Make function available globally for onclick handlers
window.leaveGroup = leaveGroup;
