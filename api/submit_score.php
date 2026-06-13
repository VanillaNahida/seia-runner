<?php
require_once __DIR__ . "/init.php";

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
    if (!is_string($score) || !preg_match('/^[1-9][0-9]{0,5}$/', $score)) {
        return false;
    }

    return intval($score) <= 999999;
}

function getStrictPostString($key, $maxBytes) {
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
        return "";
    }

    $value = trim($_POST[$key]);
    if (strlen($value) > $maxBytes || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        return null;
    }

    return $value;
}

function getEncryptedPostPayload($nonce) {
    $iv = getStrictPostString("score_iv", 32);
    $payload = getStrictPostString("score_payload", 262144);

    if ($iv === null || $payload === null) {
        return null;
    }

    if (!is_string($nonce) || !preg_match('/^[a-f0-9]{64}$/', $nonce) || $iv === "" || $payload === "") {
        return null;
    }

    if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $iv) || !preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $payload)) {
        return null;
    }

    $ivBytes = base64_decode($iv, true);
    $cipherBytes = base64_decode($payload, true);
    if ($ivBytes === false || $cipherBytes === false || strlen($ivBytes) !== 12 || strlen($cipherBytes) < 17) {
        return null;
    }

    $tag = substr($cipherBytes, -16);
    $cipherText = substr($cipherBytes, 0, -16);
    $plain = openssl_decrypt($cipherText, "aes-256-gcm", hash("sha256", $nonce, true), OPENSSL_RAW_DATA, $ivBytes, $tag);
    if ($plain === false || strlen($plain) > 262144) {
        return null;
    }

    $decoded = json_decode($plain, true);
    return is_array($decoded) ? $decoded : null;
}

function getStrictPayloadString($payload, $key, $maxBytes) {
    if (!isset($payload[$key]) || !is_string($payload[$key])) {
        return "";
    }

    $value = trim($payload[$key]);
    if (strlen($value) > $maxBytes || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        return null;
    }

    return $value;
}

function isSafeNickname($value) {
    return is_string($value) && preg_match('/^[\p{L}\p{N}_\-\s\x{4e00}-\x{9fa5}]{1,10}$/u', $value);
}

function isSafeMessage($value) {
    return is_string($value) && preg_match('/^[\p{L}\p{N}_\-\s\x{4e00}-\x{9fa5}，。！？,.!?、:：()（）]{0,30}$/u', $value);
}

function isSafeFingerprint($value) {
    return is_string($value) && preg_match('/^[a-f0-9]{1,128}$/i', $value);
}

function isSafeDevice($value) {
    return is_string($value) && preg_match('/^[A-Za-z0-9 _\-.]{1,50}$/', $value);
}

function isSafeLocation($value) {
    return is_string($value) && preg_match('/^[\p{L}\p{N}\s\x{4e00}-\x{9fa5}·.\-]{0,100}$/u', $value);
}

function parseClientElapsedMs($value) {
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{2,6}$/', $value)) {
        return null;
    }

    $elapsed = intval($value);
    return ($elapsed >= 500 && $elapsed <= 3600000) ? $elapsed : null;
}

function parseClientTimestampMs($value) {
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{12}$/', $value)) {
        return null;
    }

    return intval($value);
}

function getOperationStatInt($stats, $key) {
    if (!isset($stats[$key]) || !is_int($stats[$key])) {
        return null;
    }

    return ($stats[$key] >= 0 && $stats[$key] <= 100000) ? $stats[$key] : null;
}

function parseOperationStats($value) {
    if (!is_string($value) || $value === "" || strlen($value) > 512) {
        return null;
    }

    $stats = json_decode($value, true);
    if (!is_array($stats)) {
        return null;
    }

    $keys = [
        "jumpDown", "jumpUp", "duckDown", "duckUp", "keyDown", "keyUp",
        "pointerDown", "pointerUp", "buttonDown", "buttonUp", "firstOffsetMs", "lastOffsetMs"
    ];

    foreach ($keys as $key) {
        $value = getOperationStatInt($stats, $key);
        if ($value === null) {
            return null;
        }
        $stats[$key] = $value;
    }

    return $stats;
}

function parseJsonArrayPost($value, $maxBytes) {
    if (!is_string($value) || $value === "" || strlen($value) > $maxBytes) {
        return null;
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
}

function getArrayInt($item, $key, $min, $max) {
    if (!isset($item[$key]) || !is_int($item[$key])) {
        return null;
    }

    return ($item[$key] >= $min && $item[$key] <= $max) ? $item[$key] : null;
}

function isValidHashAnswers($answers, $session, $elapsedMs) {
    $challenge = $session["hash_challenge"] ?? null;
    if (!is_array($challenge)) {
        return false;
    }

    $seed = $challenge["seed"] ?? "";
    $difficulty = intval($challenge["difficulty"] ?? 0);
    $intervalMs = intval($challenge["interval_ms"] ?? 0);
    $maxNonce = intval($challenge["max_nonce"] ?? 0);
    if (!preg_match('/^[a-f0-9]{32}$/', $seed) || $difficulty < 1 || $difficulty > 5 || $intervalMs < 500 || $maxNonce < 1) {
        return false;
    }

    if (!is_array($answers)) {
        return false;
    }

    $expectedMin = max(1, intval(floor($elapsedMs / $intervalMs)) - 1);
    if (count($answers) < $expectedMin || count($answers) > 3600) {
        return false;
    }

    $prefix = str_repeat("0", $difficulty);
    $lastIndex = -1;
    $lastElapsed = -1;
    foreach ($answers as $answer) {
        if (!is_array($answer)) {
            return false;
        }

        $index = getArrayInt($answer, "index", 0, 3600);
        $answerElapsed = getArrayInt($answer, "elapsedMs", 0, 3600000);
        $nonce = getArrayInt($answer, "nonce", 0, $maxNonce);
        $hash = $answer["hash"] ?? "";
        if ($index === null || $answerElapsed === null || $nonce === null || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return false;
        }

        if ($index !== $lastIndex + 1 || $answerElapsed < $lastElapsed || $answerElapsed > $elapsedMs + 1000) {
            return false;
        }

        $expectedHash = hash("sha256", $seed . "|" . $index . "|" . $answerElapsed . "|" . $nonce);
        if (!hash_equals($expectedHash, $hash) || substr($hash, 0, $difficulty) !== $prefix) {
            return false;
        }

        $lastIndex = $index;
        $lastElapsed = $answerElapsed;
    }

    return true;
}

function isValidCoordinateSamples($samples, $score, $elapsedMs) {
    if (!is_array($samples)) {
        return false;
    }

    $expectedMin = max(2, intval(floor($elapsedMs / 250)) - 4);
    if (count($samples) < min($expectedMin, 20) || count($samples) > 300) {
        return false;
    }

    $lastT = -1;
    $lastScore = -1;
    foreach ($samples as $sample) {
        if (!is_array($sample)) {
            return false;
        }

        $t = getArrayInt($sample, "t", 0, 3600000);
        $x = getArrayInt($sample, "x", 0, 900);
        $y = getArrayInt($sample, "y", -100, 400);
        $vy = getArrayInt($sample, "vy", -2500, 4500);
        $grounded = getArrayInt($sample, "grounded", 0, 1);
        $ducking = getArrayInt($sample, "ducking", 0, 1);
        $sampleScore = getArrayInt($sample, "score", 0, 999999);
        if ($t === null || $x === null || $y === null || $vy === null || $grounded === null || $ducking === null || $sampleScore === null) {
            return false;
        }

        if ($t <= $lastT || $t > $elapsedMs + 1000 || $sampleScore < $lastScore || $sampleScore > $score + 80) {
            return false;
        }

        if ($x < 90 || $x > 125 || $y < -120 || $y > 220) {
            return false;
        }

        if ($grounded === 1) {
            $groundY = $ducking === 1 ? 193 : 152;
            if (abs($y - $groundY) > 8 || abs($vy) > 80) {
                return false;
            }
        }

        $lastT = $t;
        $lastScore = $sampleScore;
    }

    return true;
}

function isValidClientProofs($session, $score, $elapsedMs, $rawOperationStats, $operationStats, $hashAnswers, $coordSamples) {
    return isValidOperationStats($rawOperationStats, $operationStats, $session, $score, $elapsedMs)
        && isValidHashAnswers($hashAnswers, $session, $elapsedMs)
        && isValidCoordinateSamples($coordSamples, $score, $elapsedMs);
}

function getOperationStatsDigest($rawStats, $session, $score, $elapsedMs) {
    $salt = $session["proof_salt"] ?? "";
    if (!is_string($salt) || !preg_match('/^[a-f0-9]{32}$/', $salt)) {
        return null;
    }

    return hash_hmac("sha256", $score . "|" . $elapsedMs . "|" . $rawStats, $salt);
}

function isValidOperationStats($rawStats, $stats, $session, $score, $elapsedMs) {
    if (getOperationStatsDigest($rawStats, $session, $score, $elapsedMs) === null) {
        return false;
    }

    if ($stats["lastOffsetMs"] < $stats["firstOffsetMs"] || $stats["lastOffsetMs"] > $elapsedMs + 1000) {
        return false;
    }

    $jumpTotal = $stats["jumpDown"] + $stats["jumpUp"];
    $duckTotal = $stats["duckDown"] + $stats["duckUp"];
    $sourceTotal = $stats["keyDown"] + $stats["keyUp"] + $stats["pointerDown"] + $stats["pointerUp"] + $stats["buttonDown"] + $stats["buttonUp"];
    $totalOps = $jumpTotal + $duckTotal;

    if ($score >= 80 && $totalOps < 1) {
        return false;
    }

    if (abs($stats["jumpDown"] - $stats["jumpUp"]) > 2 || abs($stats["duckDown"] - $stats["duckUp"]) > 2) {
        return false;
    }

    return $sourceTotal >= $totalOps && $sourceTotal <= $totalOps + 4;
}

function isValidClientTimeline($startedAt, $endedAt, $elapsedMs) {
    if (!is_int($startedAt) || !is_int($endedAt) || !is_int($elapsedMs)) {
        return false;
    }

    if ($endedAt <= $startedAt) {
        return false;
    }

    return abs(($endedAt - $startedAt) - $elapsedMs) <= 1000;
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

    return normalizeClientIp($_SERVER["REMOTE_ADDR"] ?? "");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["code" => 405, "message" => "Method Not Allowed"]);
    exit;
}

$scoreNonce = getStrictPostString("score_nonce", 64);
if ($scoreNonce === null) {
    echo json_encode(["code" => 400, "message" => "请求参数包含非法字符"]);
    exit;
}

$encryptedPayload = getEncryptedPostPayload($scoreNonce);
if ($encryptedPayload === null) {
    echo json_encode(["code" => 400, "message" => "成绩数据解密失败，请刷新页面重试"]);
    exit;
}

$nickname    = getStrictPayloadString($encryptedPayload, "nickname", 64);
$message     = getStrictPayloadString($encryptedPayload, "message", 128);
$rawScore    = getStrictPayloadString($encryptedPayload, "score", 6);
$score       = isValidScoreString($rawScore) ? intval($rawScore) : 0;
$ip_addr     = resolveClientIp();
$device      = getStrictPayloadString($encryptedPayload, "device", 50);
$location    = getStrictPayloadString($encryptedPayload, "location", 100);
$fingerprint = getStrictPayloadString($encryptedPayload, "fingerprint", 128);
$gameToken   = getStrictPayloadString($encryptedPayload, "game_token", 64);
$clientElapsedMs = parseClientElapsedMs(getStrictPayloadString($encryptedPayload, "client_elapsed_ms", 7));
$clientStartedAt = parseClientTimestampMs(getStrictPayloadString($encryptedPayload, "client_started_at", 13));
$clientEndedAt = parseClientTimestampMs(getStrictPayloadString($encryptedPayload, "client_ended_at", 13));
$rawOperationStats = getStrictPayloadString($encryptedPayload, "operation_stats", 512);
$operationStats = parseOperationStats($rawOperationStats);
$rawHashAnswers = getStrictPayloadString($encryptedPayload, "hash_answers", 65535);
$hashAnswers = parseJsonArrayPost($rawHashAnswers, 65535);
$rawCoordSamples = getStrictPayloadString($encryptedPayload, "coord_samples", 65535);
$coordSamples = parseJsonArrayPost($rawCoordSamples, 65535);

if ($nickname === null || $message === null || $rawScore === null || $device === null || $location === null || $fingerprint === null || $scoreNonce === null || $gameToken === null || $rawOperationStats === null || $rawHashAnswers === null || $rawCoordSamples === null) {
    echo json_encode(["code" => 400, "message" => "请求参数包含非法字符"]);
    exit;
}

if (!isSafeNickname($nickname)) {
    echo json_encode(["code" => 400, "message" => "昵称只能包含中英文、数字、空格、下划线和短横线"]);
    exit;
}

if (!isSafeMessage($message)) {
    echo json_encode(["code" => 400, "message" => "留言包含不允许的字符"]);
    exit;
}

if (!isSafeFingerprint($fingerprint)) {
    echo json_encode(["code" => 400, "message" => "浏览器指纹获取失败，请刷新页面重试"]);
    exit;
}

if (!isSafeDevice($device)) {
    echo json_encode(["code" => 400, "message" => "设备信息获取失败，请刷新页面重试"]);
    exit;
}

if (!isSafeLocation($location)) {
    echo json_encode(["code" => 400, "message" => "归属地信息无效"]);
    exit;
}

if ($clientElapsedMs === null || !isValidClientTimeline($clientStartedAt, $clientEndedAt, $clientElapsedMs)) {
    echo json_encode(["code" => 400, "message" => "游戏时间轴参数无效"]);
    exit;
}

if ($operationStats === null || $hashAnswers === null || $coordSamples === null) {
    echo json_encode(["code" => 400, "message" => "客户端校验参数无效"]);
    exit;
}

// 成绩校验
if ($score <= 0) {
    echo json_encode(["code" => 400, "message" => "成绩无效"]);
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

if (!consumeScoreNonce($scoreNonce)) {
    echo json_encode(["code" => 403, "message" => "Score submission expired, please try again"]);
    exit;
}

if (!consumeGameSessionForScore($gameToken, $score, $clientElapsedMs, function ($session) use ($rawOperationStats, $operationStats, $score, $clientElapsedMs, $hashAnswers, $coordSamples) {
    return isValidClientProofs($session, $score, $clientElapsedMs, $rawOperationStats, $operationStats, $hashAnswers, $coordSamples);
})) {
    echo json_encode(["code" => 403, "message" => "成绩或操作统计校验失败，请正常完成一局游戏后再上传"]);
    exit;
}

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
                "UPDATE seia_score_rank SET nickname = ?, message = ?, score = ?, device = ?, location = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->bind_param("ssissi", $nickname, $message, $score, $device, $location, $oldId);
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
            // 已有更高或相同成绩时，保留最高分，但允许修改昵称和留言
            $stmt = $conn->prepare(
                "UPDATE seia_score_rank SET nickname = ?, message = ?, device = ?, location = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->bind_param("ssssi", $nickname, $message, $device, $location, $oldId);
            $stmt->execute();
            $stmt->close();

            $rank = getRunnerRank($conn, $oldScore);

            echo json_encode([
                "code" => 0,
                "message" => "提交信息已更新，保留最高成绩 " . $oldScore . " 分，当前排名第 " . $rank . " 名。",
                "data" => [
                    "id" => $oldId,
                    "rank" => $rank,
                    "updated" => false,
                    "oldScore" => $oldScore,
                    "infoUpdated" => true
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
