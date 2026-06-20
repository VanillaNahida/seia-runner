<?php
require_once __DIR__ . "/geetest_config.php";
require_once __DIR__ . "/geetest_lib.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode(["code" => 405, "message" => "Method Not Allowed"]);
    exit;
}

// GT 4.0 不需要 register 步骤，只需返回 captcha_id 供前端初始化
echo json_encode([
    "code" => 0,
    "data" => [
        "captcha_id" => GEETEST_ID
    ]
]);
