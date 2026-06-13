<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/waf.php";

runRequestWaf();

function ensureScoreSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        "httponly" => true,
        "samesite" => "Strict",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"
    ]);
    session_start();
}

function getScoreNonceTtl() {
    $ttl = getenv("SCORE_NONCE_TTL");
    if ($ttl === false || !ctype_digit($ttl)) {
        return 30;
    }

    return max(5, min(3600, intval($ttl)));
}

function pruneScoreNonces() {
    ensureScoreSession();

    $now = time();
    if (!isset($_SESSION["score_nonces"]) || !is_array($_SESSION["score_nonces"])) {
        $_SESSION["score_nonces"] = [];
        return;
    }

    foreach ($_SESSION["score_nonces"] as $nonce => $expiresAt) {
        if (!is_int($expiresAt) || $expiresAt < $now) {
            unset($_SESSION["score_nonces"][$nonce]);
        }
    }
}

function issueScoreNonce() {
    pruneScoreNonces();

    $nonce = bin2hex(random_bytes(32));
    $_SESSION["score_nonces"][$nonce] = time() + getScoreNonceTtl();

    return $nonce;
}

function consumeScoreNonce($nonce) {
    pruneScoreNonces();

    if (!is_string($nonce) || !preg_match('/^[a-f0-9]{64}$/', $nonce)) {
        return false;
    }

    if (!isset($_SESSION["score_nonces"][$nonce])) {
        return false;
    }

    unset($_SESSION["score_nonces"][$nonce]);
    return true;
}

function pruneGameSessions() {
    ensureScoreSession();

    $now = time();
    if (!isset($_SESSION["game_sessions"]) || !is_array($_SESSION["game_sessions"])) {
        $_SESSION["game_sessions"] = [];
        return;
    }

    foreach ($_SESSION["game_sessions"] as $token => $session) {
        if (!is_array($session) || !isset($session["expires_at"]) || intval($session["expires_at"]) < $now) {
            unset($_SESSION["game_sessions"][$token]);
        }
    }
}

function issueGameSession() {
    pruneGameSessions();

    $token = bin2hex(random_bytes(32));
    $challengeSeed = bin2hex(random_bytes(16));
    $challenge = [
        "seed" => $challengeSeed,
        "difficulty" => 2,
        "interval_ms" => 1200,
        "max_nonce" => 200000
    ];
    $_SESSION["game_sessions"][$token] = [
        "started_at" => microtime(true),
        "proof_salt" => bin2hex(random_bytes(16)),
        "hash_challenge" => $challenge,
        "expires_at" => time() + 3600
    ];

    return [
        "token" => $token,
        "hashChallenge" => $challenge
    ];
}

function consumeGameSessionForScore($token, $score, $clientElapsedMs = null, $operationProof = null) {
    pruneGameSessions();

    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    if (!isset($_SESSION["game_sessions"][$token]) || !is_array($_SESSION["game_sessions"][$token])) {
        return false;
    }

    $session = $_SESSION["game_sessions"][$token];
    unset($_SESSION["game_sessions"][$token]);

    $elapsed = microtime(true) - floatval($session["started_at"] ?? 0);
    if ($elapsed < 0.5 || $elapsed > 3600) {
        return false;
    }

    $scoreElapsed = $elapsed;
    if ($clientElapsedMs !== null) {
        if (!is_int($clientElapsedMs) || $clientElapsedMs < 500 || $clientElapsedMs > 3600000) {
            return false;
        }

        $clientElapsed = $clientElapsedMs / 1000;
        if ($clientElapsed > $elapsed + 2.0) {
            return false;
        }
        $scoreElapsed = $clientElapsed;
    }

    if (is_callable($operationProof) && !$operationProof($session)) {
        return false;
    }

    $maxScore = calculateMaxLegitScore($scoreElapsed) + 120;
    $minScore = max(1, calculateMinLegitScore($scoreElapsed) - 120);

    return $score >= $minScore && $score <= $maxScore;
}

function calculateMinLegitScore($elapsed) {
    $elapsed = max(0, floatval($elapsed));
    if ($elapsed < 0.5) {
        return 0;
    }

    return intval(floor(410 * $elapsed / 10));
}

function calculateMaxLegitScore($elapsed) {
    $elapsed = max(0, floatval($elapsed));
    $timeToCap = (720 - 410) / 7.5;

    if ($elapsed <= $timeToCap) {
        $distance = 410 * $elapsed + 0.5 * 7.5 * $elapsed * $elapsed;
    } else {
        $distanceToCap = 410 * $timeToCap + 0.5 * 7.5 * $timeToCap * $timeToCap;
        $distance = $distanceToCap + 720 * ($elapsed - $timeToCap);
    }

    return intval(floor($distance / 10));
}

function getDB() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
        $conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` DEFAULT CHARACTER SET utf8mb4");
        $conn->select_db($DB_NAME);
        $conn->set_charset("utf8mb4");
    } catch (mysqli_sql_exception $e) {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["code" => 500, "message" => "查询数据库出错，请稍后重试"]);
        exit;
    }

    // 自动建表
    $sql = "CREATE TABLE IF NOT EXISTS seia_score_rank (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nickname VARCHAR(30) NOT NULL,
        message VARCHAR(90) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        ip_addr VARCHAR(45) NOT NULL DEFAULT '',
        device VARCHAR(50) NOT NULL DEFAULT '',
        location VARCHAR(100) NOT NULL DEFAULT '',
        fingerprint VARCHAR(128) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_fingerprint_device (fingerprint, device)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // 旧表兼容: 尝试添加缺失的列，升级唯一键为 fingerprint+device
    try { $conn->query("ALTER TABLE seia_score_rank ADD COLUMN fingerprint VARCHAR(128) NOT NULL DEFAULT '' AFTER location"); } catch (Exception $e) {}
    try { $conn->query("ALTER TABLE seia_score_rank ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"); } catch (Exception $e) {}
    try { $conn->query("ALTER TABLE seia_score_rank DROP INDEX uk_fingerprint_ip"); } catch (Exception $e) {}
    try { $conn->query("ALTER TABLE seia_score_rank ADD UNIQUE uk_fingerprint_device (fingerprint, device)"); } catch (Exception $e) {}

    return $conn;
}

function loadSensitiveWords() {
    if (!file_exists(SENSITIVE_WORDS_FILE)) {
        return [];
    }

    $words = [];
    $lines = file(SENSITIVE_WORDS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== "") {
            $words[] = $line;
        }
    }
    return $words;
}

function checkSensitiveWords($text) {
    $words = loadSensitiveWords();
    foreach ($words as $word) {
        if (mb_strpos($text, $word) !== false) {
            return $word;
        }
    }
    return false;
}
