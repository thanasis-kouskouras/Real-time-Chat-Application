/* GROUPS PAGE REAL-TIME UPDATES MODULE
 
Handles WebSocket events for real-time updates on the Groups page.
This module listens for group-related events and updates the UI without page refresh. */

import { wsClient } from "./websocket-client.js";

class GroupsRealtimeManager {
  constructor() {
    this.currentUserGuid = null;
    this.isInitialized = false;
    this.groupsListElement = null;
    this.countElement = null;
    this.currentSearchTerm = "";

    /* Register handlers immediately on construction (before init).
    This ensures handlers are ready even if init is delayed. */
    this.registerWebSocketHandlers();
  }

  //Initialize the real-time manager
  init(userGuid) {
    if (this.isInitialized) {
      this.currentUserGuid = userGuid;
      return;
    }

    this.currentUserGuid = userGuid;
    this.groupsListElement = document.getElementById("groupsList");
    this.countElement = document.querySelector(".groups-count");

    //Get current search term from URL
    const urlParams = new URLSearchParams(window.location.search);
    this.currentSearchTerm = urlParams.get("search") || "";

    this.isInitialized = true;
  }

  //Register all WebSocket message handlers
  registerWebSocketHandlers() {
    //Handler for member joined (including when current user is added to a group)
    wsClient.addMessageHandler("member_joined", (data) => {
      this.handleMemberJoined(data);
    });

    //Handler for member left/removed
    wsClient.addMessageHandler("member_left", (data) => {
      this.handleMemberLeft(data);
    });

    //Handler for group deactivation
    wsClient.addMessageHandler("group_deactivated", (data) => {
      this.handleGroupDeactivated(data);
    });

    //Handler for group reactivation
    wsClient.addMessageHandler("group_reactivated", (data) => {
      this.handleGroupReactivated(data);
    });

    //Handler for being removed from a group
    wsClient.addMessageHandler("removed_from_group", (data) => {
      this.handleGroupRemoved(data);
    });

    //Handler for group deletion
    wsClient.addMessageHandler("group_deleted", (data) => {
      this.handleGroupDeleted(data);
    });

    //Handler for group settings updates (name, image)
    wsClient.addMessageHandler("group_settings_updated", (data) => {
      this.handleSettingsUpdated(data);
    });

    //Handler for role updates (e.g., user became admin)
    wsClient.addMessageHandler("role_updated", (data) => {
      this.handleRoleUpdated(data);
    });
  }

  //Handle when a member joins a group (or current user is added to a group)
  handleMemberJoined(data) {
    const groupRow = this.findGroupRow(data.group_guid);

    if (!groupRow) {
      /* Group not in the list (current user was just added to this group).
      Reload the full list so the new group appears. */
      this.reloadGroupsList();
      return;
    }

    /* Group already in list.
    Another member joined, update member count. */
    if (data.member_count !== undefined) {
      this.updateGroupMemberCount(groupRow, data.member_count);
    }
  }

  //Handle when a member leaves or is removed from a group
  handleMemberLeft(data) {
    //Update member count for this group if visible
    const groupRow = this.findGroupRow(data.group_guid);
    if (groupRow && data.member_count !== undefined) {
      this.updateGroupMemberCount(groupRow, data.member_count);
    }

    //If group was deactivated as a result
    if (data.group_deactivated) {
      this.handleGroupDeactivated({
        group_guid: data.group_guid,
        member_count: data.member_count,
      });
    }
  }

  //Handle when a group is deactivated
  handleGroupDeactivated(_data) {
    /* Reload the full list so the API correctly determines visibility and admin status.
    For Admins, deactivated group is returned and shown with Deactivated badge.
    For Non-admins, deactivated group is not returned and disappears from list. */
    this.reloadGroupsList();
  }

  //Handle when a group is reactivated
  handleGroupReactivated(_data) {
    /* Reload the full list so the API determines correct ordering and visibility.
    For Admins, the group moves back to its normal position without deactivated styling.
    For Non-Admins, the group reappears in the list. */
    this.reloadGroupsList();
  }

  //Handle when user is explicitly removed from a group
  handleGroupRemoved(data) {
    this.removeGroupFromList(data.group_guid);
  }

  //Handle when a group is deleted
  handleGroupDeleted(data) {
    this.removeGroupFromList(data.group_guid);
  }

  //Handle when group settings are updated
  handleSettingsUpdated(data) {
    const groupRow = this.findGroupRow(data.group_guid);
    if (!groupRow) {
      return;
    }

    //Update group name if changed
    if (data.changes && data.changes.group_name) {
      const titleElement = groupRow.querySelector(".group-title");
      if (titleElement) {
        titleElement.textContent = data.changes.group_name;
      }
    }

    //Update group image if changed
    if (data.changes && "group_image" in data.changes) {
      const imgElement = groupRow.querySelector("img");
      if (imgElement) {
        if (data.changes.group_image === null) {
          imgElement.src = "img/groupdefault.png";
        } else if (data.changes.group_image_url) {
          imgElement.src = data.changes.group_image_url + "?t=" + Date.now();
        } else {
          //Reload to get correct URL
          this.reloadGroupsList();
        }
      }
    }
  }

  //Handle when a member's role is updated
  handleRoleUpdated(data) {
    //Check if the current user's role changed and reload to update buttons (Edit/Leave)
    if (data.user_guid === this.currentUserGuid) {
      this.reloadGroupsList();
    }
  }

  //Find a group row element
  findGroupRow(groupGuid) {
    if (!this.groupsListElement) {
      return null;
    }

    //Try data attribute on the row itself
    const rowByAttr = this.groupsListElement.querySelector(
      `.group-row[data-group-guid="${groupGuid}"]`,
    );
    if (rowByAttr) {
      return rowByAttr;
    }

    //Fallback, look for hidden inputs or buttons
    const allRows = this.groupsListElement.querySelectorAll(".group-row");
    for (const row of allRows) {
      //Check hidden input in forms
      const hiddenInput = row.querySelector(
        'input[name="guid"][value="' + groupGuid + '"]',
      );
      if (hiddenInput) {
        return row;
      }

      //Check data attribute on buttons
      const leaveBtn = row.querySelector(
        `button[data-group-guid="${groupGuid}"]`,
      );
      if (leaveBtn) {
        return row;
      }
    }

    return null;
  }

  //Update the member count display for a group
  updateGroupMemberCount(groupRow, count) {
    const countElement = groupRow.querySelector(".group-members-count");
    if (countElement) {
      countElement.textContent = `${count} members`;
    }
  }

  //Remove a group from the list
  removeGroupFromList(groupGuid) {
    const groupRow = this.findGroupRow(groupGuid);
    if (groupRow) {
      //Animate removal
      groupRow.style.transition = "opacity 0.3s, transform 0.3s";
      groupRow.style.opacity = "0";
      groupRow.style.transform = "translateX(-100%)";

      setTimeout(() => {
        groupRow.remove();
        this.updateGroupsCount();
      }, 300);
    }
  }

  //Update the groups count display
  updateGroupsCount() {
    if (!this.countElement || !this.groupsListElement) {
      return;
    }

    const groupCount =
      this.groupsListElement.querySelectorAll(".group-row").length;
    let countText = "";

    if (this.currentSearchTerm === "") {
      if (groupCount === 0) {
        countText = "There are no groups yet.";
      } else if (groupCount === 1) {
        countText = "There is 1 group.";
      } else {
        countText = `There are ${groupCount} groups.`;
      }
    } else {
      if (groupCount === 0) {
        countText = "No groups found.";
      } else if (groupCount === 1) {
        countText = "Found 1 group.";
      } else {
        countText = `Found ${groupCount} groups.`;
      }
    }

    this.countElement.innerHTML = `<p class="text-center m-0">${countText}</p>`;
  }

  //Reload the entire groups list from API
  async reloadGroupsList() {
    //Use the global loadGroups function if available
    if (typeof window.loadGroups === "function") {
      await window.loadGroups(this.currentSearchTerm);
    } else {
      //Fallback, reload the page
      window.location.reload();
    }
  }
}

//Create singleton instance
export const groupsRealtimeManager = new GroupsRealtimeManager();

//Expose globally for non-module scripts
window.groupsRealtimeManager = groupsRealtimeManager;
