<?php
require_once "header.php";
$path = $GLOBALS['rootUrl'] . '/static';
echo '<script src="' . $path . '/js/group-create-page.js"></script>';
?>

<link rel="stylesheet" href="<?php echo $GLOBALS['rootUrl'] . '/static/css/groups.css'; ?>">

<main class="group-page-main">
    <div class="container container-full">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card p-4 group-page-card">

                    <!-- TITLE + BACK BUTTON -->
                    <div class="d-flex align-items-center mb-3">

                        <!-- Left -->
                        <div class="flex-fill text-start">
                            <a href="groups.php" class="theme-primary-text text-nowrap">
                                ← Back
                            </a>
                        </div>

                        <!-- Center -->
                        <div class="flex-fill text-start">
                            <h2 class="m-0 text-nowrap">Create New Group</h2>
                        </div>

                        <!-- Right (empty spacer) -->
                        <div class="flex-fill"></div>

                    </div>

                    <hr>

                    <!-- CREATE GROUP FORM -->
                    <form action="#" method="post" enctype="multipart/form-data" id="createGroupForm">

                        <!-- GROUP NAME -->
                        <div class="mb-4">
                            <h5>Group Name <span class="text-danger">*</span></h5>
                            <input type="text" name="group_name" class="form-control mt-2"
                                placeholder="Enter group name (3-50 characters)" minlength="3" maxlength="50" required>
                            <div class="form-text">Choose a descriptive name for your group</div>
                        </div>

                        <!-- GROUP IMAGE -->
                        <div class="mb-4">
                            <h5>Group Image (optional)</h5>

                            <!-- Image Preview -->
                            <div class="mb-2">
                                <div id="imagePreviewContainer" class="group-round-img-container clickable d-none"
                                    title="Click to remove image">
                                    <img id="previewImg" src="" alt="Group image preview" class="group-round-img">
                                </div>
                            </div>

                            <div class="input-group">
                                <input type="file" name="group_image" class="form-control"
                                    accept="image/jpeg,image/jpg,image/png,image/gif" id="groupImageInput">
                                <label class="input-group-text" for="groupImageInput">
                                    <i class="fas fa-image"></i>
                                </label>
                            </div>
                            <div class="form-text">Upload a group image (JPG, PNG, or GIF, max 5MB)</div>
                        </div>

                        <hr>

                        <!-- ADD MEMBERS SECTION -->
                        <div class="mb-4">
                            <h5>Add Members <span class="text-danger">*</span></h5>
                            <p class="text-muted small">Select at least 2 friends to create a group</p>

                            <input type="search" id="searchFriendsInput" class="form-control mt-2 mb-2"
                                placeholder="Search friends..." maxlength="30">

                            <div class="members-box" id="addMembersBox">
                                <div id="friends-list">
                                    <p class="text-center text-muted">Loading friends...</p>
                                </div>
                            </div>

                            <div class="form-text mt-2">
                                <span id="selectedCount">0</span> friends selected
                            </div>
                        </div>

                        <button class="btn btn-success w-100" type="submit" id="submitBtn">
                            <i class="fa-solid fa-users"></i> Create Group
                        </button>

                    </form>

                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once "footer.php"; ?>