<?php

require_once(dirname(__FILE__) . '/../dbh.inc.php');


//Get group chat
function getGroupChatByGuid($groupGuid): array
{
    $conn = getDbConnection();
    $error = "Failed to retrieve group chat. Please try again.";
    
    $sql = "SELECT group_name, group_image, created_at, updated_at, 
                   is_active, max_members, message_count, last_activity,
                   bin_to_uuid(group_guid, true) as group_guid,
                   bin_to_uuid(creator_guid, true) as creator_guid
            FROM group_chats 
            WHERE group_guid = uuid_to_bin(?, true)";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "s", $groupGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    
    if (!$row) {
        return array(false, "Group chat not found.");
    }
    
    return array($row, "");
}

//Create a new group chat using GUID
function createGroupChatByGuid($creatorGuid, $groupName, $maxMembers = 50): array
{
    $conn = getDbConnection();
    $error = "Failed to create group chat. Please try again.";
    
    //Validate group name length
    if (strlen($groupName) < 3 || strlen($groupName) > 50) {
        return array(false, "Group name must be between 3 and 50 characters.");
    }
    
    //Check for duplicate group name for this creator
    $checkSql = "SELECT group_guid FROM group_chats WHERE creator_guid = uuid_to_bin(?, true) AND group_name = ? AND is_active = 1";
    $checkStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($checkStmt, $checkSql)) {
        mysqli_stmt_close($checkStmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($checkStmt, "ss", $creatorGuid, $groupName);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    if (mysqli_num_rows($checkResult) > 0) {
        mysqli_stmt_close($checkStmt);
        return array(false, "You already have an active group with this name.");
    }
    mysqli_stmt_close($checkStmt);
    
    //Create the group
    $sql = "INSERT INTO group_chats (group_name, creator_guid, max_members, created_at, updated_at, last_activity, group_guid) 
            VALUES (?, uuid_to_bin(?, true), ?, NOW(), NOW(), NOW(), uuid_to_bin(uuid(), true))";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "ssi", $groupName, $creatorGuid, $maxMembers);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    //Get the group GUID that was just created
    $getGuidSql = "SELECT bin_to_uuid(group_guid, true) as group_guid FROM group_chats WHERE creator_guid = uuid_to_bin(?, true) AND group_name = ? ORDER BY created_at DESC LIMIT 1";
    $guidStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($guidStmt, $getGuidSql)) {
        mysqli_stmt_close($guidStmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($guidStmt, "ss", $creatorGuid, $groupName);
    mysqli_stmt_execute($guidStmt);
    $guidResult = mysqli_stmt_get_result($guidStmt);
    $guidRow = mysqli_fetch_assoc($guidResult);
    mysqli_stmt_close($guidStmt);
    mysqli_stmt_close($stmt);
    
    if (!$guidRow) {
        return array(false, "Failed to retrieve created group GUID.");
    }
    
    //Get the created group data
    list($group, $getError) = getGroupChatByGuid($guidRow['group_guid']);
    if (!$group) {
        return array(false, $getError);
    }
    
    return array($group, "");
}

//Update group chat settings
function updateGroupChatByGuid($groupGuid, $updates): array
{
    $conn = getDbConnection();
    $error = "Failed to update group chat. Please try again.";
    
    $allowedFields = ['group_name', 'max_members', 'group_image'];
    $setClauses = [];
    $params = [];
    $types = "";
    
    foreach ($updates as $field => $value) {
        if (in_array($field, $allowedFields)) {
            //Validate group_name if being updated
            if ($field === 'group_name') {
                if (strlen($value) < 3 || strlen($value) > 50) {
                    return array(false, "Group name must be between 3 and 50 characters.");
                }
                $types .= "s";
            } elseif ($field === 'max_members') {
                $types .= "i";
            } elseif ($field === 'group_image') {
                $types .= "s";
            }
            $setClauses[] = "$field = ?";
            $params[] = $value;
        }
    }
    
    if (empty($setClauses)) {
        return array(false, "No valid fields to update.");
    }
    
    //Add updated_at timestamp
    $setClauses[] = "updated_at = NOW()";
    
    $sql = "UPDATE group_chats SET " . implode(", ", $setClauses) . " WHERE group_guid = uuid_to_bin(?, true)";
    $types .= "s";
    $params[] = $groupGuid;
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_close($stmt);
    return array(true, "");
}

//Deactivate a group chat
function deactivateGroupChatByGuid($groupGuid): array
{
    $conn = getDbConnection();
    $error = "Failed to deactivate group chat. Please try again.";
    
    $sql = "UPDATE group_chats SET is_active = 0, updated_at = NOW() WHERE group_guid = uuid_to_bin(?, true)";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "s", $groupGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_close($stmt);
    return array(true, "");
}

//Reactivate a deactivated group chat (this is called when a deactivated group reaches 3+ members again)
function reactivateGroupChatByGuid($groupGuid): array
{
    $conn = getDbConnection();
    $error = "Failed to reactivate group chat. Please try again.";

    $sql = "UPDATE group_chats SET is_active = 1, updated_at = NOW() WHERE group_guid = uuid_to_bin(?, true) AND is_active = 0";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "s", $groupGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_close($stmt);
    return array(true, "");
}

//Check if a group chat is active using GUID
function isGroupChatActiveByGuid($groupGuid): bool
{
    $conn = getDbConnection();

    $sql = "SELECT is_active FROM group_chats WHERE group_guid = uuid_to_bin(?, true)";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $groupGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row && $row['is_active'] == 1;
}

/* Get all active groups where the user is currently the admin.
Covers both groups the user created and groups they were promoted into via admin_leave. */
function getGroupsWhereUserIsAdminByGuid($user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to retrieve groups. Please try again.";

    $sql = "SELECT bin_to_uuid(gc.group_guid, true) as group_guid,
                   bin_to_uuid(gc.creator_guid, true) as creator_guid,
                   gc.group_name, gc.group_image, gc.created_at, gc.updated_at,
                   gc.is_active, gc.max_members, gc.message_count, gc.last_activity
            FROM group_chats gc
            INNER JOIN group_members gm ON gc.group_guid = gm.group_guid
            WHERE gm.user_guid = uuid_to_bin(?, true)
              AND gm.role = 'admin'
              AND gc.is_active = 1";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "s", $user_guid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    $resultData = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return array($rows, "");
}

/* Delete all active groups where the user is currently the admin.
Removes all members and deactivates groups (used when a user deletes their account). */
function deleteAllGroupsWhereUserIsAdminByGuid($user_guid): array
{
    $conn = getDbConnection();

    //Get all active groups where this user is the current admin
    list($groups, $error) = getGroupsWhereUserIsAdminByGuid($user_guid);
    if ($error !== "" || !is_array($groups)) {
        return array(false, [], $error ?: "Failed to get groups");
    }

    if (empty($groups)) {
        return array(true, [], ""); //No groups to delete
    }

    $deletedGroups = [];

    mysqli_begin_transaction($conn);

    try {
        foreach ($groups as $group) {
            $groupGuid = $group['group_guid'];

            //Remove all members from the group
            $deleteMembersSql = "DELETE FROM group_members WHERE group_guid = uuid_to_bin(?, true)";
            $deleteMembersStmt = mysqli_stmt_init($conn);
            if (!mysqli_stmt_prepare($deleteMembersStmt, $deleteMembersSql)) {
                mysqli_rollback($conn);
                return array(false, [], "Failed to prepare member deletion");
            }
            mysqli_stmt_bind_param($deleteMembersStmt, "s", $groupGuid);
            if (!mysqli_stmt_execute($deleteMembersStmt)) {
                mysqli_stmt_close($deleteMembersStmt);
                mysqli_rollback($conn);
                return array(false, [], "Failed to remove group members");
            }
            mysqli_stmt_close($deleteMembersStmt);

            //Deactivate the group
            $deactivateSql = "UPDATE group_chats SET is_active = 0, updated_at = NOW() WHERE group_guid = uuid_to_bin(?, true)";
            $deactivateStmt = mysqli_stmt_init($conn);
            if (!mysqli_stmt_prepare($deactivateStmt, $deactivateSql)) {
                mysqli_rollback($conn);
                return array(false, [], "Failed to prepare group deactivation");
            }
            mysqli_stmt_bind_param($deactivateStmt, "s", $groupGuid);
            if (!mysqli_stmt_execute($deactivateStmt)) {
                mysqli_stmt_close($deactivateStmt);
                mysqli_rollback($conn);
                return array(false, [], "Failed to deactivate group");
            }
            mysqli_stmt_close($deactivateStmt);

            $deletedGroups[] = [
                'group_guid' => $groupGuid,
                'group_name' => $group['group_name']
            ];
        }

        mysqli_commit($conn);
        return array(true, $deletedGroups, "");

    } catch (Exception $e) {
        mysqli_rollback($conn);
        return array(false, [], "Error deleting groups: " . $e->getMessage());
    }
}