/* GROUP CREATE PAGE

Handles friend list loading for member selection with real-time validation. */

let validationResults = {};
let validationInProgress = false;
let validationDebounceTimer = null;

//Load friends for member selection
async function loadFriendsForSelection() {
  const friendsListDiv = document.getElementById("friends-list");

  if (!friendsListDiv) return;

  try {
    //Show loading
    friendsListDiv.innerHTML =
      '<p class="text-center text-muted">Loading friends...</p>';

    //Fetch friends from API
    const response = await ApiClient.get(ApiUrls.friendsGet());

    if (!response.friends) {
      throw new Error("Invalid response format");
    }

    const friends = response.friends;

    if (friends.length === 0) {
      friendsListDiv.innerHTML =
        '<p class="text-muted text-center m-2">You have no friends to add.</p>';
      return;
    }

    //Render friend checkboxes
    renderFriendCheckboxes(friends);
  } catch (error) {
    friendsListDiv.innerHTML =
      '<p class="text-center text-danger">Error loading friends.</p>';
  }
}

//Render friend checkboxes
function renderFriendCheckboxes(friends) {
  const friendsListDiv = document.getElementById("friends-list");
  friendsListDiv.innerHTML = "";

  friends.forEach((friend) => {
    const friendId = friend.friend_guid;
    const username = escapeHtml(friend.username);
    const lowerName = username.toLowerCase();

    //Use profile image URL from API
    const imgUrl = friend.profile_image_url || "img/profiledefault.jpg";

    //Create friend checkbox item
    const friendItem = document.createElement("label");
    friendItem.className = "member-row add-member-row";
    friendItem.setAttribute("data-name", lowerName);
    friendItem.setAttribute("data-user-guid", friendId);

    friendItem.innerHTML = `
            <div class="d-flex align-items-center gap-3">
                <img src="${imgUrl}" class="member-img" alt="">
                <span class="member-username" title="${username}">${username}</span>
                <span class="validation-status" id="validation-status-${friendId}"></span>
            </div>
            <input type="checkbox" name="member_ids[]" value="${friendId}" class="member-checkbox">
        `;

    friendsListDiv.appendChild(friendItem);
  });
}

/* Validate selected members for group creation.
Checks friendship status and ban status in real-time. */
async function validateSelectedMembers() {
  const selectedCheckboxes = document.querySelectorAll(
    'input[name="member_ids[]"]:checked',
  );
  const memberGuids = Array.from(selectedCheckboxes).map((cb) => cb.value);

  //Clear validation if no members selected
  if (memberGuids.length === 0) {
    clearAllValidationStatus();
    updateValidationSummary(null);
    return;
  }

  //Show pending status for all selected members
  memberGuids.forEach((guid) => {
    setValidationStatus(guid, "pending", "Validating...");
  });

  validationInProgress = true;
  updateSubmitButtonState();

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
          setValidationStatus(result.user_guid, "valid", "Valid");
        } else {
          let errorMessage = result.error || "Invalid";
          if (result.is_banned) {
            errorMessage = "User is banned";
          } else if (!result.is_friend) {
            errorMessage = "Not a friend";
          }
          setValidationStatus(result.user_guid, "invalid", errorMessage);
        }
      });

      //Update validation summary
      updateValidationSummary(response);
    }
  } catch (error) {
    //On error, show warning but don't block
    memberGuids.forEach((guid) => {
      setValidationStatus(guid, "pending", "Could not validate");
    });
    updateValidationSummary({
      all_valid: false,
      valid_count: 0,
      invalid_count: 0,
      error: "Could not validate members. Please try again.",
    });
  } finally {
    validationInProgress = false;
    updateSubmitButtonState();
  }
}

//Set validation status for a specific member
function setValidationStatus(userGuid, status, message) {
  const statusElement = document.getElementById(
    `validation-status-${userGuid}`,
  );
  const memberRow = document.querySelector(`[data-user-guid="${userGuid}"]`);

  if (statusElement) {
    //Clear previous status classes
    statusElement.className = "validation-status";
    statusElement.classList.add(status);

    //Set icon and message
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
    //Clear previous validation classes
    memberRow.classList.remove(
      "validation-valid",
      "validation-invalid",
      "validation-pending",
    );
    memberRow.classList.add(`validation-${status}`);
  }
}

//Clear validation status for a specific member
function clearValidationStatus(userGuid) {
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
function clearAllValidationStatus() {
  const statusElements = document.querySelectorAll(".validation-status");
  statusElements.forEach((el) => {
    el.className = "validation-status";
    el.innerHTML = "";
  });

  const memberRows = document.querySelectorAll(".member-row");
  memberRows.forEach((row) => {
    row.classList.remove(
      "validation-valid",
      "validation-invalid",
      "validation-pending",
    );
  });

  validationResults = {};
}

//Update the validation summary banner
function updateValidationSummary(response) {
  let summaryElement = document.getElementById("validationSummary");

  //Create summary element if it doesn't exist
  if (!summaryElement) {
    summaryElement = document.createElement("div");
    summaryElement.id = "validationSummary";
    summaryElement.className = "validation-summary";

    const membersBox = document.getElementById("addMembersBox");
    if (membersBox) {
      membersBox.parentNode.insertBefore(summaryElement, membersBox);
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
    //Use proper singular/plural grammar
    const validMemberWord =
      response.valid_count === 1 ? "member is" : "members are";
    summaryElement.innerHTML = `<i class="fas fa-check-circle"></i> All ${response.valid_count} selected ${validMemberWord} valid`;
  } else if (response.invalid_count > 0) {
    summaryElement.classList.add("show", "error");
    //Use proper singular/plural grammar
    const memberWord = response.invalid_count === 1 ? "member" : "members";
    const pronounWord = response.invalid_count === 1 ? "this member" : "them";
    summaryElement.innerHTML = `<i class="fas fa-times-circle"></i> ${response.invalid_count} ${memberWord} cannot be added to the group. Please deselect ${pronounWord} to continue.`;
  }
}

//Update submit button state based on validation
function updateSubmitButtonState() {
  const submitBtn = document.getElementById("submitBtn");
  if (!submitBtn) return;

  const hasInvalidMembers = Object.values(validationResults).some(
    (r) => !r.is_valid,
  );

  if (validationInProgress) {
    submitBtn.disabled = true;
    submitBtn.classList.add("btn-disabled");
    submitBtn.innerHTML =
      '<span class="validation-spinner"></span> Validating...';
  } else if (hasInvalidMembers) {
    submitBtn.disabled = true;
    submitBtn.classList.add("btn-disabled");
    submitBtn.innerHTML = '<i class="fa-solid fa-users"></i> Create Group';
  } else {
    submitBtn.disabled = false;
    submitBtn.classList.remove("btn-disabled");
    submitBtn.innerHTML = '<i class="fa-solid fa-users"></i> Create Group';
  }
}

//Debounced validation trigger
function triggerValidation() {
  if (validationDebounceTimer) {
    clearTimeout(validationDebounceTimer);
  }

  validationDebounceTimer = setTimeout(() => {
    validateSelectedMembers();
  }, 300); //300ms debounce
}

//Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  //Load friends for selection
  loadFriendsForSelection();

  //Setup create group form handler
  setupCreateGroupForm();

  //Setup search functionality
  setupFriendSearch();
});

//Setup friend search functionality
function setupFriendSearch() {
  const searchInput = document.getElementById("searchFriendsInput");
  if (!searchInput) return;

  searchInput.addEventListener("input", function () {
    const searchTerm = this.value.toLowerCase().trim();
    const friendItems = document.querySelectorAll(".member-row.add-member-row");

    friendItems.forEach((item) => {
      const friendName = item.getAttribute("data-name") || "";
      const matches = friendName.includes(searchTerm);
      item.style.display = matches ? "" : "none";
    });
  });
}

//Setup create group form handler
function setupCreateGroupForm() {
  const createForm = document.getElementById("createGroupForm");
  if (!createForm) return;

  //Setup image preview
  const imageInput = document.getElementById("groupImageInput");
  const previewContainer = document.getElementById("imagePreviewContainer");
  const previewImg = document.getElementById("previewImg");

  if (imageInput && previewContainer && previewImg) {
    imageInput.addEventListener("change", function () {
      const file = this.files[0];
      if (file) {
        //Validate file type
        const allowedTypes = [
          "image/jpeg",
          "image/jpg",
          "image/png",
          "image/gif",
        ];
        const fileType = file.type.toLowerCase();

        if (!allowedTypes.includes(fileType)) {
          FormUtilities.showToast(
            "Please select a valid image file (JPG, PNG, or GIF)",
            "error",
          );
          this.value = "";
          previewContainer.classList.add("d-none");
          return;
        }

        //Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
          FormUtilities.showToast(
            "Image file is too large. Maximum size is 5MB",
            "error",
          );
          this.value = "";
          previewContainer.classList.add("d-none");
          return;
        }

        //Show preview
        const reader = new FileReader();
        reader.onload = function (e) {
          previewImg.src = e.target.result;
          previewContainer.classList.remove("d-none");

          //Fade-in effect
          previewContainer.style.opacity = "0";
          setTimeout(() => {
            previewContainer.style.transition = "opacity 0.3s ease";
            previewContainer.style.opacity = "1";
          }, 10);
        };
        reader.onerror = function () {
          FormUtilities.showToast("Error reading image file", "error");
          previewContainer.classList.add("d-none");
        };
        reader.readAsDataURL(file);
      } else {
        previewContainer.classList.add("d-none");
      }
    });

    //Click-to-remove functionality
    previewContainer.addEventListener("click", function () {
      if (confirm("Remove selected image?")) {
        imageInput.value = "";
        previewContainer.classList.add("d-none");
      }
    });
  }

  //Setup member selection change handler with real-time validation
  document.addEventListener("change", function (e) {
    if (e.target.name === "member_ids[]") {
      updateSelectedCount();

      //If unchecked, clear that member's validation status
      if (!e.target.checked) {
        const userGuid = e.target.value;
        clearValidationStatus(userGuid);
      }

      //Trigger real-time validation
      triggerValidation();
    }
  });

  //Setup form submission
  new FormHandler(createForm, {
    apiEndpoint: ApiUrls.groupsCreate(),
    isFileUpload: true, //Send as form data to support image upload
    beforeSubmit: (formData) => {
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
          "Please remove invalid members before creating the group",
          "error",
        );
        return false;
      }

      //Validate group name
      const groupName = formData.get("group_name")?.trim();
      if (!groupName || groupName.length < 3 || groupName.length > 50) {
        FormUtilities.showToast(
          "Group name must be between 3 and 50 characters",
          "error",
        );
        return false;
      }

      //Validate member selection
      const selectedMembers = document.querySelectorAll(
        'input[name="member_ids[]"]:checked',
      );
      if (selectedMembers.length < 2) {
        FormUtilities.showToast(
          "Please select at least 2 friends to create a group",
          "error",
        );
        return false;
      }

      const memberGuids = Array.from(selectedMembers).map((cb) => cb.value);
      formData.delete("member_ids[]");
      memberGuids.forEach((guid) => {
        formData.append("member_guids[]", guid);
      });

      return true;
    },
    onSuccess: (response) => {
      let message = response.message || "Group created successfully!";
      if (response.image_uploaded) {
        message += " Image uploaded successfully!";
      }

      FormUtilities.showToast(message, "success");

      //Redirect to the new group
      setTimeout(() => {
        if (response.group_guid) {
          window.location.href = `chatbox.php?guid=${response.group_guid}&type=group`;
        } else {
          window.location.href = "groups.php";
        }
      }, 1000);
    },
  });
}

//Update selected member count
function updateSelectedCount() {
  const count = document.querySelectorAll(
    'input[name="member_ids[]"]:checked',
  ).length;
  const countElement = document.getElementById("selectedCount");
  if (countElement) {
    countElement.textContent = count;
  }
  updateSubmitButtonState();
}
