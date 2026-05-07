/* ADMIN USER MANAGEMENT
 
Handles loading, searching, filtering, and rendering users.
Also manages ban/unban actions and real-time status updates. */

//Get current user GUID from the page
let currentUserGuid = null;

//Store all loaded users for client-side filtering
let allLoadedUsers = [];
//Track current search term for count messages
let currentSearchTerm = "";

//Load all users from the API
async function loadUsers() {
  const userCountDiv = document.getElementById("user-count");
  const cardUsersDiv = document.getElementById("card-users");

  try {
    //Show loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.show("Loading users...");
    }
    userCountDiv.innerHTML =
      '<h6 class="alert-white"><p class="text-center">Loading users...</p></h6><br>';
    cardUsersDiv.innerHTML = "";

    //Fetch users from API
    const response = await ApiClient.get(ApiUrls.adminListUsers());

    //Hide loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }

    if (!response.users) {
      throw new Error("Invalid response format");
    }

    allLoadedUsers = sortUsers(response.users);
    currentSearchTerm = "";

    //Apply current filter and render
    applyFilterAndRender();
  } catch (error) {
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }
    userCountDiv.innerHTML =
      '<h6 class="alert-white"><p class="text-center text-danger">Failed to load users.</p></h6><br>';
    cardUsersDiv.innerHTML =
      '<p class="text-center text-danger">Error loading users. Please try again.</p>';
    if (window.showToast) {
      window.showToast(error.message || "Failed to load users", "error");
    }
  }
}

//Search users by username or email
async function searchUsers(searchTerm) {
  const userCountDiv = document.getElementById("user-count");
  const cardUsersDiv = document.getElementById("card-users");

  try {
    //Show loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.show("Searching users...");
    }
    userCountDiv.innerHTML =
      '<h6 class="alert-white"><p class="text-center">Searching users...</p></h6><br>';
    cardUsersDiv.innerHTML = "";

    //Fetch search results from API
    const response = await ApiClient.get(ApiUrls.adminSearchUsers(searchTerm));

    //Hide loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }

    if (!response.users) {
      throw new Error("Invalid response format");
    }

    allLoadedUsers = sortUsers(response.users);
    currentSearchTerm = searchTerm;

    //Apply current filter and render
    applyFilterAndRender();
  } catch (error) {
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }
    userCountDiv.innerHTML =
      '<h6 class="alert-white"><p class="text-center text-danger">Search failed.</p></h6><br>';
    cardUsersDiv.innerHTML =
      '<p class="text-center text-danger">Error searching users. Please try again.</p>';
    if (window.showToast) {
      window.showToast(error.message || "Failed to search users", "error");
    }
  }
}

//Sort users (Active first, then alphabetically A-Z by username within each group)
function sortUsers(users) {
  return users.sort((a, b) => {
    const aActive = a.status === "Active" ? 0 : 1;
    const bActive = b.status === "Active" ? 0 : 1;

    //Active users first
    if (aActive !== bActive) {
      return aActive - bActive;
    }

    //Alphabetically by username (case-insensitive)
    return a.username.localeCompare(b.username, undefined, {
      sensitivity: "base",
    });
  });
}

//Filter users based on the selected status filter
function filterUsers(users, filter) {
  if (filter === "default") return users;
  if (filter === "active")
    return users.filter((u) => u.status === "Active" && !u.banned);
  if (filter === "offline")
    return users.filter((u) => u.status === "Offline" && !u.banned);
  if (filter === "banned") return users.filter((u) => u.banned);
  return users;
}

//Apply the current filter and re-render
function applyFilterAndRender() {
  const filterSelect = document.getElementById("status-filter");
  const filter = filterSelect ? filterSelect.value : "default";
  const filtered = filterUsers(allLoadedUsers, filter);
  updateUserCountMessage(filtered.length, filter, currentSearchTerm);
  renderUserCards(filtered);
}

//Update the user count message based on filter and search state
function updateUserCountMessage(count, filter, searchTerm) {
  const userCountDiv = document.getElementById("user-count");
  if (!userCountDiv) return;

  const filterLabel =
    filter === "active"
      ? "active "
      : filter === "offline"
        ? "offline "
        : filter === "banned"
          ? "banned "
          : "";

  let msg;
  if (searchTerm) {
    const safe = escapeHtml(searchTerm);
    if (count === 0) {
      msg = `No ${filterLabel}users found matching "${safe}".`;
    } else if (count === 1) {
      msg = `Found 1 ${filterLabel}user matching "${safe}".`;
    } else {
      msg = `Found ${count} ${filterLabel}users matching "${safe}".`;
    }
  } else {
    if (count === 0) {
      msg = `No ${filterLabel}users found.`;
    } else if (count === 1) {
      msg = `Found 1 ${filterLabel}user.`;
    } else {
      msg = `Found ${count} ${filterLabel}users.`;
    }
  }

  userCountDiv.innerHTML = `<h6 class="alert-white"><p class="text-center">${msg}</p></h6><br>`;
}

//Get the status text from a user card element
function getCardStatusText(card) {
  const smallElements = card.querySelectorAll(".user-info small");
  for (const el of smallElements) {
    if (el.textContent.startsWith("Status:")) {
      return el.textContent;
    }
  }
  return "";
}

//Re-sort user cards in the DOM based on current status and username
function sortUserCardsInDOM() {
  const cardUsersDiv = document.getElementById("card-users");
  if (!cardUsersDiv) return;

  const cards = Array.from(cardUsersDiv.querySelectorAll(".user-card"));
  if (cards.length === 0) return;

  cards.sort((a, b) => {
    const aActive = getCardStatusText(a).includes("Active") ? 0 : 1;
    const bActive = getCardStatusText(b).includes("Active") ? 0 : 1;

    if (aActive !== bActive) {
      return aActive - bActive;
    }

    const aName = a.querySelector(".user-info strong")?.textContent || "";
    const bName = b.querySelector(".user-info strong")?.textContent || "";
    return aName.localeCompare(bName, undefined, { sensitivity: "base" });
  });

  //Re-append in sorted order
  cards.forEach((card) => cardUsersDiv.appendChild(card));
}

//Render user cards in the DOM
function renderUserCards(users) {
  const cardUsersDiv = document.getElementById("card-users");
  cardUsersDiv.innerHTML = "";

  users.forEach((user) => {
    const userGuid = user.guid;
    const username = escapeHtml(user.username);
    const email = escapeHtml(user.email);
    const status = escapeHtml(user.status);
    const isBanned = user.banned;
    const createdDate = formatDate(user.createdDate);
    const isCurrentUser = currentUserGuid && userGuid === currentUserGuid;

    //Use profile image URL from backend
    const imgUrl = user.profile_image_url;
    const isActive = status === "Active";
    const imgId = isActive
      ? "profileImgInUsersActive"
      : "profileImgInUsersInactive";

    //Create user card HTML
    const userCard = document.createElement("div");
    userCard.className =
      "form-group mb-3 d-flex justify-content-between align-items-start user-card";

    userCard.innerHTML = `
            <div id="left" class="d-flex">
                <img id="${imgId}" src="${imgUrl}" alt="Profile Image" class="me-3">
                <div class="user-info">
                    <strong>${username}</strong><br>
                    <small class="text-muted">${email}</small><br>
                    <small>Status: ${status}</small><br>
                    <small>Joined: ${createdDate}</small><br>
                    ${isBanned ? '<span class="badge bg-danger mt-1">BANNED</span>' : ""}
                </div>
            </div>
            <div id="right">
                ${
                  isCurrentUser
                    ? '<small class="text-muted">(You)</small>'
                    : isBanned
                      ? `<button type="button" data-action="unban-user" data-user-guid="${userGuid}" data-username="${username}" class="app-btn app-btn-success app-btn-fixed"><i class="fas fa-unlock"></i>Unban</button>`
                      : `<button type="button" data-action="ban-user" data-user-guid="${userGuid}" data-username="${username}" class="app-btn app-btn-outline-danger app-btn-fixed"><i class="fas fa-ban"></i>Ban</button>`
                }
            </div>
        `;

    cardUsersDiv.appendChild(userCard);
  });

  //Attach event listeners to ban/unban buttons
  attachBanUnbanHandlers();
}

//Format date string
function formatDate(dateString) {
  if (!dateString || dateString === null) {
    return "Unknown";
  }

  const date = new Date(dateString);
  if (isNaN(date.getTime())) {
    return "Unknown";
  }

  const options = { year: "numeric", month: "short", day: "numeric" };
  return date.toLocaleDateString("en-GB", options);
}

//Attach event listeners to ban/unban buttons
function attachBanUnbanHandlers() {
  //Handle ban user buttons
  document.querySelectorAll('[data-action="ban-user"]').forEach((button) => {
    button.addEventListener("click", async function (e) {
      e.preventDefault();

      const userGuid = this.dataset.userGuid;
      const username = this.dataset.username;

      if (!confirm(`Are you sure you want to ban user "${username}"?`)) {
        return;
      }

      try {
        const response = await ApiClient.post(
          ApiUrls.adminBanUser(),
          { user_guid: userGuid },
          true, //Show loading
        );

        if (window.showToast) {
          window.showToast(
            response.message || "User banned successfully",
            "success",
          );
        }

        //Update UI immediately (don't wait for WebSocket)
        updateAdminUserStatus(userGuid, "Offline", true);
      } catch (error) {
        if (window.showToast) {
          window.showToast(error.message || "Failed to ban user", "error");
        }
      }
    });
  });

  //Handle unban user buttons
  document.querySelectorAll('[data-action="unban-user"]').forEach((button) => {
    button.addEventListener("click", async function (e) {
      e.preventDefault();

      const userGuid = this.dataset.userGuid;
      const username = this.dataset.username;

      if (!confirm(`Are you sure you want to unban user "${username}"?`)) {
        return;
      }

      try {
        const response = await ApiClient.post(
          ApiUrls.adminUnbanUser(),
          { user_guid: userGuid },
          true, //Show loading
        );

        if (window.showToast) {
          window.showToast(
            response.message || "User unbanned successfully",
            "success",
          );
        }

        //Update UI immediately (don't wait for WebSocket)
        updateAdminUserStatus(userGuid, "Offline", false);
      } catch (error) {
        if (window.showToast) {
          window.showToast(error.message || "Failed to unban user", "error");
        }
      }
    });
  });
}

/* Update user status and ban state in admin panel.
Updates the admin panel UI when a user's status or ban state changes. */
function updateAdminUserStatus(userGuid, status, isBanned = null) {
  if (!userGuid) return;

  //Update the user in allLoadedUsers so filtering stays in sync
  const userInList = allLoadedUsers.find((u) => u.guid === userGuid);
  if (userInList) {
    userInList.status = status;
    if (isBanned !== null) {
      userInList.banned = isBanned;
    }
    //Re-sort and re-apply filter to reflect the change
    allLoadedUsers = sortUsers(allLoadedUsers);
    applyFilterAndRender();
    return;
  }

  //Fallback. If user not found in allLoadedUsers, update DOM directly
  const buttons = document.querySelectorAll("[data-user-guid]");
  buttons.forEach((button) => {
    if (button.dataset.userGuid === userGuid) {
      const userCard = button.closest(".user-card");
      if (userCard) {
        const username = button.dataset.username;
        const userInfo = userCard.querySelector(".user-info");
        if (userInfo) {
          const statusElements = userInfo.querySelectorAll("small");
          statusElements.forEach((el) => {
            if (el.textContent.startsWith("Status:")) {
              el.textContent = `Status: ${status}`;
            }
          });

          const img = userCard.querySelector("img");
          if (img) {
            const isActive = status === "Active";
            img.id = isActive
              ? "profileImgInUsersActive"
              : "profileImgInUsersInactive";
          }

          sortUserCardsInDOM();

          if (isBanned !== null) {
            const existingBadge = userInfo.querySelector(".badge.bg-danger");
            if (isBanned && !existingBadge) {
              const badge = document.createElement("span");
              badge.className = "badge bg-danger mt-1";
              badge.textContent = "BANNED";
              userInfo.appendChild(badge);
            } else if (!isBanned && existingBadge) {
              existingBadge.remove();
            }

            const rightDiv = userCard.querySelector("#right");
            const isCurrentUserCard =
              rightDiv && rightDiv.querySelector(".text-muted");

            if (rightDiv && !isCurrentUserCard && username) {
              if (isBanned) {
                rightDiv.innerHTML = `<button type="button" data-action="unban-user" data-user-guid="${userGuid}" data-username="${username}" class="app-btn app-btn-success app-btn-fixed"><i class="fas fa-unlock"></i>Unban</button>`;
              } else {
                rightDiv.innerHTML = `<button type="button" data-action="ban-user" data-user-guid="${userGuid}" data-username="${username}" class="app-btn app-btn-outline-danger app-btn-fixed"><i class="fas fa-ban"></i>Ban</button>`;
              }
              attachBanUnbanHandlers();
            }
          }
        }
      }
    }
  });
}

//Export for use in main.js
window.updateAdminUserStatus = updateAdminUserStatus;

document.addEventListener("DOMContentLoaded", function () {
  //Get current user GUID from window
  if (window.CURRENT_USER_GUID) {
    currentUserGuid = window.CURRENT_USER_GUID;
  }

  //Check for pending toast message after page load
  const pendingToast = sessionStorage.getItem("pendingToast");
  if (pendingToast) {
    try {
      const { message, type } = JSON.parse(pendingToast);
      window.showToast(message, type);
    } catch (e) {}
    sessionStorage.removeItem("pendingToast");
  }

  //Handle status filter dropdown
  const statusFilter = document.getElementById("status-filter");
  if (statusFilter) {
    statusFilter.addEventListener("change", function () {
      applyFilterAndRender();
    });
  }

  //Handle search form submission
  const searchForm = document.getElementById("search-form");
  const searchInput = document.getElementById("search-input");
  const backLink = document.getElementById("backToUserManagementLink");

  if (searchForm && searchInput) {
    searchForm.addEventListener("submit", async function (e) {
      e.preventDefault();

      const searchTerm = searchInput.value.trim();

      //Reset filter dropdown on new search/load
      if (statusFilter) statusFilter.value = "default";

      if (searchTerm) {
        //Update URL with search parameter for bookmarking
        const url = new URL(window.location);
        url.searchParams.set("search", searchTerm);
        window.history.pushState({}, "", url);

        //Show back link when searching
        if (backLink) backLink.classList.remove("d-none");

        //Perform search
        await searchUsers(searchTerm);
      } else {
        //Clear search parameter from URL
        const url = new URL(window.location);
        url.searchParams.delete("search");
        window.history.pushState({}, "", url);

        //Hide back link when not searching
        if (backLink) backLink.classList.add("d-none");

        //Load all users
        await loadUsers();
      }
    });
  }

  //Check if there's a search term in the URL on page load
  const urlParams = new URLSearchParams(window.location.search);
  const initialSearchTerm = urlParams.get("search");

  if (initialSearchTerm) {
    //Show back link when loading with search parameter
    if (backLink) backLink.classList.remove("d-none");
    //Perform search with the URL parameter
    searchUsers(initialSearchTerm);
  } else {
    //Load all users on page load
    loadUsers();
  }
});
