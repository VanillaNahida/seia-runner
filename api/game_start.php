<?php
require_once __DIR__ . "/init.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["code" => 405, "message" => "Method Not Allowed"]);
    exit;
}

$session = issueGameSession();

echo json_encode([
    "code" => 0,
    "data" => $session
]);
