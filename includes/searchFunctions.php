<?php


/**
 * @param mixed $rqstFromId
 * @param mixed $rqstToId
 * @param string $rqstStatus
 * @param string $rqstNotificationStatus
 * @return array
 */
function addFromTo(mixed $rqstFromId, mixed $rqstToId, string $rqstStatus, string $rqstNotificationStatus): array
{
    list($notification,$error) = getNotificationFromTo($rqstFromId, $rqstToId);
    if ($notification == null) {
        createNotification($rqstFromId, $rqstToId, $rqstStatus, $rqstNotificationStatus);
    } else {
        $RequestIDFromId = $notification['RequestID'];
        updateNotification($rqstStatus, $rqstNotificationStatus, $rqstFromId, $rqstToId, $RequestIDFromId);
    }

    return array($rqstNotificationStatus, $notification);
}

/**
 * @param mixed $rqstFromId
 * @param mixed $rqstToId
 * @param string $rqstStatus
 * @param string $rqstNotificationStatus
 * @return string
 */
function addFriend(mixed $rqstFromId, mixed $rqstToId, string $rqstStatus, string $rqstNotificationStatus="Yes"): string
{
    addFromTo($rqstFromId, $rqstToId, $rqstStatus, $rqstNotificationStatus);
    $rqstNotificationStatus = "No";
    addFromTo($rqstToId, $rqstFromId, $rqstStatus, $rqstNotificationStatus);
    return true;
}
/**
 * @param mixed $fromId
 * @param mixed $toId
 * @param mixed $status
 * @param mixed $notificationStatus
 * @return mixed
 */
function deleteFriend(mixed $fromId, mixed $toId, mixed $status, mixed $notificationStatus): mixed
{
    list($notification,$error) = getNotificationFromTo($fromId, $toId);
    if ($notification == null) {
        return false;
    } else {
        $RequestIDFromId = $notification['RequestID'];
        updateNotification($status, $notificationStatus, $fromId, $toId, $RequestIDFromId);
    }

    $notificationStatus = "No";

    list($notification,$error)  = getNotificationFromTo($toId, $fromId);
    if ($notification == null) {
        return false;
    } else {
        $RequestIDToId = $notification['RequestID'];
        updateNotification($status, $notificationStatus, $toId, $fromId, $RequestIDToId);
    }
    return true;
}


/**
 * @param mixed $rqstFromId
 * @param mixed $rqstToId
 * @param string $rqstStatus
 * @param string $rqstNotificationStatus
 * @return string
 */
function acceptRequest(mixed $rqstFromId, mixed $rqstToId, string $rqstStatus, string $rqstNotificationStatus): string
{
    list($notification,$error) = getNotificationFromTo($rqstFromId, $rqstToId);
    if ($notification == null) {
        return false;
    } else {
        $RequestIDFromId = $notification['RequestID'];
        updateNotification($rqstStatus, $rqstNotificationStatus, $rqstFromId, $rqstToId, $RequestIDFromId);
    }
    list($notification,$error)  = getNotificationFromTo($rqstToId, $rqstFromId);
    if ($notification == null) {
        createNotification($rqstToId, $rqstFromId, $rqstStatus, $rqstNotificationStatus);
        return true;
    } else {
        $RequestIDToId = $notification['RequestID'];
        updateNotification($rqstStatus, $rqstNotificationStatus, $rqstToId, $rqstFromId, $RequestIDToId);
    }
    return true;
}

/**
 * @param mixed $rqstFromId
 * @param mixed $rqstToId
 * @param string $rqstStatus
 * @param mixed $search
 * @return false
 */
function rejectRequest(mixed $rqstFromId, mixed $rqstToId, string $rqstStatus, mixed $search): bool
{
    $rqstNotificationStatus = 'Yes';
    addFromTo($rqstFromId, $rqstToId, $rqstStatus, $rqstNotificationStatus);
    addFromTo($rqstToId, $rqstFromId, $rqstStatus, $rqstNotificationStatus);
    return true;
}