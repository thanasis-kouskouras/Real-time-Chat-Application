/* INDEX PAGE LOADER

Handles client-side loading of user profile data for the index page. */

class IndexPageLoader {
  constructor() {
    this.profileImageElement = null;
    this.usernameElement = null;
    this.initialized = false;
  }

  //Initialize the page loader
  async init() {
    if (this.initialized) return;

    //Wait for DOM to be ready
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => this.setupElements());
    } else {
      this.setupElements();
    }

    this.initialized = true;
  }

  //Setup DOM elements and load data
  setupElements() {
    //Find the profile elements
    this.profileImageElement = document.getElementById(
      "homepage-center-profile-image",
    );
    this.usernameElement = document.getElementById("blackBigUsername");

    if (!this.profileImageElement || !this.usernameElement) {
      return;
    }

    //Load user profile data
    this.loadUserProfile();
  }

  //Load user profile data via API
  async loadUserProfile() {
    try {
      //Show loading indicator
      if (window.loadingIndicator) {
        window.loadingIndicator.show("Loading profile...");
      }

      //Show loading state on elements
      this.showLoadingState();

      //Make API call to get profile data
      const data = await ApiClient.get(ApiUrls.profileGet());

      if (!data.success) {
        throw new Error(data.message || "Failed to load profile data");
      }

      //Render the profile data using ApiClient response
      this.renderProfileData(data.profile);
    } catch {
      this.handleLoadingError();
    } finally {
      //Hide loading indicator
      if (window.loadingIndicator) {
        window.loadingIndicator.hide();
      }
    }
  }

  //Show loading state on profile elements
  showLoadingState() {
    if (this.profileImageElement) {
      //Show a placeholder or loading spinner for image
      this.profileImageElement.classList.add("img-loading");
      this.profileImageElement.alt = "Loading...";
    }

    if (this.usernameElement) {
      //Show loading text
      this.usernameElement.innerHTML =
        '<span class="text-muted">Loading...</span>';
    }
  }

  //Render profile data to the page
  renderProfileData(userData) {
    if (!userData) {
      throw new Error("No user data received");
    }

    //Update profile image
    if (this.profileImageElement && userData.profile_image_url) {
      this.profileImageElement.src = userData.profile_image_url;
      this.profileImageElement.alt = `${userData.user_username}'s profile picture`;
      this.profileImageElement.classList.remove("img-loading");

      //Handle image load error
      this.profileImageElement.onerror = () => {
        this.profileImageElement.src = "img/profiledefault.jpg";
      };
    }

    //Update username
    if (this.usernameElement && userData.user_username) {
      this.usernameElement.textContent = userData.user_username;
    }
  }

  //Handle loading errors
  handleLoadingError() {
    //Show error message to user
    if (window.showToast) {
      window.showToast(
        "Failed to load profile data. Please refresh the page.",
        "error",
      );
    }

    //Restore elements to a reasonable state
    if (this.profileImageElement) {
      this.profileImageElement.src = "img/profiledefault.jpg";
      this.profileImageElement.alt = "Default profile picture";
      this.profileImageElement.classList.remove("img-loading");
    }

    if (this.usernameElement) {
      this.usernameElement.innerHTML =
        '<span class="text-muted">Error loading profile</span>';
    }

    //Provide retry option
    this.showRetryOption();
  }

  //Show consistent retry option to user
  showRetryOption() {
    //Create a retry button
    const retryContainer = document.createElement("div");
    retryContainer.className = "text-center mt-3";
    retryContainer.innerHTML = `
            <button class="btn btn-outline-primary btn-sm" onclick="indexPageLoader.loadUserProfile()">
                <i class="fas fa-redo"></i> Retry Loading Profile
            </button>
        `;

    //Insert after the username element
    if (this.usernameElement && this.usernameElement.parentNode) {
      this.usernameElement.parentNode.insertBefore(
        retryContainer,
        this.usernameElement.nextSibling,
      );
    }
  }
}

//Create and initialize the loader
const indexPageLoader = new IndexPageLoader();

//Auto-initialize when script loads
indexPageLoader.init();

//Export for global access
window.indexPageLoader = indexPageLoader;
