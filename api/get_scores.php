<?php
require_once __DIR__ . "/init.php";

header("Content-Type: application/json; charset=utf-8");

$conn = getDB();

$result = $conn->query(
    "SELECT nickname, message, score, ip_addr, device, location, created_at, updated_at FROM seia_score_rank ORDER BY score DESC, created_at ASC LIMIT 100"
);

$rows = [];
while ($row = $result->fetch_assoc()) {
    // IP 脱敏: 保留首尾段，中间替换为 ***
    $parts = explode(".", $row["ip_addr"]);
    if (count($parts) === 4) {
      $row["ip_addr"] = $parts[0] . ".***.***." . $parts[3];
    } else {
      $row["ip_addr"] = "***.***";
    }
    $rows[] = $row;
}

echo json_encode([
    "code" => 0,
    "data" => $rows
]);

$conn->close();
