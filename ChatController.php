<?php

namespace MyApp;

use DateTime;
use Exception;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use SplObjectStorage;
require_once dirname(__FILE__) . "/includes/EmailQueue.php";
require_once dirname(__FILE__) . "/includes/functions.inc.php";
require_once dirname(__FILE__) . "/includes/searchFunctions.php";
require_once dirname(__FILE__) . "/includes/file-helpers.php";
require_once dirname(__FILE__) . "/config.php";
require_once dirname(__FILE__) . "/includes/upload/FileValidator.php";
require_once dirname(__FILE__) . "/includes/db/group_chats.php";
require_once dirname(__FILE__) . "/includes/db/group_members.php";
require_once dirname(__FILE__) . "/includes/db/group_messages.php";
require_once dirname(__FILE__) . "/includes/db/addrequest.php";
require_once dirname(__FILE__) . "/includes/guid-utilities.php";
require_once dirname(__FILE__) . "/includes/upload/UploadController.php";
require_once dirname(__FILE__) . "/includes/db/profileImage.php";


class ChatController implements MessageComponentInterface
{
    protected SplObjectStorage $clients;
    private array $userConnections; //For sending messages to the user, store user -> [con1, con2, con3]
    private array $connectionUser; //For closing connections from the map of userConnection, store con1 -> user, con2 -> user, con3 -> user
    private array $userAttachments; //Save temp data for answer in the 2nd request of blob upload
    private array $userTargets;
    private array $offlineTimers; //Store timers for delayed offline status
    private array $loggingOutUsers; //Track users who are logging out
    private string $success;
    private string $fail;
    private \UploadController $uploadController;
    private bool $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
        $this->clients = new SplObjectStorage;
        $this->userConnections = array();
        $this->connectionUser = array();
        $this->userAttachments = array();
        $this->offlineTimers = array();
        $this->loggingOutUsers = array();
        $this->success = "success";
        $this->fail = "fail";
        
        //Initialize upload controller with database connection
        require_once dirname(__FILE__) . "/includes/dbh.inc.php";
        $this->uploadController = new \UploadController(getDbConnection());
        
        echo "Server Started\n";
        echo "Setting all users to inactive\n";
        setUsersStatusInactive();
    }

    private function debugLog(string $message): void
    {
        if ($this->debug) {
            echo $message;
        }
    }

    //Handle server-side connection with API key
    private function handleServerConnection(ConnectionInterface $conn, $apiKey): void
    {
        //Validate API key
        if (!defined('WS_SERVER_API_KEY') || $apiKey !== WS_SERVER_API_KEY) {
            echo "Invalid API key attempt\n";
            $conn->close(403);
            return;
        }
        
        //Store server connection
        $this->clients->attach($conn);
        $serverGuid = 'SERVER_CONNECTION';
        $this->connectionUser[$conn->resourceId] = $serverGuid;
        
        if (!isset($this->userConnections[$serverGuid])) {
            $this->userConnections[$serverGuid] = new SplObjectStorage();
        }
        $this->userConnections[$serverGuid]->attach($conn);
        
        //Send confirmation
        $conn->send(json_encode([
            'type' => 'server_connected',
            'status' => true,
            'message' => 'Server connection established'
        ]));
    }
    
    //Handle server notification message
    public function handleServerNotification($data): void
    {
        $this->debugLog("=== SERVER NOTIFICATION RECEIVED ===\n");
        $this->debugLog("Type: " . ($data->type ?? 'unknown') . "\n");
        $this->debugLog("Action: " . ($data->action ?? 'unknown') . "\n");
        $this->debugLog("Full data: " . json_encode($data) . "\n");
        
        /* Handle user logout notification.
        Μark user as logging out to skip grace period. */
        if (isset($data->type) && $data->type === 'user_logout' && isset($data->user_guid)) {
            $logoutUserGuid = $data->user_guid;
            
            //Mark user as logging out
            $this->loggingOutUsers[$logoutUserGuid] = true;
            $this->debugLog("Marked user $logoutUserGuid as logging out (will skip grace period)\n");
            
            //Cancel any pending offline timer
            if (isset($this->offlineTimers[$logoutUserGuid])) {
                unset($this->offlineTimers[$logoutUserGuid]);
                $this->debugLog("Canceled pending offline timer for $logoutUserGuid\n");
            }

            //Get user data and immediately set to Offline
            list($user, $userError) = getUserByGuid($logoutUserGuid);
            if ($userError === "" && is_array($user)) {
                $this->debugLog("Immediately setting user $logoutUserGuid to Offline (bypassing grace period)\n");
                $this->updateUserStatus($user, 'Offline');
            } else {
                echo "ERROR: Could not get user data for logout: $userError\n";
            }
            
            $this->debugLog("=== END USER LOGOUT NOTIFICATION ===\n\n");
            return;
        }
        
        //Check the kind of broadcast
        if (isset($data->broadcast) && $data->broadcast === true && isset($data->target) && $data->target === 'all') {
            $this->debugLog("Mode: BROADCAST TO ALL USERS\n");
            //Broadcast to all connected users
            $this->broadcastToAllUsers($data);
        } elseif (isset($data->broadcast_to_group) && $data->broadcast_to_group) {
            $this->debugLog("Mode: BROADCAST TO GROUP\n");
            $this->debugLog("Group GUID: " . ($data->group_guid ?? 'not set') . "\n");
            //Broadcast to all group members
            $this->broadcastToGroupMembers($data);
        } elseif (isset($data->user_guid)) {
            $this->debugLog("Mode: SEND TO SPECIFIC USER\n");
            $this->debugLog("Target User GUID: " . $data->user_guid . "\n");
            //Send to specific user
            $this->sendToUserByGuid($data->user_guid, $data);
        } else {
            echo "ERROR: No routing information (no user_guid or broadcast_to_group)\n";
        }
        $this->debugLog("=== END SERVER NOTIFICATION ===\n\n");
    }
    
    //Broadcast notification to all group members
    private function broadcastToGroupMembers($notification): void
    {
        $groupGuid = $notification->group_guid ?? null;
        $this->debugLog("--- Broadcasting to group: $groupGuid ---\n");
        
        if (!$groupGuid) {
            echo "ERROR: No group_guid provided\n";
            return;
        }
        
        //Get all group members
        list($members, $error) = getGroupMembersByGuid($groupGuid);
        if ($error !== "" || !is_array($members)) {
            echo "ERROR: Failed to get group members: $error\n";
            return;
        }
        
        $this->debugLog("Found " . count($members) . " group members\n");

        $excludeUsers = $notification->exclude_users ?? [];
        if (!empty($excludeUsers)) {
            $this->debugLog("Excluding users: " . implode(', ', $excludeUsers) . "\n");
        }
        
        $sentCount = 0;
        $skippedCount = 0;
        
        //Send to each member
        foreach ($members as $member) {
            $user_guid = $member['user_guid'] ?? null;
            
            $this->debugLog("  Member: guid=$user_guid");

            if (in_array($user_guid, $excludeUsers)) {
                $this->debugLog(" - EXCLUDED\n");
                $skippedCount++;
                continue;
            }

            if (!$user_guid) {
                $this->debugLog(" - NO GUID\n");
                $skippedCount++;
                continue;
            }

            if ($this->isUserOnline($user_guid)) {
                $this->debugLog(" - ONLINE - SENDING\n");
                $this->sendToUserByGuid($user_guid, $notification);
                $sentCount++;
            } else {
                $this->debugLog(" - OFFLINE\n");
                $skippedCount++;
            }
        }
        
        $this->debugLog("Broadcast complete: $sentCount sent, $skippedCount skipped\n");
        $this->debugLog("--- End broadcast ---\n\n");
    }

    //Broadcast notification to all connected users
    private function broadcastToAllUsers($notification): void
    {
        $this->debugLog("--- Broadcasting to ALL connected users ---\n");

        $excludeUsers = $notification->exclude_users ?? [];
        if (!empty($excludeUsers)) {
            $this->debugLog("Excluding users: " . implode(', ', $excludeUsers) . "\n");
        }

        $sentCount = 0;
        $skippedCount = 0;

        //Send to all connected users (except SERVER_CONNECTION)
        foreach ($this->userConnections as $user_guid => $connections) {
            //Skip server connection
            if ($user_guid === 'SERVER_CONNECTION') {
                continue;
            }

            $this->debugLog("  User: guid=$user_guid");

            if (in_array($user_guid, $excludeUsers)) {
                $this->debugLog(" - EXCLUDED\n");
                $skippedCount++;
                continue;
            }

            if ($this->isUserOnline($user_guid)) {
                $this->debugLog(" - ONLINE - SENDING\n");
                $this->sendToUserByGuid($user_guid, $notification);
                $sentCount++;
            } else {
                $this->debugLog(" - OFFLINE\n");
                $skippedCount++;
            }
        }

        $this->debugLog("Broadcast complete: $sentCount sent, $skippedCount skipped\n");
        $this->debugLog("--- End broadcast ---\n\n");
    }

    //Send notification to user by GUID
    private function sendToUserByGuid($user_guid, $notification): void
    {
        $this->debugLog("    >> Sending to user GUID: $user_guid\n");

        if (!isset($this->userConnections[$user_guid])) {
            $this->debugLog("    >> ERROR: User not connected (no entry in userConnections)\n");
            $this->debugLog("    >> Available connections: " . implode(', ', array_keys($this->userConnections)) . "\n");
            return;
        }

        $connectionCount = count($this->userConnections[$user_guid]);
        $this->debugLog("    >> User has $connectionCount active connection(s)\n");
        
        //Convert to array if object
        $notificationArray = is_object($notification) ? (array)$notification : $notification;

        /* Remove internal/routing fields before sending to client (e.g. user_guid).
        Exception: global broadcasts (broadcast=true) or events with keep_user_guid=true. */
        $isBroadcast = isset($notificationArray['broadcast']) && $notificationArray['broadcast'] === true;
        $keepUserGuid = isset($notificationArray['keep_user_guid']) && $notificationArray['keep_user_guid'] === true;
        if (!$isBroadcast && !$keepUserGuid) {
            unset($notificationArray['user_guid']);
        }
        unset($notificationArray['broadcast_to_group']);
        unset($notificationArray['exclude_users']);
        unset($notificationArray['keep_user_guid']);
        
        $this->debugLog("    >> Notification payload: " . json_encode($notificationArray) . "\n");
        
        //Send to all user's connections
        $sent = 0;
        foreach ($this->userConnections[$user_guid] as $connection) {
            $connection->send(json_encode($notificationArray));
            $sent++;
        }
        
        $this->debugLog("    >> Successfully sent to $sent connection(s)\n");
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
        
        //Check if this is a server-side connection with API key
        if (isset($queryarray['api_key'])) {
            $this->handleServerConnection($conn, $queryarray['api_key']);
            return;
        }
        
        //Regular user connection
        $guid = $queryarray['token'];
        if (!$this->isLoggedIn($conn, $guid)) {
            $conn->close(404);
            return;
        }
        list($user, $error) = getUserByGuid($guid);
        if ($error == "") {
            //Store the new connection to send messages to later
            $this->clients->attach($conn);
            $this->connectionUser[$conn->resourceId] = $guid;
            if (!isset($this->userConnections[$guid])) {
                $this->userConnections[$guid] = new SplObjectStorage();
            }
            $this->userConnections[$guid]->attach($conn);

            //Cancel any pending offline timer for this user
            if (isset($this->offlineTimers[$guid])) {
                $this->debugLog("User $guid reconnected within grace period - canceling offline timer\n");
                unset($this->offlineTimers[$guid]);
            }
            
            //Clear logout flag if user reconnects (didn't actually logout)
            if (isset($this->loggingOutUsers[$guid])) {
                $this->debugLog("User $guid reconnected - clearing logout flag\n");
                unset($this->loggingOutUsers[$guid]);
            }

            //Clear email throttle when user comes online
            clearEmailThrottleForUserByGuid($user['user_guid']);
            $this->debugLog("Cleared email throttle for user {$user['user_guid']} (came online)\n");

            //Purge any pending queued email notifications for this user so stale entries
            //are not delivered the next time they go offline.
            \EmailQueue::removeQueueEntriesForUser($user['user_guid']);
            $this->debugLog("Purged pending email queue entries for user {$user['user_guid']} (came online)\n");

            if (!$this->updateUserStatus($user, 'Active')) {
                echo "Failed to update user status\n";
            }
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
        //Check if this is a server connection
        $user_guid = $this->connectionUser[$from->resourceId] ?? null;
        $isServerConnection = ($user_guid === 'SERVER_CONNECTION');
        
        if ($isServerConnection) {
            $this->debugLog("Message from server connection\n");
            $this->debugLog("Message type: " . gettype($msg) . "\n");
            $this->debugLog("Message length: " . strlen($msg) . "\n");

            //Check if message is empty
            if (empty($msg)) {
                $this->debugLog("Empty message received (likely handshake), ignoring\n");
                return true;
            }

            $this->debugLog("Message (first 100 chars): " . substr($msg, 0, 100) . "\n");
            $this->debugLog("Message hex (first 50 bytes): " . bin2hex(substr($msg, 0, 50)) . "\n");
            
            //Handle server notification
            $data = json_decode($msg);
            
            if ($data === null) {
                echo "ERROR: Failed to decode JSON message\n";
                echo "JSON error: " . json_last_error_msg() . "\n";
                return true;
            }
            
            $this->debugLog("Decoded data: " . json_encode($data) . "\n");
            $this->debugLog("Action: " . ($data->action ?? 'not set') . "\n");

            if (isset($data->action) && $data->action === 'server_notification') {
                $this->debugLog("Calling handleServerNotification...\n");
                $this->handleServerNotification($data);
            } else {
                echo "ERROR: Not a server_notification action\n";
            }
            return true;
        }
        
        //Regular user connection, check authentication
        if (!$this->isLoggedIn($from)) {
            $from->close(404);
            return false;
        }
        
        $this->debugLog("new message:\n");

        $targets = [];
        $numRecv = count($this->clients) - 1;
        $timestamp = new Datetime();
        $currentDate = date_format($timestamp, "d/m/Y H:i:s");
        $messageCreatedDatetime = date_format($timestamp, "Y-m-d H:i:s");
        
        list($sender, $error) = getUserByGuid($user_guid);
        if ($error !== "") {
            $from->send(json_encode([$error]));
            return false;
        }

        $messageIsBinary = false;
        
        $this->debugLog(sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, "msg", $numRecv, $numRecv == 1 ? '' : 's'));
        
        $data = json_decode($msg);
        if ($data !== null) {
            //Check if the msg property exists before accessing it
            if (isset($data->msg)) {
                //Validate message length before processing
                if (mb_strlen($data->msg) > 2000) {
                    $from->send(json_encode([
                        'type' => 'error',
                        'message' => 'Message too long (max 2000 characters)'
                    ]));
                    return;
                }
            } else {
                $data->msg = '';
            }

            $data->from = $user_guid;
            $data->fromGuid = $sender['user_guid'];
            $data->status = $this->success; 
            $data->loggedIn = true; 
            $data->statusMessage = ""; //Used for error message
            $data->isReplied = false;
            $data->attachment = null; //Used for attachmentId if an attachment is stored.
            
            list($success, $targets) = $this->getTargets($data, $targets, $user_guid);
            if (!$success) {
                $error = $targets;
                $from->send(json_encode([$error]));
                return false;
            }
            
            $this->debugLog($data->action);
            
            $data->date = $messageCreatedDatetime;
            $data->messageCreatedDatetime = $messageCreatedDatetime;           
            $this->actionController($data, $sender, $targets, $currentDate, $from, $user_guid);
        } else {
            $messageIsBinary = true;
        }
        //Save attachment and send notifications
        if ($msg) {
            $this->attachmentController($user_guid, $currentDate, $from, $sender['user_username'], $messageIsBinary, $msg);
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        //Get user whose connection disconnected
        $user_guid = $this->connectionUser[$conn->resourceId] ?? null;
        if (!$user_guid) {
            $this->debugLog("Connection $conn->resourceId has disconnected (no user found)\n");
            $this->clients->detach($conn);
            return false;
        }

        list($user, $error) = getUserByGuid($user_guid);
        if ($error != "") {
            echo "Error getting user data: $error\n";
            $this->sendMessageToMyself($user, $error);
            return false;
        }
        
        //Remove connection from tracking
        unset($this->connectionUser[$conn->resourceId]);
        $this->clients->detach($conn);
        
        //Remove connection from user's connection list
        if (isset($this->userConnections[$user_guid])) {
            $this->userConnections[$user_guid]->detach($conn);

            //Only set user to Offline if this was its LAST connection
            if ($this->userConnections[$user_guid]->count() === 0) {
                unset($this->userConnections[$user_guid]); //Clean up empty storage
                
                $this->debugLog("=== onClose: Checking if user $user_guid is logging out ===\n");

                if (isset($this->loggingOutUsers[$user_guid]) && $this->loggingOutUsers[$user_guid]) {
                    $this->debugLog("User $user_guid is logging out - setting to Offline immediately (bypassing grace period)\n");
                    $this->updateUserStatus($user, 'Offline');
                    unset($this->loggingOutUsers[$user_guid]);
                    $this->debugLog("User $user_guid logout complete in onClose\n");
                } else {
                    $this->debugLog("User $user_guid is NOT logging out - applying grace period\n");
                    $gracePeriodSeconds = 5;
                    $this->debugLog("User $user_guid last connection closed - scheduling offline status in {$gracePeriodSeconds}s\n");
                    
                    $this->offlineTimers[$user_guid] = time() + $gracePeriodSeconds; //Store timer reference
                    
                    //Schedule offline status update after grace period
                    $loop = \React\EventLoop\Loop::get();
                    $loop->addTimer($gracePeriodSeconds, function() use ($user_guid, $user) {
                        //Check if timer is still active
                        if (isset($this->offlineTimers[$user_guid])) {
                            //Check if user is still offline and not logging out
                            if ((!isset($this->userConnections[$user_guid]) || $this->userConnections[$user_guid]->count() === 0) &&
                                !(isset($this->loggingOutUsers[$user_guid]) && $this->loggingOutUsers[$user_guid])) {
                                $this->updateUserStatus($user, 'Offline');
                                $this->debugLog("User $user_guid set to Offline after grace period\n");
                            } else {
                                $this->debugLog("User $user_guid reconnected during grace period - staying online\n");
                            }
                            unset($this->offlineTimers[$user_guid]);
                        } else {
                            $this->debugLog("User $user_guid offline timer was canceled (reconnected or logged out)\n");
                        }
                    });
                }
            } else {
                $this->debugLog("User $user_guid still has " . $this->userConnections[$user_guid]->count() . " active connections\n");
            }
        }

        return true;

    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        
        $user_guid = $this->connectionUser[$conn->resourceId] ?? null;
        if (!$user_guid) {
            $this->debugLog("Error on connection with no user\n");
            $conn->close();
            return;
        }

        list($user, $error) = getUserByGuid($user_guid);
        if ($error !== "" || !is_array($user)) {
            echo "Error getting user data: $error\n";
            $conn->close();
            return;
        }

        //Remove connection from tracking
        unset($this->connectionUser[$conn->resourceId]);
        $this->clients->detach($conn);
        
        //Remove connection from user's connection list
        if (isset($this->userConnections[$user_guid])) {
            $this->userConnections[$user_guid]->detach($conn);

            //Only set user to Offline if this was their LAST connection
            if ($this->userConnections[$user_guid]->count() === 0) {
                unset($this->userConnections[$user_guid]); //Clean up empty storage
                
                if (isset($this->loggingOutUsers[$user_guid]) && $this->loggingOutUsers[$user_guid]) {
                    $this->debugLog("User $user_guid logging out (error) - setting to Offline immediately (bypassing grace period)\n");
                    $this->updateUserStatus($user, 'Offline');
                    unset($this->loggingOutUsers[$user_guid]);
                } else {
                    $gracePeriodSeconds = 5;
                    
                    $this->debugLog("User $user_guid last connection error - scheduling offline status in {$gracePeriodSeconds}s\n");
                    
                    $this->offlineTimers[$user_guid] = time() + $gracePeriodSeconds; //Store timer reference
                    
                    //Schedule offline status update after grace period
                    $loop = \React\EventLoop\Loop::get();
                    $loop->addTimer($gracePeriodSeconds, function() use ($user_guid, $user) {
                        //Check if timer is still active
                        if (isset($this->offlineTimers[$user_guid])) {
                            // Check if user is still offline and not logging out
                            if ((!isset($this->userConnections[$user_guid]) || $this->userConnections[$user_guid]->count() === 0) &&
                                !(isset($this->loggingOutUsers[$user_guid]) && $this->loggingOutUsers[$user_guid])) {
                                $this->updateUserStatus($user, 'Offline');
                                $this->debugLog("User $user_guid set to Offline after grace period (error)\n");
                            } else {
                                $this->debugLog("User $user_guid reconnected during grace period after error\n");
                            }
                            unset($this->offlineTimers[$user_guid]);
                        }
                    });
                }
            } else {
                $this->debugLog("User $user_guid still has " . $this->userConnections[$user_guid]->count() . " active connections after error\n");
            }
        }
        
        $conn->close();
    }

    public function sendMessage(array $targets, mixed $data, string $currentDate, ConnectionInterface $from, $user_username): void
    {
        $msgId = "";
        $offlineNotifications = []; //Queue for offline users

        /* Inject the sender's profile image URL into the broadcast payload so recipients can render the avatar instantly without an extra lookup. */
        if (isset($data->from) && !empty($data->from) && empty($data->sender_profile_url)) {
            $data->sender_profile_url = getProfileImageUrlByGuid($data->from);
        }

        foreach ($targets as $targetUser) {
            list($currentUser, $error) = getUserByGuid($targetUser);
            if (is_array($currentUser) && $error == "") {
                if ($data->action == "sendTextMessage" || $data->action == "sendAttachment") {
                    $attachmentId = null;
                    if (isset($data->attachmentId)) {
                        $attachmentId = $data->attachmentId;
                        
                        //Update attachment metadata with chat context for one-to-one chats
                        if ($data->action == "sendAttachment" && (!isset($data->type) || $data->type !== "group")) {
                            //Get sender info
                            list($sender, $senderError) = getUserByGuid($data->from);
                            if ($senderError === "" && is_array($sender)) {
                                $senderGuid = $sender['user_guid'];
                                $recipientGuid = $currentUser['user_guid'];
                                
                                //Update attachment with chat context using GUIDs
                                updateAttachmentChatContext($attachmentId, 'user_chat', $senderGuid, $recipientGuid);
                            }
                        }
                    }
                    if ($data->from !== $targetUser) {
                        list($msgId, $error) = createMessagebyGuid($data->from,
                            $targetUser, $data->msg, $currentDate, $attachmentId);
                    }
                    if (isset($data->attachmentId)) unset($data->attachmentId);
                    $data->chatId = $msgId;
                }

                //Handle online users immediately
                if ($this->isUserOnline($targetUser)) {
                    foreach ($this->getUserConnections($targetUser) as $currentConnection) {
                        if ($currentConnection == $from)
                            $data->fromName = 'Me';
                        else
                            $data->fromName = $user_username;
                        $this->debugLog("send from " . $data->fromName . ", to: " . (isset($data->to) ? $data->to : 'group') . "\n");
                        $currentConnection->send(json_encode($data));
                        if ($msgId != "")
                            deliverMessage($msgId);
                    }
                } else {
                    //Queue offline notifications for later
                    if ($data->action == "sendTextMessage") {
                        $messageContent = $data->msg ?? "New message";
                        $offlineNotifications[] = [
                            'type' => 'message',
                            'user_guid' => $currentUser['user_guid'],
                            'username' => $currentUser['user_username'],
                            'senderName' => $user_username,
                            'sender_guid' => $data->fromGuid,
                            'content' => $messageContent
                        ];
                    } else if ($data->action == "sendAttachment") {
                        $attachmentMessage = $user_username . " sent you an attachment";
                        if (isset($data->msg) && !empty($data->msg)) {
                            $attachmentMessage .= " with message: " . $data->msg;
                        }
                        $offlineNotifications[] = [
                            'type' => 'attachment',
                            'user_guid' => $currentUser['user_guid'],
                            'username' => $currentUser['user_username'],
                            'senderName' => $user_username,
                            'sender_guid' => $data->fromGuid,
                            'content' => $attachmentMessage
                        ];
                    }
                }
            }
        }

        $this->queueOfflineNotifications($offlineNotifications);
    }

    private function queueOfflineNotifications(array $notifications): void
    {
        foreach ($notifications as $notification) {
            \EmailQueue::addToQueue(
                $notification['type'],
                $notification['user_guid'],
                $notification['senderName'],
                $notification['content'],
                $notification['sender_guid'] ?? null
            );
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

    public function isUserOnline($user_guid): bool
    {
        if (!isset($this->userConnections[$user_guid])) {
            $this->debugLog("isUserOnline: $user_guid = false\n");
            return false;
        }

        $connectionCount = $this->userConnections[$user_guid]->count();
        $isOnline = $connectionCount > 0;
        $this->debugLog("isUserOnline: $user_guid = " . ($isOnline ? 'true' : 'false') . ", $connectionCount\n");

        return $isOnline;
    }

    public function getUserConnections($user_guid): SplObjectStorage
    {
        return $this->userConnections[$user_guid];
    }

    public function updateUserStatus($user, $status = 'Active'): bool
    {
        if (!is_array($user))
            return false;
        $statusColor = 'lightgreen';
        if ($status == 'Offline') {
            $statusColor = 'salmon';
        }

        $user_guid = $user['user_guid'];
        list($success, $error) = updateUserStatusByGuid($user_guid, $status);
        if ($error != "") {
            $this->sendMessageToMyself($user, $error);
            return false;
        }
        $data = ["friend_user_guid" => $user['user_guid'], "userStatus" => $status, "action" => "updateUserStatus",
            "color" => $statusColor, "status" => true, "loggedIn" => true];
        
        //Send status update to friends
        $sentToFriends = $this->sendToAllFriends($data, $user);

        //Also send status update to group members
        $this->sendStatusToGroupMembers($data, $user);

        //Also send status update to all admin users (for User Management page)
        $this->sendStatusToAdmins($data, $user['user_guid']);

        //Also send status update back to the user, so its own UI reflects the change
        $guid = $user['user_guid'];
        if ($this->isUserOnline($guid)) {
            foreach ($this->getUserConnections($guid) as $currentConnection) {
                $currentConnection->send(json_encode($data));
            }
        }

        return $sentToFriends;
    }
    
    //Send status update to all groups where user is a member
    private function sendStatusToGroupMembers($data, $user): void
    {
        $user_guid = $user['user_guid'];

        //Get all groups where this user is a member
        list($userGroups, $error) = getUserGroupChatsByGuid($user_guid);
        if ($error !== "" || !is_array($userGroups)) {
            return;
        }

        //For each group, send status update to all online members
        foreach ($userGroups as $group) {
            $groupGuid = $group['group_guid'];

            //Get all members of this group
            list($members, $membersError) = getGroupMembersByGuid($groupGuid);
            if ($membersError !== "" || !is_array($members)) {
                continue;
            }

            //Send status update to each online member (except the user itself)
            foreach ($members as $member) {
                $memberGuid = $member['user_guid'];

                // Skip the user whose status changed (it gets its own update separately)
                if ($memberGuid === $user_guid) {
                    continue;
                }

                //Send to online members
                if ($this->isUserOnline($memberGuid)) {
                    foreach ($this->getUserConnections($memberGuid) as $currentConnection) {
                        $currentConnection->send(json_encode($data));
                    }
                }
            }
        }
    }

    //Send status update to all online admin users (for User Management page real-time updates)
    private function sendStatusToAdmins($data, $userGuid): void
    {
        //Get all admin user GUIDs
        list($adminGuids, $error) = getAdminUserGuids();
        if ($error !== "" || !is_array($adminGuids)) {
            echo "Failed to get admin GUIDs: $error\n";
            return;
        }

        //Send status update to each online admin (except if admin is the user whose status changed)
        foreach ($adminGuids as $adminGuid) {
            if ($adminGuid === $userGuid) {
                continue;
            }

            //Send to online admins
            if ($this->isUserOnline($adminGuid)) {
                foreach ($this->getUserConnections($adminGuid) as $currentConnection) {
                    $currentConnection->send(json_encode($data));
                }
                $this->debugLog("Sent status update to admin: $adminGuid\n");
            }
        }
    }

    public function sendToAllFriends($data, $user): bool
    {
        $user_guid = $user['user_guid'];
        list($friends, $error) = getFriends($user_guid);
        $this->checkForErrorAndReport($user, $error);
        if ($error !== "") {
            return false;
        }
        if (is_array($friends)) {
            foreach ($friends as $friend) {
                $friendGuid = $friend['request_from_guid'];
                list($friendMetadata, $error) = getUserByGuid($friendGuid);
                $this->checkForErrorAndReport($user, $error);
                if ($error !== "") {
                    return false;
                }
                $friendGuid = $friendMetadata['user_guid'];
                if ($this->isUserOnline($friendGuid)) {
                    foreach ($this->getUserConnections($friendGuid) as $currentConnection) {
                        $currentConnection->send(json_encode($data));
                    }
                }
            }
        }
        return true;
    }

    public function sendToAllFriendsAfter($data, $user, $friends): bool
    {
        $error = "";
        $this->checkForErrorAndReport($user, $error);
        if ($error !== "") {
            return false;
        }
        if (is_array($friends)) {
            foreach ($friends as $friend) {
                $friendGuid = $friend['request_from_guid'];
                list($friendMetadata, $error) = getUserByGuid($friendGuid);
                $this->checkForErrorAndReport($user, $error);
                if ($error !== "") {
                    return false;
                }
                $friendGuid = $friendMetadata['user_guid'];
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
        $user_guid = $user['user_guid'];
        list($error, $counter, $chatCounter) = getNotifyCountersByGuid($user_guid);
        if ($error !== "") {
            $this->sendMessageToMyself($user, $error);
            return false;
        }
        $data = ["action" => $action];
        $guid = $user['user_guid'];
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

    public function getTargets(mixed $data, array $targets, mixed $user_guid): array
    {
        switch ($data->type) {
            case "single": //one to one message
                if (isset($data->to)) {
                    $to = $data->to;

                    //Validate GUID format directly
                    if (!isValidGuid($to)) {
                        return [false, "Invalid user GUID format"];
                    }

                    /* Verify friendship before allowing 1-on-1 messaging. Without
                    this, any logged-in user could DM any user_guid they have
                    learned, bypassing the friendship UI gate (spam / harassment
                    vector). Self-targets are skipped — they are filtered later
                    by sendMessage anyway. */
                    if ($to !== $user_guid) {
                        list($friendship, $friendErr) = getConfirmedFriendByGuid($user_guid, $to);
                        if ($friendErr !== "" || !is_array($friendship) || count($friendship) === 0) {
                            return [false, "You are not friends with this user"];
                        }
                    }

                    $targets[] = $to;
                }
                $targets[] = $user_guid;
                //Send message to one client if online
                break;
            case "group": // one to group message
                /* For group messages/attachments targets array logic is not used. 
                Instead, broadcasting is handled in handleGroupMessage/handleGroupAttachment. */
                $targets[] = $user_guid; //Just add the sender to targets for error handling
                break;
        }
        return [true, $targets];
    }

    public function actionController(mixed $data, bool|array|null $sender, array $targets, string $currentDate, ConnectionInterface $from, mixed $user_guid): void
    {
        switch ($data->action) {
            case "server_notification": //Handle notification from server-side PHP connection
                $this->handleServerNotification($data); 
                break;
            case "updateNotificationsCounter": //Incoming request
                $data->status = $this->success;
                $data->isReplied = true;
                $this->sendUsersNotifications($sender);
                break;
            case "readChat":
                try {
                    $targetGuid = $data->to;
                    
                    if (!isValidGuid($targetGuid)) {
                        echo "ERROR: Invalid target user GUID format\n";
                        $data->status = false;
                        $data->statusMessage = "Invalid user GUID format";
                        break;
                    }
                    
                    list($success, $error) = readChatByGuid($sender['user_guid'], $targetGuid);

                    if ($error !== "" || $success === false) {
                        echo "ERROR: Failed to read chat: $error\n";
                        $data->status = false;
                        $data->statusMessage = $error ?: "Failed to read chat";
                        break;
                    }
                    
                    $this->debugLog("ReadChat from : {$sender['user_guid']}, to $targetGuid\n");
                    $data->status = $this->success;
                    //Send message back to sender to trigger counter update
                    $this->sendMessage(array($user_guid), $data, $currentDate, $from, $sender['user_username']);
                    $data->isReplied = true;
                    $this->sendUsersNotifications($sender);
                } catch (\InvalidArgumentException $e) {
                    echo "ERROR: Invalid user ID format in readChat: " . $e->getMessage() . "\n";
                    $data->status = false;
                    $data->statusMessage = "Invalid user ID format: " . $e->getMessage();
                }
                break;
            case "readGroupChat":
                if (!isset($data->group_guid)) {
                    echo "ERROR: No group_guid provided for readGroupChat\n";
                    $data->status = false;
                    $data->statusMessage = "Group GUID is required";
                    break;
                }
                
                try {
                    $groupGuid = $data->group_guid;
                    
                    if (!isValidGuid($groupGuid)) {
                        echo "ERROR: Invalid group GUID format for readGroupChat\n";
                        $data->status = false;
                        $data->statusMessage = "Invalid group GUID format";
                        break;
                    }
                    
                    $this->debugLog("ReadGroupChat from user: {$sender['user_guid']}, group: $groupGuid\n");
                    
                    if (!isGroupMemberByGuid($groupGuid, $sender['user_guid'])) {
                        echo "ERROR: User {$sender['user_guid']} is not a member of group $groupGuid\n";
                        $data->status = false;
                        $data->statusMessage = "You are not a member of this group";
                        break;
                    }
                    
                    //Mark all messages in group as read for this user
                    list($success, $error) = markAllMessagesReadByGuid($groupGuid, $sender['user_guid']);
                    
                    if ($success) {
                        $data->status = $this->success;
                        $data->statusMessage = "Messages marked as read";
                    } else {
                        echo "ERROR: Failed to mark group messages as read: $error\n";
                        $data->status = false;
                        $data->statusMessage = $error ?: "Failed to mark messages as read";
                    }
                    
                    //Send message back to sender to trigger counter update
                    $this->sendMessage(array($user_guid), $data, $currentDate, $from, $sender['user_username']);
                    $data->isReplied = true;
                    $this->sendUsersNotifications($sender);
                } catch (Exception $e) {
                    echo "ERROR: Exception in readGroupChat: " . $e->getMessage() . "\n";
                    $data->status = false;
                    $data->statusMessage = "Internal error";
                }
                break;
            case "syncFrom":
                list($data->syncedData, $error) = syncFullChat($data->lastdate);
                foreach ($data->syncedData as &$item) {
                    $item['chat_message'] = decrypt($item['chat_message']);
                    list($item['attachmentUrl'], $item['attachmentMimetype']) = getAttachmentUrlAndMime($item['chat_attachment_guid']);
                }
                list($data->syncedFriendData, $error) = getSyncFriendsByGuid($sender['user_guid'], $data->lastdate);
                $this->sendMessage($targets, $data, $currentDate, $from, $sender['user_username']);
                break;
            case "sendTextMessage":
                $this->sendMessage($targets, $data, $currentDate, $from, $sender['user_username']);
                $data->status = $this->success;
                $data->isReplied = true;
                break;
            case "logout":
                //Mark user as logging out to skip grace period in onClose
                $this->loggingOutUsers[$user_guid] = true;
                $this->debugLog("Marked user {$user_guid} as logging out (will skip grace period in onClose)\n");

                //Cancel any pending offline timer
                if (isset($this->offlineTimers[$user_guid])) {
                    unset($this->offlineTimers[$user_guid]);
                    $this->debugLog("Canceled pending offline timer for user {$user_guid}\n");
                }

                //Immediately set to Offline
                $this->debugLog("Calling updateUserStatus() to set user {$user_guid} to Offline...\n");
                $statusResult = $this->updateUserStatus($sender, 'Offline');
                $this->debugLog("updateUserStatus() result: " . ($statusResult ? 'SUCCESS' : 'FAILED') . "\n");

                //Close the connection
                $this->debugLog("Closing WebSocket connection for user {$user_guid}...\n");
                $from->close(1000, 'User logout');
                break;
            case "deleteMessage":
                //Check if this is a group chat deletion
                if (isset($data->type) && $data->type === 'group' && isset($data->group_guid)) {
                    $this->handleGroupMessageDeletion($data, $sender, $from);
                } else {
                    //One-on-one chat deletion, now using message GUID
                    deleteMessageByGuid($data->chatId);
                    $this->sendMessage($targets, $data, $currentDate, $from, $sender['user_username']);
                }
                $data->status = $this->success;
                $data->isReplied = true;
                break;
            case "sendAttachment":
                $this->userAttachments[$user_guid] = $data;
                list($data->status, $data->attachmentDescription, $data->statusMessage) = \FileValidator::isFileValid($data->filename, $data->filesize);

                if ($data->status) {
                    //Store attachment metadata for the binary upload phase
                    $this->userTargets[$user_guid] = $targets;
                    
                    //Send success response back to client, file will be processed in attachmentController
                    $responseData = [
                        'action' => 'sendAttachment',
                        'status' => true,
                        'message' => 'Ready to receive file data',
                        'loggedIn' => true
                    ];
                } else {
                    $data->isReplied = true; //Prevent duplicate response when binary arrives
                    $responseData = [
                        'action' => 'sendAttachment',
                        'status' => false,
                        'statusMessage' => $data->statusMessage,
                        'loggedIn' => true
                    ];
                }
                
                //Send response to the client
                $from->send(json_encode($responseData));
                break;
            case "group_message":
                $this->handleGroupMessage($data, $sender, $currentDate, $from);
                break;
            case "group_typing":
                $this->handleGroupTyping($data, $sender);
                break;
            case "direct_typing":
                $this->handleDirectTyping($data, $sender);
                break;
            default:
                break;
        }
    }

    public function attachmentController(mixed $user_guid, string $currentDate, ConnectionInterface $from, $user_username, bool $messageIsBinary, $msg): void
    {
        try {
            if (array_key_exists($user_guid, $this->userAttachments)) {
                $data = $this->userAttachments[$user_guid];
                if (!$data->isReplied) { 
                    if ($data->status != $this->success) {
                        //Error send to sender only
                        $this->sendMessage(array($user_guid,), $data, $currentDate, $from, $user_username);
                        $data->isReplied = true;
                    } else if ($data->action == "sendAttachment" && $messageIsBinary) {

                        //Create temporary file from binary message data
                        $tempFilePath = tempnam(sys_get_temp_dir(), 'chat_upload_');
                        if (file_put_contents($tempFilePath, $msg) === false) {
                            echo "ERROR: Failed to create temporary file for upload\n";
                            $this->sendErrorResponse($from, "Failed to process file upload");
                            return;
                        }

                    //Prepare file data array for the upload system
                    $fileData = [
                        'name' => $data->filename,
                        'type' => $data->filetype,
                        'tmp_name' => $tempFilePath,
                        'size' => strlen($msg),
                        'error' => UPLOAD_ERR_OK
                    ];

                    //Determine chat context
                    $chatGuid = null;
                    if (isset($data->group_guid) && $data->type === "group") {
                        //Group chat, uses group GUID as chat GUID
                        $chatGuid = $data->group_guid;
                    } else if ($data->type === "group" && !isset($data->group_guid)) {
                        echo "ERROR: Group chat type specified but no group_guid provided\n";

                        //Send error response back to client
                        $errorResponse = [
                            'action' => 'sendAttachment',
                            'status' => false,
                            'error' => 'Group GUID is required for group chat attachments',
                            'loggedIn' => true
                        ];
                        
                        //Get connection to send error
                        if (isset($this->userConnections[$user_guid])) {
                            foreach ($this->getUserConnections($user_guid) as $connection) {
                                $connection->send(json_encode($errorResponse));
                            }
                        }
                        
                        @unlink($tempFilePath);
                        $data->isReplied = true;
                        return;
                    } else if (isset($data->to)) {
                        // One-to-one chat, uses the friend request GUID as conversation ID
                        $senderGuid = $user_guid;
                        $recipientGuid = $data->to;
                        
                        //Get the request GUID for this friendship
                        $chatGuid = getConversationId($senderGuid, $recipientGuid);
                        
                        if (!$chatGuid) {
                            echo "ERROR: No friendship found between users\n";
                            @unlink($tempFilePath);
                            $this->sendErrorResponse($from, "No friendship found");
                            $data->isReplied = true;
                            return;
                        }
                        
                    } else if ($data->type === "single") {
                        // Single chat recipient not set directly, so get it from the targets array instead
                        if (isset($this->userTargets[$user_guid]) && is_array($this->userTargets[$user_guid])) {
                            $targets = $this->userTargets[$user_guid];
                            $this->debugLog("DEBUG: Found targets for single chat: " . json_encode($targets) . "\n");
                            
                            //Find the recipient (not the sender)
                            $recipientGuid = null;
                            foreach ($targets as $target) {
                                if ($target !== $user_guid) {
                                    $recipientGuid = $target;
                                    break;
                                }
                            }
                            
                            if ($recipientGuid) {
                                $chatGuid = getConversationId($user_guid, $recipientGuid);
                                
                                if (!$chatGuid) {
                                    echo "ERROR: No friendship found between users\n";
                                    @unlink($tempFilePath);
                                    return;
                                }
                                
                                //Update data object for later use
                                $data->to = $recipientGuid;
                            } else {
                                echo "ERROR: No recipient found in targets for single chat\n";
                                @unlink($tempFilePath);
                                return;
                            }
                        } else {
                            echo "ERROR: No targets found for single chat\n";
                            @unlink($tempFilePath);
                            return;
                        }
                    } else {
                        echo "ERROR: No targets found in chat context\n";
                        @unlink($tempFilePath);
                        return;
                    }

                    //Use the upload system to process the file
                    $uploadResult = $this->uploadController->uploadChatMedia($user_guid, $chatGuid, $fileData);
                    
                    if (!$uploadResult['success']) {
                        echo "ERROR: Upload failed: " . ($uploadResult['error']['message'] ?? 'Unknown error') . "\n";
                        
                        //Send error response to client
                        $errorData = $data;
                        $errorData->status = $this->fail;
                        $errorData->statusMessage = "File upload failed: " . ($uploadResult['error']['message'] ?? 'Unknown error');
                        $this->sendMessage(array($user_guid), $errorData, $currentDate, $from, $user_username);
                        $data->isReplied = true;
                        return;
                    }
                    
                    //Update data with upload results
                    $data->attachment = $uploadResult['data']['url'];
                    $data->attachmentId = $uploadResult['data']['file_guid'];
                    
                    $this->debugLog("DEBUG: Updated data - attachment: {$data->attachment}, attachmentId: {$data->attachmentId}\n");
                    $this->debugLog("DEBUG: Upload result URL: " . $uploadResult['data']['url'] . "\n");

                    //Check if this is a group chat attachment
                    if (isset($data->group_guid) && $data->type === "group") {
                        $this->debugLog("DEBUG: Handling group attachment\n");
                        $this->handleGroupAttachment($data, $from, $user_username, $currentDate);
                    } else {
                        //Handle one-on-one attachment
                        $this->debugLog("DEBUG: Handling one-to-one attachment\n");
                        $this->sendMessage($this->userTargets[$user_guid],
                            $data, $currentDate, $from, $user_username);
                    }
                    $data->isReplied = true;
                }
            }
        }
        } catch (Exception $e) {
            echo "ERROR: Exception in attachmentController: " . $e->getMessage() . "\n";
            echo "ERROR: Stack trace: " . $e->getTraceAsString() . "\n";
            
            $this->sendErrorResponse($from, "File upload failed: " . $e->getMessage());
            
            //Mark as replied to prevent further processing
            if (isset($this->userAttachments[$user_guid])) {
                $this->userAttachments[$user_guid]->isReplied = true;
            }
        }
    }
    
    //Send error response to client
    private function sendErrorResponse(ConnectionInterface $from, string $message): void
    {
        $errorResponse = [
            'action' => 'sendAttachment',
            'status' => false,
            'error' => $message,
            'loggedIn' => true
        ];
        
        try {
            $from->send(json_encode($errorResponse));
        } catch (Exception $e) {
            echo "ERROR: Failed to send error response: " . $e->getMessage() . "\n";
        }
    }
    
    //Handle group attachment (similar to handleGroupMessage but for attachments)
    private function handleGroupAttachment($data, ConnectionInterface $from, $user_username, $currentDate): void
    {
        try {
            //Validate group GUID format directly
            $groupGuid = $data->group_guid ?? null;
            
            if (!$groupGuid || !isValidGuid($groupGuid)) {
                $errorData = [
                    'action' => 'sendAttachment',
                    'status' => false,
                    'statusMessage' => 'Invalid group GUID format',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                return;
            }
            
            //Validate group exists and get group info
            list($groupInfo, $groupError) = getGroupChatByGuid($groupGuid);
            if ($groupError !== "" || !is_array($groupInfo)) {
                $errorData = [
                    'action' => 'sendAttachment',
                    'status' => false,
                    'error' => 'Group not found',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                echo "Group not found for GUID: $groupGuid\n";
                return;
            }
            
            $sender = null;
            
            //Get sender info
            list($sender, $error) = getUserByGuid($data->from);
            if ($error !== "" || !is_array($sender)) {
                echo "Failed to get sender info: $error\n";
                return;
            }
            
            //Validate user is a member of the group
            if (!isGroupMemberByGuid($groupGuid, $sender['user_guid'])) {
                $errorData = [
                    'action' => 'sendAttachment',
                    'status' => false,
                    'error' => 'You are not a member of this group',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                echo "User {$sender['user_guid']} is not a member of group {$groupGuid}\n";
                return;
            }
            
            $messageContent = $data->msg ?? '';
            $attachmentGuid = $data->attachmentId ?? null;
            list($message, $msgError) = sendGroupMessageByGuid($groupGuid, $sender['user_guid'], $messageContent, $attachmentGuid);
            
            if ($msgError !== "" || !$message) {
                $errorData = [
                    'action' => 'sendAttachment',
                    'status' => false,
                    'error' => $msgError ?: 'Failed to send attachment',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                echo "Failed to store group attachment: $msgError\n";
                return;
            }
            
            //Get all group members using GUID-based function
            list($members, $membersError) = getGroupMembersByGuid($groupGuid);
            if ($membersError !== "" || !is_array($members)) {
                echo "Failed to get group members: $membersError\n";
                return;
            }
            
            //Get sender's profile image URL
            require_once dirname(__FILE__) . "/includes/db/profileImage.php";
            $profileImageUrl = getProfileImageUrlByGuid($sender['user_guid']);
            
            //Prepare broadcast data (same format as one-on-one sendAttachment)
            $broadcastData = [
                'action' => 'sendAttachment',
                'type' => 'group',
                'group_guid' => $groupGuid,
                'chatId' => $message['message_guid'],
                'senderGuid' => $sender['user_guid'],
                'senderName' => $sender['user_username'],
                'sender_profile_url' => $profileImageUrl,
                'msg' => $messageContent,
                'attachment' => $data->attachment,
                'filetype' => $data->filetype,
                'filename' => $data->filename,
                'date' => $message['sent_at'],
                'status' => true,
                'loggedIn' => true
            ];
            
            $this->debugLog("DEBUG: Broadcasting attachment with URL: " . $data->attachment . "\n");
            $this->debugLog("DEBUG: Broadcast data: " . json_encode($broadcastData) . "\n");
            
            //Broadcast to all online group members (group chats do not send email notifications)
            foreach ($members as $member) {
                //Use GUID from member data
                $memberGuid = $member['user_guid'];
                $memberUser = $member; //Member data already includes user info

                if ($this->isUserOnline($memberGuid)) {
                    //Send to online members
                    foreach ($this->getUserConnections($memberGuid) as $currentConnection) {
                        //Mark if this is the sender
                        $sendData = $broadcastData;
                        if ($currentConnection == $from) {
                            $sendData['fromName'] = 'Me';
                        } else {
                            $sendData['fromName'] = $sender['user_username'];
                        }
                        $currentConnection->send(json_encode($sendData));
                        $this->debugLog("Sent group attachment to online member: {$memberUser['user_username']}\n");
                    }
                }
            }

            $this->debugLog("Group attachment broadcast complete for group {$groupGuid}\n");
            
        } catch (\InvalidArgumentException $e) {
            $errorData = [
                'action' => 'sendAttachment',
                'status' => false,
                'error' => 'Invalid group ID format: ' . $e->getMessage(),
                'loggedIn' => true
            ];
            $from->send(json_encode($errorData));
            echo "Invalid group ID format in handleGroupAttachment: " . $e->getMessage() . "\n";
        }
    }

    public function sendMessageToMyself($user, $message): void
    {
        $conn = ($this->userConnections[$user['user_guid']]);  // if user logged on
        $conn->send(json_encode($message));
    }

    public function isUserOnlineDB(string|null $jwt, string|null $remember_me): array|bool
    {
        return is_user_logged_in_websocket($jwt, $remember_me);
    }

    public function isLoggedIn(ConnectionInterface $conn, string|null $guid = null): bool
    {
        //Check if this is a server connection (no cookies needed)
        $headers = $conn->httpRequest->getHeaders();
        if (!isset($headers['Cookie'])) {
            /* No cookies means this might be a server connection or invalid client.
            Server connections are handled in onOpen, so this is unauthorized. */
            $conn->send(json_encode(value: array(["error" => 'Unauthorized'])));
            return false;
        }
        
        $raw_cookie = $headers['Cookie'];
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
            return false;
        }
        if ($guid !== null) {
            return $guid == $userLogged[0]['user_guid'];
        }
        return true;
    }

    public function handleGroupMessage(mixed $data, array $sender, string $currentDate, ConnectionInterface $from): void
    {
        $this->debugLog("Handling group message from user {$sender['user_guid']} to group {$data->group_guid}\n");
        
        //Validate that group_guid and message are present
        if (!isset($data->group_guid) || !isset($data->msg)) {
            $errorData = [
                'action' => 'group_message',
                'status' => false,
                'error' => 'Missing group_guid or message content',
                'loggedIn' => true
            ];
            $from->send(json_encode($errorData));
            return;
        }
        
        try {
            $groupGuid = $data->group_guid;
            
            if (!isValidGuid($groupGuid)) {
                $errorData = [
                    'action' => 'group_message',
                    'status' => false,
                    'error' => 'Invalid group GUID format',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                return;
            }
            
            $messageContent = $data->msg;
            
            //Validate if user is a member of the group
            if (!isGroupMemberByGuid($groupGuid, $sender['user_guid'])) {
                $errorData = [
                    'action' => 'group_message',
                    'status' => false,
                    'error' => 'You are not a member of this group',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                echo "User {$sender['user_guid']} is not a member of group {$groupGuid}\n";
                return;
            }

            //Check if group is active (cannot send messages to deactivated groups)
            if (!\isGroupChatActiveByGuid($groupGuid)) {
                $errorData = [
                    'action' => 'group_message',
                    'status' => false,
                    'error' => 'Cannot send messages to a deactivated group. The group needs at least 3 members to be active.',
                    'group_deactivated' => true,
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                echo "Group {$groupGuid} is deactivated, cannot send messages\n";
                return;
            }

            //Store the message
            list($message, $error) = sendGroupMessageByGuid($groupGuid, $sender['user_guid'], $messageContent);
            
            if ($error !== "" || !$message) {
                $errorData = [
                    'action' => 'group_message',
                    'status' => false,
                    'error' => $error ?: 'Failed to send message',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                echo "Failed to store group message: $error\n";
                return;
            }
            
            //Get all group members
            list($members, $membersError) = getGroupMembersByGuid($groupGuid);
            if ($membersError !== "" || !is_array($members)) {
                echo "Failed to get group members: $membersError\n";
                return;
            }
            
            //Get sender's profile image URL
            require_once dirname(__FILE__) . "/includes/db/profileImage.php";
            $profileImageUrl = getProfileImageUrlByGuid($sender['user_guid']);
            
            //Prepare broadcast data
            $broadcastData = [
                'type' => 'group_message',
                'action' => 'group_message',
                'group_guid' => $groupGuid, 
                'message_guid' => $message['message_guid'],
                'sender_guid' => $sender['user_guid'], 
                'sender_name' => $sender['user_username'],
                'sender_profile_url' => $profileImageUrl,
                'message' => $messageContent,
                'sent_at' => $message['sent_at'],
                'date' => $message['sent_at'], 
                'status' => true,
                'loggedIn' => true
            ];
            
            //Broadcast to all online group members (group chats do not send email notifications)
            foreach ($members as $member) {
                //Use GUID from member data
                $memberGuid = $member['user_guid'];
                $memberUser = $member; //Member data already includes user info

                if ($this->isUserOnline($memberGuid)) {
                    //Send to online members
                    foreach ($this->getUserConnections($memberGuid) as $currentConnection) {
                        //Mark if this is the sender
                        $sendData = $broadcastData;
                        if ($currentConnection == $from) {
                            $sendData['fromName'] = 'Me';
                        } else {
                            $sendData['fromName'] = $sender['user_username'];
                        }
                        $currentConnection->send(json_encode($sendData));
                        $this->debugLog("Sent group message to online member: {$memberUser['user_username']}\n");
                    }
                }
            }

            $this->debugLog("Group message broadcast complete for group {$groupGuid}\n");
            
        } catch (\InvalidArgumentException $e) {
            $errorData = [
                'action' => 'group_message',
                'status' => false,
                'error' => 'Invalid group ID format: ' . $e->getMessage(),
                'loggedIn' => true
            ];
            $from->send(json_encode($errorData));
            echo "Invalid group ID format in handleGroupMessage: " . $e->getMessage() . "\n";
        }
    }

    public function handleGroupTyping(mixed $data, array $sender): void
    {
        $this->debugLog("Handling group typing from user {$sender['user_guid']} in group {$data->group_guid}\n");
        
        //Validate that group_guid and is_typing are present
        if (!isset($data->group_guid) || !isset($data->is_typing)) {
            return;
        }
        
        try {
            $groupGuid = $data->group_guid;
            
            if (!isValidGuid($groupGuid)) {
                echo "Invalid group GUID format for typing indicator\n";
                return;
            }
            
            $isTyping = (bool)$data->is_typing;
            
            //Validate user is a member of the group
            if (!isGroupMemberByGuid($groupGuid, $sender['user_guid'])) {
                echo "User {$sender['user_guid']} is not a member of group {$groupGuid}\n";
                return;
            }
            
            //Get all group members
            list($members, $membersError) = getGroupMembersByGuid($groupGuid);
            if ($membersError !== "" || !is_array($members)) {
                echo "Failed to get group members: $membersError\n";
                return;
            }
            
            //Prepare broadcast data
            $broadcastData = [
                'type' => 'group_typing',
                'action' => 'group_typing',
                'group_guid' => $groupGuid, 
                'user_guid' => $sender['user_guid'],
                'username' => $sender['user_username'],
                'is_typing' => $isTyping,
                'status' => true,
                'loggedIn' => true
            ];
            
            //Broadcast to all online group members except the sender
            foreach ($members as $member) {
                if ($member['user_guid'] === $sender['user_guid']) {
                    continue; // Skip the sender
                }
                
                $memberGuid = $member['user_guid'];
                $memberUser = $member; //Member data already includes user info
                
                if ($this->isUserOnline($memberGuid)) {
                    //Send to online members
                    foreach ($this->getUserConnections($memberGuid) as $currentConnection) {
                        $currentConnection->send(json_encode($broadcastData));
                    }
                    $this->debugLog("Sent typing indicator to online member: {$memberUser['user_username']}\n");
                }
            }
            
            $this->debugLog("Group typing indicator broadcast complete for group {$groupGuid}\n");
            
        } catch (\InvalidArgumentException $e) {
            echo "Invalid group ID format in handleGroupTyping: " . $e->getMessage() . "\n";
        }
    }

    //Handle direct typing indicator (one-to-one chat)
    public function handleDirectTyping(mixed $data, array $sender): void
    {
        if (!isset($data->to) || !isset($data->is_typing)) {
            return;
        }

        $recipientGuid = $data->to;
        if (!isValidGuid($recipientGuid)) {
            return;
        }

        $broadcastData = [
            'type'      => 'direct_typing',
            'action'    => 'direct_typing',
            'from_guid' => $sender['user_guid'],
            'username'  => $sender['user_username'],
            'is_typing' => (bool)$data->is_typing,
            'status'    => true,
            'loggedIn'  => true
        ];

        if ($this->isUserOnline($recipientGuid)) {
            foreach ($this->getUserConnections($recipientGuid) as $conn) {
                $conn->send(json_encode($broadcastData));
            }
        }
    }

    public function handleGroupMessageDeletion(mixed $data, array $sender, ConnectionInterface $from): void
    {
        $this->debugLog("Handling group message deletion from user {$sender['user_guid']} in group {$data->group_guid}\n");
        
        //Validate that group_guid and chatId are present
        if (!isset($data->group_guid) || !isset($data->chatId)) {
            $errorData = [
                'action' => 'deleteMessage',
                'status' => false,
                'error' => 'Missing group_guid or message GUID',
                'loggedIn' => true
            ];
            $from->send(json_encode($errorData));
            return;
        }
        
        try {
            $groupGuid = $data->group_guid;
            
            if (!isValidGuid($groupGuid)) {
                $errorData = [
                    'action' => 'deleteMessage',
                    'status' => false,
                    'error' => 'Invalid group GUID format',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                return;
            }
            
            $messageGuid = $data->chatId;
            
            if (!isValidGuid($messageGuid)) {
                $errorData = [
                    'action' => 'deleteMessage',
                    'status' => false,
                    'error' => 'Invalid message GUID format',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                return;
            }
            
            //Validate user is a member of the group
            if (!isGroupMemberByGuid($groupGuid, $sender['user_guid'])) {
                $errorData = [
                    'action' => 'deleteMessage',
                    'status' => false,
                    'error' => 'You are not a member of this group',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                echo "User {$sender['user_guid']} is not a member of group {$groupGuid}\n";
                return;
            }
            
            //Delete the message from database (soft delete, matching one-to-one behavior)
            list($success, $error) = deleteGroupMessageWithGuid($messageGuid, $sender['user_guid']);
            if ($error !== "" || !$success) {
                $errorData = [
                    'action' => 'deleteMessage',
                    'status' => false,
                    'error' => $error ?: 'Failed to delete message',
                    'loggedIn' => true
                ];
                $from->send(json_encode($errorData));
                echo "Failed to delete group message {$messageGuid}: $error\n";
                return;
            }
            $this->debugLog("Deleted group message {$messageGuid} from group {$groupGuid}\n");
            
            //Get all group members
            list($members, $membersError) = getGroupMembersByGuid($groupGuid);
            if ($membersError !== "" || !is_array($members)) {
                echo "Failed to get group members: $membersError\n";
                return;
            }
            
            //Prepare broadcast data 
            $broadcastData = [
                'type' => 'group',
                'action' => 'deleteMessage',
                'group_guid' => $groupGuid, 
                'chatId' => $messageGuid,
                'status' => true,
                'loggedIn' => true
            ];
            
            $this->debugLog("Broadcasting delete to " . count($members) . " group members\n");
            $this->debugLog("Broadcast data: " . json_encode($broadcastData) . "\n");
            
            //Broadcast to all online group members
            $sentCount = 0;
            $offlineCount = 0;
            foreach ($members as $member) {
                $memberGuid = $member['user_guid'];
                $this->debugLog("  Processing member GUID: $memberGuid\n");
                
                //Use GUID from member data
                $memberGuid = $member['user_guid'];
                $memberUser = $member; //Member data already includes user info
                
                $this->debugLog("  Member GUID: $memberGuid, Username: {$memberUser['user_username']}\n");
                
                if ($this->isUserOnline($memberGuid)) {
                    //Send to online members
                    $connectionCount = 0;
                    foreach ($this->getUserConnections($memberGuid) as $currentConnection) {
                        //Mark if this is the sender
                        $sendData = $broadcastData;
                        if ($currentConnection == $from) {
                            $sendData['fromName'] = 'Me';
                        } else {
                            $sendData['fromName'] = $sender['user_username'];
                        }
                        $currentConnection->send(json_encode($sendData));
                        $connectionCount++;
                    }
                    $this->debugLog("  SENT to {$memberUser['user_username']} ($connectionCount connections)\n");
                    $sentCount++;
                } else {
                    $this->debugLog("  OFFLINE: {$memberUser['user_username']}\n");
                    $offlineCount++;
                }
            }
            
            $this->debugLog("Group message deletion broadcast complete: $sentCount online, $offlineCount offline\n");
            
        } catch (\InvalidArgumentException $e) {
            $errorData = [
                'action' => 'deleteMessage',
                'status' => false,
                'error' => 'Invalid group ID format: ' . $e->getMessage(),
                'loggedIn' => true
            ];
            $from->send(json_encode($errorData));
            echo "Invalid group ID format in handleGroupMessageDeletion: " . $e->getMessage() . "\n";
        }
    }

}
