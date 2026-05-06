<?php
ob_start();
// user.php (Updated with Notification Bell)
include("connection.php");
include('session.php');
require_once("shipment_helpers.php");
requireRole('user');

// =================================================================================
// 1. API HANDLERS
// =================================================================================

// --- A. GET SHIPMENT DETAILS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_shipment_details') {
    ob_clean();
    header('Content-Type: application/json');
    $shipmentId = intval($_POST['id']);

    // Select * will include origin_lat, origin_lng, etc.
    $query = mysqli_query($conn, "SELECT * FROM shipments WHERE id='$shipmentId'");
    $shipment = mysqli_fetch_assoc($query);

    if (!$shipment) {
        echo json_encode(['success' => false, 'message' => 'Shipment not found']);
        exit;
    }

    $history = getShipmentTimeline($conn, $shipment);
    $status = strtoupper((string) ($shipment['status'] ?? ''));
    $prediction = "Standard routing applied.";
    $confidence = 85;

    if ($status == 'DELIVERED') {
        $prediction = "Completed successfully.";
        $confidence = 100;
    } elseif ($status == 'CANCELLED') {
        $prediction = "Cancelled.";
        $confidence = 0;
    } else {
        if (!empty($shipment['ai_estimated_time'])) {
            $prediction = $shipment['ai_estimated_time'];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $shipment,
        'tracking_no' => getShipmentTrackingNo($shipment),
        'history' => $history,
        'ai_prediction' => ['text' => $prediction, 'score' => $confidence],
    ]);
    exit;
}

// --- B. GET AI UPDATES ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_ai_updates') {
    ob_clean();
    header('Content-Type: application/json');

    date_default_timezone_set('Asia/Manila');
    $currentDate = date("l, F j, Y");
    $currentTime = date("g:i A");

    // -------------------------------------------------------------------------
    // 🔴 CONFIGURATION: PASTE YOUR GEMINI API KEY BELOW
    // -------------------------------------------------------------------------
    $apiKey = "AIzaSyAtembvSAcweA_ZNeW8bGSUDDJsBflAY30-"; // <--- ILAGAY DITO ANG IYONG API KEY (e.g. "AIzaSy...")

    // Check if API Key is missing
    if (empty($apiKey)) {
        echo json_encode([
            'success' => true,
            'message' => "AI Setup Needed: Please insert API Key in user.php",
            'icon' => "⚙️",
            'color' => "text-secondary",
            'timestamp' => $currentTime
        ]);
        exit;
    }

    $prompt = "You are a logistics logistics & weather AI specialist for the Philippines. 
               Current Context: $currentTime, $currentDate.
               Generate a very short, witty 1-sentence forecast about logistics/traffic/weather in Metro Manila.
               Return ONLY raw JSON: { \"message\": \"text\", \"type\": \"weather|traffic|info\" }";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;
    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($httpCode === 200 && isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        $rawText = $decoded['candidates'][0]['content']['parts'][0]['text'];

        // Extract JSON from response (in case of markdown wrappers)
        if (preg_match('/\{.*\}/s', $rawText, $matches)) {
            $aiData = json_decode($matches[0], true);
            $type = $aiData['type'] ?? 'info';

            $icon = '📡';
            $color = 'text-primary';

            if ($type == 'traffic') {
                $icon = '🚦';
                $color = 'text-warning';
            } elseif ($type == 'weather') {
                $icon = '🌦️';
                $color = 'text-info';
            }

            echo json_encode(['success' => true, 'message' => $aiData['message'], 'icon' => $icon, 'color' => $color, 'timestamp' => $currentTime]);
            exit;
        }
    }

    // Fallback if API fails
    echo json_encode([
        'success' => true,
        'message' => "System normal. Clear skies ahead!",
        'icon' => "🌤️",
        'color' => "text-success",
        'timestamp' => $currentTime
    ]);
    exit;
}

// --- C. SUBMIT RATING ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    ob_clean();
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $rating = intval($_POST['rating']);
    $feedback = mysqli_real_escape_string($conn, $_POST['feedback']);
    if (mysqli_query($conn, "UPDATE shipments SET rating='$rating', feedback_text='$feedback' WHERE id='$id'")) {
        echo json_encode(['success' => true, 'message' => 'Thank you for your feedback!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
    }
    exit;
}

// =================================================================================
// 2. PAGE CONTENT
// =================================================================================
include("darkmode.php");
include('loading.html');

$username = $_SESSION['email'];
$userQuery = mysqli_query($conn, "SELECT * FROM accounts WHERE email='" . mysqli_real_escape_string($conn, $username) . "'");
$user = mysqli_fetch_assoc($userQuery);
$userId = $user['id'];
$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 'user.png';

// HELPERS & FETCH DATA
function getStatusBadge($status)
{
    switch (strtoupper($status)) {
        case 'PENDING':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'IN_TRANSIT':
            return '<span class="badge bg-primary">In Transit</span>';
        case 'DELIVERED':
            return '<span class="badge bg-success">Delivered</span>';
        case 'CANCELLED':
            return '<span class="badge bg-danger">Cancelled</span>';
        default:
            return '<span class="badge bg-secondary">' . $status . '</span>';
    }
}

$summary = ['Pending' => 0, 'In Transit' => 0, 'Delivered' => 0];
$totalQuery = mysqli_query($conn, "SELECT status FROM shipments WHERE user_id='" . intval($userId) . "'");
$totalCount = 0;
while ($row = mysqli_fetch_assoc($totalQuery)) {
    $st = strtoupper($row['status']);
    if (in_array($st, ['PENDING', 'BOOKED']))
        $summary['Pending']++;
    elseif (in_array($st, ['IN_TRANSIT', 'READY_TO_DISPATCH', 'ARRIVED']))
        $summary['In Transit']++;
    elseif ($st == 'DELIVERED')
        $summary['Delivered']++;
    $totalCount++;
}

$shipmentDateColumn = getShipmentDateColumn($conn);

// Financials
$financeQuery = mysqli_query($conn, "SELECT
        SUM(CASE WHEN UPPER(status)='DELIVERED' THEN price ELSE 0 END) AS spent,
        SUM(CASE WHEN UPPER(status) NOT IN ('DELIVERED','CANCELLED') THEN price ELSE 0 END) AS upcoming
    FROM shipments
    WHERE user_id='$userId'");
$finance = mysqli_fetch_assoc($financeQuery);
$totalSpent = $finance['spent'] ?? 0;
$upcomingExpense = $finance['upcoming'] ?? 0;

// Logistics Action Center
$actionItems = [];

$pendingActionQuery = mysqli_query($conn, "SELECT id, receiver_name, {$shipmentDateColumn} AS booked_on
    FROM shipments
    WHERE user_id='$userId'
      AND UPPER(status) IN ('PENDING', 'BOOKED')
      AND {$shipmentDateColumn} <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY {$shipmentDateColumn} ASC
    LIMIT 4");
while ($pendingActionQuery && $row = mysqli_fetch_assoc($pendingActionQuery)) {
    $actionItems[] = [
        'priority' => 1,
        'variant' => 'warning',
        'icon' => 'bi bi-hourglass-split',
        'title' => 'Delay Alert: Shipment #' . $row['id'] . ' requires immediate consolidation.',
        'detail' => 'Pending since ' . date('M d, Y g:i A', strtotime($row['booked_on'])) . '.',
        'timestamp' => strtotime($row['booked_on']),
    ];
}

$cancelledActionQuery = mysqli_query($conn, "SELECT id, receiver_name, COALESCE(cancel_reason, feedback_text, 'No reason supplied.') AS cancel_reason, updated_at
    FROM shipments
    WHERE user_id='$userId'
      AND UPPER(status)='CANCELLED'
    ORDER BY updated_at DESC
    LIMIT 4");
while ($cancelledActionQuery && $row = mysqli_fetch_assoc($cancelledActionQuery)) {
    $actionItems[] = [
        'priority' => 3,
        'variant' => 'danger',
        'icon' => 'bi bi-x-octagon',
        'title' => 'Shipment #' . $row['id'] . ' cancelled by ' . ($row['receiver_name'] ?: 'the receiver') . '.',
        'detail' => 'Reason: ' . $row['cancel_reason'],
        'timestamp' => strtotime($row['updated_at']),
    ];
}

$proofActionQuery = mysqli_query($conn, "SELECT id, updated_at
    FROM shipments
    WHERE user_id='$userId'
      AND UPPER(status)='DELIVERED'
      AND (proof_image IS NULL OR proof_image = '')
    ORDER BY updated_at DESC
    LIMIT 4");
while ($proofActionQuery && $row = mysqli_fetch_assoc($proofActionQuery)) {
    $actionItems[] = [
        'priority' => 2,
        'variant' => 'danger',
        'icon' => 'bi bi-image',
        'title' => 'Incomplete: Upload proof of delivery for #' . $row['id'] . '.',
        'detail' => 'Delivered shipment is still missing its proof-of-delivery attachment.',
        'timestamp' => strtotime($row['updated_at']),
    ];
}

usort($actionItems, static function (array $left, array $right): int {
    if ($left['priority'] === $right['priority']) {
        return $right['timestamp'] <=> $left['timestamp'];
    }

    return $left['priority'] <=> $right['priority'];
});

$actionItems = array_slice($actionItems, 0, 6);
$actionCount = count($actionItems);
$topAction = $actionItems[0] ?? null;

// Recent Shipments
$shipments = mysqli_query($conn, "SELECT * FROM shipments WHERE user_id='$userId' ORDER BY created_at DESC LIMIT 8");

// Command Center Analytics
$dailyRevenueLookup = [];
$dailyRevenueLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $dayKey = date('Y-m-d', strtotime("-{$i} days"));
    $dailyRevenueLabels[] = date('M d', strtotime($dayKey));
    $dailyRevenueLookup[$dayKey] = 0.0;
}

$revenueAnalytics = mysqli_query($conn, "SELECT DATE({$shipmentDateColumn}) AS revenue_day, COALESCE(SUM(price), 0) AS total_revenue
    FROM shipments
    WHERE user_id='$userId'
      AND DATE({$shipmentDateColumn}) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
      AND UPPER(status) <> 'CANCELLED'
    GROUP BY DATE({$shipmentDateColumn})
    ORDER BY revenue_day ASC");
while ($revenueAnalytics && $row = mysqli_fetch_assoc($revenueAnalytics)) {
    if (array_key_exists($row['revenue_day'], $dailyRevenueLookup)) {
        $dailyRevenueLookup[$row['revenue_day']] = (float) $row['total_revenue'];
    }
}
$dailyRevenueValues = array_values($dailyRevenueLookup);

$statusDistribution = [
    'Consolidated' => 0,
    'Cancelled' => 0,
    'Delivered' => 0,
    'Archived' => 0,
];
$distributionQuery = mysqli_query($conn, "SELECT UPPER(status) AS status_name, COUNT(*) AS total
    FROM shipments
    WHERE user_id='$userId'
      AND UPPER(status) IN ('CONSOLIDATED', 'CANCELLED', 'DELIVERED', 'ARCHIVED')
    GROUP BY UPPER(status)");
while ($distributionQuery && $row = mysqli_fetch_assoc($distributionQuery)) {
    $key = match ($row['status_name']) {
        'CONSOLIDATED' => 'Consolidated',
        'CANCELLED' => 'Cancelled',
        'DELIVERED' => 'Delivered',
        'ARCHIVED' => 'Archived',
        default => null,
    };

    if ($key !== null) {
        $statusDistribution[$key] = (int) $row['total'];
    }
}

$statusDistributionLabels = array_keys($statusDistribution);
$statusDistributionValues = array_values($statusDistribution);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <!-- GOOGLE FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bs-primary: #222831;
            --bs-primary-rgb: 34, 40, 49;

            --sidebar-width: 260px;
            --primary-color: #222831;
            --primary-hover: #393E46;
            --secondary-color: #DFD0B8;
            --text-main: #222831;
            --text-secondary: #393E46;
            --border-color: #948979;

            /* Dark Mode Variables */
            --dark-bg: #1c2027;
            --dark-card: #222831;
            --dark-border: #393E46;
            --dark-text-main: #DFD0B8;
            --dark-text-sec: #948979;

            --shadow-sm: 0 1px 2px 0 rgb(34 40 49 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(34 40 49 / 0.1), 0 2px 4px -2px rgb(34 40 49 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(34 40 49 / 0.1), 0 4px 6px -4px rgb(34 40 49 / 0.1);
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-main);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #ffffff;
            color: var(--text-main);
            z-index: 1040;
            transition: all 0.3s ease;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 24px;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .sidebar.collapsed {
            margin-left: calc(var(--sidebar-width) * -1);
        }

        .content.expanded {
            margin-left: 0;
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-left: calc(var(--sidebar-width) * -1);
            }

            .sidebar.show {
                margin-left: 0;
            }

            .content {
                margin-left: 0;
            }

            .content.expanded {
                margin-left: 0;
            }

            .content.mobile-expanded {
                margin-left: var(--sidebar-width);
            }
        }

        /* Navigation Links */
        .nav-link {
            font-weight: 500;
            color: var(--text-secondary) !important;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            border-radius: var(--radius-md);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .nav-link:hover {
            color: var(--text-main) !important;
            background: var(--secondary-color);
        }

        .nav-link.active {
            color: #ffffff !important;
            background: var(--primary-color);
            font-weight: 600;
        }

        .nav-link i {
            font-size: 1.1rem;
            margin-right: 12px;
        }

        /* Cards & UI */
        .card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            background: white;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
            padding: 1rem 1.25rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        .clickable-row {
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .clickable-row:hover {
            background-color: var(--secondary-color) !important;
        }

        #modalMap {
            height: 350px;
            width: 100%;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            z-index: 1;
            border: 1px solid var(--border-color);
        }

        .leaflet-routing-container {
            display: none !important;
        }

        .btn {
            border-radius: var(--radius-md);
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        /* Logo Fixes */
        body:not(.dark-mode) .sidebar img[alt="Logo"] {
            filter: brightness(0);
        }

        body.dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text-main);

            --bs-body-bg: var(--dark-bg);
            --bs-body-color: var(--dark-text-main);
            --bs-border-color: var(--dark-border);
            --bs-card-bg: var(--dark-card);
        }

        body.dark-mode .sidebar {
            background: var(--dark-card);
            border-right: 1px solid var(--dark-border);
            color: var(--dark-text-main);
        }

        body.dark-mode .nav-link {
            color: var(--dark-text-sec) !important;
        }

        body.dark-mode .nav-link:hover {
            color: var(--dark-text-main) !important;
            background: var(--dark-border);
        }

        body.dark-mode .nav-link.active {
            color: var(--dark-bg) !important;
            background: var(--border-color);
        }

        body.dark-mode .card,
        body.dark-mode .modal-content,
        body.dark-mode .list-group-item,
        body.dark-mode .dropdown-menu {
            background-color: var(--dark-card) !important;
            color: var(--dark-text-main) !important;
            border-color: var(--dark-border) !important;
        }

        body.dark-mode .dropdown-item {
            color: var(--dark-text-main);
        }

        body.dark-mode .dropdown-item:hover,
        body.dark-mode .dropdown-item:focus {
            background-color: rgba(255, 255, 255, 0.05);
            color: white;
        }

        body.dark-mode .text-dark {
            color: #fafafa !important;
        }

        body.dark-mode .text-muted {
            color: var(--dark-text-sec) !important;
        }

        body.dark-mode .text-secondary {
            color: var(--dark-text-sec) !important;
        }

        body.dark-mode .text-primary {
            color: #948979 !important;
        }

        body.dark-mode .border {
            border-color: var(--dark-border) !important;
        }

        body.dark-mode .bg-white {
            background-color: var(--dark-card) !important;
        }

        body.dark-mode .bg-light {
            background-color: var(--dark-bg) !important;
            color: #fafafa !important;
            border-color: var(--dark-border) !important;
        }

        /* Tables */
        body.dark-mode .table {
            --bs-table-bg: var(--dark-card);
            --bs-table-color: var(--dark-text-main);
            --bs-table-border-color: var(--dark-border);
            color: var(--dark-text-main) !important;
        }

        body.dark-mode .table th,
        body.dark-mode .table td {
            background-color: var(--dark-card) !important;
            color: var(--dark-text-main) !important;
            border-color: var(--dark-border) !important;
        }

        body.dark-mode .table-light th {
            background-color: var(--dark-bg) !important;
            color: #fafafa !important;
        }

        body.dark-mode .table-hover tbody tr:hover td {
            background-color: rgba(255, 255, 255, 0.02) !important;
            color: #fff !important;
        }

        /* Forms */
        body.dark-mode .form-control,
        body.dark-mode .input-group-text,
        body.dark-mode textarea,
        body.dark-mode select {
            background-color: var(--dark-bg) !important;
            border-color: var(--dark-border) !important;
            color: white !important;
        }

        body.dark-mode .form-control:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3);
        }

        body.dark-mode .btn-close {
            filter: invert(1);
        }

        /* Map Dark Mode */
        body.dark-mode .leaflet-layer,
        body.dark-mode .leaflet-control-zoom-in,
        body.dark-mode .leaflet-control-zoom-out,
        body.dark-mode .leaflet-control-attribution {
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }

        /* Custom Header */
        .top-header {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 12px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }

        body.dark-mode .top-header {
            background: var(--dark-card);
            border-color: var(--dark-border);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            font-size: 1.5rem;
        }

        .command-panel {
            background: linear-gradient(160deg, #0f172a 0%, #16213a 52%, #0b1220 100%);
            border: 1px solid rgba(148, 163, 184, 0.14);
            color: #e2e8f0;
            overflow: hidden;
        }

        .command-panel .card-header,
        .command-panel .card-body {
            background: transparent;
            border-color: rgba(148, 163, 184, 0.14);
        }

        .command-panel .text-muted,
        .command-panel .text-secondary {
            color: #94a3b8 !important;
        }

        .command-panel .command-kicker {
            font-size: 0.72rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .action-alert {
            border: 1px solid transparent;
            border-radius: var(--radius-md);
        }

        .action-alert-warning {
            background: rgba(245, 158, 11, 0.12);
            border-color: rgba(245, 158, 11, 0.28);
        }

        .action-alert-danger {
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.28);
        }

        body.dark-mode .command-panel {
            background: linear-gradient(160deg, #020617 0%, #0f172a 55%, #111827 100%) !important;
            border-color: rgba(71, 85, 105, 0.45) !important;
        }

        body.dark-mode .action-alert-warning {
            background: rgba(245, 158, 11, 0.16);
            border-color: rgba(245, 158, 11, 0.3);
        }

        body.dark-mode .action-alert-danger {
            background: rgba(239, 68, 68, 0.16);
            border-color: rgba(239, 68, 68, 0.3);
        }
    </style>
</head>

<body>
    <!-- Sidebar is fully visible by default -->
    <div class="sidebar flex-shrink-0 p-3" id="sidebar">
        <div class="text-center mb-4 mt-2">
            <img src="Remorig.png" alt="Logo" class="img-fluid mb-2" style="max-width: 120px; transition: 0.3s ease;">
            <h6 class="fw-semibold text-uppercase text-muted mb-0 logo-title"
                style="letter-spacing: 1px; font-size: 0.75rem;">Core Transaction 3</h6>
        </div>
        <hr class="border-secondary opacity-25">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="user.php" class="nav-link active"><i class="bi bi-grid-1x2"></i> <span
                        class="sidebar-text">Dashboard</span></a>
            </li>
            <li class="nav-item">
                <a href="bookshipment.php" class="nav-link"><i class="bi bi-box-seam"></i> <span
                        class="sidebar-text">Book Shipment</span></a>
            </li>
            <li class="nav-item">
                <a href="My_shipment.php" class="nav-link"><i class="bi bi-truck"></i> <span class="sidebar-text">My
                        Shipments</span></a>
            </li>
            <li class="nav-item">
                <a href="shiphistory.php" class="nav-link"><i class="bi bi-clock-history"></i> <span
                        class="sidebar-text">History</span></a>
            </li>
            <li class="nav-item">
                <a href="feedback.php" class="nav-link"><i class="bi bi-chat-square-text"></i> <span
                        class="sidebar-text">Feedback</span></a>
            </li>
        </ul>
    </div>

    <div class="content" id="mainContent">
        <header class="top-header d-flex align-items-center justify-content-between sticky-top">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
                <div>
                    <h5 class="fw-bold mb-0">Dashboard</h5>
                    <small class="text-muted">Welcome back, <?php echo htmlspecialchars($user['username']); ?></small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
                    <label class="form-check-label text-muted" for="userThemeToggle"><i
                            class="bi bi-moon-stars"></i></label>
                    <input class="form-check-input m-0" type="checkbox" role="switch" id="userThemeToggle">
                </div>

                <div class="dropdown mx-1">
                    <a href="#" class="text-dark position-relative" id="notifDropdown" data-bs-toggle="dropdown"
                        onclick="markRead()">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center"
                            style="width:36px;height:36px;">
                            <i class="bi bi-bell"></i>
                        </div>
                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm border border-white"
                            id="notifBadge" style="display: none; padding: 0.25em 0.5em; font-size: 0.65em;">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow p-0"
                        style="width: 320px; max-height: 480px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <li
                            class="p-3 border-bottom fw-bold bg-light d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <small class="text-primary cursor-pointer text-decoration-none" style="cursor:pointer;"
                                onclick="location.href='feedback.php'">View All</small>
                        </li>
                        <div id="notifList">
                            <li class="text-center p-4 text-muted small">No new notifications</li>
                        </div>
                    </ul>
                </div>

                <div class="dropdown">
                    <a href="#" data-bs-toggle="dropdown" class="d-block link-dark text-decoration-none"
                        style="cursor: pointer;">
                        <img src="<?php echo $profileImage; ?>" alt="mdo" width="36" height="36"
                            class="rounded-circle object-fit-cover border border-2 border-primary">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end text-small shadow"
                        style="border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <li><a class="dropdown-item" href="user-profile.php"><i
                                    class="bi bi-person me-2"></i>Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i
                                    class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <div class="card border-0 mb-4 overflow-hidden position-relative"
            style="background: linear-gradient(135deg, #222831 0%, #393E46 100%); color: white; border-radius: var(--radius-lg);">
            <div class="card-body p-4 p-lg-5 position-relative z-1">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <h2 class="fw-bold mb-2">Track & Manage Shipments</h2>
                        <p class="mb-4 opacity-75">Input your tracking ID to get real-time delivery status and insights.
                        </p>
                        <div class="input-group input-group-lg bg-white rounded-3 overflow-hidden p-1 shadow-sm"
                            style="max-width: 500px;">
                            <span class="input-group-text bg-transparent border-0"><i
                                    class="bi bi-search text-muted"></i></span>
                            <input type="number" id="quickTrackInput"
                                class="form-control border-0 shadow-none text-dark fs-6"
                                placeholder="Tracking ID (e.g., 1024)">
                            <button class="btn btn-primary fw-bold px-4" onclick="quickTrack()">Track</button>
                        </div>
                    </div>
                    <div class="col-lg-5 d-none d-lg-block text-end position-relative">
                        <i class="bi bi-box-seam position-absolute"
                            style="font-size: 12rem; color: rgba(255,255,255,0.08); bottom: -70px; right: 0;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fw-semibold small text-uppercase">Pending</span>
                            <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-hourglass-split fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0"><?php echo $summary['Pending']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fw-semibold small text-uppercase">In Transit</span>
                            <div class="metric-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-truck fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0"><?php echo $summary['In Transit']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fw-semibold small text-uppercase">Delivered</span>
                            <div class="metric-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-check2-circle fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0"><?php echo $summary['Delivered']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fw-semibold small text-uppercase">Total</span>
                            <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-boxes fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0"><?php echo $totalCount; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">Recent Shipments</h6>
                        <a href="My_shipment.php" class="btn btn-sm btn-light">View All</a>
                    </div>
                    <div class="table-responsive p-0">
                        <table class="table table-hover align-middle mb-0 border-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 border-0">ID</th>
                                    <th class="border-0">Receiver</th>
                                    <th class="border-0">Destination</th>
                                    <th class="border-0">Status</th>
                                    <th class="pe-4 border-0 text-end">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($shipments && mysqli_num_rows($shipments) > 0):
                                    while ($s = mysqli_fetch_assoc($shipments)): ?>
                                        <tr class="clickable-row" onclick="openShipmentModal(<?php echo $s['id']; ?>)">
                                            <td class="ps-4"><span
                                                    class="text-primary fw-semibold">#<?php echo $s['id']; ?></span></td>
                                            <td><span class="text-dark fw-medium"><?php echo $s['receiver_name']; ?></span></td>
                                            <td class="text-muted small">
                                                <?php echo substr($s['destination_address'], 0, 15) . '...'; ?>
                                            </td>
                                            <td><?php echo getStatusBadge($s['status']); ?></td>
                                            <td class="pe-4 text-end text-dark fw-medium">
                                                ₱<?php echo number_format($s['price'], 0); ?></td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5"><i
                                                class="bi bi-inbox fs-2 d-block mb-2"></i>No shipments found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card command-panel h-100">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="command-kicker">Command Center</div>
                                        <h6 class="fw-bold mb-0 text-white">Daily Revenue</h6>
                                    </div>
                                    <span class="badge text-bg-light border-0" style="color:#0f172a;">Last 7 Days</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="small text-secondary mb-3">Revenue trend based on booked shipments, excluding cancelled loads.</p>
                                <div style="height:240px;"><canvas id="dailyRevenueChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card command-panel h-100">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="command-kicker">Command Center</div>
                                        <h6 class="fw-bold mb-0 text-white">Shipment Status Distribution</h6>
                                    </div>
                                    <span class="badge text-bg-light border-0" style="color:#0f172a;">Live Mix</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="small text-secondary mb-3">Current distribution across consolidated, cancelled, delivered, and archived shipments.</p>
                                <div style="height:240px;"><canvas id="statusDistributionChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-md-6">
                        <div class="card p-4 border-0 h-100 shadow-sm text-white"
                            style="background: linear-gradient(135deg, #393E46 0%, #948979 100%); border-radius: var(--radius-lg);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-white-50 text-uppercase fw-semibold mb-1 d-block">Total
                                        Spent</small>
                                    <h3 class="fw-bold mb-0">₱<?php echo number_format($totalSpent, 2); ?></h3>
                                </div>
                                <div class="bg-white bg-opacity-25 rounded-circle p-3"><i
                                        class="bi bi-wallet2 fs-4"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-4 border-0 h-100 shadow-sm" style="border-radius: var(--radius-lg);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted text-uppercase fw-semibold mb-1 d-block">Upcoming
                                        Payables</small>
                                    <h3 class="fw-bold text-dark mb-0">
                                        ₱<?php echo number_format($upcomingExpense, 2); ?></h3>
                                </div>
                                <div class="bg-light rounded-circle p-3 text-secondary"><i
                                        class="bi bi-credit-card fs-4"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <?php if ($topAction): ?>
                    <div class="action-alert action-alert-<?php echo $topAction['variant'] === 'warning' ? 'warning' : 'danger'; ?> shadow-sm mb-4 d-flex align-items-start gap-3 p-3">
                        <i class="<?php echo $topAction['icon']; ?> fs-4 mt-1 <?php echo $topAction['variant'] === 'warning' ? 'text-warning' : 'text-danger'; ?>"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Action Required</h6>
                            <small class="d-block"><?php echo htmlspecialchars($topAction['title']); ?></small>
                            <small class="text-muted"><?php echo htmlspecialchars($topAction['detail']); ?></small>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card mb-4 border-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold m-0"><i class="bi bi-list-check text-primary me-2"></i>Action Center</h6>
                        <?php if ($actionCount > 0): ?><span
                                class="badge bg-danger rounded-pill fw-medium"><?php echo $actionCount; ?>
                                Open</span><?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($actionCount > 0): ?>
                            <div class="d-grid gap-3">
                                <?php foreach ($actionItems as $item): ?>
                                    <div class="action-alert action-alert-<?php echo $item['variant'] === 'warning' ? 'warning' : 'danger'; ?> p-3">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="<?php echo $item['variant'] === 'warning' ? 'text-warning' : 'text-danger'; ?>">
                                                <i class="<?php echo $item['icon']; ?> fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold mb-1"><?php echo htmlspecialchars($item['title']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($item['detail']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted small"><i
                                    class="bi bi-check-circle fs-3 d-block mb-1 text-success"></i>No action items right now.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4 border-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">📢 AI Forecast</h6>
                        <span
                            class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill"><i
                                class="bi bi-stars"></i> Gemini</span>
                    </div>
                    <div class="card-body">
                        <div id="incomingUpdatesList" class="small">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Fetching insight...
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm position-relative overflow-hidden"
                    style="background: var(--text-main); color: white; border-radius: var(--radius-lg);">
                    <div class="card-body p-4 text-center position-relative z-1">
                        <h6 class="fw-bold mb-3 d-flex align-items-center justify-content-center gap-2"><i
                                class="bi bi-headset"></i> Need Support?</h6>
                        <p class="small text-white-50 mb-3">Our team is available 24/7 to assist you with your
                            shipments.</p>
                        <a href="feedback.php" class="btn btn-light btn-sm fw-bold px-4 text-dark w-100">Contact
                            Support</a>
                    </div>
                    <div class="position-absolute end-0 bottom-0 opacity-25" style="transform: translate(20%, 20%);"><i
                            class="bi bi-life-preserver" style="font-size: 8rem;"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shipmentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 overflow-hidden shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Tracking #<span id="modalShipmentId"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-lg-7 p-4 bg-white">
                            <div id="modalMap"></div>

                            <div class="d-flex justify-content-between mb-4 text-center">
                                <div><small class="text-muted fw-bold d-block">ORIGIN</small><span class="fw-bold small"
                                        id="modalSender">...</span></div>
                                <div class="text-muted"><i class="bi bi-arrow-right"></i></div>
                                <div><small class="text-muted fw-bold d-block">DESTINATION</small><span
                                        class="fw-bold small" id="modalReceiver">...</span></div>
                            </div>

                            <div class="mb-3 bg-light p-3 rounded border-start border-4 border-primary">
                                <small class="text-muted fw-bold">Delivery Address</small>
                                <div class="fw-semibold text-dark" id="modalAddress">...</div>
                            </div>

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold text-primary"><i class="bi bi-stars"></i> Gemini Prediction</h6>
                                    <p class="small fst-italic mb-2" id="modalAiText">Analyzing...</p>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" id="modalAiBar" style="width: 0%"></div>
                                    </div>
                                    <div class="text-end small text-success fw-bold" id="modalAiScore">0%</div>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="p-2 border rounded text-center"><small>Distance</small>
                                        <h6 class="mb-0 fw-bold" id="modalDistance">0 km</h6>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 border rounded text-center"><small>Total Cost</small>
                                        <h6 class="mb-0 fw-bold text-success" id="modalPrice">₱0</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5 bg-light p-4 border-start">
                            <h6 class="fw-bold text-secondary text-uppercase mb-4">History</h6>
                            <ul class="timeline position-relative ps-0" style="list-style: none;" id="modalTimeline">
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button id="btnViewProof" class="btn btn-success d-none" onclick="viewProof()"><i
                            class="bi bi-card-image"></i> Proof</button>
                    <button id="btnRate" class="btn btn-warning d-none" onclick="openRatingModal()"><i
                            class="bi bi-star-fill"></i> Rate</button>
                    <button class="btn btn-dark" onclick="openWaybill()"><i class="bi bi-upc-scan"></i>
                        Generate Waybill</button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="waybillModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Waybill</h5><button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0"><iframe id="waybillFrame" src=""
                        style="width: 100%; height: 80vh; border: none;"></iframe></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ratingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Rate Service</h5><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <form id="ratingForm"><input type="hidden" name="id" id="ratingShipmentId"><input type="hidden"
                            name="rating" id="ratingValue" value="0">
                        <div class="display-4 text-warning mb-3"><i class="bi bi-star" onclick="setStar(1)"
                                id="star1"></i><i class="bi bi-star" onclick="setStar(2)" id="star2"></i><i
                                class="bi bi-star" onclick="setStar(3)" id="star3"></i><i class="bi bi-star"
                                onclick="setStar(4)" id="star4"></i><i class="bi bi-star" onclick="setStar(5)"
                                id="star5"></i></div><textarea class="form-control" name="feedback"
                            placeholder="Comment" style="height: 100px"></textarea>
                    </form>
                </div>
                <div class="modal-footer"><button class="btn btn-primary w-100" onclick="submitRating()">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        if (typeof initDarkMode === 'function') initDarkMode('userThemeToggle', 'userDarkMode');

        // Sidebar Toggle
        document.getElementById('hamburger').addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('mainContent');
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            } else {
                sidebar.classList.toggle('show');
                content.classList.toggle('mobile-expanded');
            }
        });

        // Charts
        const dailyRevenueLabels = <?php echo json_encode($dailyRevenueLabels); ?>;
        const dailyRevenueValues = <?php echo json_encode($dailyRevenueValues); ?>;
        const statusDistributionLabels = <?php echo json_encode($statusDistributionLabels); ?>;
        const statusDistributionValues = <?php echo json_encode($statusDistributionValues); ?>;
        const chartGridColor = 'rgba(148, 163, 184, 0.18)';
        const chartTickColor = '#cbd5e1';

        if (document.getElementById('dailyRevenueChart')) {
            new Chart(document.getElementById('dailyRevenueChart'), {
                type: 'line',
                data: {
                    labels: dailyRevenueLabels,
                    datasets: [{
                        label: 'Revenue',
                        data: dailyRevenueValues,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.18)',
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#dbeafe',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: chartTickColor,
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: chartTickColor },
                            grid: { color: chartGridColor }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: chartTickColor,
                                callback: value => '₱' + Number(value).toLocaleString()
                            },
                            grid: { color: chartGridColor }
                        }
                    }
                }
            });
        }

        if (document.getElementById('statusDistributionChart')) {
            new Chart(document.getElementById('statusDistributionChart'), {
                type: 'pie',
                data: {
                    labels: statusDistributionLabels,
                    datasets: [{
                        data: statusDistributionValues,
                        backgroundColor: ['#3b82f6', '#ef4444', '#10b981', '#64748b'],
                        borderColor: '#0f172a',
                        borderWidth: 2,
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: chartTickColor,
                                padding: 18,
                                boxWidth: 14,
                            }
                        }
                    }
                }
            });
        }

        // AI Updates
        fetchAiUpdates();
        function fetchAiUpdates() {
            const c = document.getElementById('incomingUpdatesList');
            const fd = new FormData(); fd.append('action', 'get_ai_updates');
            fetch('user.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                if (d.success) c.innerHTML = `<div class="d-flex gap-2"><div class="fs-4">${d.icon}</div><div><strong>Logistics Insight</strong><br>${d.message}</div></div>`;
            });
        }

        function quickTrack() { const id = document.getElementById('quickTrackInput').value; if (id) openShipmentModal(id); }

        // --- MAP & MODAL LOGIC (FIXED) ---
        let shipmentMap = null;
        let shipmentRoute = null;

        function openShipmentModal(id) {
            const m = new bootstrap.Modal(document.getElementById('shipmentDetailsModal'));
            document.getElementById('modalShipmentId').textContent = id;
            m.show(); // Show modal first

            // 1. Fetch Text Data (Immediate)
            const fd = new FormData(); fd.append('action', 'get_shipment_details'); fd.append('id', id);
            fetch('user.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                if (d.success) {
                    document.getElementById('modalShipmentId').textContent = d.tracking_no || id;
                    document.getElementById('modalSender').textContent = d.data.sender_name;
                    document.getElementById('modalReceiver').textContent = d.data.receiver_name;
                    document.getElementById('modalAddress').textContent = d.data.destination_address;
                    document.getElementById('modalDistance').textContent = d.data.distance_km + ' km';
                    document.getElementById('modalPrice').textContent = '₱' + Number(d.data.price).toLocaleString();

                    document.getElementById('modalAiText').innerText = d.ai_prediction.text;
                    document.getElementById('modalAiScore').innerText = d.ai_prediction.score + "%";
                    document.getElementById('modalAiBar').style.width = d.ai_prediction.score + "%";

                    const tl = document.getElementById('modalTimeline'); tl.innerHTML = '';
                    d.history.forEach((h, i) => {
                        const col = h.status === 'Cancelled' ? 'bg-danger' : (i === d.history.length - 1 ? 'bg-primary' : 'bg-success');
                        tl.innerHTML += `<li class="mb-4 position-relative ps-4"><div class="position-absolute top-0 start-0 translate-middle-x rounded-circle ${col}" style="width:12px;height:12px;top:5px;"></div><div><strong>${h.status}</strong><br><small class="text-muted">${h.time}</small><div class="small text-primary fw-semibold">${h.location || 'Shipment network'}</div><p class="small mb-0 text-muted">${h.desc}</p></div></li>`;
                    });

                    const btnProof = document.getElementById('btnViewProof'); const btnRate = document.getElementById('btnRate');
                    btnProof.classList.add('d-none'); btnRate.classList.add('d-none');
                    if ((d.data.status || '').toString().toUpperCase() === 'DELIVERED') {
                        if (d.data.proof_image) { btnProof.classList.remove('d-none'); btnProof.setAttribute('data-img', d.data.proof_image); }
                        if (d.data.rating == 0) btnRate.classList.remove('d-none');
                    }

                    // 2. Map Rendering Logic (Delayed)
                    setTimeout(() => {
                        if (!shipmentMap) {
                            shipmentMap = L.map('modalMap').setView([14.5995, 120.9842], 5);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(shipmentMap);
                        }

                        // Force resize after modal is fully open
                        shipmentMap.invalidateSize();

                        // Clear previous route
                        if (shipmentRoute) {
                            shipmentMap.removeControl(shipmentRoute);
                            shipmentRoute = null;
                        }

                        // Draw new route
                        if (d.data.origin_lat && d.data.origin_lng && d.data.dest_lat && d.data.dest_lng) {
                            const origin = L.latLng(d.data.origin_lat, d.data.origin_lng);
                            const dest = L.latLng(d.data.dest_lat, d.data.dest_lng);
                            shipmentRoute = L.Routing.control({
                                waypoints: [origin, dest],
                                routeWhileDragging: false, addWaypoints: false, fitSelectedRoutes: true, show: false,
                                lineOptions: { styles: [{ color: '#4e73df', opacity: 0.8, weight: 6 }] },
                                createMarker: function (i, wp) {
                                    const col = i === 0 ? '#198754' : '#dc3545';
                                    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="${col}" width="40" height="40"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5" fill="white"/></svg>`;
                                    return L.marker(wp.latLng, { icon: L.divIcon({ className: 'custom-pin', html: svg, iconSize: [40, 40], iconAnchor: [20, 40] }) });
                                }
                            }).addTo(shipmentMap);
                        } else {
                            shipmentMap.setView([14.5995, 120.9842], 5); // Default view
                        }
                    }, 500); // 500ms delay to wait for modal slide
                }
            });
        }

        function openWaybill() {
            const id = document.getElementById('modalShipmentId').textContent;
            if (id) { document.getElementById('waybillFrame').src = 'generate_waybill.php?id=' + id; new bootstrap.Modal(document.getElementById('waybillModal')).show(); }
        }

        function viewProof() {
            const img = document.getElementById('btnViewProof').getAttribute('data-img');
            if (!img) return;
            const proofUrl = img.startsWith('uploads/') ? img : ('uploads/' + img);
            window.open(proofUrl, '_blank');
        }

        function openRatingModal() {
            document.getElementById('ratingShipmentId').value = document.getElementById('modalShipmentId').textContent;
            bootstrap.Modal.getInstance(document.getElementById('shipmentDetailsModal')).hide();
            new bootstrap.Modal(document.getElementById('ratingModal')).show();
        }
        function setStar(n) { document.getElementById('ratingValue').value = n; for (let i = 1; i <= 5; i++) document.getElementById('star' + i).className = i <= n ? 'bi bi-star-fill' : 'bi bi-star'; }
        function submitRating() {
            const fd = new FormData(document.getElementById('ratingForm')); fd.append('action', 'submit_rating');
            fetch('user.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { alert(d.message); location.reload(); });
        }

        // --- NOTIFICATION LOGIC ---
        function fetchNotifications() {
            fetch('api/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notifBadge');
                    const list = document.getElementById('notifList');

                    if (data.count > 0) {
                        badge.innerText = data.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }

                    let html = '';
                    if (data.data.length > 0) {
                        data.data.forEach(notif => {
                            let bgClass = notif.is_read == 0 ? 'bg-light' : '';
                            let icon = notif.is_read == 0 ? 'bi-circle-fill text-primary' : 'bi-check-circle text-muted';

                            html += `
                        <li>
                            <a class="dropdown-item ${bgClass} p-2 border-bottom" href="${notif.link}">
                                <div class="d-flex align-items-start">
                                    <i class="bi ${icon} me-2 mt-1" style="font-size: 10px;"></i>
                                    <div>
                                        <small class="fw-bold d-block">${notif.title}</small>
                                        <small class="text-muted text-wrap">${notif.message}</small>
                                        <br>
                                        <small class="text-secondary" style="font-size: 0.7rem;">${new Date(notif.created_at).toLocaleString()}</small>
                                    </div>
                                </div>
                            </a>
                        </li>`;
                        });
                    } else {
                        html = '<li class="text-center p-3 text-muted small">No notifications</li>';
                    }
                    list.innerHTML = html;
                });
        }

        function markRead() {
            fetch('api/get_notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=read_all'
            }).then(() => {
                document.getElementById('notifBadge').style.display = 'none';
            });
        }

        fetchNotifications();
        setInterval(fetchNotifications, 5000); // Check every 5 seconds
    </script>
</body>

</html>
