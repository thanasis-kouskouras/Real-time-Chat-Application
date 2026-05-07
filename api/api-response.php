<?php
/* API RESPONSE HELPER

Common functions for sending JSON responses and handling input from API endpoints. */

require_once __DIR__ . '/../includes/guid-utilities.php';
require_once __DIR__ . '/guid-error-handler.php';


//Send JSON response
function sendResponse($success, $data = null, $message = "", $statusCode = 200) {
    http_response_code($statusCode);

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        if (is_array($data)) {
            $response = array_merge($response, $data);
        } else {
            $response['data'] = $data;
        }
    }

    //Prevent browser caching of API responses
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo json_encode($response);
    exit;
}

//Send error response
function sendError($message, $statusCode = 400, $errors = null): void
{
    http_response_code($statusCode);

    $response = [
        'success' => false,
        'message' => $message
    ];

    if ($errors !== null) {
        $response['errors'] = $errors;
    }

    //Prevent browser caching of API responses
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo json_encode($response);
    exit;
}

//Get JSON input from request body
function getJsonInput() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON input', 400);
    }
    
    return $data ?? [];
}

/* Get input from either JSON or POST.
Automatically detects content type and returns appropriate data. */
function getInput() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (str_contains($contentType, 'application/json')) {
        return getJsonInput();
    }
    
    return $_POST;
}

//Validate user ID (GUID format only)
function validateUserGuid($id, $fieldName = 'user_guid'): ?string {
    if (empty($id)) {
        sendError("$fieldName is required", 400);
    }
    
    if (!is_string($id)) {
        sendError("$fieldName must be a GUID string", 400);
    }
    
    try {
        $guidGenerator = getGuidGenerator();
        if (!$guidGenerator->validateGuid($id)) {
            GuidErrorHandler::handleInvalidGuidFormat($id, 'validateUserGuid', $fieldName);
        }
        
        //Verify if user exists
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE user_guid = uuid_to_bin(?, true)");
        if (!$stmt) {
            throw new RuntimeException('Database error: ' . mysqli_error($conn));
        }
        
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->fetch_assoc();
        $stmt->close();
        
        if (!$exists) {
            GuidErrorHandler::handleGuidNotFound($id, 'user', 'validateUserGuid');
        }
        
        return $id;
    } catch (Exception $e) {
        app_log("User ID validation error: " . $e->getMessage());
        GuidErrorHandler::handleGuidException($e, 'validateUserGuid');
    }
    return null;
}


//Validate array of user IDs (GUID format only)
function validateUserGuidArray($ids, string $fieldName = 'user_guids'): array {
    if (!is_array($ids)) {
        sendError("$fieldName must be an array", 400);
    }
    
    if (empty($ids)) {
        sendError("$fieldName cannot be empty", 400);
    }
    
    $validGuids = [];
    $guidGenerator = getGuidGenerator();
    $conn = getDbConnection();
    
    foreach ($ids as $index => $id) {
        if (!is_string($id)) {
            sendError("User ID at index $index in $fieldName must be a GUID string", 400);
        }
        
        try {
            if (!$guidGenerator->validateGuid($id)) {
                GuidErrorHandler::handleInvalidGuidFormat($id, "validateUserGuidArray[index:$index]", $fieldName);
            }
            
            //Verify if user exists
            $stmt = $conn->prepare("SELECT 1 FROM users WHERE user_guid = uuid_to_bin(?, true)");
            if (!$stmt) {
                throw new RuntimeException('Database error: ' . mysqli_error($conn));
            }
            
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->fetch_assoc();
            $stmt->close();
            
            if (!$exists) {
                GuidErrorHandler::handleGuidNotFound($id, 'user', "validateUserGuidArray[index:$index]");
            }
            
            $validGuids[] = $id;
        } catch (Exception $e) {
            app_log("User ID array validation error at index $index: " . $e->getMessage());
            GuidErrorHandler::handleGuidException($e, "validateUserGuidArray[index:$index]");
        }
    }
    
    return $validGuids;
}

//Validate required fields in input data
function validateRequiredFields($data, $requiredFields) {
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        sendError("Missing required fields: " . implode(', ', $missingFields), 400);
    }
    
    return true;
}

function sendSuccessResponse($data = null, $message = "Success", $statusCode = 200) {
    sendResponse(true, $data, $message, $statusCode);
}

function sendErrorResponse($message, $statusCode = 400, $errors = null) {
    sendError($message, $statusCode, $errors);
}

//Check rate limit for an action (GUID format only)
function checkRateLimit($user_guid, $action, $limit, $windowSeconds) {
    require_once __DIR__ . '/../includes/dbh.inc.php';
    $conn = getDbConnection();
    
    /* Convert identifier to 16-byte binary key for DB storage.
    Accepts GUID format (user sessions) and arbitrary strings (email/IP). */
    try {
        $guidGenerator = getGuidGenerator();
        if ($guidGenerator->validateGuid($user_guid)) {
            $user_guidBytes = $guidGenerator->guidToBytes($user_guid);
        } else {
            //Non-GUID identifier (email/IP address) — MD5 hash fits BINARY(16)
            $user_guidBytes = md5($user_guid, true);
        }
    } catch (Exception $e) {
        app_log("Rate limit check error: " . $e->getMessage());
        return true; //Fail open only on unexpected errors
    }
    
    //Create rate_limits table if it doesn't exist
    $createTableSql = "CREATE TABLE IF NOT EXISTS rate_limits (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_guid BINARY(16) NOT NULL,
        action VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_action (user_guid, action, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
    
    $conn->query($createTableSql);
    
    //Calculate time threshold
    $threshold = date('Y-m-d H:i:s', time() - $windowSeconds);
    
    //Clean up old entries for this user and action
    $cleanupSql = "DELETE FROM rate_limits WHERE user_guid = ? AND action = ? AND created_at < ?";
    $cleanupStmt = $conn->prepare($cleanupSql);
    if ($cleanupStmt) {
        $cleanupStmt->bind_param("sss", $user_guidBytes, $action, $threshold);
        $cleanupStmt->execute();
        $cleanupStmt->close();
    }
    
    //Count recent actions
    $countSql = "SELECT COUNT(*) as count FROM rate_limits WHERE user_guid = ? AND action = ? AND created_at >= ?";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        //If rate limit check fails, allow the fail open action
        return true;
    }
    
    $countStmt->bind_param("sss", $user_guidBytes, $action, $threshold);
    $countStmt->execute();
    $result = $countStmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'] ?? 0;
    $countStmt->close();
    
    //Check if limit exceeded
    if ($count >= $limit) {
        $retryAfter = $windowSeconds;
        header("Retry-After: $retryAfter");
        sendError("Rate limit exceeded. Please try again later.", 429);
    }
    
    //Record this action
    $insertSql = "INSERT INTO rate_limits (user_guid, action, created_at) VALUES (?, ?, NOW())";
    $insertStmt = $conn->prepare($insertSql);
    if ($insertStmt) {
        $insertStmt->bind_param("ss", $user_guidBytes, $action);
        $insertStmt->execute();
        $insertStmt->close();
    }
    
    return true;
}