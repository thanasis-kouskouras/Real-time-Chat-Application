<?php
/* GET PENDING NOTIFICATIONS ENDPOINT

GET /api/friend-api.php?action=get-pending-notifications

Returns pending friend request notifications with user details. */

require_once __DIR__ . '/../../includes/db/profileImage.php';

//Only allow GET requests
if ($method !== 'GET') {
    sendError("Method not allowed. Use GET.", 405);
}

//Rate limit (max 30 requests per minute)
checkRateLimit($user_guid, 'get_pending', 30, 60);

//Get all pending notifications with user details in one query
$conn = getDbConnection();
$sql = "SELECT
            bin_to_uuid(ar.request_from_guid, true) as request_from_guid,
            ar.request_datetime,
            u.user_username
        FROM addrequest ar
        JOIN users u ON ar.request_from_guid = u.user_guid
        WHERE ar.request_to_guid = uuid_to_bin(?, true)
        AND ar.request_status = 'Pending' 
        AND ar.request_notification_status = 'Yes'
        AND u.user_is_deleted != 1
        AND u.user_banned != 1
        ORDER BY ar.request_datetime DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    app_log("Failed to prepare SQL: " . $conn->error);
    sendError('Database error', 500);
}

$stmt->bind_param("s", $user_guid);
if (!$stmt->execute()) {
    app_log("Failed to execute SQL: " . $stmt->error);
    sendError('Database error', 500);
}

$result = $stmt->get_result();
$notifications = [];

while ($row = $result->fetch_assoc()) {
    //Get profile image URL using the helper function
    $profileImageUrl = getProfileImageUrlByGuid($row['request_from_guid']);
    
    $notifications[] = [
        'fromUserGuid' => $row['request_from_guid'],
        'fromUsername' => $row['user_username'],
        'datetime' => $row['request_datetime'],
        'profileImageUrl' => $profileImageUrl,
        'canRespond' => true //All are pending, so all can be responded to
    ];
}

$stmt->close();

//Success
sendResponse(true, [
    'notifications' => $notifications
], 'Notifications retrieved successfully', 200);