<?php
/* CREATE GROUP ENDPOINT

POST /api/group-chat-api.php?action=create
 
Creates a new group chat with the specified name and members.
Accepts both JSON and form data (for image upload). */

require_once __DIR__ . '/../../includes/websocket_notifications.php';
require_once __DIR__ . '/../../includes/db/group_notifications.php';
require_once __DIR__ . '/../../includes/upload/UploadController.php';

try {
    //Only allow POST requests
    if ($method !== 'POST') {
        sendError("Method not allowed. Use POST.", 405);
    }

    //Rate limiting (5 group creations per hour)
    checkRateLimit($user_guid, 'create_group', 5, 3600);

    //Handle both JSON and form data
    $data = [];
    $hasImage = false;
    
    //Check if this is a multipart/form-data request has files
    if (!empty($_FILES) || !empty($_POST)) {
        //Form data request
        $data['group_name'] = $_POST['group_name'] ?? '';
        
        //Handle member_guids array from form
        if (isset($_POST['member_guids'])) {
            //Handle JSON string from form data
            if (is_string($_POST['member_guids'])) {
                $decoded = json_decode($_POST['member_guids'], true);
                $data['member_guids'] = is_array($decoded) ? $decoded : [];
            } elseif (is_array($_POST['member_guids'])) {
                $data['member_guids'] = $_POST['member_guids'];
            } else {
                $data['member_guids'] = [];
            }
        } else {
            $data['member_guids'] = [];
        }
        
        //Check for image upload
        $hasImage = isset($_FILES['group_image']) && $_FILES['group_image']['error'] === UPLOAD_ERR_OK;

    } else {
        //JSON request
        $requestBody = file_get_contents('php://input');
        $data = json_decode($requestBody, true);

        if (!$data) {
            sendError("Invalid JSON in request body", 400);
        }
    }

    //Validate required fields
    if (!isset($data['group_name']) || !isset($data['member_guids'])) {
        sendError("Missing required fields: group_name and member_guids", 400);
    }

    //Validate and sanitize group name
    $groupName = validateGroupName($data['group_name']);

    //Validate member IDs array
    $memberGuids = validateUserGuidArray($data['member_guids'] ?? [], 'member_guids');

    //Validate minimum 2 friends selected
    if (count($memberGuids) < 2) {
        sendError("At least 2 friends must be selected to create a group", 400);
    }

    //Validate maximum members (creator + invited = max 50)
    if (count($memberGuids) > 49) {
        sendError("Cannot invite more than 49 members (maximum 50 including creator)", 400);
    }

    //Check all selected users are friends with the creator
    $nonFriends = [];
    foreach ($memberGuids as $memberGuid) {
        
        //Check friendship in both directions
        list($friendship1) = getConfirmedFriendByGuid($user_guid, $memberGuid);
        list($friendship2) = getConfirmedFriendByGuid($memberGuid, $user_guid);
        
        $areFriends = (is_array($friendship1) && count($friendship1) > 0) || 
                      (is_array($friendship2) && count($friendship2) > 0);
        
        if (!$areFriends) {
            $nonFriends[] = $memberGuid;
        }
    }

    if (count($nonFriends) > 0) {
        sendError("All selected users must be your friends. Non-friends: " . implode(", ", $nonFriends), 400);
    }

    //Create the group
    list($group, $error) = createGroupChatByGuid($user_guid, $groupName);

    if (!$group || !isset($group['group_guid'])) {
        sendError($error ?: "Failed to create group chat", 500);
    }

    $groupGuid = $group['group_guid'];
    $groupImageName = null;
    $imageUploadError = null;

    //Handle image upload if present
    if ($hasImage) {
        $file = $_FILES['group_image'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        //Preflight validation (fast fail before resize/processing)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExt, $allowedExtensions)) {
            $imageUploadError = "Invalid image type: $fileExt. Only JPG, PNG, and GIF are allowed.";
            app_log("Invalid image type for group $groupGuid: $fileExt");
        } else if ($file['size'] > 5 * 1024 * 1024) {
            $imageUploadError = "Image file is too large. Maximum size is 5MB.";
            app_log("Image too large for group $groupGuid: {$file['size']} bytes");
        } else {
            try {
                $uploadController = new UploadController(getDbConnection());
                $result = $uploadController->uploadGroupImage($groupGuid, $file);

                if ($result['success']) {
                    $groupImageName = basename($result['data']['file_path']);
                } else {
                    $imageUploadError = $result['error']['message'] ?? 'Image upload failed';
                    app_log("Group image upload failed for $groupGuid: $imageUploadError");
                }
            } catch (Exception $e) {
                $imageUploadError = "Image upload error: " . $e->getMessage();
                app_log("Exception during image upload for group $groupGuid: " . $e->getMessage());
            }
        }
    }

    //Add creator as admin member
    list($creatorMember, $error) = addGroupMemberByGuid($groupGuid, $user_guid, 'admin');

    if (!$creatorMember) {
        //Rollback (deactivate the group if can't add creator)
        deactivateGroupChatByGuid($groupGuid);
        sendError("Failed to add creator to group: " . $error, 500);
    }

    //Add members directly to the group (no invitations)
    $addedMembers = [];
    $failedMembers = [];

    foreach ($memberGuids as $memberGuid) {
        //Add member directly to the group
        list($member, $addError) = addGroupMemberByGuid($groupGuid, $memberGuid, 'member');
        
        if ($member) {
            $addedMembers[] = $memberGuid;
            
            //Send WebSocket notification to the new member directly
            list($memberUser, $memberError) = getUserByGuid($memberGuid);
            if (!$memberError && $memberUser) {
                //Send notification to the member who was added
                $notificationData = [
                    'type' => 'member_joined',
                    'action' => 'member_joined',
                    'group_guid' => $groupGuid,
                    'user_guid' => $memberGuid,
                    'username' => $memberUser['user_username'],
                    'group_name' => $groupName,
                    'joined_at' => date('Y-m-d H:i:s'),
                    'member_count' => count($addedMembers) + 1,
                    'status' => true,
                    'loggedIn' => true
                ];
                sendToUserByGuid($memberGuid, $notificationData);

                //Create database notification for the notifications page
                list($notificationResult, $notificationError) = createGroupNotificationByGuid(
                    $memberGuid,
                    $groupGuid,
                    'added_to_group',
                    $groupName
                );

                if ($notificationResult) {
                    //Send real-time notification to user's notifications page
                    sendGroupNotificationCreatedByGuid(
                        $memberGuid,
                        $notificationResult['notification_guid'],
                        $groupGuid,
                        $groupName,
                        'added_to_group',
                        true
                    );
                } else {
                    app_log("Failed to create notification for member $memberGuid: $notificationError");
                }
            }
        } else {
            $failedMembers[] = [
                'user_guid' => $memberGuid,
                'error' => $addError
            ];
        }
    }

    //Get member count (creator + added members)
    $memberCount = count($addedMembers) + 1;

    //Prepare response
    $responseData = [
        'group_guid' => $groupGuid,
        'group_name' => $groupName,
        'created_at' => $group['created_at'],
        'member_count' => $memberCount,
        'members_added' => count($addedMembers),
        'failed_members' => $failedMembers
    ];

    //Add image info if uploaded
    if ($groupImageName) {
        $responseData['group_image'] = $groupImageName;
        $responseData['image_uploaded'] = true;
    }
    
    //Add image upload error if there was one
    if ($imageUploadError) {
        $responseData['image_upload_error'] = $imageUploadError;
    }

    //Build success message
    $successMessage = "Group created successfully";
    if (count($failedMembers) > 0) {
        $successMessage .= " (Note: " . count($failedMembers) . " member(s) could not be added)";
    }

    //Return success response
    sendResponse(true, $responseData, $successMessage, 201);
    
} catch (Exception $e) {
    app_log("Error in create group: " . $e->getMessage());
    app_log("Stack trace: " . $e->getTraceAsString());
    sendError("Internal server error", 500);
} catch (Error $e) {
    app_log("Fatal error in create group: " . $e->getMessage());
    app_log("Stack trace: " . $e->getTraceAsString());
    sendError("Internal server error", 500);
}