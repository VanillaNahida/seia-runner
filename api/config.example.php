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

// 百度内容审核 (请替换为你的真实 API 凭证)
define("BAIDU_APP_ID", "APP_ID");
define("BAIDU_API_KEY", "API_KEY");
define("BAIDU_SECRET_KEY", "SECRET_KEY");
// 内容审核策略ID（可选，留空则使用默认策略；可在百度云控制台-内容审核-策略管理中创建）
define("BAIDU_CENSOR_STRATEGY_ID", "STRATEGY_ID");