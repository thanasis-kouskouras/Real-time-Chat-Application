<?php
require_once(dirname(__FILE__) . '/db/addrequest.php');

function addFromToByGuid(string $fromGuid, string $toGuid, string $rqstStatus, string $rqstNotificationStatus): array
{
    $notification = getNotificationFromToByGuid($fromGuid, $toGuid);
    if ($notification === false || $notification === null) {
        $success = addFriendByGuid($fromGuid, $toGuid, $rqstStatus, $rqstNotificationStatus);
        return array($rqstNotificationStatus, $success ? [] : false);
    } else {
        $success = updateNotificationByGuid($fromGuid, $toGuid, $rqstStatus, $rqstNotificationStatus);
        return array($rqstNotificationStatus, $success ? $notification : false);
    }
}

function deleteFriendByGuid(string $fromGuid, string $toGuid, string $status, string $notificationStatus): bool
{
    $success1 = updateNotificationByGuid($fromGuid, $toGuid, $status, $notificationStatus);
    $success2 = updateNotificationByGuid($toGuid, $fromGuid, $status, "No");
    return $success1 && $success2;
}

function acceptRequestByGuid(string $fromGuid, string $toGuid, string $rqstStatus, string $rqstNotificationStatus): bool
{
    //Update the original request (from sender to receiver)
    $success1 = updateNotificationByGuid($fromGuid, $toGuid, $rqstStatus, $rqstNotificationStatus);

    //Create or update the reverse request (from receiver to sender) for bidirectional friendship
    $reverseNotification = getNotificationFromToByGuid($toGuid, $fromGuid);
    if ($reverseNotification === false || $reverseNotification === null) {
        $success2 = createFriendRequestRecord($toGuid, $fromGuid, $rqstStatus, $rqstNotificationStatus);
    } else {
        $success2 = updateNotificationByGuid($toGuid, $fromGuid, $rqstStatus, $rqstNotificationStatus);
    }

    return $success1 && $success2;
}

function rejectRequestByGuid(string $fromGuid, string $toGuid, string $rqstStatus): bool
{
    $rqstNotificationStatus = 'Yes';
    addFromToByGuid($fromGuid, $toGuid, $rqstStatus, $rqstNotificationStatus);
    addFromToByGuid($toGuid, $fromGuid, $rqstStatus, $rqstNotificationStatus);
    return true;
}