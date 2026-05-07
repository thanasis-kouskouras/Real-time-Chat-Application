<?php
/* GROUP DETAILS ENDPOINT
 
GET /api/group-chat-api.php?action=details&group_guid=X

Retrieves detailed information about a specific group.
Includes group metadata, member list with roles and online status. */

require_once __DIR__ . '/../../includes/upload/UploadController.php';
require_once __DIR__ . '/../../includes/dbh.inc.php';

//Only allow GET requests
if ($method !== 'GET') {
    sendError("Method not allowed. Use GET.", 405);
}

//Rate limit (max 20 group detail requests per minute)
checkRateLimit($user_guid, 'group_details', 20, 60);

//Get group_guid parameter
$groupGuid = $_GET['group_guid'] ?? '';

if (empty($groupGuid)) {
    sendError("Group GUID is required", 400);
}

//Validate GUID format
if (!isValidGuid($groupGuid)) {
    sendError("Invalid group GUID format", 400);
}

//Get group data directly
try {
    $conn = getDbConnection();
    
    //Get group metadata 
    $stmt = $conn->prepare("
        SELECT 
            bin_to_uuid(group_guid, true) as group_guid,
            group_name,
            group_image,
            bin_to_uuid(creator_guid, true) as creator_guid,
            created_at,
            updated_at,
            is_active,
            max_members,
            last_activity
        FROM group_chats 
        WHERE group_guid = uuid_to_bin(?, true)
    ");
    
    if (!$stmt) {
        throw new RuntimeException('Database error: ' . mysqli_error($conn));
    }
    
    $stmt->bind_param("s", $groupGuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if (!$group) {
        sendError("Group not found", 404);
    }
    
    //Verify user is group member
    $memberStmt = $conn->prepare("
        SELECT 1 FROM group_members 
        WHERE group_guid = uuid_to_bin(?, true) 
        AND user_guid = uuid_to_bin(?, true)
    ");
    
    if (!$memberStmt) {
        throw new RuntimeException('Database error: ' . mysqli_error($conn));
    }
    
    $memberStmt->bind_param("ss", $groupGuid, $user_guid);
    $memberStmt->execute();
    $memberResult = $memberStmt->get_result();
    $isMember = $memberResult->fetch_assoc();
    $memberStmt->close();
    
    if (!$isMember) {
        sendError("You are not a member of this group", 403);
    }
    
    //Get group statistics
    $statsStmt = $conn->prepare("
        SELECT 
            COUNT(*) as member_count,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count
        FROM group_members 
        WHERE group_guid = uuid_to_bin(?, true)
    ");
    
    if (!$statsStmt) {
        throw new RuntimeException('Database error: ' . mysqli_error($conn));
    }
    
    $statsStmt->bind_param("s", $groupGuid);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    $statsStmt->close();
    
    //Get message count (placeholder, depends on message table structure)
    $stats['message_count'] = 0;
    
    //Get member list with roles and online status
    $membersStmt = $conn->prepare("
        SELECT
            bin_to_uuid(gm.user_guid, true) as user_guid,
            u.user_username,
            gm.role,
            gm.joined_at,
            u.user_status,
            u.user_banned,
            gm.last_read_at,
            0 as unread_count
        FROM group_members gm
        JOIN users u ON gm.user_guid = u.user_guid
        WHERE gm.group_guid = uuid_to_bin(?, true)
        ORDER BY gm.role DESC, gm.joined_at ASC
    ");
    
    if (!$membersStmt) {
        throw new RuntimeException('Database error: ' . mysqli_error($conn));
    }
    
    $membersStmt->bind_param("s", $groupGuid);
    $membersStmt->execute();
    $membersResult = $membersStmt->get_result();
    $members = [];
    
    while ($member = $membersResult->fetch_assoc()) {
        $members[] = [
            'user_guid' => $member['user_guid'],
            'user_username' => $member['user_username'],
            'role' => $member['role'],
            'joined_at' => $member['joined_at'],
            'is_online' => ($member['user_status'] === 'Active'),
            'last_read_at' => $member['last_read_at'],
            'unread_count' => (int)$member['unread_count'],
            'profile_image_url' => getProfileImageUrlByGuid($member['user_guid']),
            'user_status' => $member['user_status'],
            'user_banned' => (int)$member['user_banned']
        ];
    }
    $membersStmt->close();
    
    //Determine current user's role
    $userRole = 'member';
    foreach ($members as $member) {
        if ($member['user_guid'] === $user_guid && $member['role'] === 'admin') {
            $userRole = 'admin';
            break;
        }
    }
    
    //Initialize upload controller for group image URL
    $uploadController = new UploadController(getDbConnection());
    
} catch (Exception $e) {
    app_log("Group details - Database error: " . $e->getMessage());
    sendError("Failed to retrieve group details", 500);
}

//Build response with GUID identifiers only
$response = [
    'group' => [
        'group_guid' => $group['group_guid'], 
        'group_name' => $group['group_name'],
        'group_image' => $group['group_image'],
        'group_image_url' => $uploadController->getGroupImageUrl($groupGuid),
        'creator_guid' => $group['creator_guid'],
        'created_at' => $group['created_at'],
        'updated_at' => $group['updated_at'],
        'is_active' => (bool)$group['is_active'],
        'max_members' => (int)$group['max_members'],
        'member_count' => (int)$stats['member_count'],
        'admin_count' => (int)$stats['admin_count'],
        'message_count' => (int)$stats['message_count'],
        'last_activity' => $group['last_activity']
    ],
    'members' => $members,
    'user_role' => $userRole
];

//Return success response
sendResponse(true, $response, "", 200);