<?php

namespace MyApp;

use DateTime;
use Exception;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use SplObjectStorage;

require_once dirname(__FILE__) . "/includes/email_queue_manager.php";
require_once dirname(__FILE__) . "/includes/functions.inc.php";
require_once dirname(__FILE__) . "/includes/functions.inc.php";
require_once dirname(__FILE__) . "/includes/searchFunctions.php";
require_once dirname(__FILE__) . "/config.php";
require_once dirname(__FILE__) . "/file-upload.php";


class ChatController implements MessageComponentInterface
{
    protected SplObjectStorage $clients;
    // for sending messages to the user
    private array $userConnections; // store user -> [con1, con2, con3]
    //for closing cons from the map of userConnection
    private array $connectionUser; // store con1 -> user, con2 -> user, con3 -> user
    private array $userAttachments;// save temp data for answer in the 2nd request of blob upload
    private array $userTargets;
    private string $success;
    private string $fail;

    public function __construct()
    {
        $this->clients = new SplObjectStorage;
        $this->userConnections = array();
        $this->connectionUser = array();
        $this->userAttachments = array();
        $this->success = "success";
        $this->fail = "fail";
        echo "Server Started\n";
        echo "Setting all users to inactive\n";
        setUsersStatusInactive();
    }

    public function checkForErrorAndReport($user, $error): void
    {
        if ($error != "") {
            $this->sendMessageToMyself($user, [$error,]);
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $querystring = $conn->httpRequest->getUri()->getQuery();
        parse_str($querystring, $queryarray);
        $guid = $queryarray['token'];
        if (!$this->isLoggedIn($conn, $guid)) {
            $conn->close(404);
        }
        list($user, $error) = getUserByGuid($guid);
        if ($error == "") {
            // Store the new connection to send messages to later
            $this->clients->attach($conn);
            $this->connectionUser[$conn->resourceId] = $guid;
            if (!isset($this->userConnections[$guid])) {
                $this->userConnections[$guid] = new SplObjectStorage();
            }
            $this->userConnections[$guid]->attach($conn);
            if ($this->updateUserStatus($user, 'Active')) {
                if ($this->sendUsersNotifications($user, 'initializeNotificationsCounter'))
                    echo "New connection stored! ($conn->resourceId, $guid)\n";
                else
                    echo "something bad happened here..\n";
            } else
                echo "something bad happened here..2\n";
        } else {
            $data = array();
            $data["error"] = $error;
            $data["status"] = true;
            $data["loggedIn"] = true;

            $conn->send(json_encode($data));
            echo $error . "\n";
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        if (!$this->isLoggedIn($from)) {
            $from->close(404);
            return false;
        }
        echo "new message:\n";
        $targets = [];
        $numRecv = count($this->clients) - 1;
        $timestamp = new Datetime();
        $currentDate = date_format($timestamp, "d/m/Y H:i:s");
        $messageCreatedDatetime = date_format($timestamp, "Y-m-d H:i:s");
        $userGuid = $this->connectionUser[$from->resourceId];
        list($sender, $error) = getUserByGuid($userGuid);
        if ($error !== "") {
            $from->send(json_encode([$error]));
            return false;
        }
        $messageIsBinary = false;
        echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, "msg", $numRecv, $numRecv == 1 ? '' : 's');
        $data = json_decode($msg);
        if ($data !== null) {
            // Check if the msg property exists before accessing it
            if (isset($data->msg)) {
                $data->msg = htmlspecialchars($data->msg);
            } else {
                $data->msg = ''; // Set default empty message
            }
            $data->from = $userGuid;
            $data->fromId = $sender['UsersID'];
            $data->status = $this->success; // used for current status (success, fail
            $data->loggedIn = true; // used for current status (success, fail
            $data->statusMessage = ""; // used for error message
            $data->isReplied = false; /* used for if message have been replied already
             in case we receive 2 messages like in sendAttachment */
            $data->attachment = null; // used for attachmentId if we store an attachment.
            list($success, $targets) = $this->getTargets($data, $targets, $userGuid);
            if (!$success) { // targets variable has the error now
                $error = $targets;
                $from->send(json_encode([$error]));
                return false;
            }
            echo $data->action;
            $data->date = date_format($timestamp, DATE_FORMAT);
            $data->messageCreatedDatetime = $messageCreatedDatetime;
            $this->actionController($data, $sender, $targets, $currentDate, $from, $userGuid);
        } else {
            $messageIsBinary = true;
        }
        // save attachment and send notifications
        if ($msg) {
            $this->attachmentController($userGuid, $currentDate, $from, $sender['usersUsername'], $messageIsBinary, $msg);
        }
    }

    public
    function onClose(ConnectionInterface $conn)
    {
        // get user that his connection disconnected
        $userGuid = $this->connectionUser[$conn->resourceId] ?? null;
        if (!$userGuid) {
            echo "Connection $conn->resourceId has disconnected (no user found)\n";
            $this->clients->detach($conn);
            return false;
        }

        list($user, $error) = getUserByGuid($userGuid);
        if ($error != "") {
            echo "Error getting user data: $error\n";
            $this->sendMessageToMyself($user, $error);
            return false;
        }
        // The connection is closed, remove it, as we can no longer send it messages
        if ($this->isLoggedIn($conn)){
            $this->updateUserStatus($user, 'Offline');
        }
        // Remove connection from tracking
        unset($this->connectionUser[$conn->resourceId]);
        $this->clients->detach($conn);
        // Remove connection from user's connection list
        if (isset($this->userConnections[$userGuid])) {
            $this->userConnections[$userGuid]->detach($conn);

            // Only set user to Offline if this was their LAST connection
            if ($this->userConnections[$userGuid]->count() === 0) {
                unset($this->userConnections[$userGuid]); // Clean up empty storage
                $this->updateUserStatus($user, 'Offline');
                echo "User $userGuid set to Offline (last connection closed)\n";
            } else {
                echo "User $userGuid still has " . $this->userConnections[$userGuid]->count() . " active connections\n";
            }
        }

        echo "Connection $conn->resourceId has disconnected\n";
        return true;

    }

    public
    function onError(ConnectionInterface $conn, Exception $e)
    {

        $userGuid = $this->connectionUser[$conn->resourceId];
        $user = getUserByGuid($userGuid);

        // The connection is closed, remove it, as we can no longer send it messages
        $this->updateUserStatus($user, 'Offline');
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * @param array $targets
     * @param mixed $data
     * @param string $currentDate
     * @param ConnectionInterface $from
     * @param $usersUsername
     * @return void
     */

    public function sendMessage(array $targets, mixed $data,
                                string $currentDate,
                                ConnectionInterface $from,
        $usersUsername): void
    {
        $msgId = "";
        $offlineNotifications = []; // Queue for offline users

        foreach ($targets as $targetUser) {
            list($currentUser, $error) = getUserByGuid($targetUser);
            if (is_array($currentUser) && $error == "") {
                if ($data->action == "sendTextMessage" || $data->action == "sendAttachment") {
                    $attachmentId = null;
                    if (isset($data->attachmentId)) {
                        $attachmentId = $data->attachmentId;
                    }
                    if ($data->from !== $targetUser) {
                        list($msgId, $error) = createMessagebyGuid($data->from,
                            $targetUser, $data->msg, $currentDate, $attachmentId);
                    }
                    if (isset($data->attachmentId)) unset($data->attachmentId);
                    $data->chatId = $msgId;
                }

                // Handle online users immediately
                if ($this->isUserOnline($targetUser)) {
                    foreach ($this->getUserConnections($targetUser) as $currentConnection) {
                        if ($currentConnection == $from)
                            $data->fromName = 'Me';
                        else
                            $data->fromName = $usersUsername;
                        echo "send from " . $data->fromName . ", to: " . $data->to . "\n";
                        $currentConnection->send(json_encode($data));
                        if ($msgId != "")
                            deliverMessage($msgId);
                    }
                } else {
                    // Queue offline notifications for later
                    if ($data->action == "sendTextMessage") {
                        $messageContent = $data->msg ?? "New message";
                        $offlineNotifications[] = [
                            'type' => 'message',
                            'userId' => $currentUser['UsersID'],
                            'username' => $currentUser['usersUsername'],
                            'senderName' => $usersUsername,
                            'content' => $messageContent
                        ];
                    } else if ($data->action == "sendAttachment") {
                        $attachmentMessage = "📎 " . $usersUsername . " sent you an attachment";
                        if (isset($data->msg) && !empty($data->msg)) {
                            $attachmentMessage .= " with message: " . $data->msg;
                        }
                        $offlineNotifications[] = [
                            'type' => 'attachment',
                            'userId' => $currentUser['UsersID'],
                            'username' => $currentUser['usersUsername'],
                            'senderName' => $usersUsername,
                            'content' => $attachmentMessage
                        ];
                    }
                }
            }
        }

        // Queue offline notifications for async processing (non-blocking)
        $this->queueOfflineNotifications($offlineNotifications);
    }

    private function queueOfflineNotifications(array $notifications): void
    {
        foreach ($notifications as $notification) {
            \EmailQueue::addToQueue(
                $notification['type'],
                $notification['userId'],
                $notification['senderName'],
                $notification['content']
            );
            echo "Queued email notification for offline user: {$notification['username']}\n";
        }
    }


    private function processOfflineNotifications(array $notifications): void
    {
        foreach ($notifications as $notification) {
            sendMessageNotificationEmail(
                $notification['userId'],
                $notification['senderName'],
                $notification['content']
            );
            echo "Email notification sent to offline user: {$notification['username']}\n";
        }
    }

    public function filterTargets($targets, $excludedList)
    {
        $filtered = array();
        foreach ($targets as $targetUser) {
            if (!in_array($targetUser, $excludedList, true)) {
                $filtered[] = ($targetUser);
            }
        }
        return $filtered;
    }

    public function isUserOnline($userGuid): bool
    {
        if (!isset($this->userConnections[$userGuid])) {
            echo "isUserOnline: $userGuid = false\n";
            return false;
        }

        $connectionCount = $this->userConnections[$userGuid]->count();
        $isOnline = $connectionCount > 0;
        echo "isUserOnline: $userGuid = " . ($isOnline ? 'true' : 'false') . ", $connectionCount\n";

        return $isOnline;
    }

    public function getUserConnections($userGuid): SplObjectStorage
    {
        return $this->userConnections[$userGuid];  // if user logged on
    }

    public function updateUserStatus($user, $status = 'Active'): bool
    {
        if (!is_array($user))
            return false;
        $statusColor = 'lightgreen';
        if ($status == 'Offline') {
            $statusColor = 'salmon';
        }
        $id = $user['UsersID'];
        // update db
        list($success, $error) = updateUserStatus($id, $status);
        if ($error != "") {
            $this->sendMessageToMyself($user, $error);
            return false;
        }
        $data = ["friendUserId" => $id, "userStatus" => $status, "action" => "updateUserStatus",
            "color" => $statusColor, "status" => true, "loggedIn" => true];
        return $this->sendToAllFriends($data, $user);
    }

    public function sendToAllFriends($data, $user): bool
    {
        $id = $user['UsersID'];
        list($friends, $error) = getFriends($id);
        $this->checkForErrorAndReport($user, $error);
        if ($error !== "") {
            return false;
        }
        if (is_array($friends)) {
            foreach ($friends as $friend) {
                $friendId = $friend['rqstFromId'];
                list($friendMetadata, $error) = getUserById($friendId);
                $this->checkForErrorAndReport($user, $error);
                if ($error !== "") {
                    return false;
                }
                $friendGuid = $friendMetadata['usersGuid'];
                if ($this->isUserOnline($friendGuid)) {
                    foreach ($this->getUserConnections($friendGuid) as $currentConnection) {
                        $currentConnection->send(json_encode($data));
                    }
                }
            }
        }
        return true;
    }

    public function sendUsersNotifications($user, $action = "updateNotificationsCounter"): bool
    {
        $id = $user['UsersID'];
        list($error, $counter, $chatCounter) = getNotifyCounters($id);
        if ($error !== "") {
            $this->sendMessageToMyself($user, $error);
            return false;
        }
        $data = ["action" => $action];
        $guid = $user['usersGuid'];
        $data['counter'] = $counter;
        $data['chatCounter'] = $chatCounter;
        $data['status'] = true;
        $data['loggedIn'] = true;
        if ($this->isUserOnline($guid)) {
            foreach ($this->getUserConnections($guid) as $currentConnection) {
                $currentConnection->send(json_encode($data));
            }
        }
        return true;
    }

    public function friendRequestManager($fromId, $toId, $action): array
    {
        $status = "Confirm";
        $notificationStatus = "Yes";
        $error = "Something went wrong.Try again!";
        $currentError = "Success";
        $actionResult = true;

        switch ($action) {
            case $action == "accept":
                $currentError = "Friend request accepted!";
                $actionResult = acceptRequest($fromId, $toId, $status, $notificationStatus);
                break;
            case $action == "add":
                $currentError = "Request successfully sent!";
                $status = "Pending";
                $actionResult = addFriend($fromId, $toId, $status, $notificationStatus);

                // Send email notification for friend request to offline user
                list($toUser, $error) = getUserById($toId);
                list($fromUser, $error2) = getUserById($fromId);
                if ($error == "" && $error2 == "" && !$this->isUserOnline($toUser['usersGuid'])) {
                    if(sendFriendRequestNotificationEmail($toId, $fromUser['usersUsername'])){
                        echo "Email notification sent for friend request to offline user: {$toUser['usersUsername']}\n";
                    }
                }
                break;
            case $action == "reject":
                $currentError = "Friend request declined!";
                $status = "Reject";
                $actionResult = rejectRequest($fromId, $toId, $status, $notificationStatus);
                break;
            case $action == "delete":
                $currentError = "Friend successfully deleted!";
                $status = "Reject";
                $actionResult = deleteFriend($fromId, $toId, $status, $notificationStatus);
                break;
            case $action == "cancel":
                $currentError = "Request successfully canceled!";
                $status = "Reject";
                $actionResult = deleteFriend($fromId, $toId, $status, $notificationStatus);
                break;
        }
        if (!$actionResult)
            return array($error, $actionResult);
        return array($currentError, $actionResult);
    }

    /**
     * @param mixed $data
     * @param array $targets
     * @param mixed $userGuid
     * @return array
     */
    public function getTargets(mixed $data, array $targets, mixed $userGuid): array
    {
        switch ($data->type) {
            case "single": //one to one msg aka friend
                if (isset($data->to)) {
                    $to = $data->to;
                    if (my_is_int($to)) {
                        list($to, $error) = getUserById($to);
                        if ($error !== "") {
                            return [false, $error];
                        }
                        $targets[] = $to['usersGuid'];
                    }
                }
                $targets[] = $userGuid;
                // send message to one client if online
                break;
            case "group": // one to group msg aka group
                $targets[] = $data->to;
                foreach ($data->multiTo as $target)
                    $targets[] = $target;
                // send message to one client if online
                break;
        }
        return [true, $targets];
    }

    /**
     * @param mixed $data
     * @param bool|array|null $sender
     * @param array $targets
     * @param string $currentDate
     * @param ConnectionInterface $from
     * @param mixed $userGuid
     * @return void
     */
    public function actionController(mixed $data, bool|array|null $sender, array $targets, string $currentDate, ConnectionInterface $from, mixed $userGuid): void
    {
        switch ($data->action) {
            case "friend":
                unset($sender['usersPassword']);
                $data->fromDetails = $sender;
                list($data->msg, $data->reqStatus) = $this->friendRequestManager($sender['UsersID'], $data->to, $data->subAction);
                $this->sendMessage($targets, $data, $currentDate, $from, $sender['usersUsername']);
                $data->status = $this->success;
                $data->isReplied = true;
                break;
            case "updateNotificationsCounter": // incoming request
                $data->status = $this->success;
                $data->isReplied = true;
                $this->sendUsersNotifications($sender);
                break;
            case "readChat":
                // readChat from to
                readChat($data->to, $sender['UsersID']);
                echo "ReadChat from : {$sender['UsersID']}, to $data->to\n";
                $data->status = $this->success;
                $data->isReplied = true;
                $this->sendUsersNotifications($sender);
                break;
            case "syncFrom":
                list($data->syncedData, $error) = syncFullChat($data->lastdate);
                foreach ($data->syncedData as &$item) {
                    $item['chatMessage'] = decrypt($item['chatMessage']);
                    list($item['attachmentUrl'], $item['attachmentMimetype']) = getAttachmentUrlAndMime($item['chatAttachmentId']);
                }
                list($data->syncedFriendData, $error) = getSyncFriends($sender['UsersID'], $data->lastdate);
                $this->sendMessage($targets, $data, $currentDate, $from, $sender['usersUsername']);
                break;
            case "sendTextMessage":
                $this->sendMessage($targets, $data, $currentDate, $from, $sender['usersUsername']);
                $data->status = $this->success;
                $data->isReplied = true;
                break;
            case "deleteMessage":
                deleteMessageById($data->chatId);
                $this->sendMessage($targets, $data, $currentDate, $from, $sender['usersUsername']);
                $data->status = $this->success;
                $data->isReplied = true;
                break;
            case "sendAttachment":
                $this->userAttachments[$userGuid] = $data;
                list($data->status, $data->attachmentDescription, $data->statusMessage) = isFileValid($data->filename, $data->filesize);
                if ($data->status) {
                    list($attachmentId, $error) = createAttachment($data->filename, $data->filetype);
                    $data->attachment = getAttachmentUrl($attachmentId);
                    $data->attachmentId = $attachmentId;
                    $this->userTargets[$userGuid] = $targets;
                }
                break;
            case "deleteAccount":
                echo "Deleting account: {$sender}\n";
                $this->updateUserStatus($sender, 'Offline');
                deleteAccountManager($sender['UsersID']);
                $this->sendMessage($targets, $data, $currentDate, $from, $sender['usersUsername']);
                $data->status = $this->success;
                $data->isReplied = true;
                break;
            default:
                break;
        }
    }

    /**
     * @param mixed $userGuid
     * @param string $currentDate
     * @param ConnectionInterface $from
     * @param $usersUsername
     * @param bool $messageIsBinary
     * @param $msg
     * @return void
     */
    public function attachmentController(mixed $userGuid, string $currentDate, ConnectionInterface $from, $usersUsername, bool $messageIsBinary, $msg): void
    {
        if (array_key_exists($userGuid, $this->userAttachments)) {
            $data = $this->userAttachments[$userGuid];
            if (!$data->isReplied) { // when upload we get 2 messages, and we reply only 1
                if ($data->status != $this->success) {
                    // error
                    $this->sendMessage(array($userGuid,), $data, $currentDate, $from, $usersUsername);
                    $data->isReplied = true;
                } else if ($data->action == "sendAttachment"
                    && $data->attachmentId != null && $messageIsBinary) {
                    list($metadata, $error) = getAttachmentById($data->attachmentId);
                    // here have attachment in $msg,
                    $fileSaveLocation = getFileSavePath($metadata['guid'], $metadata['extension']);
                    echo "Saving file to : $fileSaveLocation\n";
                    file_put_contents($fileSaveLocation, $msg); // save file to disk
                    // now send the metadata to user
                    $this->sendMessage($this->userTargets[$userGuid], $data, $currentDate, $from, $usersUsername);
                    $data->isReplied = true;
                }
            }
        }
    }

    public function sendMessageToMyself($user, $message): void
    {
        $conn = ($this->userConnections[$user['Guid']]);  // if user logged in
        $conn->send(json_encode($message));
    }

    public function isUserOnlineDB(string|null $jwt, string|null $remember_me): array|bool
    {
        return is_user_logged_in_websocket($jwt, $remember_me);
    }

    /**
     * Extra check if user is logged in db
     * @param ConnectionInterface $conn
     * @param string|null $guid
     * @return bool
     */
    public function isLoggedIn(ConnectionInterface $conn, string|null $guid = null): bool
    {
        $raw_cookie = $conn->httpRequest->getHeaders()['Cookie'];
        parse_str(strtr($raw_cookie[0], array('&' => '%26', '+' => '%2B', ';' => '&')), $cookies);
        $jwt = null;
        $rememberMe = null;
        if (isset($cookies['jwt'])) {
            $jwt = $cookies['jwt'];
        }
        if (isset($cookies['remember_me'])) {
            $rememberMe = $cookies['remember_me'];
        }
        $userLogged = $this->isUserOnlineDB($jwt, $rememberMe);
        if (!$userLogged || !$userLogged[0]) {
            $conn->send(json_encode(value: array(["error" => 'Unauthorized'])));
            echo "unauthorized\n";
            return false;
        }
        if ($guid !== null) {
            return $guid == $userLogged[0]['usersGuid'];
        }
        return true;
    }

}