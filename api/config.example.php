<?php
// 禁止直接访问
if (basename($_SERVER["SCRIPT_FILENAME"]) === "config.php") {
    header("HTTP/1.0 403 Forbidden");
    exit;
}
// 数据库配置
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "your_db_password";
$DB_NAME = "seia_runner";

// 敏感词库路径，一行一个敏感词
define("SENSITIVE_WORDS_FILE", __DIR__ . "/../sensitive_words/sensitive_words_lines.txt");

// 字数限制
define("NICKNAME_MAX_LENGTH", 10);
define("MESSAGE_MAX_LENGTH", 30);
