/* GROUP EDIT PAGE

Handles group editing (renaming, image management, member management, and admin leave flow). */

let currentUserGuid = null;
let groupGuid = null;
let memberIds = new Set();
let groupData = null;

//Validation state for Add Members
let validationResults = {};
let validationInProgress = false;
let validationDebounceTimer = null;

//State for Admin Leave feature
let adminLeaveValidationResults = {};
let adminLeaveValidationInProgress = false;
let adminLeaveValidationDebounceTimer = null;
let selectedSuccessorGuid = null;

//Load and display group members
async function loadGroupMembers() {
  const membersListDiv = document.getElementById("current-members-list");
  const memberCountSpan = document.getElementById("memberCount");

  if (!membersListDiv || !groupGuid) return;

  try {
    membersListDiv.innerHTML =
      '<div class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading members...</div>';
    if (memberCountSpan) memberCountSpan.textContent = "Loading...";

    const response = await ApiClient.get(ApiUrls.groupsDetails(groupGuid));

    if (!response.success || !response.group || !response.members) {
      throw new Error(response.message || "Invalid response format");
    }

    //Update global state
    groupData = response.group;
    memberIds.clear();
    response.members.forEach((member) => memberIds.add(member.user_guid));

    //Update UI
    if (memberCountSpan) memberCountSpan.textContent = response.members.length;
    updateFormFields(response.group);
    renderMembers(response.members);
    loadFriendsForAdding();
  } catch (error) {
    membersListDiv.innerHTML = `<div class="text-center text-danger">Error: ${error.message || "Unknown error"}</div>`;
    if (memberCountSpan) memberCountSpan.textContent = "Error";
    FormUtilities.showToast(
      "Failed to load group members. Please refresh the page.",
      "error",
    );
  }
}

//Update form fields with group data
function updateFormFields(group) {
  //Update group name input
  const groupNameInput = document.querySelector('input[name="group_name"]');
  if (groupNameInput && group.group_name) {
    groupNameInput.value = group.group_name;
  }

  //Update image display with transition and error handling
  const currentImg = document.getElementById("currentGroupImage");
  const imageContainer = document.getElementById("currentGroupImageContainer");
  const deleteForm = document.getElementById("groupDeleteImageForm");

  if (currentImg && imageContainer) {
    const imageSrc = group.group_image_url;
    const hasCustomImage = imageSrc && imageSrc !== "img/groupdefault.png";

    //Load image and show it when ready
    const tempImg = new Image();
    tempImg.onload = function () {
      //Hide loading spinner and show actual image
      imageContainer.classList.add("d-none");
      currentImg.src = imageSrc;
      currentImg.classList.remove("d-none");
    };
    tempImg.onerror = function () {
      //If custom image fails to load, show default and hide delete button
      imageContainer.classList.add("d-none");
      currentImg.src = "img/groupdefault.png";
      currentImg.classList.remove("d-none");

      //Hide delete button since custom image doesn't exist
      if (deleteForm) {
        deleteForm.classList.add("d-none");
      }
    };
    tempImg.src = imageSrc;

    //Show/hide delete button based on whether custom image exists
    if (deleteForm) {
      if (hasCustomImage) {
        deleteForm.classList.remove("d-none");
      } else {
        deleteForm.classList.add("d-none");
      }
    }
  }

  //Setup delete button
  const deleteBtn = document.getElementById("deleteGroupBtn");
  if (deleteBtn && group.group_name) {
    deleteBtn.onclick = () => deleteGroup(groupGuid, group.group_name);
  }
}

//Render members list
function renderMembers(members) {
  const membersListDiv = document.getElementById("current-members-list");
  membersListDiv.innerHTML = "";

  //Used to decide whether to show the Leave button for admins
  const hasOtherMembers = members.length > 1;

  members.forEach((member) => {
    const userGuid = member.user_guid;
    const username = escapeHtml(member.user_username);
    const role = member.role;
    const isOwner = role === "owner" || role === "admin";
    const isCurrentUser = currentUserGuid && userGuid == currentUserGuid;
    const imgUrl = member.profile_image_url || "img/profiledefault.jpg";

    //Determine which button to show
    let actionButton = "";
    if (isCurrentUser && isOwner && hasOtherMembers) {
      //Admin with other members (show Leave button)
      actionButton = `<button type="button" class="app-btn app-btn-outline-danger app-btn-fixed" onclick="openAdminLeaveModal()"><i class="fa-solid fa-right-from-bracket"></i> Leave</button>`;
    } else if (!isOwner && !isCurrentUser) {
      //Non-admin, non-current user (show Remove button)
      actionButton = `<button type="button" class="app-btn app-btn-outline-danger app-btn-fixed" onclick="removeMemberConfirm('${userGuid}', '${escapeHtml(username)}')"><i class="fa-solid fa-user-minus"></i> Remove</button>`;
    }

    const memberRow = document.createElement("div");
    memberRow.className = "member-row";
    memberRow.innerHTML = `
            <div class="d-flex align-items-center gap-3">
                <img src="${imgUrl}" class="member-img" alt="">
                <span class="member-username" title="${username}">${username}${role === "admin" ? '<span class="badge bg-primary ms-2">Admin</span>' : ""}${isCurrentUser ? ' <small class="text-muted">(You)</small>' : ""}</span>
            </div>
            ${actionButton}
        `;
    membersListDiv.appendChild(memberRow);
  });
}

//Load and display available friends
async function loadFriendsForAdding() {
  const friendsListDiv = document.getElementById("available-friends-list");
  if (!friendsListDiv) return;

  try {
    friendsListDiv.innerHTML =
      '<div class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading friends...</div>';

    const response = await ApiClient.get(ApiUrls.friendsGet());

    if (!response.success || !response.friends) {
      throw new Error(response.message || "Invalid response format");
    }

    const availableFriends = response.friends.filter(
      (friend) => !memberIds.has(friend.friend_guid),
    );

    if (availableFriends.length === 0) {
      friendsListDiv.innerHTML =
        '<div class="text-muted text-center m-2">All your friends are already members.</div>';
      return;
    }

    friendsListDiv.innerHTML = "";

    //Clear validation state when reloading friends
    validationResults = {};
    updateValidationSummaryEdit(null);

    availableFriends.forEach((friend) => {
      const friendRow = document.createElement("label");
      friendRow.className = "member-row add-member-row";
      friendRow.setAttribute("data-name", friend.username.toLowerCase());
      friendRow.setAttribute("data-user-guid", friend.friend_guid);

      const imgUrl = friend.profile_image_guid
        ? `download.php?type=profile&guid=${friend.profile_image_guid}`
        : "img/profiledefault.jpg";

      friendRow.innerHTML = `
                <div class="d-flex align-items-center gap-3">
                    <img src="${imgUrl}" class="member-img" alt="">
                    <span class="member-username" title="${escapeHtml(friend.username)}">${escapeHtml(friend.username)}</span>
                    <span class="validation-status" id="validation-status-${friend.friend_guid}"></span>
                </div>
                <input type="checkbox" name="new_member_guids[]" value="${friend.friend_guid}" class="member-checkbox">
            `;
      friendsListDiv.appendChild(friendRow);
    });
  } catch (error) {
    friendsListDiv.innerHTML = `<div class="text-center text-danger">Error: ${error.message || "Unknown error"}</div>`;
    FormUtilities.showToast(
      "Failed to load friends list. Please refresh the page.",
      "error",
    );
  }
}

//Member management functions
function removeMemberConfirm(userGuid, username) {
  if (confirm(`Are you sure you want to remove ${username} from this group?`)) {
    removeMember(userGuid, username);
  }
}

async function removeMember(userGuid, username) {
  try {
    const button = document.querySelector(`button[onclick*="${userGuid}"]`);
    if (button) {
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }

    const response = await ApiClient.post(ApiUrls.groupsRemoveMember(), {
      group_guid: groupGuid,
      user_guid: userGuid,
    });

    if (!response.success) {
      throw new Error(response.message || "Failed to remove member");
    }

    FormUtilities.showToast(
      `${username} has been removed from the group`,
      "success",
    );
    await loadGroupMembers();
  } catch (error) {
    FormUtilities.showToast(
      `Failed to remove member: ${error.message || "Unknown error"}`,
      "error",
    );

    const button = document.querySelector(`button[onclick*="${userGuid}"]`);
    if (button) {
      button.disabled = false;
      button.innerHTML = "Remove";
    }
  }
}

async function deleteGroup(groupGuid, groupName) {
  if (
    !confirm(
      `Are you sure you want to delete the group "${groupName}"? This action cannot be undone.`,
    )
  ) {
    return;
  }

  try {
    const deleteButton = document.querySelector(
      'button[onclick*="deleteGroup"]',
    );
    if (deleteButton) {
      deleteButton.disabled = true;
      deleteButton.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    }

    const response = await ApiClient.post(ApiUrls.groupsDelete(), {
      group_guid: groupGuid,
    });

    if (!response.success) {
      throw new Error(response.message || "Failed to delete group");
    }

    FormUtilities.showToast("Group deleted successfully", "success");
    setTimeout(() => (window.location.href = "groups.php"), 1000);
  } catch (error) {
    FormUtilities.showToast(
      `Failed to delete group: ${error.message || "Unknown error"}`,
      "error",
    );

    const deleteButton = document.querySelector(
      'button[onclick*="deleteGroup"]',
    );
    if (deleteButton) {
      deleteButton.disabled = false;
      deleteButton.innerHTML = '<i class="fas fa-trash"></i> Delete Group';
    }
  }
}

//Setup friend search and selection functionality
function setupFriendSearch() {
  const searchInput = document.getElementById("searchFriendsInput");
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      const searchTerm = this.value.toLowerCase();
      document
        .querySelectorAll("#available-friends-list .add-member-row")
        .forEach((row) => {
          const name = row.getAttribute("data-name") || "";
          row.style.display = name.includes(searchTerm) ? "" : "none";
        });
    });
  }

  //Update selected count when checkboxes change and trigger validation
  document.addEventListener("change", function (e) {
    if (e.target.name === "new_member_guids[]") {
      const count = document.querySelectorAll(
        'input[name="new_member_guids[]"]:checked',
      ).length;
      const selectedCountSpan = document.getElementById("selectedCount");

      if (selectedCountSpan) selectedCountSpan.textContent = count;

      //If unchecked, clear that member's validation status
      if (!e.target.checked) {
        const userGuid = e.target.value;
        clearValidationStatusEdit(userGuid);
      }

      //Trigger real-time validation
      triggerValidationEdit();

      //Update button state
      updateAddMembersButtonState();
    }
  });
}

//Validate selected members for adding to group
async function validateSelectedMembersEdit() {
  const selectedCheckboxes = document.querySelectorAll(
    'input[name="new_member_guids[]"]:checked',
  );
  const memberGuids = Array.from(selectedCheckboxes).map((cb) => cb.value);

  //Clear validation if no members selected
  if (memberGuids.length === 0) {
    clearAllValidationStatusEdit();
    updateValidationSummaryEdit(null);
    return;
  }

  //Show pending status for all selected members
  memberGuids.forEach((guid) => {
    setValidationStatusEdit(guid, "pending", "Validating...");
  });

  validationInProgress = true;
  updateAddMembersButtonState();

  try {
    const response = await ApiClient.post(
      ApiUrls.friendsValidateMembersForGroup(),
      {
        member_guids: memberGuids,
      },
    );

    if (response.success && response.validation_results) {
      //Process validation results
      response.validation_results.forEach((result) => {
        validationResults[result.user_guid] = result;

        if (result.is_valid) {
          setValidationStatusEdit(result.user_guid, "valid", "Valid");
        } else {
          let errorMessage = result.error || "Invalid";
          if (result.is_banned) {
            errorMessage = "User is banned";
          } else if (!result.is_friend) {
            errorMessage = "Not a friend";
          }
          setValidationStatusEdit(result.user_guid, "invalid", errorMessage);
        }
      });

      //Update validation summary
      updateValidationSummaryEdit(response);
    }
  } catch (error) {
    memberGuids.forEach((guid) => {
      setValidationStatusEdit(guid, "pending", "Could not validate");
    });
    updateValidationSummaryEdit({
      all_valid: false,
      valid_count: 0,
      invalid_count: 0,
      error: "Could not validate members. Please try again.",
    });
  } finally {
    validationInProgress = false;
    updateAddMembersButtonState();
  }
}

//Set validation status for a specific member
function setValidationStatusEdit(userGuid, status, message) {
  const statusElement = document.getElementById(
    `validation-status-${userGuid}`,
  );
  const memberRow = document.querySelector(`[data-user-guid="${userGuid}"]`);

  if (statusElement) {
    statusElement.className = "validation-status";
    statusElement.classList.add(status);

    let icon = "";
    switch (status) {
      case "valid":
        icon = '<i class="fas fa-check-circle validation-icon"></i>';
        break;
      case "invalid":
        icon = '<i class="fas fa-times-circle validation-icon"></i>';
        break;
      case "pending":
        icon = '<span class="validation-spinner"></span>';
        break;
    }
    statusElement.innerHTML = `${icon} <span>${message}</span>`;
  }

  if (memberRow) {
    memberRow.classList.remove(
      "validation-valid",
      "validation-invalid",
      "validation-pending",
    );
    memberRow.classList.add(`validation-${status}`);
  }
}

//Clear validation status for a specific member
function clearValidationStatusEdit(userGuid) {
  const statusElement = document.getElementById(
    `validation-status-${userGuid}`,
  );
  const memberRow = document.querySelector(`[data-user-guid="${userGuid}"]`);

  if (statusElement) {
    statusElement.className = "validation-status";
    statusElement.innerHTML = "";
  }

  if (memberRow) {
    memberRow.classList.remove(
      "validation-valid",
      "validation-invalid",
      "validation-pending",
    );
  }

  delete validationResults[userGuid];
}

//Clear all validation statuses
function clearAllValidationStatusEdit() {
  const statusElements = document.querySelectorAll(
    "#available-friends-list .validation-status",
  );
  statusElements.forEach((el) => {
    el.className = "validation-status";
    el.innerHTML = "";
  });

  const memberRows = document.querySelectorAll(
    "#available-friends-list .member-row",
  );
  memberRows.forEach((row) => {
    row.classList.remove(
      "validation-valid",
      "validation-invalid",
      "validation-pending",
    );
  });

  validationResults = {};
}

//Update the validation summary banner for Edit Group
function updateValidationSummaryEdit(response) {
  let summaryElement = document.getElementById("validationSummaryEdit");

  //Create summary element if it doesn't exist
  if (!summaryElement) {
    summaryElement = document.createElement("div");
    summaryElement.id = "validationSummaryEdit";
    summaryElement.className = "validation-summary";

    const friendsBox = document.getElementById("available-friends-list");
    if (friendsBox && friendsBox.parentNode) {
      friendsBox.parentNode.insertBefore(summaryElement, friendsBox);
    }
  }

  if (!response) {
    summaryElement.classList.remove("show", "success", "error", "warning");
    summaryElement.innerHTML = "";
    return;
  }

  summaryElement.classList.remove("success", "error", "warning");

  if (response.error) {
    summaryElement.classList.add("show", "warning");
    summaryElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${response.error}`;
  } else if (response.all_valid) {
    summaryElement.classList.add("show", "success");
    const validMemberWord =
      response.valid_count === 1 ? "member is" : "members are";
    summaryElement.innerHTML = `<i class="fas fa-check-circle"></i> All ${response.valid_count} selected ${validMemberWord} valid`;
  } else if (response.invalid_count > 0) {
    summaryElement.classList.add("show", "error");
    const memberWord = response.invalid_count === 1 ? "member" : "members";
    const pronounWord = response.invalid_count === 1 ? "this member" : "them";
    summaryElement.innerHTML = `<i class="fas fa-times-circle"></i> ${response.invalid_count} ${memberWord} cannot be added to the group. Please deselect ${pronounWord} to continue.`;
  }
}

//Update Add Members button state
function updateAddMembersButtonState() {
  const addMembersBtn = document.getElementById("addMembersBtn");
  if (!addMembersBtn) return;

  const selectedCount = document.querySelectorAll(
    'input[name="new_member_guids[]"]:checked',
  ).length;
  const hasInvalidMembers = Object.values(validationResults).some(
    (r) => !r.is_valid,
  );

  if (validationInProgress) {
    addMembersBtn.disabled = true;
    addMembersBtn.classList.add("btn-disabled");
    addMembersBtn.innerHTML =
      '<span class="validation-spinner"></span> Validating...';
  } else if (hasInvalidMembers) {
    addMembersBtn.disabled = true;
    addMembersBtn.classList.add("btn-disabled");
    addMembersBtn.innerHTML =
      '<i class="fas fa-user-plus"></i> Add Selected Members';
  } else if (selectedCount === 0) {
    addMembersBtn.disabled = true;
    addMembersBtn.classList.remove("btn-disabled");
    addMembersBtn.innerHTML =
      '<i class="fas fa-user-plus"></i> Add Selected Members';
  } else {
    addMembersBtn.disabled = false;
    addMembersBtn.classList.remove("btn-disabled");
    addMembersBtn.innerHTML =
      '<i class="fas fa-user-plus"></i> Add Selected Members';
  }
}

//Debounced validation trigger
function triggerValidationEdit() {
  if (validationDebounceTimer) {
    clearTimeout(validationDebounceTimer);
  }

  validationDebounceTimer = setTimeout(() => {
    validateSelectedMembersEdit();
  }, 300);
}

//Setup standard FormHandler patterns
function setupStandardFormHandlers() {
  //Group Name Update Form
  const nameForm = document.querySelector('form[action*="update_name"]');
  if (nameForm) {
    new FormHandler(
      nameForm,
      FormUtilities.createStandardConfig({
        apiEndpoint: ApiUrls.groupsUpdateName(groupGuid),
        requiredFields: ["group_name"],
        fieldLabels: { group_name: "Group name" },
        customValidation: (formData) => {
          const data =
            formData instanceof FormData
              ? FormUtilities.formDataToObject(formData)
              : formData;
          const name = data.group_name?.trim();

          if (!name || name.length < 3 || name.length > 50) {
            FormUtilities.showToast(
              "Group name must be between 3 and 50 characters",
              "error",
            );
            return false;
          }

          return true;
        },
        customSuccess: (response) => {
          FormUtilities.showToast(
            response.message || "Group name updated successfully",
            "success",
          );
          if (groupData && response.group_name) {
            groupData.group_name = response.group_name;
          }
        },
      }),
    );
  }

  //Group Image Upload Form
  const imageForm = document.getElementById("groupImageForm");
  if (imageForm) {
    new FormHandler(
      imageForm,
      FormUtilities.createStandardConfig({
        apiEndpoint: ApiUrls.groupsUpdateImage(),
        isFileUpload: true,
        customValidation: () => {
          const fileInput = imageForm.querySelector('input[type="file"]');
          if (!fileInput?.files?.length) {
            FormUtilities.showToast("Please select an image file", "error");
            return false;
          }

          if (
            !FormUtilities.validateImageFile(fileInput.files[0], {
              allowedTypes: [
                "image/jpeg",
                "image/jpg",
                "image/png",
                "image/gif",
              ],
            })
          ) {
            return false;
          }

          return true;
        },
        customSuccess: (response) => {
          FormUtilities.showToast(
            "Group image updated successfully",
            "success",
          );

          if (response.image_url) {
            FormUtilities.updateElement(
              "#currentGroupImage",
              response.image_url,
              "src",
            );
            document
              .getElementById("groupDeleteImageForm")
              .classList.remove("d-none");
          }

          const fileInput = imageForm.querySelector('input[type="file"]');
          if (fileInput) fileInput.value = "";
        },
      }),
    );
  }

  //Group Image Delete Form
  const deleteImageForm = document.getElementById("groupDeleteImageForm");
  if (deleteImageForm) {
    new FormHandler(
      deleteImageForm,
      FormUtilities.createStandardConfig({
        apiEndpoint: ApiUrls.groupsDeleteImage(),
        method: "DELETE",
        customValidation: () => {
          if (!confirm("Are you sure you want to delete the group image?")) {
            return false;
          }
          return true;
        },
        customSuccess: () => {
          FormUtilities.showToast(
            "Group image deleted successfully",
            "success",
          );
          FormUtilities.updateElement(
            "#currentGroupImage",
            "img/groupdefault.png?t=" + Date.now(),
            "src",
          );
          deleteImageForm.classList.add("d-none");
        },
      }),
    );
  }

  //Add Members Form
  const addMembersForm = document.querySelector('form[action*="add_members"]');
  if (addMembersForm) {
    new FormHandler(
      addMembersForm,
      FormUtilities.createStandardConfig({
        apiEndpoint: ApiUrls.groupsAddMembers(),
        customValidation: (formData) => {
          //Check if validation is still in progress
          if (validationInProgress) {
            FormUtilities.showToast(
              "Please wait for validation to complete",
              "warning",
            );
            return false;
          }

          //Check for invalid members
          const hasInvalidMembers = Object.values(validationResults).some(
            (r) => !r.is_valid,
          );
          if (hasInvalidMembers) {
            FormUtilities.showToast(
              "Please remove invalid members before adding",
              "error",
            );
            return false;
          }

          const checkboxes = addMembersForm.querySelectorAll(
            'input[name="new_member_guids[]"]:checked',
          );

          if (checkboxes.length === 0) {
            FormUtilities.showToast(
              "Please select at least one friend to add",
              "error",
            );
            return false;
          }

          if (!groupGuid) {
            FormUtilities.showToast("Invalid group GUID", "error");
            return false;
          }

          const memberGuids = Array.from(checkboxes).map((cb) => cb.value); //Keep as strings for GUIDs

          if (formData instanceof FormData) {
            //Add group_guid
            formData.append("group_guid", groupGuid);
            //Add member_guids as array (FormData handles arrays automatically)
            memberGuids.forEach((guid) => {
              formData.append("member_guids[]", guid);
            });
          } else {
            formData.group_guid = groupGuid;
            formData.member_guids = memberGuids;
          }
          return true;
        },
        customSuccess: async (response) => {
          const addedCount = response.added_count || 1;
          FormUtilities.showToast(
            `Successfully added ${addedCount} member(s) to the group`,
            "success",
          );

          //Clear validation state
          validationResults = {};
          updateValidationSummaryEdit(null);

          await loadGroupMembers();
        },
      }),
    );
  }
}

//---------------------------------------------------------------------------
// ADMIN LEAVE MODAL FUNCTION
//---------------------------------------------------------------------------

//Open the Admin Leave Modal
function openAdminLeaveModal() {
  //Reset state
  adminLeaveValidationResults = {};
  selectedSuccessorGuid = null;

  //Create modal if it doesn't exist
  let modal = document.getElementById("adminLeaveModal");
  if (!modal) {
    createAdminLeaveModal();
    modal = document.getElementById("adminLeaveModal");
  }

  //Update group name in modal
  const groupNameElement = document.getElementById("adminLeaveGroupName");
  if (groupNameElement && groupData) {
    groupNameElement.textContent = groupData.group_name;
    groupNameElement.title = groupData.group_name;
  }

  //Reset validation summary
  updateAdminLeaveSummary(null);

  //Reset button state
  updateAdminLeaveButtonState();

  //Populate members list (excluding current admin)
  populateSuccessorList();

  //Show modal
  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();
}

//Create the Admin Leave Modal HTML
function createAdminLeaveModal() {
  const groupName = groupData ? groupData.group_name : "Group";

  const modalHtml = `
    <div class="modal fade" id="adminLeaveModal" tabindex="-1" aria-labelledby="adminLeaveModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="adminLeaveModalLabel">Edit Group</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="admin-leave-group-label mb-1">Group</p>
            <p class="admin-leave-group-name mb-3 text-truncate" id="adminLeaveGroupName" title="${escapeHtml(groupName)}">${escapeHtml(groupName)}</p>

            <h6 class="mb-2">Select New Admin</h6>
            <hr class="mt-0 mb-3">
            <p class="text-muted mb-3">Choose a member to become the new admin before you leave.</p>

            <input type="search" id="searchSuccessorInput" class="form-control mb-3" placeholder="Search members...">

            <div id="adminLeaveValidationSummary" class="validation-summary"></div>

            <div class="members-box members-box-limited" id="successorMembersList">
              <p class="text-muted text-center m-2">Loading members...</p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="app-btn app-btn-outline-secondary" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i>Cancel</button>
            <button type="button" class="app-btn app-btn-danger" id="adminLeaveBtn" disabled onclick="confirmAdminLeave()">
              <i class="fa-solid fa-right-from-bracket"></i> Leave Group
            </button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML("beforeend", modalHtml);

  //Setup search functionality
  document
    .getElementById("searchSuccessorInput")
    .addEventListener("input", function () {
      const searchTerm = this.value.toLowerCase();
      document
        .querySelectorAll("#successorMembersList .member-row")
        .forEach((row) => {
          const name = row.getAttribute("data-name") || "";
          row.style.display = name.includes(searchTerm) ? "" : "none";
        });
    });
}

//Populate the successor selection list
function populateSuccessorList() {
  const listDiv = document.getElementById("successorMembersList");
  if (!listDiv || !groupGuid) return;

  listDiv.innerHTML =
    '<p class="text-muted text-center m-2"><i class="fa fa-spinner fa-spin"></i> Loading members...</p>';

  //Fetch fresh member data
  ApiClient.get(ApiUrls.groupsDetails(groupGuid))
    .then((response) => {
      if (!response.success || !response.members) {
        listDiv.innerHTML =
          '<p class="text-danger text-center">Failed to load members</p>';
        return;
      }

      const eligibleMembers = response.members.filter(
        (m) => m.user_guid !== currentUserGuid,
      );

      if (eligibleMembers.length === 0) {
        listDiv.innerHTML =
          '<p class="text-muted text-center">No other members in this group</p>';
        return;
      }

      listDiv.innerHTML = "";

      eligibleMembers.forEach((member) => {
        const memberRow = document.createElement("label");
        memberRow.className = "member-row add-member-row";
        memberRow.setAttribute("data-name", member.user_username.toLowerCase());
        memberRow.setAttribute("data-user-guid", member.user_guid);

        const imgUrl = member.profile_image_url || "img/profiledefault.jpg";
        const userGuid = member.user_guid;

        memberRow.innerHTML = `
          <div class="d-flex align-items-center gap-3">
            <img src="${imgUrl}" class="member-img" alt="">
            <span class="member-username" title="${escapeHtml(member.user_username)}">
              ${escapeHtml(member.user_username)}
              ${member.role === "admin" ? '<span class="badge bg-primary ms-2">Admin</span>' : ""}
            </span>
            <span class="validation-status" id="successor-validation-${userGuid}"></span>
          </div>
          <input type="radio" name="successor_guid" value="${userGuid}" class="successor-radio successor-radio-btn">
        `;

        listDiv.appendChild(memberRow);
      });

      //Add change listener for radio buttons
      document.querySelectorAll(".successor-radio").forEach((radio) => {
        radio.addEventListener("change", handleSuccessorSelection);
      });

      //Make rows clickable to select radio
      document
        .querySelectorAll("#successorMembersList .member-row")
        .forEach((row) => {
          row.style.cursor = "pointer";
          row.addEventListener("click", function (e) {
            if (e.target.type !== "radio") {
              const radio = this.querySelector('input[type="radio"]');
              if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event("change"));
              }
            }
          });
        });
    })
    .catch((error) => {
      listDiv.innerHTML =
        '<p class="text-danger text-center">Error loading members</p>';
    });
}

//Clear validation status for a specific successor
function clearSuccessorValidationStatus(userGuid) {
  const statusElement = document.getElementById(
    `successor-validation-${userGuid}`,
  );
  const memberRow = document
    .querySelector(`input[value="${userGuid}"]`)
    ?.closest(".member-row");

  if (statusElement) {
    statusElement.className = "validation-status";
    statusElement.innerHTML = "";
  }

  if (memberRow) {
    memberRow.classList.remove(
      "validation-valid",
      "validation-invalid",
      "validation-pending",
    );
  }

  delete adminLeaveValidationResults[userGuid];
}

//Handle successor selection change
function handleSuccessorSelection(event) {
  const previousSuccessorGuid = selectedSuccessorGuid; //Store previous
  selectedSuccessorGuid = event.target.value;

  //Clear previous validation data
  adminLeaveValidationResults = {};

  //Clear previous user's validation status UI
  if (previousSuccessorGuid) {
    clearSuccessorValidationStatus(previousSuccessorGuid);
  }

  //Update row styling
  document
    .querySelectorAll("#successorMembersList .member-row")
    .forEach((row) => {
      row.classList.remove("selected");
    });
  event.target.closest(".member-row").classList.add("selected");

  //Trigger validation for new selection
  triggerSuccessorValidation();
}

//Trigger validation for selected successor
function triggerSuccessorValidation() {
  if (adminLeaveValidationDebounceTimer) {
    clearTimeout(adminLeaveValidationDebounceTimer);
  }

  adminLeaveValidationDebounceTimer = setTimeout(() => {
    validateSelectedSuccessor();
  }, 300);
}

//Validate the selected successor
async function validateSelectedSuccessor() {
  if (!selectedSuccessorGuid) {
    updateAdminLeaveButtonState();
    return;
  }

  //Show pending status
  setSuccessorValidationStatus(
    selectedSuccessorGuid,
    "pending",
    "Validating...",
  );
  adminLeaveValidationInProgress = true;
  updateAdminLeaveButtonState();

  try {
    const response = await ApiClient.post(
      ApiUrls.friendsValidateMembersForGroup(),
      {
        member_guids: [selectedSuccessorGuid],
      },
    );

    if (
      response.success &&
      response.validation_results &&
      response.validation_results.length > 0
    ) {
      const result = response.validation_results[0];
      adminLeaveValidationResults[selectedSuccessorGuid] = result;

      if (result.is_banned) {
        setSuccessorValidationStatus(
          selectedSuccessorGuid,
          "invalid",
          "User is banned",
        );
        updateAdminLeaveSummary({
          all_valid: false,
          invalid_count: 1,
          error: "Selected user is banned and cannot become admin.",
        });
      } else if (result.user_exists === false) {
        setSuccessorValidationStatus(
          selectedSuccessorGuid,
          "invalid",
          "User not found",
        );
        updateAdminLeaveSummary({
          all_valid: false,
          invalid_count: 1,
          error: "Selected user no longer exists.",
        });
      } else {
        //Valid (member exists and is not banned)
        setSuccessorValidationStatus(selectedSuccessorGuid, "valid", "Valid");
        updateAdminLeaveSummary({ all_valid: true, valid_count: 1 });
      }
    }
  } catch (error) {
    setSuccessorValidationStatus(
      selectedSuccessorGuid,
      "pending",
      "Could not validate",
    );
    updateAdminLeaveSummary({
      error: "Could not validate member. Please try again.",
    });
  } finally {
    adminLeaveValidationInProgress = false;
    updateAdminLeaveButtonState();
  }
}

//Set validation status for successor
function setSuccessorValidationStatus(userGuid, status, message) {
  const statusElement = document.getElementById(
    `successor-validation-${userGuid}`,
  );
  const memberRow = document
    .querySelector(`input[value="${userGuid}"]`)
    ?.closest(".member-row");

  if (statusElement) {
    statusElement.className = "validation-status " + status;
    let icon = "";
    switch (status) {
      case "valid":
        icon =
          '<i class="fas fa-check-circle validation-icon text-success"></i>';
        break;
      case "invalid":
        icon =
          '<i class="fas fa-times-circle validation-icon text-danger"></i>';
        break;
      case "pending":
        icon = '<span class="validation-spinner"></span>';
        break;
    }
    statusElement.innerHTML = `${icon} <span class="ms-1">${message}</span>`;
  }

  if (memberRow) {
    memberRow.classList.remove(
      "validation-valid",
      "validation-invalid",
      "validation-pending",
    );
    memberRow.classList.add(`validation-${status}`);
  }
}

//Update admin leave validation summary
function updateAdminLeaveSummary(response) {
  let summaryElement = document.getElementById("adminLeaveValidationSummary");
  if (!summaryElement) return;

  summaryElement.classList.remove("show", "success", "error", "warning");

  if (!response) {
    summaryElement.innerHTML = "";
    return;
  }

  if (response.error) {
    summaryElement.classList.add("show", "warning");
    summaryElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${response.error}`;
  } else if (response.all_valid) {
    summaryElement.classList.add("show", "success");
    summaryElement.innerHTML = `<i class="fas fa-check-circle"></i> Selected member can become admin`;
  } else if (response.invalid_count > 0) {
    summaryElement.classList.add("show", "error");
    summaryElement.innerHTML = `<i class="fas fa-times-circle"></i> ${response.error || "Selected member cannot become admin."}`;
  }
}

//Update admin leave button state
function updateAdminLeaveButtonState() {
  const btn = document.getElementById("adminLeaveBtn");
  if (!btn) return;

  const hasInvalidSelection = Object.values(adminLeaveValidationResults).some(
    (r) => r.is_banned || r.user_exists === false,
  );
  const hasValidSelection =
    selectedSuccessorGuid &&
    adminLeaveValidationResults[selectedSuccessorGuid] &&
    !adminLeaveValidationResults[selectedSuccessorGuid].is_banned &&
    adminLeaveValidationResults[selectedSuccessorGuid].user_exists !== false;

  if (adminLeaveValidationInProgress) {
    btn.disabled = true;
    btn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1"></span> Validating...';
  } else if (!selectedSuccessorGuid) {
    btn.disabled = true;
    btn.innerHTML =
      '<i class="fa-solid fa-right-from-bracket"></i> Leave Group';
  } else if (hasInvalidSelection) {
    btn.disabled = true;
    btn.innerHTML =
      '<i class="fa-solid fa-right-from-bracket"></i> Leave Group';
  } else if (hasValidSelection) {
    btn.disabled = false;
    btn.innerHTML =
      '<i class="fa-solid fa-right-from-bracket"></i> Leave Group';
  } else {
    btn.disabled = true;
    btn.innerHTML =
      '<i class="fa-solid fa-right-from-bracket"></i> Leave Group';
  }
}

//Confirm admin leave with selected successor
function confirmAdminLeave() {
  if (!selectedSuccessorGuid) return;

  //Get successor username for confirmation
  const radio = document.querySelector(
    `input[value="${selectedSuccessorGuid}"]`,
  );
  const memberRow = radio?.closest(".member-row");
  const usernameSpan = memberRow?.querySelector(".member-username");
  const username = usernameSpan?.textContent?.trim() || "the selected member";

  //Show confirmation
  if (
    confirm(
      `Are you sure you want to leave the group and make "${username}" the new admin?`,
    )
  ) {
    executeAdminLeave();
  }
}

//Execute admin leave API call
async function executeAdminLeave() {
  const btn = document.getElementById("adminLeaveBtn");
  if (btn) {
    btn.disabled = true;
    btn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1"></span> Leaving...';
  }

  try {
    const response = await ApiClient.post(ApiUrls.groupsAdminLeave(), {
      group_guid: groupGuid,
      successor_guid: selectedSuccessorGuid,
    });

    if (response.success) {
      FormUtilities.showToast(
        response.message || "You have left the group successfully",
        "success",
      );

      //Close modal
      const modal = bootstrap.Modal.getInstance(
        document.getElementById("adminLeaveModal"),
      );
      if (modal) modal.hide();

      //Redirect to groups page
      setTimeout(() => {
        window.location.href = "groups.php";
      }, 1500);
    } else {
      throw new Error(response.message || "Failed to leave group");
    }
  } catch (error) {
    FormUtilities.showToast(
      `Failed to leave group: ${error.message || "Unknown error"}`,
      "error",
    );

    //Reset button
    if (btn) {
      btn.disabled = false;
      btn.innerHTML =
        '<i class="fa-solid fa-right-from-bracket"></i> Leave Group';
    }
  }
}

//---------------------------------------------------------------------------
// End Admin Leave Modal Functions
//---------------------------------------------------------------------------

//Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  //Get current user GUID
  if (window.CURRENT_USER_GUID) currentUserGuid = window.CURRENT_USER_GUID;

  //Get group GUID from URL
  const urlParams = new URLSearchParams(window.location.search);
  const urlGroupGuid = urlParams.get("guid");

  if (urlGroupGuid) {
    groupGuid = urlGroupGuid;
  } else if (window.GROUP_GUID) {
    //Fallback to PHP-provided global if URL param is missing
    groupGuid = window.GROUP_GUID;
  }

  //Validate we have a valid group GUID
  if (!groupGuid) {
    FormUtilities.showToast(
      "Invalid group GUID. Redirecting to groups page.",
      "error",
    );
    setTimeout(() => (window.location.href = "groups.php"), 2000);
    return;
  }

  //Setup functionality
  setupFriendSearch();
  setupStandardFormHandlers();

  //Load data via API
  loadGroupMembers();

  //Make functions globally available
  window.removeMemberConfirm = removeMemberConfirm;
  window.deleteGroup = deleteGroup;
  window.openAdminLeaveModal = openAdminLeaveModal;
  window.confirmAdminLeave = confirmAdminLeave;
});
