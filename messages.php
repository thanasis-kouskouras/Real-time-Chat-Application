<?php
require 'header.php';
?>
<main class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div id="card-searchUp" class="card p-4">
                    <h2 class="text-center">Messages</h2><br>
                    <div>
                        <?php
                            list($unDeliveredChat, $error) = getUnReadChat($userid);
                            checkError($error);
                            $queryResults = count($unDeliveredChat);
                            $output = "";
                            if ($queryResults == 0) {
                                $output = "There are no messages!";
                            } else if ($queryResults == 1) {
                                $output = "There is " . $queryResults . " message!";
                            } else if ($queryResults > 1) {
                                $output = "There are " . $queryResults . " messages!";
                            }
                            echo "<h6 class='alert-white'><p class='text-center'>$output</p></h6>";
                            echo "<br>";
                            ?>
                    </div>
                    <div id="card-search" class="card-body">
                        <?php

                            /**
                             * @param mixed $fromUserUid
                             * @param int $total
                             * @param mixed $rqstFromId
                             * @param $imgHtml
                             * @param $message
                             * @return void
                             */
                            function getUnDeliveredChatHtml(mixed $fromUserUid, int $total,
                                                            mixed $rqstFromId, $imgHtml, $message): void
                            {
                                echo "<h6 class='alert-primary'><p class='text-center'>Total <b> $total </b> new messages from $fromUserUid. ($message)</p></h6>";
                                echo "<div class='form-group mb-0 d-flex justify-content-between'>
                                        <div id='left'>";
                                echo $imgHtml;
                                echo "<strong id='black'> " . $fromUserUid . " </strong>";

                                echo "</div>
                                        <div id='right'>";
                                echo "<button id='buttonFriend' class='btn btn-success' onclick=friendManager('chat',{$rqstFromId},false,'','chatbox.php') >Chat</button>";
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

                            $isEncrypted = $GLOBALS['isEncrypted'];
                            foreach ($unDeliveredChat as $currentChat) {
                                $name = $currentChat['fromName'];
                                $fromId = $currentChat['fromId'];
                                $total = $currentChat['total'];
                                list($fromProfileImg, $error) = getProfileImage($fromId);
                                checkError($error);
                                $imgHtml = getImgHtml($fromProfileImg, $fromId);
                                //get last message
                                list($currentLastMessage, $error) = getLastChat($fromId, $userid);
                                checkError($error);
                                $message = $currentLastMessage['chatMessage'];
                                if ($isEncrypted) {
                                    $message = decrypt($message);
                                }
                                if ($currentLastMessage['url'] !== null) {
                                    $message = "Attachment";
                                }
                                $message = substr($message, 0, 10) . '..';
                                getUnDeliveredChatHtml($name, $total, $fromId, $imgHtml, $message);
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