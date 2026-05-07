<?php
/* GROUP CHAT API ENDPOINTS

Handles all HTTP API requests for group chat functionality. */

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/auth.php'; 
require_once __DIR__ . '/../includes/encryption.php'; 
require_once __DIR__ . '/../includes/db/group_chats.php';
require_once __DIR__ . '/../includes/db/group_members.php';
require_once __DIR__ . '/../includes/db/group_messages.php';
require_once __DIR__ . '/../includes/db/addrequest.php';
require_once __DIR__ . '/../includes/db/profileImage.php';

//Set JSON response header
header('Content-Type: application/json');

//Verify group admin authorization (checks if user has admin role in the specified group)
function requireGroupAdminByGuid($groupGuid, $user_guid) {
    if (!isGroupAdminByGuid($groupGuid, $user_guid)) {
        sendError("Access denied. This action requires group admin privileges.", 403);
    }
    return true;
}

//Verify group exists and is active
function requireActiveGroupByGuid($groupGuid) {
    list($group, $error) = getGroupChatByGuid($groupGuid);
    
    if (!$group) {
        sendError($error ?: "Group not found", 404);
    }
    
    if (!$group['is_active']) {
        sendError("This group is no longer active", 400);
    }
    
    return $group;
}

//Validate and sanitize group name
function validateGroupName($groupName) {
    //Trim whitespace
    $groupName = trim($groupName);
    
    //Check if empty after trim
    if (empty($groupName)) {
        sendError("Group name cannot be empty", 400);
    }
    
    //Check length (3-50 characters)
    $originalLength = mb_strlen($groupName, 'UTF-8');
    if ($originalLength < 3) {
        sendError("Group name must be at least 3 characters long", 400);
    }
    
    if ($originalLength > 50) {
        sendError("Group name cannot exceed 50 characters", 400);
    }
    
    //Check for potentially malicious patterns before encoding
    if (preg_match('/<script|javascript:|onerror=|onclick=/i', $groupName)) {
        sendError("Group name contains invalid characters", 400);
    }
    
    return $groupName;
}

//Get action parameter
$action = $_GET['action'] ?? '';

// Authenticate user
try {
    $user = requireApiAuthentication();

    //Get user GUID for API operations
    if (!isset($user['user_guid']) || !$user['user_guid']) {
        throw new RuntimeException("User GUID not found in authentication data");
    }

    $user_guid = $user['user_guid'];
} catch (Exception $e) {
    app_log($e->getMessage());
    sendError("Authentication failed", 401);
}

//Get request method
$method = $_SERVER['REQUEST_METHOD'];

//Route requests based on action
try {
    switch ($action) {
        case 'create':
            require_once __DIR__ . '/group-chat-endpoints/create-group.php';
            break;
            
        case 'list':
            require_once __DIR__ . '/group-chat-endpoints/list-groups.php';
            break;
            
        case 'details':
            require_once __DIR__ . '/group-chat-endpoints/group-details.php';
            break;
            
        case 'messages':
            require_once __DIR__ . '/group-chat-endpoints/get-messages.php';
            break;

        case 'remove_member':
            require_once __DIR__ . '/group-chat-endpoints/remove-member.php';
            break;
            
        case 'leave':
            require_once __DIR__ . '/group-chat-endpoints/leave-group.php';
            break;
            
        case 'update_settings':
            require_once __DIR__ . '/group-chat-endpoints/update-settings.php';
            break;
            
        case 'update_name':
            require_once __DIR__ . '/group-chat-endpoints/update-name.php';
            break;
            
        case 'update_image':
            require_once __DIR__ . '/group-chat-endpoints/update-image.php';
            break;
            
        case 'delete_image':
            require_once __DIR__ . '/group-chat-endpoints/delete-image.php';
            break;
            
        case 'add_members':
            require_once __DIR__ . '/group-chat-endpoints/add-members.php';
            break;
            
        case 'delete':
            require_once __DIR__ . '/group-chat-endpoints/delete-group.php';
            break;
            
        case 'get_combined_unread_count':
            require_once __DIR__ . '/group-chat-endpoints/get-combined-unread-count.php';
            break;
            
        case 'get_combined_notification_count':
            require_once __DIR__ . '/group-chat-endpoints/get-combined-notification-count.php';
            break;

        case 'get_group_notifications':
            require_once __DIR__ . '/group-chat-endpoints/get-group-notifications.php';
            break;

        case 'acknowledge_group_notification':
            require_once __DIR__ . '/group-chat-endpoints/acknowledge-group-notification.php';
            break;

        case 'acknowledge_group_notifications_by_group':
            require_once __DIR__ . '/group-chat-endpoints/acknowledge-group-notifications-by-group.php';
            break;

        case 'admin_leave':
            require_once __DIR__ . '/group-chat-endpoints/admin-leave-group.php';
            break;

        default:
            sendError("Invalid action specified", 400);
    }
} catch (Exception $e) {
    app_log($e->getMessage());
    sendError("An error occurred", 500);
}