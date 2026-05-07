<?php
require 'header.php';
require_once 'includes/guid-utilities.php';
require_once 'includes/db/group_members.php';

//Include group edit page JavaScript
$path = $GLOBALS['rootUrl'] . '/static';
echo '<script src="' . $path . '/js/group-edit-page.js"></script>';

$user_guid = $user["user_guid"];

$groupGuid = isset($_GET["guid"]) ? trim($_GET["guid"]) : '';
if (!$groupGuid || !validateGuid($groupGuid)) {
    header("location: groups.php");
    exit();
}

//Check if user is a member of this group
if (!isGroupMemberByGuid($groupGuid, $user_guid)) {
    //User is not a member of this group
    header("location: groups.php");
    exit();
}

//Only admins can access the edit page
if (!isGroupAdminByGuid($groupGuid, $user_guid)) {
    //Redirect non-admins back to the group chat
    header("location: chatbox.php?guid=" . urlencode($groupGuid) . "&type=group");
    exit();
}

//Determine return URL (where to go back to)
$returnUrl = isset($_GET["from"]) ? $_GET["from"] : 'groups.php';
if ($returnUrl === 'chat') {
    $returnUrl = "chatbox.php?guid=" . urlencode($groupGuid) . "&type=group";
} elseif ($returnUrl !== 'groups.php') {
    //Sanitize 9only allow specific return URLs)
    $returnUrl = 'groups.php';
}
?>

<link rel="stylesheet" href="<?php echo $GLOBALS['rootUrl'] . '/static/css/groups.css'; ?>">

<script>
//Pass the group GUID to JavaScript (all other group data is loaded via API)
window.GROUP_GUID = '<?php echo htmlspecialchars($groupGuid, ENT_QUOTES, 'UTF-8'); ?>';
</script>

<main class="group-page-main">
    <div class="container container-full">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7 col-xl-6">
                <div class="card p-4 group-page-card">

                    <!-- BACK + TITLE -->
                    <div class="d-flex align-items-center mb-3 position-relative">

                        <!-- Back link (left) -->
                        <p class="mt-2 mb-0">
                            <a href="<?php echo htmlspecialchars($returnUrl); ?>"
                                class="theme-primary-text text-nowrap">
                                ← Back
                            </a>
                        </p>

                        <!-- Center title -->
                        <h2 class="position-absolute start-50 translate-middle-x m-0 text-nowrap">
                            Edit Group
                        </h2>

                    </div>


                    <hr>

                    <!-- RENAME GROUP -->
                    <div class="edit-section mb-4">
                        <h5>Rename Group</h5>
                        <form action="api/group-chat-api.php?action=update_name" method="post"
                            class="d-flex gap-2 mt-2">
                            <input type="hidden" name="group_guid"
                                value="<?php echo htmlspecialchars($groupGuid, ENT_QUOTES, 'UTF-8'); ?>">

                            <input type="text" name="group_name" class="form-control" minlength="3" maxlength="50"
                                placeholder="Loading group name..." required>

                            <button type="submit" class="app-btn app-btn-primary app-btn-fixed"><i
                                    class="fa-solid fa-save"></i>Save</button>
                        </form>
                    </div>

                    <!-- GROUP IMAGE -->
                    <div class="edit-section mb-4">
                        <h5>Group Image</h5>

                        <!-- Current Image (shows loading state until API loads) -->
                        <div class="mb-2">
                            <div id="currentGroupImageContainer" class="group-round-img-container">
                                <i class="fas fa-spinner fa-spin text-muted"></i>
                            </div>
                            <img id="currentGroupImage" src="" alt="Current group image" class="group-round-img d-none">


                            <form action="api/group-chat-api.php?action=update_image" method="post"
                                enctype="multipart/form-data" class="mt-2" id="groupImageForm">

                                <input type="hidden" name="group_guid"
                                    value="<?php echo htmlspecialchars($groupGuid, ENT_QUOTES, 'UTF-8'); ?>">

                                <div class="input-group mb-2">
                                    <input type="file" name="group_image" class="form-control"
                                        accept="image/jpeg,image/jpg,image/png,image/gif">
                                    <button type="submit" class="app-btn app-btn-primary">
                                        <i class="fa-solid fa-upload"></i> Upload
                                    </button>
                                </div>
                            </form>

                            <form action="api/group-chat-api.php?action=delete_image" method="post"
                                id="groupDeleteImageForm" class="d-none">
                                <input type="hidden" name="group_guid"
                                    value="<?php echo htmlspecialchars($groupGuid, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="app-btn app-btn-outline-secondary">
                                    <i class="fas fa-trash"></i> Delete Image
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- MEMBERS LIST -->
                    <div class="edit-section mb-4">
                        <h5>Members (<span id="memberCount">Loading...</span>)</h5>

                        <div class="members-box">
                            <div id="current-members-list">
                                <p class="text-muted text-center m-2">Loading members...</p>
                            </div>
                        </div>
                    </div>

                    <!-- ADD MEMBERS -->
                    <div class="edit-section mb-4">
                        <h5>Add Members</h5>

                        <input type="search" id="searchFriendsInput" class="form-control mb-2"
                            placeholder="Search friends..." maxlength="30">

                        <!-- All inside the form -->
                        <form action="api/group-chat-api.php?action=add_members" method="post" class="mt-2">

                            <input type="hidden" name="group_guid"
                                value="<?php echo htmlspecialchars($groupGuid, ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="members-box" id="addMembersBox">
                                <div id="available-friends-list">
                                    <p class="text-muted text-center m-2">Loading friends...</p>
                                </div>
                            </div>

                            <div class="form-text mt-2">
                                <span id="selectedCount">0</span> friends selected
                            </div>

                            <button type="submit" class="app-btn app-btn-success mt-3" id="addMembersBtn">
                                <i class="fa-solid fa-user-plus"></i> Add Selected Members
                            </button>

                        </form>
                    </div>

                    <!-- DELETE GROUP -->
                    <div class="mt-4">
                        <button type="button" class="app-btn app-btn-outline-danger" id="deleteGroupBtn">
                            <i class="fa-solid fa-users-slash"></i> Delete Group
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>
</main>

<!-- All JavaScript functionality is handled by static/js/group-edit-page.js -->

<?php require 'footer.php'; ?>