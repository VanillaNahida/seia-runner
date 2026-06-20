<?php
// Geetest 极验验证配置
// 复制此文件为 geetest_config.php 并填入实际的 ID 和 Key
if (basename($_SERVER["SCRIPT_FILENAME"]) === "geetest_config.example.php") {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

define("GEETEST_ID", "your_geetest_id_here");
define("GEETEST_KEY", "your_geetest_key_here");
