<?php
require_once __DIR__ . "/init.php";
require_once __DIR__ . "/captcha/geetest_config.php";
require_once __DIR__ . "/captcha/geetest_lib.php";

header("Content-Type: application/json; charset=utf-8");

function getRunnerRank($conn, $score) {
    $stmt = $conn->prepare("SELECT COUNT(*) + 1 AS runner_rank FROM seia_score_rank WHERE score > ?");
    $stmt->bind_param("i", $score);
    $stmt->execute();
    $rankRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return intval($rankRow["runner_rank"]);
}

function isValidScoreString($score) {
    if (!is_string($score) || !ctype_digit($score)) {
        return false;
    }

    $normalized = ltrim($score, "0");
    if ($normalized === "") {
        return false;
    }
    
    // 最大分数为2147483647
    $max = "2147483647";
    return strlen($normalized) < strlen($max)
        || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) <= 0);
}

function enforceScoreRateLimit($fingerprint, $ipAddr) {
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "seia-runner-ratelimit";
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        error_log("Failed to create score rate-limit directory: " . $dir);
        return;
    }

    $key = hash("sha256", $fingerprint . "|" . $ipAddr);
    $path = $dir . "/" . $key . ".json";
    $now = time();
    $window = [];

    if (is_file($path)) {
        $raw = file_get_contents($path);
        $decoded = $raw === false ? [] : json_decode($raw, true);
        $window = is_array($decoded) ? $decoded : [];
    }

    $window = array_values(array_filter($window, function ($timestamp) use ($now) {
        return is_int($timestamp) && $now - $timestamp < 60;
    }));

    if (count($window) >= 5) {
        echo json_encode(["code" => 429, "message" => "Too many submissions, please try again later"]);
        exit;
    }

    $window[] = $now;
    file_put_contents($path, json_encode($window), LOCK_EX);
}

function normalizeClientIp($value) {
    $value = trim((string)$value, " \t\n\r\0\x0B\"[]");
    return filter_var($value, FILTER_VALIDATE_IP) ? $value : "";
}

function firstValidClientIp($value) {
    foreach (explode(",", (string)$value) as $part) {
        $ip = normalizeClientIp($part);
        if ($ip !== "") {
            return $ip;
        }
    }

    return "";
}

function resolveClientIp() {
    $singleIpHeaders = [
        "HTTP_CF_CONNECTING_IP",
        "HTTP_TRUE_CLIENT_IP",
        "HTTP_X_REAL_IP",
        "HTTP_X_CLIENT_IP"
    ];

    foreach ($singleIpHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = normalizeClientIp($_SERVER[$header]);
            if ($ip !== "") {
                return $ip;
            }
        }
    }

    if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $ip = firstValidClientIp($_SERVER["HTTP_X_FORWARDED_FOR"]);
        if ($ip !== "") {
            return $ip;
        }
    }

    if (!empty($_POST["ip_addr"])) {
        $ip = normalizeClientIp($_POST["ip_addr"]);
        if ($ip !== "") {
            return $ip;
        }
    }

    return normalizeClientIp($_SERVER["REMOTE_ADDR"] ?? "");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["code" => 405, "message" => "Method Not Allowed"]);
    exit;
}

$nickname    = isset($_POST["nickname"])    ? trim($_POST["nickname"])    : "";
$message     = isset($_POST["message"])     ? trim($_POST["message"])     : "";
$rawScore    = isset($_POST["score"])       ? trim($_POST["score"])       : "";
$score       = isValidScoreString($rawScore) ? intval($rawScore)           : 0;
$ip_addr     = resolveClientIp();
$device      = isset($_POST["device"])      ? trim($_POST["device"])      : "";
$location    = isset($_POST["location"])    ? trim($_POST["location"])    : "";
$fingerprint = isset($_POST["fingerprint"])  ? trim($_POST["fingerprint"]) : "";
$scoreNonce  = isset($_POST["score_nonce"]) ? trim($_POST["score_nonce"]) : "";
$lotNumber     = isset($_POST["lot_number"])     ? trim($_POST["lot_number"])     : "";
$captchaOutput = isset($_POST["captcha_output"]) ? trim($_POST["captcha_output"]) : "";
$passToken     = isset($_POST["pass_token"])     ? trim($_POST["pass_token"])     : "";
$genTime       = isset($_POST["gen_time"])       ? trim($_POST["gen_time"])       : "";

if (mb_strlen($fingerprint, "UTF-8") > 128) {
    echo json_encode(["code" => 400, "message" => "Fingerprint is too long"]);
    exit;
}
if (mb_strlen($device, "UTF-8") > 50) {
    echo json_encode(["code" => 400, "message" => "Device info is too long"]);
    exit;
}
if (mb_strlen($location, "UTF-8") > 100) {
    echo json_encode(["code" => 400, "message" => "Location is too long"]);
    exit;
}

// 昵称校验
if ($nickname === "") {
    echo json_encode(["code" => 400, "message" => "请输入昵称"]);
    exit;
}
if (mb_strlen($nickname, "UTF-8") > NICKNAME_MAX_LENGTH) {
    echo json_encode(["code" => 400, "message" => "昵称不能超过" . NICKNAME_MAX_LENGTH . "个字"]);
    exit;
}

// 留言长度校验
if (mb_strlen($message, "UTF-8") > MESSAGE_MAX_LENGTH) {
    echo json_encode(["code" => 400, "message" => "留言不能超过" . MESSAGE_MAX_LENGTH . "个字"]);
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

$nonceToken = consumeScoreNonce($scoreNonce);
if ($nonceToken === false) {
    echo json_encode(["code" => 403, "message" => "Score submission expired, please try again"]);
    exit;
}

// 签名校验：SHA-256(score|nonce|token|fingerprint)
$checksum = isset($_POST["checksum"]) ? trim($_POST["checksum"]) : "";
$expected = hash("sha256", $score . "|" . $scoreNonce . "|" . $nonceToken . "|" . $fingerprint);
if (!hash_equals($expected, $checksum)) {
    echo json_encode(["code" => 403, "message" => "Invalid request signature"]);
    exit;
}

// 极验验证码校验 (GT 4.0)
$geetestLib = new GeetestLib(GEETEST_ID, GEETEST_KEY);
$geetestResult = $geetestLib->validate($lotNumber, $captchaOutput, $passToken, $genTime);

// 不使用降级验证，防止绕过攻击
if ($geetestResult->getStatus() !== 1) {
    error_log("[Geetest] 二次验证: 验证失败 | captcha_id=" . GEETEST_ID . " | msg=" . $geetestResult->getMsg());
    echo json_encode(["code" => 403, "message" => "验证码验证失败，请重新提交"]);
    exit;
}
error_log("[Geetest] 二次验证: 验证通过 | captcha_id=" . GEETEST_ID);

enforceScoreRateLimit($fingerprint, $ip_addr);

try {
    $conn = getDB();
} catch (Exception $e) {
    echo json_encode(["code" => 500, "message" => "上传成绩出错，请稍后重试"]);
    exit;
}

try {
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
                "UPDATE seia_score_rank SET nickname = ?, message = ?, score = ?, device = ?, location = ?, real_ip = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->bind_param("ssisssi", $nickname, $message, $score, $device, $location, $ip_addr, $oldId);
            $stmt->execute();
            $stmt->close();

        $rank = getRunnerRank($conn, $score);

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
                "UPDATE seia_score_rank SET nickname = ?, message = ?, device = ?, location = ?, real_ip = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->bind_param("sssssi", $nickname, $message, $device, $location, $ip_addr, $oldId);
            $stmt->execute();
            $stmt->close();

        $rank = getRunnerRank($conn, $oldScore);

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
            "INSERT INTO seia_score_rank (nickname, message, score, ip_addr, real_ip, device, location, fingerprint) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssisssss", $nickname, $message, $score, $ip_addr, $ip_addr, $device, $location, $fingerprint);

        if ($stmt->execute()) {
            $rankId = $conn->insert_id;

        $rank = getRunnerRank($conn, $score);

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
} catch (mysqli_sql_exception $e) {
    echo json_encode(["code" => 500, "message" => "上传成绩出错，请稍后重试"]);
    exit;
}
