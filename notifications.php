<?php
require 'header.php';
?>
<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div id='card-searchUp' class="card p-4">
                    <h2 class="text-center">Notifications</h2><br>
                    <div>
                        <?php
                            list($notifications, $error) = getPendingNotifications($userid);
                            checkError($error);
                            $queryResults = count($notifications);
                            $output = "";
                            if ($queryResults == 0) {
                                $output = "There are no notifications!";
                            } else if ($queryResults == 1) {
                                $output = "There is " . $queryResults . " notification!";
                            } else if ($queryResults > 1) {
                                $output = "There are " . $queryResults . " notifications!";
                            }
                            echo "<h6 class='alert-white'><p class='text-center'>$output</p></h6>";
                            echo "<br>";
                            ?>
                    </div>
                    <div id="card-search" class="card-body">
                        <?php
                            /**
                             * @param mixed $fromUserUid
                             * @param mixed $rqstDatetime
                             * @param mixed $rqstFromId
                             * @param $imgHtml
                             * @return void
                             */
                            function getUnDeliveredChatHtml(mixed $fromUserUid, int $total, mixed $rqstFromId, $imgHtml): void
                            {
                                echo "<h6 class='alert-primary'><p class='text-center'>Total <b> $total </b> new messages from $fromUserUid.</p></h6>";
                                echo "<div class='form-group mb-0 d-flex justify-content-between'>
                                        <div id='left'>";
                                echo $imgHtml;
                                echo "<strong id='black'> " . $fromUserUid . " </strong>";

                                echo "</div>
                                        <div id='right'>";
                                echo "<button id='buttonFriend' class='btn btn-success' onclick=friendManager('chat',{$rqstFromId},false,'','chatbox.php') >Chat</button>";
                                echo "</div></div>";
                            }

                            /**
                             * @param mixed $fromUserUid
                             * @param mixed $rqstDatetime
                             * @param mixed $rqstFromId
                             * @param $imgHtml
                             * @param bool|array|null $notificationRequest
                             * @return void
                             */
                            function generateNotificationHtml(mixed $fromUserUid, mixed $rqstDatetime, mixed $rqstFromId, $imgHtml, bool|array|null $notificationRequest): void
                            {
                                echo "<h6 class='alert-primary'><p class='text-center'>Friend request from $fromUserUid. Sent on $rqstDatetime.</p></h6>";
                                echo "<div class='form-group mb-0 d-flex justify-content-between'>
                                        <div id='left'>";
                                echo $imgHtml;
                                echo "<strong id='black'> " . $fromUserUid . " </strong>";
                                echo "</div>
                                        <div id='right'>";
                                if ($notificationRequest > 0) {
                                    if ($notificationRequest['rqstStatus'] == "Pending") {
                                        echo "<button id='buttonFriendActions' class='btn btn-success' onclick=friendManager('accept',{$rqstFromId},false,'','notifications.php') name='acceptrequest' >Accept</button>";
                                        echo '<i> </i>';
                                        echo "<button id='buttonFriendActions' class='btn btn-danger' onclick=friendManager('reject',{$rqstFromId},false,'','notifications.php') name='rejectrequest' >Decline</button>";
                                    }
                                }
                                echo "</div></div>";
                                echo "<br>";
                            }

                            /**
                             * @param array $fromProfileImg
                             * @param mixed $rqstFromId
                             * @return string
                             */
                            function getImgHtml(array $fromProfileImg, mixed $rqstFromId): string
                            {
                                $imgHtml = "<img id='profileImage' src='img/profiledefault.jpg' >";
                                if ($fromProfileImg['imgType'] == "jpg") {
                                    if ($fromProfileImg['imgStatus'] == 0) {
                                        $imgHtml = "<img id='profileImage' src='uploads/profile" . $rqstFromId . "." . $fromProfileImg['imgType'] . "?" . mt_rand() . "'>";
                                    }
                                } else {
                                    $imgHtml = "<img id='profileImage' src='uploads/profile" . $rqstFromId . "." . $fromProfileImg['imgType'] . "?" . mt_rand() . "'>";
                                }
                                return $imgHtml;
                            }

                            foreach ($notifications as $notification) {
                                $rqstFromId = $notification['rqstFromId'];
                                $RequestIDFromId = $notification['RequestID'];
                                $rqstStatus = $notification['rqstStatus'];
                                $rqstNotificationStatus = $notification['rqstNotificationStatus'];
                                $rqstDatetime = $notification['rqstDatetime'];
                                list($fromProfileImg, $error) = getProfileImage($rqstFromId);
                                checkError($error);
                                list($notificationRequest, $error) = getNotificationFromTo($rqstFromId, $userid);
                                checkError($error);
                                if ($fromProfileImg) {
                                    list($fromUser, $error) = getUserById($rqstFromId);
                                    checkError($error);
                                    $fromUserUid = $fromUser['usersUsername'];
                                    $imgHtml = getImgHtml($fromProfileImg, $rqstFromId);
                                    generateNotificationHtml($fromUserUid, $rqstDatetime, $rqstFromId, $imgHtml, $notificationRequest);
                                }
                            }
                            ?>
                    </div>
                    <?php
                        require 'footerMessage.php';
                        ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
require 'footer.php';
?>