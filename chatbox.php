<?php
require 'header.php';
require_once('includes/upload/FileValidator.php');

echo "<link rel='stylesheet' href=" . $path . "/css/chatbox.css>";
echo "<link rel='stylesheet' href=" . $path . "/css/group-chat.css>";
echo "<script src='" . $path . "/js/chatbox-page.js'></script>";

//Detect if this is a group chat or one-on-one chat
$isGroupChat = false;
$groupGuid = null;
$chatToGuid = null;

if (isset($_GET["guid"]) && isset($_GET["type"]) && $_GET["type"] === 'group') {
    //Group chat mode
    $isGroupChat = true;
    $groupGuid = trim($_GET['guid']);
    
    //Validate GUID format
    if (!validateGuid($groupGuid)) {
        echo "<script>window.location='messages.php';</script>";
        exit();
    }
    
} else if (isset($_GET["guid"]) && (!isset($_GET["type"]) || $_GET["type"] === 'user')) {
    //One-on-one chat mode
    $chatToGuid = trim($_GET['guid']);
    
    //Validate GUID format
    if (!validateGuid($chatToGuid)) {
        echo "<script>window.location='friends.php';</script>";
        exit();
    }
} else {
    echo "<script>window.location='friends.php';</script>";
    exit();
}
?>

<script>
//Export PHP constants to JavaScript as window properties
window.MAX_FILE_SIZE = <?php echo MAX_FILE_SIZE; ?>;

//Export allowed file extensions from PHP
window.ALLOWED_EXTENSIONS = <?php
        $allowedExtensions = FileValidator::getAllowedExtensions();
        echo json_encode(array_keys($allowedExtensions));
        ?>;
</script>

<div class=mainChatDiv>
    <main id="mainMargin" class="d-flex vw-100 responsive-height align-items-center justify-content-center">
        <div class="container-fluid mt-5 mb-5">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-11 col-md-10 col-lg-10 col-xl-10">
                    <?php if ($isGroupChat): ?>
                    <!-- Group Chat (Flex layout with chat and sidebar) -->
                    <div class="d-flex gap-2 flex-column flex-lg-row">
                        <!-- Chat Modal -->
                        <div class="flex-grow-1 flex-min-0">
                            <div class="text-center">
                                <div id="modalDialog" class="modal-dialog">
                                    <div class="modal-content">
                                        <?php else: ?>
                                        <!-- One-on-Οne Chat (Centered layout) -->
                                        <div class="d-flex justify-content-center">
                                            <div class="flex-grow-1 flex-min-0">
                                                <div class="text-center">
                                                    <div id="modalDialog" class="modal-dialog">
                                                        <div class="modal-content">
                                                            <?php endif; ?>
                                                            <div class="modal-header">
                                                                <?php
                                    if ($isGroupChat) {
                                        //Group chat header (data will be loaded via JavaScript)
                                        echo <<< EOD
                                            <div id='left'>
                                                <div class="group-image-container">
                                                    <img id="groupImage" src='img/profiledefault.jpg' onerror="this.src='img/profiledefault.jpg';" >
                                                    <i class="fa fa-users group-icon-badge"></i>
                                                </div>
                                                <strong id="white"><span id="group-name-display">Loading...</span></strong>
                                                <span class="member-count-indicator">
                                                    <i class="fa fa-user"></i> Loading...
                                                </span>
                                                <input id="groupGuid" value="{$groupGuid}" hidden>
                                                <input id="isGroupChat" value="1" hidden>
                                            </div>
                                            <div id='right'>
                                                <button id="buttonGroupInfo" class='btn btn-secondary d-none' type='button'
                                                        onclick="window.location.href='group_edit.php?guid={$groupGuid}&from=chat'">
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                                </button>
                                                <button id="buttonCloseChatBox" class='alert-white btn-danger' type='button'
                                                        onclick="window.location.href='groups.php'">x</button>
                                            </div>
                                            EOD;
                                    } else {
                                        //One-on-Οne Ψhat header (data will be loaded via JavaScript)
                                        echo <<< EOD
                                            <div id='left'>
                                                <img id="profileImage" src='img/profiledefault.jpg' onerror="this.src='img/profiledefault.jpg';" >
                                                <strong id="white"><span id="friend-name-display">Loading...</span></strong>
                                                <input id="toUsername" value="" hidden>
                                                <input id="isGroupChat" value="0" hidden>
                                            </div>
                                            <div id='right'>
                                                <strong id="friend-status-display" class='offlineColor'>Loading...</strong>
                                                <button id="buttonCloseChatBox" class='alert-white btn-danger' type='button'
                                                        onclick="window.location.href='friends.php'">x</button>
                                            </div>
                                            EOD;
                                    }
                                    ?>
                                                            </div>
                                                            <div class="modal-body" id="bodyMsg">
                                                                <!-- Messages will be loaded via JavaScript API calls -->
                                                                <div id="loadingMessages">
                                                                    <div class="skeleton-message to">
                                                                        <div class="skeleton skeleton-profile"></div>
                                                                        <div class="skeleton-bubble">
                                                                            <div class="skeleton skeleton-text long">
                                                                            </div>
                                                                            <div class="skeleton skeleton-text medium">
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="skeleton-message from">
                                                                        <div class="skeleton-bubble">
                                                                            <div class="skeleton skeleton-text short">
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="skeleton-message to">
                                                                        <div class="skeleton skeleton-profile"></div>
                                                                        <div class="skeleton-bubble">
                                                                            <div class="skeleton skeleton-text medium">
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="skeleton-message from">
                                                                        <div class="skeleton-bubble">
                                                                            <div class="skeleton skeleton-text long">
                                                                            </div>
                                                                            <div class="skeleton skeleton-text short">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div id="messageEditArea">
                                                                <form id='chat_form' action="" method="post">
                                                                    <div id="file-chosen-container">
                                                                        <span id="file-chosen"></span>
                                                                        <button type="button" id="clear-file-btn"
                                                                            class="clear-file-button d-none"
                                                                            title="Clear attachment">X</button>
                                                                    </div>

                                                                    <!-- Input area with textarea and buttons side by side -->
                                                                    <div id="chat-input-container">
                                                                        <div id="textarea-wrapper">
                                                                            <textarea id="chat_message" name="textarea"
                                                                                onclick="setCursorToStart(this)"
                                                                                placeholder="Type a message or drop files here..."
                                                                                maxlength="2000"></textarea>
                                                                        </div>

                                                                        <!-- Buttons column -->
                                                                        <div id="chat-buttons">
                                                                            <div class="button-row">
                                                                                <button class="btn btn-primary"
                                                                                    id="send" name="textarea-submit"
                                                                                    type="button">
                                                                                    <i class="fa fa-paper-plane"></i>
                                                                                </button>
                                                                                <label
                                                                                    class="btn btn-secondary buttonLabel"
                                                                                    for="actual-btn">+</label>
                                                                            </div>
                                                                            <div class="button-row">
                                                                                <button class="btn btn-secondary"
                                                                                    id="audioCallButton"
                                                                                    name="textarea-submit"
                                                                                    type="button">
                                                                                    <i class="fa fa-microphone"></i>
                                                                                </button>
                                                                                <button class="btn btn-secondary"
                                                                                    id="videoCallButton"
                                                                                    name="textarea-submit"
                                                                                    type="button">
                                                                                    <i class="fa fa-video-camera"></i>
                                                                                </button>
                                                                                <button class="btn btn-secondary"
                                                                                    id="photoButton"
                                                                                    name="textarea-submit"
                                                                                    type="button">
                                                                                    <i class="fa fa-camera"></i>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Hidden elements -->
                                                                    <p id="callStatus"></p>
                                                                    <div id="connectedContainer">
                                                                        <canvas id="canvas" width="320" height="240"
                                                                            hidden></canvas>
                                                                        <audio hidden="hidden" id="localAudioStream"
                                                                            autoplay></audio>
                                                                        <audio hidden="hidden" id="audioRemoteStream"
                                                                            autoplay></audio>
                                                                        <audio hidden="hidden"
                                                                            id="localAudioRecordingStream"
                                                                            controls></audio>
                                                                        <video id="remoteStream" autoplay playsinline
                                                                            hidden></video>
                                                                        <video id="localVideoStream" autoplay
                                                                            playsinline hidden></video>
                                                                        <video hidden="hidden" id="recording"
                                                                            playsinline controls></video>
                                                                        <div class="mainChatDivRowCenter">
                                                                            <div id="recording-indicator"></div>
                                                                            <button type="button" id="stopButton"
                                                                                class="btn-danger" hidden>Stop
                                                                                Recording</button>
                                                                            <button type="button" id="capture"
                                                                                class="btn-success"
                                                                                hidden>Capture</button>
                                                                            <button type="button" id="captureCancel"
                                                                                class="btn-danger" hidden="hidden"
                                                                                onclick="onCancelCapture()">Cancel</button>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Hidden inputs -->
                                                                    <input id="toUser" name="toUser"
                                                                        value="<?php echo $chatToGuid ?? ''; ?>"
                                                                        type="text" hidden />
                                                                    <input id="fromUser" name="fromUser"
                                                                        value="<?php echo $user_guid; ?>" type="text"
                                                                        hidden />
                                                                    <?php if ($isGroupChat): ?>
                                                                    <input id="currentGroupGuid" name="currentGroupGuid"
                                                                        value="<?php echo $groupGuid; ?>" type="text"
                                                                        hidden />
                                                                    <?php endif; ?>
                                                                    <input type="file" id="actual-btn" accept="<?php
                                                               $allowedExtensions = FileValidator::getAllowedExtensions();
                                                               $acceptFileTypes = array_map(function($ext) {
                                                                   return '.' . $ext;
                                                               }, array_keys($allowedExtensions));
                                                               echo implode(',', $acceptFileTypes);
                                                               ?>" hidden />
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($isGroupChat): ?>
                                            </div> <!-- Close flex-grow-1 for chat -->

                                            <!-- Active Members Sidebar -->
                                            <div class="flex-shrink-0 chat-members-sidebar">
                                                <div id="membersSidebar">
                                                    <!-- Members will be loaded via JavaScript API calls -->
                                                    <h5 class="text-center mb-3">Members</h5>
                                                    <div class="text-center text-muted">
                                                        <i class="fa fa-spinner fa-spin"></i>
                                                        <p>Loading members...</p>
                                                    </div>
                                                </div>
                                            </div> <!-- Close sidebar flex-shrink-0 -->
                                        </div> <!-- Close d-flex for group chat -->
                                        <?php else: ?>
                                    </div> <!-- Close flex-grow-1 for one-on-one chat -->
                                </div> <!-- Close d-flex for one-on-one -->
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
    </main>
</div>

<script src='<?php echo $path . "/js/chat/group-chat-page.js" ?>'></script>
<?php
require 'footer.php';
?>