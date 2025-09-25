<?php
/**
 * @param array $result
 * @param $search
 * @return void
 */
function htmlCheckInputAndResult(array $result, $search): void
{
    $queryResults = count($result);
    if (empty($search) !== false) {
        echo "<h6 class='alert-danger'><p class='text-center'>Fill in the Search Field!</p></h6>";
        exit();
    }
    if (invalidUsername($search) !== false) {
        echo "<h6 class='alert-danger'><p class='text-center'>Invalid Username!</p></h6>";
        exit();
    }
    if ($queryResults == 0) {
        echo "<h6 class='alert-danger'><p class='text-center'>There are no matching results!</p></h6>";
        exit();
    }
    if ($queryResults == 1) {
        $string = "There is " . $queryResults . " result!";
    } else if ($queryResults > 1) {
        $string = "There are " . $queryResults . " results!";
    }
    echo "<h6 class='alert-white'><p class='text-center'>$string</p></h6>";
    echo "<br>";
}

?>

<?php
function getData($UserID, $userid): array
{
    list($rowInvt, $error) = getNotificationFromTo($UserID, $userid);
    checkError($error);
    list($rowRqst, $error) = getNotificationFromTo($userid, $UserID);
    checkError($error);
    return array($rowRqst, $rowInvt);
}

/**
 * @param bool|array|null $rowInvt
 * @param bool|array|null $rowRqst
 * @param $toId
 * @return void
 */
function searchResults(bool|array|null $rowInvt, bool|array|null $rowRqst, $toId): void
{
    if ($rowInvt) {
        htmlNotificationStatus2($rowInvt['rqstStatus'], $rowRqst['rqstStatus'], $rowInvt['rqstNotificationStatus'], $toId);
    } else if ($rowRqst) {
        htmlNotificationStatus($rowRqst['rqstStatus'], $rowInvt['rqstStatus'], $rowInvt['rqstNotificationStatus'], $toId);
    } else {
        echo <<<EOD
        <button id="buttonSearchFriend" class='btn btn-primary' onclick=friendManager('add',{$toId})  name='addfriend1' >+Friend Request</button>
        EOD;
    }
}

/**
 * @param $rqstStatus
 * @param $rqstStatus2
 * @param mixed $rqstNotificationStatusInvt
 * @return void
 */
function htmlNotificationStatus2($rqstStatus, $rqstStatus2, mixed $rqstNotificationStatusInvt, $toId): void
{
    if ($rqstStatus == "Pending" && $rqstNotificationStatusInvt == "Yes") {
        echo "<button id='buttonFriendActions' class='btn btn-success' onclick=friendManager('accept',{$toId}) name='acceptrequest' >Accept</button>";
        echo '<i> </i>';
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('reject',{$toId}) name='rejectrequest'>Decline</button>";
    } else if ($rqstStatus2 == "Pending" && $rqstNotificationStatusInvt == "No") {
        echo "<button id='buttonFriendActions' class='btn btn-outline-primary'  name='requestsent' disabled>Pending</button>";
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('cancel',{$toId}) name='cancelrequest' >Cancel</button>";
    } else if ($rqstStatus == "Reject") { 
        echo "<button id='buttonSearchFriend' class='btn btn-primary' onclick=friendManager('add',{$toId}) name='addfriend2' >+Friend Request</button>";
    } else if ($rqstStatus == "Confirm" && $rqstNotificationStatusInvt == "Yes") {
        echo "<button id='buttonFriendActions' class='btn btn-primary' onclick=friendManager('chat',{$toId}) name='chat'>Chat</button>";
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('delete',{$toId}) name='deletefriend'>Delete</button>";
    }
}

/**
 * @param $rqstStatus
 * @param $rqstStatus2
 * @param mixed $rqstNotificationStatus
 * @return void
 */
function htmlNotificationStatus($rqstStatus, $rqstStatus2, mixed $rqstNotificationStatus, $toId): void
{
    if ($rqstStatus == "Pending" && $rqstNotificationStatus == "Yes") {
        echo "<button id='buttonFriendActions' class='btn btn-outline-primary' name='requestsent' disabled>Pending</button>";
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('cancel',{$toId})  name='cancelrequest' >Cancel</button>";
    } else if ($rqstStatus2 == "Pending" && $rqstNotificationStatus == "No") {
        echo "<button id='buttonFriendActions' class='btn btn-success' onclick=friendManager('accept',{$toId}) name='acceptrequest' >Accept</button>";
        echo '<i> </i>';
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('reject',{$toId}) name='rejectrequest' >Decline</button>";
    } else if ($rqstStatus == "Reject" && $rqstNotificationStatus == "No") {
        echo "<button id='buttonFriendActions' class='btn btn-outline-primary' name='requestsent' disabled>Pending</button>";
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('cancel',{$toId}) name='cancelrequest' >Cancel</button>";
    } else if ($rqstStatus2 == "Reject" && $rqstNotificationStatus == "Yes") {
        echo "<button id='buttonSearchFriend' class='btn btn-primary' onclick=friendManager('add',{$toId}) name='addfriend3'>+Friend Request</button>";
    } else if ($rqstStatus == "Confirm" && $rqstNotificationStatus == "Yes") {
        echo "<button id='buttonFriendActions' class='btn btn-primary' onclick=friendManager('chat',{$toId}) name='chat' >Chat</button>";
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('delete',{$toId}) name='deletefriend' >Delete</button>";
    }
}

/**
 * @param $status
 * @param $notificationStatus
 * @param $toId
 * @return void
 */
function getFriendActionButton($status, $notificationStatus, $toId): void
{

    if ($status == "Pending" && $notificationStatus == "Yes") {
        echo "<button id='buttonFriendActions' class='btn btn-success' onclick=friendManager('accept',{$toId})  name='acceptrequest' >Accept</button>";
        echo '<i> </i>';
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('reject',{$toId})   name='rejectrequest' >Decline</button>";
    } else if ($status == "Pending" && $notificationStatus == "No") {
        echo "<button id='buttonFriendActions' class='btn btn-outline-primary' name='requestsent'  disabled>Pending</button>";
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('cancel',{$toId})   name='cancelrequest' >Cancel</button>";
    } else if ($status == "Reject" && $notificationStatus == "No") {
        echo "<button id='buttonSearchFriend' class='btn btn-primary'  onclick=friendManager('add',{$toId})   name='addfriend4' >+Friend Request</button>";
    } else if ($status == "Reject" && $notificationStatus == "Yes") {
        echo "<button id='buttonSearchFriend' class='btn btn-primary' onclick=friendManager('add',{$toId})    name='addfriend5' >+Friend Request</button>";
    } else if ($status == "Confirm" && $notificationStatus == "Yes") {
        echo "<button id='buttonFriendActions' class='btn btn-primary' onclick=friendManager('chat',{$toId})   name='chat' >Chat</button>";
        echo '<i> </i>';
        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('delete',{$toId})   name='deletefriend' >Delete</button>";
    }
}

/**
 * @param bool|array|null $rowInvt
 * @param bool|array|null $rowRqst
 * @param $toId
 * @return void
 */
function getButtons(bool|array|null $rowInvt, bool|array|null $rowRqst, $toId): void
{
    if ($rowInvt) {
        getFriendActionButton($rowInvt['rqstStatus'],
            $rowInvt['rqstNotificationStatus'], $toId);
    } else if ($rowRqst) {
        getFriendActionButton($rowRqst['rqstStatus'],
            $rowRqst['rqstNotificationStatus'], $toId);
    } else {
        echo <<< EOD
        <button id="buttonSearchFriend" class='btn btn-primary' onclick=friendManager('add',{$toId}) name='addfriend6'>+Friend Request</button>
        EOD;
    }
}

/**
 * @param int $imgStatus
 * @param mixed $toId
 * @param $imgType
 * @param mixed $userUsername
 * @param string $search
 * @return void
 */
function profileToHtml(int $imgStatus, mixed $toId, $imgType, mixed $userUsername, string $search): void
{
    echo '<div class="form-group mb-0 d-flex justify-content-between">';
    echo '<div id="left">';
    if ($imgStatus == 1) {
        echo "<img id='profileImage' src='img/profiledefault.jpg'>";
    } else {
        echo "<img id=profileImage src='uploads/profile" . $toId . "." . $imgType . "?" . mt_rand() . "'>";
    }
    echo "<strong id='black'> " . $userUsername . " </strong>";
    echo '</div>';
    echo '<div id="right">';
    echo "<input type='hidden'  name='toId' value='$toId'>";
    echo "<input type='hidden' name='search' value='$search'>";
}

/**
 * @param mixed $row
 * @param string $search
 * @param mixed $currentUserId
 * @return void
 */
function generateHtml(mixed $row, string $search, mixed $currentUserId): void
{
    $toUserUsername = $row['usersUsername'];
    $toUserId = $row['UsersID'];
    list($img, $error) = getProfileImage($toUserId);
    checkError($error);
    if ($toUserId === "$currentUserId") {
        exit(1);
    }
    $imgStatus = $img['imgStatus'];
    list($rowRqst, $rowInvt) = getData($toUserId, $currentUserId);
    if ($img) {
        profileToHtml($imgStatus, $toUserId, $img['imgType'], $toUserUsername, $search);
        if ($img['imgType'] == "jpg") {
            if ($img['imgStatus'] == 0) {
                searchResults($rowInvt, $rowRqst, $toUserId);
            } else getButtons($rowInvt, $rowRqst, $toUserId);
        } else getButtons($rowInvt, $rowRqst, $toUserId);
    }
    echo '</div></div>';
    echo "<p></p>";
}

?>