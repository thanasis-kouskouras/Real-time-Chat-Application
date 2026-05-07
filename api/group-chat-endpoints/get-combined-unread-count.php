<?php
/* GET COMBINED UNREAD COUNT ENDPOINT
 
 GET /api/group-chat-api.php?action=get_combined_unread_count
 
 Returns combined unread count for one-on-one chats and group chats. */

//Only allow GET requests
if ($method !== 'GET') {
    sendError("Method not allowed. Use GET.", 405);
}

//Rate limit (30 requests per minute per user)
checkRateLimit($user_guid, 'get_combined_unread_count', 30, 60);

try {
    $conn = getDbConnection();
    
    /* Count one-on-one unread messages with status 0 (Undelivered) or 1 (Delivered but not read). 
    Check friend relationship in both directions. */
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT cb.chat_from_guid) as count 
        FROM chatbox cb
        JOIN users u ON cb.chat_from_guid = u.user_guid
        WHERE cb.chat_to_guid = uuid_to_bin(?, true) 
        AND cb.chat_status IN (0, 1)
        AND u.user_is_deleted != 1
        AND u.user_banned != 1
        AND EXISTS (
            SELECT 1 FROM addrequest ar
            WHERE ar.request_status = 'Confirm'
            AND ar.request_notification_status = 'Yes'
            AND (
                (ar.request_from_guid = cb.chat_from_guid AND ar.request_to_guid = cb.chat_to_guid)
                OR
                (ar.request_from_guid = cb.chat_to_guid AND ar.request_to_guid = cb.chat_from_guid)
            )
        )
    ");
    $stmt->bind_param("s", $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();
    $oneOnOneUnread = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    //Count groups with unread messages
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM group_members
        WHERE user_guid = uuid_to_bin(?, true)
        AND unread_count > 0
    ");
    $stmt->bind_param("s", $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();
    $groupUnread = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    $combinedCount = $oneOnOneUnread + $groupUnread;
    
    sendResponse(true, [
        'unread_count' => $combinedCount
    ]);
    
} catch (Exception $e) {
    app_log("Error getting combined unread count: " . $e->getMessage());
    sendResponse(true, ['unread_count' => 0]); //Fail gracefully
}