/* SEARCH PAGE

Handles user search via API. */

//Search users via API
async function searchUsers(searchTerm) {
  const searchResultsDiv = document.getElementById("card-search");
  const searchCountDiv = document.getElementById("search-count");

  if (!searchTerm || searchTerm.trim() === "") {
    searchCountDiv.innerHTML = "";
    searchResultsDiv.innerHTML =
      '<p class="text-center text-muted">Enter a username to search</p>';
    return;
  }

  if (searchTerm.length > 30) {
    searchCountDiv.innerHTML = "";
    searchResultsDiv.innerHTML =
      '<p class="text-center text-danger">Search term is too long (max 30 characters).</p>';
    return;
  }

  try {
    //Show loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.show("Searching users...");
    }
    searchCountDiv.innerHTML = "";
    searchResultsDiv.innerHTML = '<p class="text-center">Searching...</p>';

    //Fetch search results from API
    const response = await ApiClient.get(ApiUrls.searchUsers(searchTerm));

    //Hide loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }

    if (!response.users) {
      throw new Error("Invalid response format");
    }

    const users = response.users;
    const userCount = users.length;

    //Show results count
    if (userCount === 0) {
      searchCountDiv.innerHTML = `<h6 class="alert-white"><p class="text-center">No users found.</p></h6><br>`;
      searchResultsDiv.innerHTML = `<p class="text-center text-muted">No users found matching "${escapeHtml(searchTerm)}".</p>`;
      return;
    }

    //Render search results
    renderSearchResults(users, searchTerm);
  } catch (error) {
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }
    searchCountDiv.innerHTML = "";
    if (error.statusCode === 400) {
      searchResultsDiv.innerHTML = `<h6 class="alert-white"><p class="text-center">No users found.</p></h6><br><p class="text-center text-muted">${escapeHtml(error.message)}</p>`;
    } else {
      searchResultsDiv.innerHTML =
        '<p class="text-center text-danger">Error searching users. Please try again.</p>';
      if (window.showToast) {
        window.showToast(error.message || "Failed to search users", "error");
      }
    }
  }
}

//Render search results
function renderSearchResults(users, searchTerm) {
  const searchResultsDiv = document.getElementById("card-search");
  const searchCountDiv = document.getElementById("search-count");
  searchResultsDiv.innerHTML = "";

  //Set count header outside the scrollable container
  searchCountDiv.innerHTML = `<h6 class="alert-white"><p class='text-center'>Found ${users.length} user${users.length !== 1 ? "s" : ""} matching "${escapeHtml(searchTerm)}".</p></h6><br>`;

  users.forEach((user) => {
    const userGuid = user.guid;
    const usernameRaw = user.username;
    const friendshipStatus = user.friendshipStatus;

    //Generate profile image URL
    const imgUrl = user.profile_image_guid
      ? `download.php?type=profile&guid=${user.profile_image_guid.replace(/-/g, "")}`
      : "img/profiledefault.jpg";

    //Use neutral image ID (hide online status for privacy)
    const imgId = "profileImgInSearch";

    //Create search result card
    const resultCard = document.createElement("div");
    resultCard.className =
      "user-card form-group mb-1 d-flex align-items-center";
    resultCard.classList.add("gap-3");

    //Use GUID for friend actions
    const friendActionParam = `guid=${userGuid}`;

    //Determine buttons based on friendship status
    let buttons = "";
    if (friendshipStatus === "friends") {
      buttons = `
                <button class="app-btn app-btn-outline-danger app-btn-fixed"
                        onclick="friendManager('delete','${userGuid}',false,'','search.php?search=${encodeURIComponent(searchTerm)}')"
                        name='deletefriend'><i class="fa-solid fa-user-minus"></i>Unfriend</button>
                <button class="app-btn app-btn-outline-primary app-btn-fixed"
                        onclick="window.location.href='chatbox.php?${friendActionParam}&type=user'"
                        name='chat'><i class="fa-solid fa-comments"></i>Chat</button>
            `;
    } else if (friendshipStatus === "pending_sent") {
      buttons = `
                <button class="app-btn app-btn-outline-secondary app-btn-fixed"
                        onclick="friendManager('cancel','${userGuid}',false,'','search.php?search=${encodeURIComponent(searchTerm)}')"
                        name='cancelrequest'><i class="fa-solid fa-xmark"></i>Cancel</button>
            `;
    } else if (friendshipStatus === "pending_received") {
      buttons = `
                <button class="app-btn app-btn-outline-success app-btn-fixed"
                        onclick="friendManager('accept','${userGuid}',false,'','search.php?search=${encodeURIComponent(searchTerm)}')"
                        name='acceptrequest'><i class="fa-solid fa-check"></i>Accept</button>
                <button class="app-btn app-btn-outline-danger app-btn-fixed"
                        onclick="friendManager('reject','${userGuid}',false,'','search.php?search=${encodeURIComponent(searchTerm)}')"
                        name='rejectrequest'><i class="fa-solid fa-xmark"></i>Decline</button>
            `;
    } else {
      buttons = `
                <button class="app-btn app-btn-primary app-btn-fixed"
                        onclick="friendManager('add','${userGuid}',false,'','search.php?search=${encodeURIComponent(searchTerm)}')"
                        name='addfriend'><i class="fa-solid fa-user-plus"></i>Add</button>
            `;
    }

    resultCard.innerHTML = `
            <div id='left' class='d-flex align-items-center friend-card-left'>
                <img id='${imgId}' src='${imgUrl}' alt='Profile Image' class='friend-img-margin'>
                <strong id='black' class='friend-name-truncate'></strong>
            </div>
            <div id='right' class='d-flex gap-2 flex-shrink-0'>
                ${buttons}
            </div>
        `;

    //Set username and tooltip using DOM methods
    const strongElement = resultCard.querySelector("#black");
    strongElement.textContent = usernameRaw;
    strongElement.title = usernameRaw;

    searchResultsDiv.appendChild(resultCard);
  });
}

//Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  //Check if there's a search term in the URL
  const urlParams = new URLSearchParams(window.location.search);
  const searchTerm = urlParams.get("search");

  if (searchTerm) {
    //Perform search with the URL parameter
    searchUsers(searchTerm);
  } else {
    //Show initial message
    document.getElementById("search-count").innerHTML = "";
    document.getElementById("card-search").innerHTML =
      '<p class="text-center text-muted">Enter a username to search.</p>';
  }
});
