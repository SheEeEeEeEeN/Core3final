<?php

include("connection.php");

$limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$limit  = max(1, min(100, $limit));          
$offset = max(0, $offset);

$accounts = [];
$where_clause = "";
$params = [];
$types = "";

if ($search !== '') {
    $where_clause = " WHERE username LIKE ? OR email LIKE ? OR phone_number LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types  = "sss";
}

$sql = "SELECT 
            id,
            username,
            email,
            phone_number,
            profile_image,
            created_at
        FROM accounts
        $where_clause
        ORDER BY id DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "error"   => "Prepare failed",
        "message" => $conn->error
    ], JSON_PRETTY_PRINT);
    exit;
}


if ($search !== '') {
    $like = "%$search%";
    $stmt->bind_param("sssii", $like, $like, $like, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "error"   => "Execute failed",
        "message" => $stmt->error
    ], JSON_PRETTY_PRINT);
    exit;
}

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}

$stmt->close();


echo json_encode([
    "status"  => "success",
    "count"   => count($accounts),
    "total"   => null,          
    "limit"   => $limit,
    "offset"  => $offset,
    "search"  => $search,
    "data"    => $accounts
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$conn->close();