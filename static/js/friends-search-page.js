/* FRIENDS SEARCH PAGE
 
Handles friend search via API. */

let allFriends = [];

//Load and search friends
async function loadAndSearchFriends(searchTerm) {
  const searchResultsDiv = document.getElementById("card-friend-search");
  const countDiv = document.querySelector(".friend-count");

  if (!searchResultsDiv) {
    return;
  }

  try {
    //Show loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.show("Searching friends...");
    }

    //Fetch all friends from API
    const response = await ApiClient.get(ApiUrls.friendsGet());

    //Hide loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }

    if (!response.friends) {
      throw new Error("Invalid response format");
    }

    allFriends = response.friends;

    //Filter friends by search term
    const filteredFriends = allFriends.filter((friend) =>
      friend.username.toLowerCase().includes(searchTerm.toLowerCase()),
    );

    const friendCount = filteredFriends.length;

    //Update count
    if (friendCount === 0) {
      if (countDiv) {
        countDiv.innerHTML =
          '<h6 class="alert-white"><p class="text-center">There is no such friend.</p></h6>';
      }
      searchResultsDiv.innerHTML = "";
      return;
    } else if (friendCount === 1) {
      if (countDiv) {
        countDiv.innerHTML =
          '<h6 class="alert-white"><p class="text-center">There is 1 friend.</p></h6><br>';
      }
    } else {
      if (countDiv) {
        countDiv.innerHTML = `<h6 class="alert-white"><p class="text-center">There are ${friendCount} friends.</p></h6><br>`;
      }
    }

    //Render filtered friends
    renderFriends(filteredFriends);
  } catch (error) {
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }
    searchResultsDiv.innerHTML =
      '<p class="text-center text-danger">Error searching friends. Please try again.</p>';
    if (window.showToast) {
      window.showToast(error.message || "Failed to search friends", "error");
    }
  }
}

//Sort friends (active users first, then alphabetically A-Z within each group)
function sortFriends(friends) {
  return friends.sort((a, b) => {
    const aActive = a.status === "Active";
    const bActive = b.status === "Active";

    //Active users come first
    if (aActive && !bActive) return -1;
    if (!aActive && bActive) return 1;

    //Within the same status group, sort alphabetically A-Z
    return a.username.toLowerCase().localeCompare(b.username.toLowerCase());
  });
}

//Render friend cards
function renderFriends(friends) {
  const searchResultsDiv = document.getElementById("card-friend-search");
  searchResultsDiv.innerHTML = "";

  //Sort friends (active first, then alphabetically)
  const sortedFriends = sortFriends(friends);

  sortedFriends.forEach((friend) => {
    const friendId = friend.friend_guid;
    const usernameRaw = friend.username; //Keep raw for title attribute
    const status = friend.status;

    //Generate profile image URL
    const imgUrl = friend.profile_image_guid
      ? `download.php?type=profile&guid=${friend.profile_image_guid}`
      : "img/profiledefault.jpg";

    const isActive = status === "Active";
    const imgId = isActive
      ? "profileImgInFriendsActive"
      : "profileImgInFriendsInactive";

    //Create friend card
    const friendCard = document.createElement("div");
    friendCard.className =
      "user-card form-group mb-1 d-flex justify-content-between";

    friendCard.innerHTML = `
            <div id="left" class="d-flex align-items-center friend-card-left">
                <img id="${imgId}" data-friend-guid="${friendId}" src="${imgUrl}" alt="profileImage" class="friend-img-margin">
                <strong id="black" class="friend-name-truncate"></strong>
            </div>
            <div id="right" class="d-flex gap-2 flex-shrink-0">
                <button class="app-btn app-btn-outline-danger app-btn-fixed"
                        onclick="friendManager('delete','${friendId}',false,'','friends.php')"
                        name="deletefriend"><i class="fa-solid fa-user-minus"></i>Unfriend</button>
                <button class="app-btn app-btn-primary app-btn-fixed"
                        onclick="friendManager('chat','${friendId}')"
                        name="chat"><i class="fa-solid fa-comments"></i>Chat</button>
            </div>
        `;

    //Set username and tooltip using DOM methods
    const strongElement = friendCard.querySelector("#black");
    strongElement.textContent = usernameRaw;
    strongElement.title = usernameRaw;

    searchResultsDiv.appendChild(friendCard);
  });
}

//Update friend status and re-render with sorting (for real-time updates)
function updateFriendStatus(friendGuid, newStatus) {
  const friendIndex = allFriends.findIndex((f) => f.friend_guid === friendGuid);

  if (friendIndex !== -1) {
    //Update the friend's status in the data
    allFriends[friendIndex].status = newStatus;

    //Get current search term to re-apply filter
    const urlParams = new URLSearchParams(window.location.search);
    const searchTerm = urlParams.get("search") || "";

    //Re-filter and re-render the friends list with proper sorting
    const filteredFriends = allFriends.filter((friend) =>
      friend.username.toLowerCase().includes(searchTerm.toLowerCase()),
    );

    renderFriends(filteredFriends);
  }
}

//Expose function globally for ui-updates.js to call
window.updateFriendStatusAndRerender = updateFriendStatus;

//Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  //Get search term from URL query string
  const urlParams = new URLSearchParams(window.location.search);
  const searchTerm = urlParams.get("search") || "";

  const friendCountDiv = document.querySelector(".friend-count");

  if (searchTerm) {
    loadAndSearchFriends(searchTerm);
  } else {
    if (friendCountDiv) {
      friendCountDiv.innerHTML =
        '<h6 class="alert-white"><p class="text-center">Please enter a search term</p></h6>';
    }
  }
});
