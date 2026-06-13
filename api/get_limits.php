<?php
require_once __DIR__ . "/init.php";

header("Content-Type: application/json; charset=utf-8");

$nicknameMax = defined("NICKNAME_MAX_LENGTH") ? (int)NICKNAME_MAX_LENGTH : 12;
$messageMax = defined("MESSAGE_MAX_LENGTH") ? (int)MESSAGE_MAX_LENGTH : 50;

echo json_encode([
    "code" => 0,
    "data" => [
        "nickname_max_length" => $nicknameMax,
        "message_max_length" => $messageMax
    ]
]);
