/* GROUPS PAGE

Handles group list display via API. */

let currentUserGuid = null;
let currentSearchTerm = "";

//Load groups from API
async function loadGroups(searchTerm = "") {
  const groupsListDiv = document.getElementById("groupsList");
  const countDiv = document.querySelector(".groups-count");

  try {
    //Show loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.show("Loading groups...");
    }

    if (countDiv) {
      countDiv.innerHTML = '<p class="text-center m-0">Loading groups...</p>';
    }
    groupsListDiv.innerHTML = "";

    //Check ApiClient availability
    if (!window.ApiClient) {
      throw new Error("ApiClient is not available");
    }

    //Fetch groups from API
    const response = await ApiClient.get(ApiUrls.groupsList());

    //Hide loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }

    if (!response.groups) {
      throw new Error("Invalid response format");
    }

    //Map API response fields to expected format
    let groups = response.groups.map((g) => {
      const mappedGroup = {
        guid: g.group_guid,
        name: g.group_name,
        memberCount: g.member_count,
        isAdmin: g.is_admin, //User's role in this group (admin or member)
        isActive: g.is_active,
        group_image_url: g.group_image_url,
      };

      return mappedGroup;
    });

    //Filter groups based on search term
    if (searchTerm) {
      const lowerSearch = searchTerm.toLowerCase();
      groups = groups.filter((g) => g.name.toLowerCase().includes(lowerSearch));
    }

    const groupCount = groups.length;

    //Update count text
    let countText = "";
    if (searchTerm === "") {
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

    if (countDiv) {
      countDiv.innerHTML = `<p class="text-center m-0">${countText}</p>`;
    }

    //Render groups
    renderGroups(groups, searchTerm);
  } catch (error) {
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }
    if (countDiv) {
      countDiv.innerHTML =
        '<p class="text-center m-0 text-danger">Failed to load groups.</p>';
    }
    groupsListDiv.innerHTML =
      '<p class="text-center text-danger">Error loading groups. Please try again.</p>';
    if (window.showToast) {
      window.showToast(error.message || "Failed to load groups", "error");
    }
  }
}

//Render groups
function renderGroups(groups, searchTerm) {
  const groupsListDiv = document.getElementById("groupsList");
  groupsListDiv.innerHTML = "";

  if (groups.length === 0) {
    if (searchTerm) {
      groupsListDiv.innerHTML = `
                <p class='text-center mt-4 text-muted'>No groups match your search.</p>
            `;
    }
    return;
  }

  groups.forEach((group) => {
    const groupGuid = group.guid;
    const groupName = escapeHtml(group.name);
    const memberCount = group.memberCount || 0;
    const isAdmin = group.isAdmin; //User is admin (can manage group)
    const isActive = group.isActive;

    //Group image
    const imgSrc = group.group_image_url;

    const rowStyle = isActive ? "" : "opacity: 0.6; background-color: #f8f9fa;";

    //Create group row
    const groupRow = document.createElement("div");
    groupRow.className = "group-row";
    groupRow.setAttribute("data-group-guid", groupGuid);
    groupRow.style.cssText = rowStyle;

    groupRow.innerHTML = `
            <img src="${imgSrc}" alt="Group image">

            <div class="group-info-wrapper">
                <div class="group-title"></div>
                <div class="group-members-count">${memberCount} members</div>
                ${!isActive ? '<span class="badge bg-secondary">Deactivated</span>' : ""}
            </div>

            <div class="group-actions">
                ${
                  isAdmin
                    ? `
                    <button class="app-btn app-btn-outline-secondary app-btn-fixed"
                            onclick="window.acknowledgeAndNavigate('${groupGuid}', ['group_deactivated', 'became_admin'], 'group_edit.php?guid=${groupGuid}')">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                `
                    : `
                    <button class="app-btn app-btn-outline-danger app-btn-fixed"
                            data-group-guid="${groupGuid}"
                            data-group-name="${groupName}"
                            onclick="window.leaveGroup(this.dataset.groupGuid, this.dataset.groupName)">
                        <i class="fa-solid fa-right-from-bracket"></i> Leave
                    </button>
                `
                }

                <button class="app-btn app-btn-primary app-btn-fixed"
                        onclick="window.acknowledgeAndNavigate('${groupGuid}', ['added_to_group', 'group_reactivated', 'group_deactivated', 'became_admin'], 'chatbox.php?guid=${groupGuid}&type=group')">
                   <i class="fa-solid fa-comments"></i>Chat
                </button>
            </div>
        `;

    //Set group name and tooltip using DOM methods (safer for special characters)
    const titleElement = groupRow.querySelector(".group-title");
    titleElement.textContent = group.name;
    titleElement.title = group.name;

    groupsListDiv.appendChild(groupRow);
  });
}

/* Acknowledge group notifications before navigating to chat or edit page.
This auto-dismisses notifications when user interacts with a group from the Groups page. */
function acknowledgeAndNavigate(groupGuid, notificationTypes, redirectUrl) {
  try {
    if (window.ApiUrls) {
      fetch(ApiUrls.groupsAcknowledgeGroupNotificationsByGroup(), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          group_guid: groupGuid,
          notification_types: notificationTypes,
        }),
        keepalive: true,
      }).catch(() => {});
    }
  } catch {}

  //Navigate immediately (keepalive ensures request completes despite page unload)
  window.location.href = redirectUrl;
}

//Make functions available globally
window.acknowledgeAndNavigate = acknowledgeAndNavigate;
window.loadGroups = loadGroups;

//Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  //Get current user GUID from window (set by PHP)
  if (window.CURRENT_USER_GUID) {
    currentUserGuid = window.CURRENT_USER_GUID;
  }

  //Initialize real-time updates manager
  if (window.groupsRealtimeManager) {
    window.groupsRealtimeManager.init(currentUserGuid);
  }

  //Get search term from URL
  const urlParams = new URLSearchParams(window.location.search);
  currentSearchTerm = urlParams.get("search") || "";

  //Handle Create Group button and Back link visibility based on search
  const createGroupForm = document.getElementById("createGroupForm");
  const backToGroupsLink = document.getElementById("backToGroupsLink");

  if (currentSearchTerm) {
    //Hide Create Group button when searching
    if (createGroupForm) {
      createGroupForm.style.display = "none";
    }
    //Show Back to Groups link when searching
    if (backToGroupsLink) {
      backToGroupsLink.classList.remove("d-none");
    }
  }

  //Load groups
  loadGroups(currentSearchTerm);
});
