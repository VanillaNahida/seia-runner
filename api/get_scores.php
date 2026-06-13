<?php
require_once __DIR__ . "/init.php";

header("Content-Type: application/json; charset=utf-8");

try {
    $conn = getDB();
} catch (Exception $e) {
    echo json_encode(["code" => 500, "message" => "查询数据库出错，请稍后重试"]);
    exit;
}

$allowedTypes = ['day', 'week', 'month', 'all'];
$type = isset($_GET['type']) && in_array($_GET['type'], $allowedTypes, true) ? $_GET['type'] : 'all';
$page = isset($_GET['page']) && preg_match('/^[1-9][0-9]{0,5}$/', (string)$_GET['page']) ? intval($_GET['page']) : 1;
$query = isset($_GET['query']) && is_string($_GET['query']) ? trim($_GET['query']) : '';
if (mb_strlen($query, 'UTF-8') > 20 || preg_match('/[\x00-\x1F\x7F%_\\]/', $query)) {
    echo json_encode(["code" => 400, "message" => "搜索关键词无效"]);
    exit;
}

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

$cond = "";
$params = [];

if ($query !== '') {
    $cond = "WHERE nickname LIKE ? ESCAPE '\\\\'";
    $params[] = '%' . addcslashes($query, "\\%_") . '%';
} else {
    switch ($type) {
        case 'day':
            $cond = "WHERE DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $cond = "WHERE DATE_SUB(CURDATE(), INTERVAL 7 DAY) <= created_at";
            break;
        case 'month':
            $cond = "WHERE DATE_SUB(CURDATE(), INTERVAL 30 DAY) <= created_at";
            break;
        case 'all':
        default:
            $cond = "";
            break;
    }
}

$countSql = "SELECT COUNT(*) as total FROM seia_score_rank " . ($cond ? $cond : '');
try {
    $countStmt = $conn->prepare($countSql);
    if ($query !== '') {
        $countStmt->bind_param("s", $params[0]);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $total = $countRow['total'];
    $countStmt->close();

    if ($pageSize === 0) {
        $sql = "SELECT id, nickname, message, score, ip_addr, device, location, created_at, updated_at FROM seia_score_rank " . ($cond ? $cond : '') . " ORDER BY score DESC, created_at ASC";
        $stmt = $conn->prepare($sql);
        if ($query !== '') {
            $stmt->bind_param("s", $params[0]);
        }
    } else {
        $offset = ($page - 1) * $pageSize;
        $sql = "SELECT id, nickname, message, score, ip_addr, device, location, created_at, updated_at FROM seia_score_rank " . ($cond ? $cond : '') . " ORDER BY score DESC, created_at ASC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        if ($query !== '') {
            $stmt->bind_param("sii", $params[0], $offset, $pageSize);
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
