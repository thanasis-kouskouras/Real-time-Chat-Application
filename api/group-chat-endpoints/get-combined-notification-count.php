<?php
/* GET COMBINED NOTIFICATION COUNT ENDPOINT

GET /api/group-chat-api.php?action=get_combined_notification_count
 
Returns combined notification count for friend requests and group notifications. */

require_once __DIR__ . '/../../includes/db/group_notifications.php';

//Only allow GET requests
if ($method !== 'GET') {
    sendError("Method not allowed. Use GET.", 405);
}

//Rate limit (30 requests per minute per user)
checkRateLimit($user_guid, 'get_combined_notification_count', 30, 60);

try {
    $conn = getDbConnection();

    //Count pending friend requests
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM addrequest
        WHERE request_to_guid = uuid_to_bin(?, true)
        AND request_status = 'Pending'
        AND request_notification_status = 'Yes'
    ");
    $stmt->bind_param("s", $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();
    $friendRequestCount = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();

    //Count pending group notifications
    $groupNotificationCount = getGroupNotificationCountByGuid($user_guid);

    $totalNotifications = $friendRequestCount + $groupNotificationCount;

    sendResponse(true, [
        'notification_count' => $totalNotifications,
        'friend_requests' => $friendRequestCount,
        'group_notifications' => $groupNotificationCount
    ]);

} catch (Exception $e) {
    app_log("Error getting combined notification count: " . $e->getMessage());
    sendResponse(true, [
        'notification_count' => 0,
        'friend_requests' => 0,
        'group_notifications' => 0
    ]); //Fail gracefully
}