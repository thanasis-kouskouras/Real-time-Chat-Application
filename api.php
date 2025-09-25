<?php

require 'protect.php';
function isValidJSON($str): bool
{
    json_decode($str);
    return json_last_error() == JSON_ERROR_NONE;
}

$json_params = file_get_contents("php://input");

if (strlen($json_params) > 0 && isValidJSON($json_params)) {
    $decoded_params = json_decode($json_params, true);
    $received_key = $decoded_params['key1'];
    $userToken = $decoded_params['token'];
    if ($received_key == '123456') {
        $data_type = $decoded_params['data_type'];
        if ($data_type == 'json_g_profile') {
            header('Content-Type: application/json');  // <-- header declaration
            list($url, $error) = getProfileImagePath($userToken); // get profile image path ready
            $jsonData = json_encode(["url" => $url]);
            echo $jsonData . "\n";
        } else if ($data_type == 'json_search_users') {
            $searchString = filter_var($decoded_params['searchString'], FILTER_SANITIZE_STRING);
            header('Content-Type: application/json');  // <-- header declaration
            list($result, $error) = searchUserExceptMe($searchString, getUserByGuid($userToken)[0]['UsersID']); // search api for android
            $jsonData = json_encode(["result" => $result]);
            echo $jsonData . "\n";
        }
    }

} else {
    header('Content-Type: application/json');  // <-- header declaration
    echo json_encode('msg:failed');

}
