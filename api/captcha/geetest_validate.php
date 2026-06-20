<?php
require_once __DIR__ . "/geetest_config.php";
require_once __DIR__ . "/geetest_lib.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["code" => 405, "message" => "Method Not Allowed"]);
    exit;
}

$lot_number     = isset($_POST["lot_number"])     ? trim($_POST["lot_number"])     : "";
$captcha_output = isset($_POST["captcha_output"]) ? trim($_POST["captcha_output"]) : "";
$pass_token     = isset($_POST["pass_token"])     ? trim($_POST["pass_token"])     : "";
$gen_time       = isset($_POST["gen_time"])       ? trim($_POST["gen_time"])       : "";

$gtLib = new GeetestLib(GEETEST_ID, GEETEST_KEY);

// 尝试在线验证，失败则降级
$result = $gtLib->validate($lot_number, $captcha_output, $pass_token, $gen_time);

if ($result->getStatus() !== 1) {
    error_log("[Geetest] 二次验证: 在线验证失败，回退到本地校验 | captcha_id=" . GEETEST_ID . " | msg=" . $result->getMsg());
    $result = $gtLib->failValidate($lot_number, $captcha_output, $pass_token, $gen_time);
} else {
    error_log("[Geetest] 二次验证: 在线验证通过 | captcha_id=" . GEETEST_ID);
}

if ($result->getStatus() === 1) {
    echo json_encode(["code" => 0, "message" => "success"]);
} else {
    echo json_encode(["code" => 1, "message" => "验证失败，请重新完成验证码"]);
}
