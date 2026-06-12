<?php
require_once __DIR__ . "/init.php";

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["code" => 405, "message" => "Method Not Allowed"]);
    exit;
}

$nickname    = isset($_POST["nickname"])    ? trim($_POST["nickname"])    : "";
$message     = isset($_POST["message"])     ? trim($_POST["message"])     : "";
$score       = isset($_POST["score"])       ? intval($_POST["score"])     : 0;
$ip_addr     = isset($_POST["ip_addr"])     ? trim($_POST["ip_addr"])     : "";
$device      = isset($_POST["device"])      ? trim($_POST["device"])      : "";
$location    = isset($_POST["location"])    ? trim($_POST["location"])    : "";
$fingerprint = isset($_POST["fingerprint"])  ? trim($_POST["fingerprint"]) : "";

// 昵称校验
if ($nickname === "") {
    echo json_encode(["code" => 400, "message" => "请输入昵称"]);
    exit;
}
if (mb_strlen($nickname) > 10) {
    echo json_encode(["code" => 400, "message" => "昵称不能超过10个字"]);
    exit;
}

// 留言长度校验
if (mb_strlen($message) > 30) {
    echo json_encode(["code" => 400, "message" => "留言不能超过30个字"]);
    exit;
}

// 成绩校验
if ($score <= 0) {
    echo json_encode(["code" => 400, "message" => "成绩无效"]);
    exit;
}

// 指纹校验
if ($fingerprint === "") {
    echo json_encode(["code" => 400, "message" => "浏览器指纹获取失败，请刷新页面重试"]);
    exit;
}

// 设备校验
if ($device === "") {
    echo json_encode(["code" => 400, "message" => "设备信息获取失败，请刷新页面重试"]);
    exit;
}

// 敏感词检测
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

$conn = getDB();

// 查询是否已存在同指纹+同设备的记录
$stmt = $conn->prepare(
    "SELECT id, score FROM seia_score_rank WHERE fingerprint = ? AND device = ? LIMIT 1"
);
$stmt->bind_param("ss", $fingerprint, $device);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $oldScore = intval($existing["score"]);
    $oldId = intval($existing["id"]);

    if ($score > $oldScore) {
        // 新成绩更高，更新记录
        $stmt = $conn->prepare(
            "UPDATE seia_score_rank SET nickname = ?, message = ?, score = ?, device = ?, location = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param("ssissi", $nickname, $message, $score, $device, $location, $oldId);
        $stmt->execute();
        $stmt->close();

        $rankResult = $conn->query("SELECT COUNT(*) + 1 AS runner_rank FROM seia_score_rank WHERE score > $score");
        $rankRow = $rankResult->fetch_assoc();
        $rank = intval($rankRow["runner_rank"]);

        echo json_encode([
            "code" => 0,
            "message" => "成绩已更新",
            "data" => [
                "id" => $oldId,
                "rank" => $rank,
                "updated" => true,
                "improved" => $score - $oldScore
            ]
        ]);
    } else {
        // 已有更高记录，仅更新昵称和留言
        $stmt = $conn->prepare(
            "UPDATE seia_score_rank SET nickname = ?, message = ?, device = ?, location = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param("ssssi", $nickname, $message, $device, $location, $oldId);
        $stmt->execute();
        $stmt->close();

        $rankResult = $conn->query("SELECT COUNT(*) + 1 AS runner_rank FROM seia_score_rank WHERE score > $oldScore");
        $rankRow = $rankResult->fetch_assoc();
        $rank = intval($rankRow["runner_rank"]);

        echo json_encode([
            "code" => 0,
            "message" => "你有更高的成绩 (" . $oldScore . " 分)，当前排名第 " . $rank . " 名。",
            "data" => [
                "id" => $oldId,
                "rank" => $rank,
                "updated" => false,
                "oldScore" => $oldScore
            ]
        ]);
    }
} else {
    // 新记录，插入
    $stmt = $conn->prepare(
        "INSERT INTO seia_score_rank (nickname, message, score, ip_addr, device, location, fingerprint) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("ssissss", $nickname, $message, $score, $ip_addr, $device, $location, $fingerprint);

    if ($stmt->execute()) {
        $rankId = $conn->insert_id;

        $rankResult = $conn->query("SELECT COUNT(*) + 1 AS runner_rank FROM seia_score_rank WHERE score > $score");
        $rankRow = $rankResult->fetch_assoc();
        $rank = intval($rankRow["runner_rank"]);

        echo json_encode([
            "code" => 0,
            "message" => "上传成功",
            "data" => [
                "id" => $rankId,
                "rank" => $rank,
                "updated" => false
            ]
        ]);
    } else {
        echo json_encode(["code" => 500, "message" => "上传失败，请稍后再试"]);
    }

    $stmt->close();
}

$conn->close();
