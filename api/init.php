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

function getDB() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    // 先不指定数据库，连接后创建数据库（如果不存在）
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
    if ($conn->connect_error) {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["code" => 500, "message" => "数据库连接失败: " . $conn->connect_error]);
        exit;
    }

    $conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` DEFAULT CHARACTER SET utf8mb4");
    $conn->select_db($DB_NAME);
    $conn->set_charset("utf8mb4");

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
