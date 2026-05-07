<?php
require_once(dirname(__FILE__) . '/../dbh.inc.php');
require_once(dirname(__FILE__) . '/../guid-utilities.php');

//Get all members of a group
function getGroupMembersByGuid($groupGuid): array
{
    $conn = getDbConnection();
    $error = "Failed to retrieve group members. Please try again.";
    
    $sql = "SELECT bin_to_uuid(gm.group_guid, true) as group_guid,
                   bin_to_uuid(gm.user_guid, true) as user_guid, gm.role, gm.joined_at,
                   gm.last_read_at, gm.unread_count,
                   u.user_username, u.user_banned
            FROM group_members gm
            INNER JOIN users u ON gm.user_guid = u.user_guid
            WHERE gm.group_guid = uuid_to_bin(?, true)
            ORDER BY gm.role DESC, gm.joined_at ASC";
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
    $rows = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    return array($rows, "");
}


//Check if a user is a member of a group
function isGroupMemberByGuid($groupGuid, $user_guid): bool
{
    //Validate GUID formats
    if (!isValidGuid($groupGuid) || !isValidGuid($user_guid)) {
        return false;
    }
    
    $conn = getDbConnection();
    
    $sql = "SELECT COUNT(*) as count FROM group_members gm 
            WHERE gm.group_guid = uuid_to_bin(?, true) 
            AND gm.user_guid = uuid_to_bin(?, true)";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $groupGuid, $user_guid);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $row && $row['count'] > 0;
}

//Check if a user is an admin of a group
function isGroupAdminByGuid($groupGuid, $user_guid): bool
{
    $conn = getDbConnection();
    
    $sql = "SELECT 1 FROM group_members 
            WHERE group_guid = uuid_to_bin(?, true) 
            AND user_guid = uuid_to_bin(?, true) 
            AND role = 'admin'";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $groupGuid, $user_guid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $isAdmin = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    
    return $isAdmin;
}

//Add a member to a group
function addGroupMemberByGuid($groupGuid, $user_guid, $role = 'member'): array
{
    $conn = getDbConnection();
    $error = "Failed to add group member. Please try again.";
    
    //Validate role
    if (!in_array($role, ['admin', 'member'])) {
        return array(false, "Invalid role. Must be 'admin' or 'member'.");
    }
    
    //Check if user is already a member
    if (isGroupMemberByGuid($groupGuid, $user_guid)) {
        return array(false, "User is already a member of this group.");
    }
    
    //Check if group has reached max members
    $countSql = "SELECT COUNT(*) as member_count, gc.max_members 
                 FROM group_members gm
                 INNER JOIN group_chats gc ON gm.group_guid = gc.group_guid
                 WHERE gm.group_guid = uuid_to_bin(?, true)
                 GROUP BY gc.max_members";
    $countStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($countStmt, $countSql)) {
        mysqli_stmt_close($countStmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($countStmt, "s", $groupGuid);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countData = mysqli_fetch_assoc($countResult);
    mysqli_stmt_close($countStmt);
    
    if ($countData && $countData['member_count'] >= $countData['max_members']) {
        return array(false, "Group has reached maximum member limit.");
    }
    
    //Add the member
    $sql = "INSERT INTO group_members (group_guid, user_guid, role, joined_at) 
            VALUES (uuid_to_bin(?, true), uuid_to_bin(?, true), ?, NOW())";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "sss", $groupGuid, $user_guid, $role);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_close($stmt);
    
    //Get the added member data
    $getMemberSql = "SELECT bin_to_uuid(group_guid, true) as group_guid,
                            bin_to_uuid(user_guid, true) as user_guid, role, joined_at,
                            last_read_at, unread_count
                     FROM group_members
                     WHERE group_guid = uuid_to_bin(?, true) AND user_guid = uuid_to_bin(?, true)";
    $getMemberStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($getMemberStmt, $getMemberSql)) {
        mysqli_stmt_close($getMemberStmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($getMemberStmt, "ss", $groupGuid, $user_guid);
    mysqli_stmt_execute($getMemberStmt);
    $memberResult = mysqli_stmt_get_result($getMemberStmt);
    $memberData = mysqli_fetch_assoc($memberResult);
    mysqli_stmt_close($getMemberStmt);
    
    return array($memberData, "");
}

function getUserGroupChatsByGuid($user_guid): array
{
    // Validate GUID format
    if (!isValidGuid($user_guid)) {
        return array(false, "Invalid user GUID format");
    }
    
    $conn = getDbConnection();
    $error = "Failed to retrieve user group chats. Please try again.";
    
    $sql = "SELECT gc.group_name, gc.group_image,
                   gc.created_at, gc.updated_at, gc.is_active, gc.max_members, gc.message_count,
                   gc.last_activity, gm.role,
                   gm.unread_count as unread_count,
                   bin_to_uuid(gc.group_guid, true) as group_guid,
                   bin_to_uuid(gc.creator_guid, true) as creator_guid,
                   (SELECT COUNT(*) FROM group_members WHERE group_guid = gc.group_guid) as member_count
            FROM group_chats gc
            INNER JOIN group_members gm ON gc.group_guid = gm.group_guid
            WHERE gm.user_guid = uuid_to_bin(?, true) AND (gc.is_active = 1 OR gm.role = 'admin')
            ORDER BY gc.is_active DESC, gc.last_activity DESC";
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

//Remove a member from a group
function removeGroupMemberByGuid($groupGuid, $user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to remove group member. Please try again.";
    
    //Check if user is a member
    if (!isGroupMemberByGuid($groupGuid, $user_guid)) {
        return array(false, "User is not a member of this group.");
    }
    
    //Check if this is the last admin
    if (isGroupAdminByGuid($groupGuid, $user_guid)) {
        $adminCountSql = "SELECT COUNT(*) as admin_count FROM group_members 
                          WHERE group_guid = uuid_to_bin(?, true) AND role = 'admin'";
        $adminCountStmt = mysqli_stmt_init($conn);
        if (!mysqli_stmt_prepare($adminCountStmt, $adminCountSql)) {
            mysqli_stmt_close($adminCountStmt);
            return array(false, $error);
        }
        mysqli_stmt_bind_param($adminCountStmt, "s", $groupGuid);
        mysqli_stmt_execute($adminCountStmt);
        $adminCountResult = mysqli_stmt_get_result($adminCountStmt);
        $adminCountData = mysqli_fetch_assoc($adminCountResult);
        mysqli_stmt_close($adminCountStmt);
        
        if ($adminCountData['admin_count'] <= 1) {
            return array(false, "Cannot remove the last admin. Promote another member first.");
        }
    }
    
    //Remove the member
    $sql = "DELETE FROM group_members WHERE group_guid = uuid_to_bin(?, true) AND user_guid = uuid_to_bin(?, true)";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $groupGuid, $user_guid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_close($stmt);
    return array(true, "");
}

//Update a member's role in a group
function updateMemberRoleByGuid($groupGuid, $userGuid, $newRole): array
{
    $conn = getDbConnection();
    $error = "Failed to update member role. Please try again.";

    //Validate GUID formats
    if (!isValidGuid($groupGuid) || !isValidGuid($userGuid)) {
        return array(false, "Invalid GUID format.");
    }

    //Validate role
    if (!in_array($newRole, ['admin', 'member'])) {
        return array(false, "Invalid role. Must be 'admin' or 'member'.");
    }

    //Check if user is a member
    if (!isGroupMemberByGuid($groupGuid, $userGuid)) {
        return array(false, "User is not a member of this group.");
    }

    $sql = "UPDATE group_members
            SET role = ?
            WHERE group_guid = uuid_to_bin(?, true)
            AND user_guid = uuid_to_bin(?, true)";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "sss", $newRole, $groupGuid, $userGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affectedRows === 0) {
        return array(false, "No changes made - member may already have this role.");
    }

    return array(true, "");
}

// Clear unread counts for all members of a group (used when a group is deactivated to prevent permanent unread indicators)
function clearGroupUnreadCountsByGuid($groupGuid): array
{
    //Validate GUID format
    if (!isValidGuid($groupGuid)) {
        return array(false, "Invalid group GUID format");
    }

    $conn = getDbConnection();
    $error = "Failed to clear unread counts. Please try again.";

    $sql = "UPDATE group_members SET unread_count = 0 WHERE group_guid = uuid_to_bin(?, true)";
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

//Get total unread group count for all groups a user belongs to
function getTotalGroupUnreadCountByGuid($user_guid): int
{
    // Validate GUID format
    if (!isValidGuid($user_guid)) {
        return 0;
    }
    
    $conn = getDbConnection();
    
    //Count groups with unread messages
    $sql = "SELECT COUNT(*) as group_count
            FROM group_members gm
            WHERE gm.user_guid = uuid_to_bin(?, true)
            AND gm.unread_count > 0";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    
    mysqli_stmt_bind_param($stmt, "s", $user_guid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $data && $data['group_count'] ? (int)$data['group_count'] : 0;
}