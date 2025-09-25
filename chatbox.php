<?php
require 'header.php';
require_once('file-upload.php');

$path = $GLOBALS['rootUrl'] . '/static';
echo "<link rel='stylesheet' href=" . $path . "/css/chatbox.css>";
if (isset($_POST["chat"]) || isset($_GET["chat"])) {
    if (isset($_POST["chat"])) {
        $chatToId = filter_var($_POST['rqstFromId'], FILTER_SANITIZE_NUMBER_INT);
        $toUser = $chatToId;
        list($friendData, $error) = getUserById($chatToId);
        if (!is_array($friendData)) {
            echo "<script>window.location='friends.php';</script>";
            exit();
        }
    } else if (isset($_GET["chat"])) {
        $hasAcceptedCall = false;
        $toUser = filter_var($_GET['toUser'], FILTER_SANITIZE_NUMBER_INT);
        list($friendData, $error) = getUserById($toUser);
        $queryResults = 0;
        if (is_array($friendData)) {
            list($friend, $error) = getConfirmedFriend($userid, $toUser);
            $queryResults = count($friend);
        }
        if ($queryResults == 0) {
            echo "<script>window.location='friends.php';</script>";
            exit();
        } else {
            $chatToId = $toUser;
        }
    }
} else {
    echo "<script>window.location='friends.php';</script>";
    exit();
}

/**
 * @param mixed $chatMessage
 * @param mixed $ChatboxId
 * @param null $url
 * @param null $mimetype
 * @param null $chatDate
 * @return void
 */
function generateMyMessage(mixed $chatMessage, mixed $ChatboxId, $url = null, $mimetype = null, $filename = null, $chatDate = null): void
{
    $isDeleted = false;
    $dateMedia = "";
    $spanDatePrint = "";
    $datePrint = "";
    $result = "";

    $button = "<button id='buttonDelete' onclick='delete_message($ChatboxId)' class='alert-white btn-danger' type='submit'>x</button>";
    if ($chatMessage == '<i>Deleted Message</i>') {
        $button = "";
        $isDeleted = true;
    }
    $divClass = 'divFromText';
    $divClass2 = 'divFrom2';
    if (!$isDeleted && $mimetype !== null) {
        if (str_starts_with($mimetype, 'audio')) {
            $button = "<button id='buttonDeleteSound' onclick='delete_message($ChatboxId)' class='alert-white btn-danger' type='submit'>x</button>";
        }
        $result .= $button;
        $result .= getMediaHtml($mimetype, $url, $filename);
        $dateMedia = "<span class='small_span_dark'>  $chatDate</span> <br>";
        $divClass = 'divFrom';
    } else {
        $result .= $button;
        if (!$isDeleted)
            $spanDatePrint = "<br> <span class='small_span_white'> $chatDate</span>";
        $result .= "<p class='pFrom' '>" . $chatMessage . " $spanDatePrint </p>";
    }

    $idMedia = $ChatboxId . "_media";
    $datePrint = "<div id='$idMedia' class='$divClass2'>$dateMedia</div>";
    echo <<< EOD
         <div id='$ChatboxId'  class='$divClass'>
                $result
         </div>
         $datePrint
         EOD;
}

/**
 * @param mixed $imagePath
 * @param $chatMessage
 * @param $ChatboxId
 * @param $url
 * @param $mimetype
 * @param $chatStatus
 * @param $chatIsDeleted
 * @param $chatDate
 * @return void
 */
function generateToMessage(mixed $imagePath, $chatMessage, $ChatboxId, $url, $mimetype, $chatStatus, $chatIsDeleted, $filename, $chatDate): void
{
    $result = "<img id=profileImgInMessage src='$imagePath'>";
    $divClass = 'divToText';
    $divClass2 = 'divTo2';
    $spanDatePrint = "";
    $datePrint = "";
    $dateMedia = "";

    if ($mimetype != null && !$chatIsDeleted) {
        $divClass = 'divTo';
        $result .= getMediaHtml($mimetype, $url, $filename);
        $dateMedia = "<span class='small_span'>  $chatDate</span> <br>";
    } else {
        if (!$chatIsDeleted)
            $spanDatePrint = "<br> <span class='small_span'> $chatDate</span>";
        $result .= "<p class='pTo'>" . $chatMessage . " $spanDatePrint </p>";
    }
    $spanId = "unread_" . $ChatboxId;
    $span = "<span id='$spanId' class='dotBlock'></span>";
    if ($chatStatus == 2)// already read
        $span = "";

    $idMedia = $ChatboxId . "_media";
    $datePrint = "<div id='$idMedia' class='$divClass2'>$dateMedia</div>";
    echo <<< EOD
         <div id='$ChatboxId'  class='$divClass'>
         $span
         $result
         </div>
         $datePrint
         EOD;
}

/**
 * @param $mimetype
 * @param $url
 * @return string
 */
function getMediaHtml($mimetype, $url, $filename): string
{
    $media = "";
    if (str_starts_with($mimetype, 'image')) {
        $media = "<img class='imgChat' src=" . $url . " alt='image' />";
    } else if (str_starts_with($mimetype, 'audio')) {
        $media = " <audio controls='controls'> <source src=" . $url . " type=" . $mimetype . "> </audio>";
    } else if (str_starts_with($mimetype, 'video')) {
        $media = "<video src=" . $url . " controls='controls'> </video>";
    } else if (str_starts_with($mimetype, 'application/')) {
        // Handle document types based on MIME type
        $icon = '📄'; // Default document icon

        // Determine the right icon based on MIME type
        if ($mimetype === 'application/pdf') {
            $icon = '📄'; // PDF document icon
        } else if ($mimetype === 'application/msword' || $mimetype === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $icon = '📄'; // Word document icon
        } else if ($mimetype === 'application/vnd.ms-excel' || $mimetype === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            $icon = '📊'; // Excel spreadsheet icon
        } else if ($mimetype === 'application/vnd.ms-powerpoint' || $mimetype === 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
            $icon = '📑'; // PowerPoint presentation icon
        }

        // Create a styled document link
        $media = "<div class='document-link'>
                   <span class='doc-icon'>{$icon}</span>
                   <span class='doc-name'>{$filename}</span>
                  </div>";
    } else if ($mimetype === 'text/plain') {
        // Handle text files
        $media = "<div class='document-link'>
                   <span class='doc-icon'>📝</span>
                   <span class='doc-name'>{$filename}</span>
                  </div>";
    }

    $result = "<a href=" . $url . " role='link' target='_blank' rel='noopener noreferrer'>" . $media . " </a>";
    return $result;
}
?>
<script>
// Export PHP constants to JavaScript
const MAX_FILE_SIZE = <?php echo MAX_FILE_SIZE; ?>;

// Export allowed file extensions from PHP
const ALLOWED_EXTENSIONS = <?php
        // Get the list of allowed extensions
        $allowedExtensions = getAllowedExtensions();
        echo json_encode(array_keys($allowedExtensions));
        ?>;
</script>
<script src=<?php echo $path . "/js/chatbox.js" ?>></script>
<div class=mainChatDiv>
    <main id="mainMargin" class="d-flex vw-100 responsive-height align-items-center justify-content-center">
        <div class="container mt-5 pt-5 mb-5">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4 col-xl-4">
                    <div class="text-center">
                        <div id="modalDialog" class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <?php
                                    list($img, $error) = getProfileImagePath($toUser);
                                    $statusColor = 'onlineColor';//active
                                    if ($friendData['usersStatus'] == 'Offline') {
                                        $statusColor = 'offlineColor';//inactive

                                    }
                                    echo <<< EOD
                                        <div id='left'>
                                            <img id="profileImage" src='$img'  >
                                            <strong id="white">{$friendData['usersUsername']}</strong>
                                            <input id="toUsername" value="{$friendData['usersUsername']}" hidden>
                                        </div>
                                        <div id='right'>
                                            <form id="CloseChatForm" action="friends.php" >
                                            <strong id="{$friendData['UsersID']}_status" class='$statusColor' >{$friendData['usersStatus']}</strong>
                                            <button id="buttonCloseChatBox"
                                             class='alert-white btn-danger' 
                                             type='submit'>x</button>
                                            </form>
                                        </div>
                                        EOD;
                                    ?>
                                </div>
                                <div class="modal-body" id="bodyMsg">
                                    <?php
                                    $UserId = $userid;
                                    list($chat, $error) = getChat($userid, $chatToId);
                                    deliverChat($userid, $chatToId);
                                    $chatCount = count($chat);
                                    $isEncrypted = $GLOBALS['isEncrypted'];

                                    if ($chatCount == 0) {
                                        echo "<div class='center' id='noMessageYet' >
                                               <p class='pMessage'>There is no message yet..</p></div>";
                                    } else if ($chatCount > 0) {
                                        list($toImagePath, $error) = getProfileImagePath($chatToId);
                                        foreach ($chat as $msg) {
                                            $currentTextMessage = $msg['chatMessage'];
                                            $date = new DateTime($msg["chatCreatedDate"]);
                                            $date = date_format($date, DATE_FORMAT);
                                            if ($isEncrypted) {
                                                $currentTextMessage = decrypt($currentTextMessage);
                                            }

                                            if ($msg['chatFromId'] == $UserId) {
                                                generateMyMessage($currentTextMessage, $msg['ChatboxID'], $msg["url"], $msg["mimetype"],$msg["filename"], $date);
                                            } else if ($msg['chatFromId'] == $chatToId) {
                                                generateToMessage($toImagePath, $currentTextMessage, $msg['ChatboxID'], $msg["url"],
                                                    $msg["mimetype"], $msg['chatStatus'], $msg['chatIsDeleted'],$msg["filename"], $date);
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                                <div id="messageEditArea">
                                    <div class='text-center'>
                                        <form id='chat_form' action="" method="post">
                                            <div class="form-group mb-0 d-flex justify-content-between">
                                                <div class="center">
                                                    <span id="file-chosen"></span>
                                                    <div id="fileChooseDiv">
                                                        <div id="flexGrow2">
                                                            <textarea id="chatMessage" name="textarea"
                                                                onclick="readMessage(); setCursorToStart(this)"
                                                                placeholder="Type a message or drop files here..."></textarea>
                                                            <p id="callStatus"></p>
                                                            <div id="connectedContainer">
                                                                <canvas id="canvas" width="320" height="240"
                                                                    hidden></canvas>
                                                                <audio hidden="hidden" id="localAudioStream"
                                                                    autoplay></audio>
                                                                <audio hidden="hidden" id="audioRemoteStream"
                                                                    autoplay></audio>
                                                                <audio hidden="hidden" id="localAudioStream"
                                                                    autoplay></audio>
                                                                <audio hidden="hidden" id="localAudioRecordingStream"
                                                                    controls></audio>
                                                                <video id="remoteStream" autoplay playsinline
                                                                    hidden></video>
                                                                <video id="localVideoStream" autoplay playsinline
                                                                    hidden></video>
                                                                <video hidden="hidden" id="recording" playsinline
                                                                    controls></video>
                                                                <div class="mainChatDivRowCenter">
                                                                    <div id="recording-indicator"></div>
                                                                    <button type="button" id="stopButton"
                                                                        class="btn-danger">Stop Recording
                                                                    </button>
                                                                    <button id="capture" class="btn-success" hidden>
                                                                        Capture
                                                                    </button>
                                                                    <button id="captureCancel" class="btn-danger"
                                                                        hidden="hidden" onclick="onCancelCapture()">
                                                                        Cancel
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <input id="toUser" name="toUser"
                                                            value="<?php echo $chatToId; ?>" type="text" hidden />
                                                        <input id="fromUser" name="fromUser"
                                                            value="<?php echo $UserId; ?>" type="text" hidden />
                                                        <!-- actual upload which is hidden -->
                                                        <input type="file" id="actual-btn" accept="<?php
                                                               $allowedExtensions = getAllowedExtensions();
                                                               $acceptFileTypes = array_map(function($ext) {
                                                                   return '.' . $ext;
                                                               }, array_keys($allowedExtensions));
                                                               echo implode(',', $acceptFileTypes);
                                                               ?>" hidden />

                                                        <!-- our custom upload button -->
                                                        <div id="flexGrow1">
                                                            <div class="mainChatDivRow">
                                                                <button class="btn btn-secondary" id="send"
                                                                    name="textarea-submit" type="submit">
                                                                    <i class="fa fa-paper-plane"></i></button>
                                                                <label class="buttonLabel" for="actual-btn">+</label>
                                                            </div>
                                                            <div class="mainChatDivRow">
                                                                <button class="btn btn-secondary" id="audioCallButton"
                                                                    name="textarea-submit">
                                                                    <i class="fa fa-microphone"></i>
                                                                </button>
                                                                <button class="btn btn-secondary" id="videoCallButton"
                                                                    name="textarea-submit">
                                                                    <i class="fa fa-video-camera"></i>
                                                                </button>
                                                                <button class="btn btn-secondary" id="photoButton"
                                                                    name="textarea-submit">
                                                                    <i class="fa fa-camera" aria-hidden="true"></i>
                                                                </button>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php
                                if (isset($_GET["error"])) {
                                    if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "stmtfailed") {
                                        echo "<h6><p class='alert-danger'>Something went wrong.Try again!</p></h6>";
                                    }
                                    exit();
                                }
                                require 'footerMessage.php';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

</div>
<?php
require 'footer.php';
?>