<?php
require_once __DIR__ . "/init.php";

header("Content-Type: application/json; charset=utf-8");

try {
    $conn = getDB();
} catch (Exception $e) {
    echo json_encode(["code" => 500, "message" => "查询数据库出错，请稍后重试"]);
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';

$rawPageSize = isset($_GET['pageSize']) ? $_GET['pageSize'] : '10';
if ($rawPageSize === 'all') {
    $pageSize = 0;
} else {
    $pageSize = intval($rawPageSize);
    $allowed = [10, 20, 50, 100];
    if (!in_array($pageSize, $allowed)) {
        $pageSize = 10;
    }
}

// 构建 WHERE 子句
// 注意：date 参数经过正则验证后直接拼入 SQL，避免 bind_param 引用传递的兼容性问题
// query 参数继续使用 prepared statement 防止 SQL 注入
$whereClauses = [];
$bindParams = [];
$bindTypes = '';

if ($query !== '') {
    $whereClauses[] = "nickname LIKE ?";
    $bindParams[] = '%' . $query . '%';
    $bindTypes .= 's';
}

if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $whereClauses[] = "DATE(updated_at) = '$date'";
}

if ($query === '' && $date === '') {
    switch ($type) {
        case 'day':
            $whereClauses[] = "DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $whereClauses[] = "DATE_SUB(CURDATE(), INTERVAL 7 DAY) <= created_at";
            break;
        case 'month':
            $whereClauses[] = "DATE_SUB(CURDATE(), INTERVAL 30 DAY) <= created_at";
            break;
    }
}

$cond = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

$countSql = "SELECT COUNT(*) as total FROM seia_score_rank " . $cond;
try {
    $countStmt = $conn->prepare($countSql);
    if ($bindParams) {
        $countStmt->bind_param($bindTypes, ...$bindParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $total = $countRow['total'];
    $countStmt->close();

    if ($pageSize === 0) {
        $sql = "SELECT id, nickname, message, score, ip_addr, device, location, created_at, updated_at FROM seia_score_rank " . $cond . " ORDER BY score DESC, created_at ASC";
        $stmt = $conn->prepare($sql);
        if ($bindParams) {
            $stmt->bind_param($bindTypes, ...$bindParams);
        }
    } else {
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT id, nickname, message, score, ip_addr, device, location, created_at, updated_at FROM seia_score_rank " . $cond . " ORDER BY score DESC, created_at ASC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        if ($bindParams) {
            $stmt->bind_param($bindTypes . "ii", ...array_merge($bindParams, [$offset, $pageSize]));
        } else {
            $stmt->bind_param("ii", $offset, $pageSize);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $parts = explode(".", $row["ip_addr"]);
        if (count($parts) === 4) {
            $row["ip_addr"] = $parts[0] . ".***.***." . $parts[3];
        } else {
            $row["ip_addr"] = "***.***";
        }
        $rows[] = $row;
    }

    $stmt->close();
    $conn->close();
} catch (mysqli_sql_exception $e) {
    echo json_encode(["code" => 500, "message" => "查询数据库出错，请稍后重试"]);
    exit;
}

echo json_encode([
    "code" => 0,
    "data" => $rows,
    "total" => $total,
    "page" => $page,
    "pageSize" => $pageSize === 0 ? $total : $pageSize,
    "totalPages" => $pageSize === 0 ? 1 : ceil($total / $pageSize)
]);
