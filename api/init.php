<?php
require_once __DIR__ . "/config.php";

function getDB() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
        if ($conn->connect_error) {
            error_log("DB connection failed: " . $conn->connect_error);
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode(["code" => 500, "message" => "数据库连接失败"]);
            exit;
        }

        $conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db($DB_NAME);
        $conn->set_charset("utf8mb4");

        $sql = "CREATE TABLE IF NOT EXISTS seia_score_rank (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nickname VARCHAR(30) NOT NULL,
            message VARCHAR(90) NOT NULL,
            score BIGINT NOT NULL DEFAULT 0,
            ip_addr VARCHAR(45) NOT NULL DEFAULT '',
            device VARCHAR(50) NOT NULL DEFAULT '',
            location VARCHAR(100) NOT NULL DEFAULT '',
            fingerprint VARCHAR(128) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_fingerprint_device (fingerprint, device)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->query($sql);

        // Migrations with proper error handling
        try { $conn->query("ALTER TABLE seia_score_rank ADD COLUMN fingerprint VARCHAR(128) NOT NULL DEFAULT '' AFTER location"); }
        catch (mysqli_sql_exception $e) { if (!str_contains($e->getMessage(), "Duplicate column")) error_log("Migration: fingerprint - " . $e->getMessage()); }

        try { $conn->query("ALTER TABLE seia_score_rank ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"); }
        catch (mysqli_sql_exception $e) { if (!str_contains($e->getMessage(), "Duplicate column")) error_log("Migration: updated_at - " . $e->getMessage()); }

        try { $conn->query("ALTER TABLE seia_score_rank DROP INDEX uk_fingerprint_ip"); }
        catch (mysqli_sql_exception $e) {}

        try { $conn->query("ALTER TABLE seia_score_rank ADD UNIQUE uk_fingerprint_device (fingerprint, device)"); }
        catch (mysqli_sql_exception $e) { if (!str_contains($e->getMessage(), "Duplicate key")) error_log("Migration: uk_fingerprint_device - " . $e->getMessage()); }

        // Critical: ensure score is BIGINT
        try {
            $result = $conn->query("SHOW COLUMNS FROM seia_score_rank LIKE 'score'");
            $col = $result->fetch_assoc();
            if ($col && stripos($col["Type"], "BIGINT") === false) {
                $conn->query("ALTER TABLE seia_score_rank MODIFY COLUMN score BIGINT NOT NULL DEFAULT 0");
                error_log("Migration: score column changed to BIGINT");
            }
        } catch (mysqli_sql_exception $e) {
            error_log("CRITICAL: score BIGINT migration failed - " . $e->getMessage());
        }

        return $conn;
    } catch (mysqli_sql_exception $e) {
        error_log("DB init fatal: " . $e->getMessage());
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["code" => 500, "message" => "服务器内部错误"]);
        exit;
    }
}

function loadSensitiveWords() {
    if (!file_exists(SENSITIVE_WORDS_FILE)) return [];
    $words = [];
    $lines = file(SENSITIVE_WORDS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== "") $words[] = $line;
    }
    return $words;
}

function checkSensitiveWords($text) {
    $words = loadSensitiveWords();
    foreach ($words as $word) {
        if (mb_strpos($text, $word) !== false) return $word;
    }
    return false;
}
