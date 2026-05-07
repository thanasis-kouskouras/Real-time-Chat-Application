/* FRIENDS PAGE

Handles friend list display via API. */

let allFriends = []; //Store friends globally for real-time updates

//Load friends from API
async function loadFriends() {
  const friendCountDiv = document.getElementById("friend-count");
  const friendListDiv = document.getElementById("card-friend");

  try {
    //Show loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.show("Loading friends...");
    }
    friendCountDiv.innerHTML =
      '<h6 class="alert-white"><p class="text-center">Loading friends...</p></h6><br>';
    friendListDiv.innerHTML = "";

    //Fetch friends from API
    const response = await ApiClient.get(ApiUrls.friendsGet());

    //Hide loading indicator
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }

    if (!response.friends) {
      throw new Error("Invalid response format");
    }

    const friends = response.friends;
    allFriends = friends; //Store globally for real-time updates
    const friendCount = friends.length;

    // Update friend count
    if (friendCount === 0) {
      friendCountDiv.innerHTML =
        '<h6 class="alert-white"><p class="text-center">There are no friends.</p></h6><br>';
    } else if (friendCount === 1) {
      friendCountDiv.innerHTML =
        '<h6 class="alert-white"><p class="text-center">There is 1 friend.</p></h6><br>';
    } else {
      friendCountDiv.innerHTML = `<h6 class="alert-white"><p class="text-center">There are ${friendCount} friends.</p></h6><br>`;
    }

    //Render friend cards
    renderFriends(friends);
  } catch (error) {
    if (window.loadingIndicator) {
      window.loadingIndicator.hide();
    }
    friendCountDiv.innerHTML =
      '<h6 class="alert-white"><p class="text-center text-danger">Failed to load friends.</p></h6><br>';
    friendListDiv.innerHTML =
      '<p class="text-center text-danger">Error loading friends. Please try again.</p>';
    if (window.showToast) {
      window.showToast(error.message || "Failed to load friends", "error");
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
  const friendListDiv = document.getElementById("card-friend");
  friendListDiv.innerHTML = "";

  //Sort friends (active first, then alphabetically)
  const sortedFriends = sortFriends(friends);

  sortedFriends.forEach((friend) => {
    const friendGuid = friend.friend_guid;
    const usernameRaw = friend.username;
    const status = friend.status;

    //Use profile image URL from API
    const imgUrl = friend.profile_image_url || "img/profiledefault.jpg";

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
                <img id="${imgId}" data-friend-guid="${friendGuid}" src="${imgUrl}" alt="profileImage" class="friend-img-margin">
                <strong id="black" class="friend-name-truncate"></strong>
            </div>
            <div id="right" class="d-flex gap-2">
                <button class="app-btn app-btn-outline-danger app-btn-fixed" 
                        onclick="friendManager('delete','${friendGuid}',false,'','friends.php')" 
                        name="deletefriend"><i class="fa-solid fa-user-minus"></i>Unfriend</button>
                <button class="app-btn app-btn-primary app-btn-fixed" 
                        onclick="friendManager('chat','${friendGuid}')" 
                        name="chat"><i class="fa-solid fa-comments"></i>Chat</button>
            </div>
        `;

    //Set username and tooltip using DOM methods (safer for special characters)
    const strongElement = friendCard.querySelector("#black");
    strongElement.textContent = usernameRaw; //textContent automatically escapes HTML
    strongElement.title = usernameRaw; //title attribute shows full username on hover

    friendListDiv.appendChild(friendCard);
  });
}

//Update friend status and re-render with sorting (for real-time updates)
function updateFriendStatus(friendGuid, newStatus) {
  const friendIndex = allFriends.findIndex((f) => f.friend_guid === friendGuid);

  if (friendIndex !== -1) {
    //Update the friend's status in the data
    allFriends[friendIndex].status = newStatus;

    //Re-render the friends list with proper sorting
    renderFriends(allFriends);
  }
}

//Expose function globally for ui-updates.js to call
window.updateFriendStatusAndRerender = updateFriendStatus;

//Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  loadFriends();
});
