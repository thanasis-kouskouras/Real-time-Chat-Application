<?php
/* NOTIFICATION HELPER FUNCTION
  
Notification counters function. */

require_once __DIR__ . '/db/addrequest.php';
require_once __DIR__ . '/db/chatbox.php';
require_once __DIR__ . '/db/users.php';
require_once __DIR__ . '/db/group_notifications.php';

function getNotifyCountersByGuid($user_guid): array
{
    list($user, $error) = getUserByGuid($user_guid);
    if ($error !== "" || !is_array($user)) {
        return [$error, 0, 0];
    }

    $user_guid = $user['user_guid'];

    //Get friend request notifications
    list($resultPending, $error) = getPendingNotificationsByGuid($user_guid);
    if ($error !== "")
        return [$error, 0, 0];
    $friendRequestCount = count($resultPending);

    //Get group notifications count
    $groupNotificationCount = getGroupNotificationCountByGuid($user_guid);

    //Combined notification counter
    $notificationCounter = $friendRequestCount + $groupNotificationCount;

    //Count unread direct messages (status 0 or 1)
    list($resultUnReadChat, $error) = getUnReadChatByGuid($user_guid);
    if ($error !== "")
        return [$error, 0, 0];
    $chatCounter = count($resultUnReadChat);

    return array("", $notificationCounter, $chatCounter);
}