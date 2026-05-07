<?php
/* VALIDATE MEMEBERS FOR GROUP CREATION ENDPOINT

POST /api/friend-api.php?action=validate-members-for-group
 
Validates multiple users for group creation by checking:
 1. Friendship status with the requesting user (admin)
 2. Whether the user is banned by the web app admin
This endpoint provides real-time validation during the Create Group flow. */

require_once __DIR__ . '/../../includes/db/addrequest.php';
require_once __DIR__ . '/../../includes/db/users.php';

//Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (20 requests per minute per user)
checkRateLimit($user_guid, 'validate_members_for_group', 20, 60);

//Get request body
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$data = [];

if (str_contains($contentType, 'application/json')) {
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError("Invalid JSON in request body", 400);
    }
} else {
    //Handle form data
    $data = $_POST;
}

//Validate required field (member_guids)
if (!isset($data['member_guids']) || !is_array($data['member_guids'])) {
    sendError("member_guids array is required", 400);
}

$memberGuids = $data['member_guids'];

//Validate array is not empty
if (empty($memberGuids)) {
    sendError("member_guids array cannot be empty", 400);
}

//Limit the number of GUIDs that can be validated at once (prevent abuse)
if (count($memberGuids) > 50) {
    sendError("Cannot validate more than 50 members at once", 400);
}

//Validate each member GUID and collect results
$validationResults = [];
$validCount = 0;
$invalidCount = 0;

foreach ($memberGuids as $memberGuid) {
    $result = [
        'user_guid' => $memberGuid,
        'username' => null,
        'is_valid' => false,
        'is_friend' => false,
        'is_banned' => false,
        'user_exists' => false,
        'error' => null
    ];

    //Validate GUID format
    if (!is_string($memberGuid) || empty($memberGuid)) {
        $result['error'] = 'Invalid GUID format';
        $invalidCount++;
        $validationResults[] = $result;
        continue;
    }

    //Check if user exists and get their details (including ban status)
    list($userData, $userError) = getUserByGuidWithBanStatus($memberGuid);

    if ($userError !== "" || !is_array($userData)) {
        $result['error'] = 'User not found';
        $invalidCount++;
        $validationResults[] = $result;
        continue;
    }

    $result['user_exists'] = true;
    $result['username'] = $userData['user_username'];

    //Check if user is banned
    if (isset($userData['user_banned']) && $userData['user_banned'] == 1) {
        $result['is_banned'] = true;
        $result['error'] = 'User is banned';
        $invalidCount++;
        $validationResults[] = $result;
        continue;
    }

    //Check if user is deleted
    if (isset($userData['user_is_deleted']) && $userData['user_is_deleted'] == 1) {
        $result['error'] = 'User account has been deleted';
        $invalidCount++;
        $validationResults[] = $result;
        continue;
    }

    //Check friendship status
    list($friendship, $friendshipError) = getConfirmedFriendByGuid($user_guid, $memberGuid);
    $isFriend = ($friendshipError === "" && is_array($friendship) && count($friendship) > 0);

    $result['is_friend'] = $isFriend;

    if (!$isFriend) {
        $result['error'] = 'User is not your friend';
        $invalidCount++;
        $validationResults[] = $result;
        continue;
    }

    //All checks passed (user is valid for group creation)
    $result['is_valid'] = true;
    $result['error'] = null;
    $validCount++;
    $validationResults[] = $result;
}

//Determine if all users are valid
$allValid = ($invalidCount === 0 && $validCount > 0);

sendResponse(true, [
    'validation_results' => $validationResults,
    'all_valid' => $allValid,
    'valid_count' => $validCount,
    'invalid_count' => $invalidCount
], 'Validation completed', 200);

/* Get user including ban status.
This is a helper function specifically for validation. */
function getUserByGuidWithBanStatus($guid): array
{
    $conn = getDbConnection();
    $error = "Failed to find user. Please try again.";

    $sql = "SELECT user_username, user_banned, user_is_deleted,
                   bin_to_uuid(user_guid, true) as user_guid
            FROM users
            WHERE user_guid = uuid_to_bin(?, true);";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "s", $guid);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);

    if (!$row) {
        return array(false, "User not found");
    }

    return array($row, "");
}