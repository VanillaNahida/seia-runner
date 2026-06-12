<?php
require_once __DIR__ . "/init.php";

header("Content-Type: application/json; charset=utf-8");

// ---- CSRF Protection ----
if (!isset($_POST["csrf_token"]) || !isset($_SESSION["csrf_token"]) || $_POST["csrf_token"] !== $_SESSION["csrf_token"]) {
    echo json_encode(["code" => 403, "message" => "CSRF 校验失败，请刷新页面重试"]);
    exit;
}
unset($_SESSION["csrf_token"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["code" => 405, "message" => "Method Not Allowed"]);
    exit;
}

// ---- Rate Limiting (max 3 submissions per 60s per fingerprint) ----
$rateLimitDir = __DIR__ . "/../data/ratelimit";
@mkdir($rateLimitDir, 0755, true);
$rateLimitKey = $rateLimitDir . "/" . md5($_POST["fingerprint"] ?? "anon") . ".json";
$window = [];
if (file_exists($rateLimitKey)) {
    $raw = @file_get_contents($rateLimitKey);
    $window = $raw ? json_decode($raw, true) : [];
    if (!is_array($window)) $window = [];
}
$now = time();
$window = array_values(array_filter($window, function($t) use ($now) { return $now - $t < 60; }));
if (count($window) >= 3) {
    echo json_encode(["code" => 429, "message" => "提交过于频繁，请稍后再试"]);
    exit;
}
$window[] = $now;
file_put_contents($rateLimitKey, json_encode($window), LOCK_EX);

$nickname    = isset($_POST["nickname"])    ? trim($_POST["nickname"])    : "";
$message     = isset($_POST["message"])     ? trim($_POST["message"])     : "";
$score       = isset($_POST["score"])       ? intval($_POST["score"])     : 0;
$device      = isset($_POST["device"])      ? trim($_POST["device"])      : "";
$location    = isset($_POST["location"])    ? trim($_POST["location"])    : "";
$fingerprint = isset($_POST["fingerprint"])  ? trim($_POST["fingerprint"]) : "";

// ---- Server-side IP detection ----
$ip_addr = $_SERVER["REMOTE_ADDR"] ?? "";
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
    $forwarded = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
    $ip_addr = trim($forwarded[0]);
}

if ($nickname === "") {
    echo json_encode(["code" => 400, "message" => "请输入昵称"]);
    exit;
}
if (mb_strlen($nickname) > 10) {
    echo json_encode(["code" => 400, "message" => "昵称不能超过10个字"]);
    exit;
}
if (mb_strlen($message) > 30) {
    echo json_encode(["code" => 400, "message" => "留言不能超过30个字"]);
    exit;
}

// ---- Score sanity: reasonable upper bound ----
if ($score <= 0) {
    echo json_encode(["code" => 400, "message" => "成绩无效"]);
    exit;
}
if ($score > 99999) {
    echo json_encode(["code" => 400, "message" => "成绩异常，请勿作弊"]);
    exit;
}

if ($fingerprint === "") {
    echo json_encode(["code" => 400, "message" => "浏览器指纹获取失败，请刷新页面重试"]);
    exit;
}
if ($device === "") {
    echo json_encode(["code" => 400, "message" => "设备信息获取失败，请刷新页面重试"]);
    exit;
}

$badWord = checkSensitiveWords($nickname);
if ($badWord !== false) {
    echo json_encode(["code" => 400, "message" => "昵称包含敏感词，请换一个昵称再试"]);
    exit;
}
if ($message !== "") {
    $badWord = checkSensitiveWords($message);
    if ($badWord !== false) {
        echo json_encode(["code" => 400, "message" => "留言包含敏感词，请换一个留言再试"]);
        exit;
    }
}

try {
    $conn = getDB();

    $stmt = $conn->prepare("SELECT id, score FROM seia_score_rank WHERE fingerprint = ? AND device = ? LIMIT 1");
    $stmt->bind_param("ss", $fingerprint, $device);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $oldScore = intval($existing["score"]);
        $oldId = intval($existing["id"]);

        if ($score > $oldScore) {
            $stmt = $conn->prepare("UPDATE seia_score_rank SET nickname = ?, message = ?, score = ?, device = ?, location = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssissi", $nickname, $message, $score, $device, $location, $oldId);
            $stmt->execute();
            $stmt->close();

            // Fixed: parameterized rank query
            $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 AS runner_rank FROM seia_score_rank WHERE score > ?");
            $rankStmt->bind_param("i", $score);
            $rankStmt->execute();
            $rank = intval($rankStmt->get_result()->fetch_assoc()["runner_rank"]);
            $rankStmt->close();

            echo json_encode(["code" => 0, "message" => "成绩已更新", "data" => ["id" => $oldId, "rank" => $rank, "updated" => true, "improved" => $score - $oldScore]]);
        } else {
            $stmt = $conn->prepare("UPDATE seia_score_rank SET nickname = ?, message = ?, device = ?, location = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssi", $nickname, $message, $device, $location, $oldId);
            $stmt->execute();
            $stmt->close();

            $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 AS runner_rank FROM seia_score_rank WHERE score > ?");
            $rankStmt->bind_param("i", $oldScore);
            $rankStmt->execute();
            $rank = intval($rankStmt->get_result()->fetch_assoc()["runner_rank"]);
            $rankStmt->close();

            echo json_encode(["code" => 0, "message" => "你有更高的成绩 (" . $oldScore . " 分)，当前排名第 " . $rank . " 名。", "data" => ["id" => $oldId, "rank" => $rank, "updated" => false, "oldScore" => $oldScore]]);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO seia_score_rank (nickname, message, score, ip_addr, device, location, fingerprint) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissss", $nickname, $message, $score, $ip_addr, $device, $location, $fingerprint);

        if ($stmt->execute()) {
            $rankId = $conn->insert_id;
            $rankStmt = $conn->prepare("SELECT COUNT(*) + 1 AS runner_rank FROM seia_score_rank WHERE score > ?");
            $rankStmt->bind_param("i", $score);
            $rankStmt->execute();
            $rank = intval($rankStmt->get_result()->fetch_assoc()["runner_rank"]);
            $rankStmt->close();

            echo json_encode(["code" => 0, "message" => "上传成功", "data" => ["id" => $rankId, "rank" => $rank, "updated" => false]]);
        } else {
            echo json_encode(["code" => 500, "message" => "上传失败，请稍后再试"]);
        }
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    error_log("submit_score error: " . $e->getMessage());
    echo json_encode(["code" => 500, "message" => "服务器内部错误，请稍后再试"]);
}
