<?php
include("connection.php");
require_once("shipment_helpers.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =================================================================================
// 1. API HANDLER (Internal)
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_shipment_details') {

    while (ob_get_level())
        ob_end_clean();
    header('Content-Type: application/json');

    $shipmentId = intval($_POST['id']);
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $query = mysqli_query($conn, "SELECT * FROM shipments WHERE id='$shipmentId' AND user_id='$userId' LIMIT 1");
    $shipment = mysqli_fetch_assoc($query);

    if (!$shipment) {
        echo json_encode(['success' => false, 'message' => 'Shipment not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $shipment,
        'tracking_no' => getShipmentTrackingNo($shipment),
        'timeline' => getShipmentTimeline($conn, $shipment),
    ]);
    exit;
}

if (!function_exists('getHistoryStatusPresentation')) {
    function getHistoryStatusPresentation(string $status): array
    {
        $normalized = normalizeShipmentStatus($status);

        return match ($normalized) {
            'PENDING', 'BOOKED' => ['label' => 'Pending', 'badge' => 'bg-warning text-dark'],
            'READY_TO_DISPATCH', 'CONSOLIDATED', 'IN_TRANSIT', 'ARRIVED', 'OUT_FOR_DELIVERY' => ['label' => 'In Transit', 'badge' => 'bg-info text-dark'],
            'DELIVERED' => ['label' => 'Delivered', 'badge' => 'bg-success'],
            'CANCELLED' => ['label' => 'Cancelled', 'badge' => 'bg-danger'],
            'ARCHIVED' => ['label' => 'Archived', 'badge' => 'bg-secondary'],
            default => ['label' => ucwords(strtolower($status)), 'badge' => 'bg-secondary'],
        };
    }
}

// =================================================================================
// 2. PAGE CONTENT
// =================================================================================

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$shipmentDateColumn = getShipmentDateColumn($conn);

$historyMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$searchTerm = trim((string) ($_GET['q'] ?? ''));

$statusMap = [
    'Pending' => ['PENDING', 'BOOKED'],
    'In Transit' => ['READY_TO_DISPATCH', 'CONSOLIDATED', 'IN_TRANSIT', 'ARRIVED', 'OUT_FOR_DELIVERY'],
    'Delivered' => ['DELIVERED'],
    'Cancelled' => ['CANCELLED'],
    'Archived' => ['ARCHIVED'],
];

$whereClauses = [
    "user_id = {$user_id}",
    "DATE_FORMAT({$shipmentDateColumn}, '%Y-%m') = '" . mysqli_real_escape_string($conn, $historyMonth) . "'",
];

if ($statusFilter !== '' && isset($statusMap[$statusFilter])) {
    $escapedStatuses = array_map(
        static fn(string $value): string => "'" . $value . "'",
        $statusMap[$statusFilter]
    );
    $whereClauses[] = 'UPPER(status) IN (' . implode(', ', $escapedStatuses) . ')';
}

if ($searchTerm !== '') {
    $searchValue = mysqli_real_escape_string($conn, $searchTerm);
    $whereClauses[] = "(CAST(id AS CHAR) LIKE '%{$searchValue}%'
        OR COALESCE(shipment_code, '') LIKE '%{$searchValue}%'
        OR COALESCE(contract_number, '') LIKE '%{$searchValue}%'
        OR COALESCE(receiver_name, '') LIKE '%{$searchValue}%'
        OR COALESCE(destination_address, '') LIKE '%{$searchValue}%')";
}

$historySql = "SELECT * FROM shipments WHERE " . implode(' AND ', $whereClauses) . " ORDER BY {$shipmentDateColumn} DESC, id DESC";

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'shipment_history_' . $historyMonth . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Tracking No', 'Sender', 'Receiver', 'Origin', 'Destination', 'Weight (kg)', 'Price', 'Payment Method', 'Status', 'Booked Date', 'Proof Image']);

    $exportResult = $conn->query($historySql);
    if ($exportResult) {
        while ($row = $exportResult->fetch_assoc()) {
            $statusMeta = getHistoryStatusPresentation((string) ($row['status'] ?? ''));
            fputcsv($output, [
                getShipmentTrackingNo($row),
                $row['sender_name'] ?? '',
                $row['receiver_name'] ?? '',
                $row['origin_address'] ?? '',
                $row['destination_address'] ?? ($row['specific_address'] ?? ''),
                $row['weight'] ?? '',
                $row['price'] ?? '',
                strtoupper((string) ($row['payment_method'] ?? '')),
                $statusMeta['label'],
                !empty($row[$shipmentDateColumn]) ? date('Y-m-d H:i:s', strtotime($row[$shipmentDateColumn])) : '',
                $row['proof_image'] ?? '',
            ]);
        }
    }

    fclose($output);
    exit;
}

$result = $conn->query($historySql);

$username = $_SESSION['email'] ?? '';
$query = mysqli_query($conn, "SELECT * FROM accounts WHERE email = '$username'");
$user = mysqli_fetch_assoc($query);
$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 'user.png';
$csvExportUrl = 'shiphistory.php?export=csv&month=' . urlencode($historyMonth) . '&status=' . urlencode($statusFilter) . '&q=' . urlencode($searchTerm);
$selectedMonthLabel = date('F Y', strtotime($historyMonth . '-01'));

include("darkmode.php");
include('loading.html');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | Shipment History</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <style>
        :root {
            --bs-primary: #222831;
            --bs-primary-rgb: 34, 40, 49;
            --sidebar-width: 260px;
            --primary-color: #222831;
            --primary-hover: #393E46;
            --secondary-color: #dddad6ff;
            --text-main: #222831;
            --text-secondary: #393E46;
            --border-color: #948979;

            --dark-bg: #1c2027;
            --dark-card: #222831;
            --dark-border: #393E46;
            --dark-text-main: #DFD0B8;
            --dark-text-sec: #948979;

            --shadow-sm: 0 1px 2px 0 rgb(34 40 49 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(34 40 49 / 0.1), 0 2px 4px -2px rgb(34 40 49 / 0.1);
            --shadow: 0 0.15rem 1.75rem 0 rgb(34 40 49 / 0.15);
            --radius-md: 8px;
            --radius-lg: 12px;
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

        body:not(.dark-mode) .sidebar img[alt="Logo"] {
            filter: brightness(0);
        }

        /* Timeline CSS */
        .timeline-shell {
            background: linear-gradient(165deg, #0f172a 0%, #172554 48%, #020617 100%);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 20px;
            color: #e2e8f0;
            padding: 1.25rem;
        }

        .timeline-shell .text-muted {
            color: #94a3b8 !important;
        }

        .timeline-vertical {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
        }

        .timeline-vertical::before {
            content: "";
            position: absolute;
            left: 22px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, rgba(59, 130, 246, 0.9), rgba(16, 185, 129, 0.2));
        }

        .timeline-event {
            position: relative;
            padding-left: 68px;
            margin-bottom: 1.35rem;
        }

        .timeline-event:last-child {
            margin-bottom: 0;
        }

        .timeline-marker {
            position: absolute;
            left: 0;
            top: 0;
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.86);
            border: 1px solid rgba(148, 163, 184, 0.24);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.35);
        }

        .timeline-panel {
            background: rgba(15, 23, 42, 0.58);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 16px;
            padding: 0.95rem 1rem;
            backdrop-filter: blur(10px);
        }

        .timeline-status {
            color: #f8fafc;
            font-weight: 700;
        }

        .timeline-location {
            color: #93c5fd;
            font-size: 0.86rem;
            font-weight: 600;
        }

        .timeline-notes {
            color: #cbd5e1;
            font-size: 0.85rem;
        }

        .timeline-time {
            color: #94a3b8;
            font-size: 0.78rem;
            letter-spacing: 0.02em;
        }

        .timeline-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.14);
            border: 1px solid rgba(59, 130, 246, 0.22);
            color: #bfdbfe;
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
        }

        .timeline-meta-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 16px;
            padding: 1rem;
        }

        /* Dark Mode Overrides */
        body.dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text-main);
            --bs-card-bg: var(--dark-card);
            --bs-body-bg: var(--dark-bg);
            --bs-border-color: var(--dark-border);
            --bs-body-color: var(--dark-text-main);
        }

        body.dark-mode .sidebar {
            background: var(--dark-card);
            border-right: 1px solid var(--dark-border);
            color: var(--dark-text-main);
        }

        body.dark-mode .top-header {
            background-color: var(--dark-card) !important;
            color: var(--dark-text-main) !important;
            border: 1px solid var(--dark-border);
        }

        body.dark-mode .card {
            background-color: var(--dark-card) !important;
            color: var(--dark-text-main) !important;
            border: 1px solid var(--dark-border) !important;
        }

        body.dark-mode .text-muted {
            color: var(--dark-text-sec) !important;
        }

        body.dark-mode .modal-content {
            background-color: var(--dark-card);
            color: var(--dark-text-main);
            border: 1px solid var(--dark-border);
        }

        body.dark-mode .btn-close {
            filter: invert(1);
        }

        body.dark-mode table,
        body.dark-mode tbody tr,
        body.dark-mode td,
        body.dark-mode th {
            background-color: var(--dark-card) !important;
            color: var(--dark-text-main) !important;
            border-color: var(--dark-border) !important;
        }

        body.dark-mode .table {
            --bs-table-bg: var(--dark-card);
            --bs-table-color: var(--dark-text-main);
            --bs-table-border-color: var(--dark-border);
            --bs-table-hover-bg: var(--dark-bg);
            --bs-table-hover-color: var(--dark-text-main);
        }

        body.dark-mode .table-primary th {
            background-color: var(--dark-bg) !important;
            color: var(--dark-text-main) !important;
            border-bottom: 2px solid var(--dark-border) !important;
        }

        body.dark-mode td.text-primary {
            color: #87b0ff !important;
        }

        body.dark-mode table tbody tr:hover td {
            background-color: var(--dark-bg) !important;
        }

        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: var(--dark-bg);
            color: white;
            border-color: var(--dark-border);
        }

        body.dark-mode .bg-light {
            background-color: var(--dark-bg) !important;
            color: white;
            border-color: var(--dark-border) !important;
        }

        body.dark-mode .timeline-shell {
            background: linear-gradient(165deg, #020617 0%, #0f172a 55%, #111827 100%);
            border-color: rgba(71, 85, 105, 0.42);
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

        .clickable-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .clickable-row:hover {
            background-color: var(--secondary-color) !important;
        }
    </style>
</head>

<body>
    <div class="sidebar flex-shrink-0 p-3" id="sidebar">
        <div class="text-center mb-4 mt-2">
            <img src="Remorig.png" alt="Logo" class="img-fluid mb-2" style="max-width: 120px; transition: 0.3s ease;">
            <h6 class="fw-semibold text-uppercase text-muted mb-0 logo-title"
                style="letter-spacing: 1px; font-size: 0.75rem;">Core Transaction 3</h6>
        </div>
        <hr class="border-secondary opacity-25">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="user.php" class="nav-link"><i class="bi bi-grid-1x2"></i> <span
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
                <a href="shiphistory.php" class="nav-link active"><i class="bi bi-clock-history"></i> <span
                        class="sidebar-text">History</span></a>
            </li>
            <li class="nav-item">
                <a href="feedback.php" class="nav-link"><i class="bi bi-chat-square-text"></i> <span
                        class="sidebar-text">Feedback</span></a>
            </li>
        </ul>
    </div>

    <div class="content" id="mainContent">
        <header
            class="top-header d-flex align-items-center justify-content-between sticky-top mb-4 p-3 bg-white shadow-sm rounded-3">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
                <div>
                    <h5 class="fw-bold mb-0">Shipment History</h5>
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
                        <img src="<?php echo $profileImage ?? 'default-avatar.png'; ?>" alt="mdo" width="36" height="36"
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
                                    class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <div class="container-fluid px-4">
            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-lg-3">
                            <label class="form-label small text-uppercase text-muted fw-semibold">Search</label>
                            <input type="text" name="q" class="form-control"
                                value="<?php echo htmlspecialchars($searchTerm); ?>"
                                placeholder="Tracking, receiver, destination">
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label small text-uppercase text-muted fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>
                                    Pending</option>
                                <option value="In Transit" <?php echo $statusFilter === 'In Transit' ? 'selected' : ''; ?>>In Transit</option>
                                <option value="Delivered" <?php echo $statusFilter === 'Delivered' ? 'selected' : ''; ?>>
                                    Delivered</option>
                                <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>
                                    Cancelled</option>
                                <option value="Archived" <?php echo $statusFilter === 'Archived' ? 'selected' : ''; ?>>
                                    Archived</option>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label small text-uppercase text-muted fw-semibold">Booked Month</label>
                            <input type="month" name="month" class="form-control"
                                value="<?php echo htmlspecialchars($historyMonth); ?>">
                        </div>
                        <div class="col-lg-3">
                            <div class="d-flex gap-2 justify-content-lg-end">
                                <button type="submit" class="btn btn-primary"><i
                                        class="bi bi-funnel me-1"></i>Apply</button>
                                <a href="<?php echo htmlspecialchars($csvExportUrl); ?>"
                                    class="btn btn-outline-success">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                                </a>
                            </div>
                        </div>
                        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center pt-2 border-top">
                            <h5 class="fw-bold mb-0">Shipment Records</h5>
                            <small class="text-muted">Showing records for
                                <?php echo htmlspecialchars($selectedMonthLabel); ?></small>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>Tracking No</th>
                                    <th>Destination</th>
                                    <th>Weight (kg)</th>
                                    <th>Price (₱)</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Booked Date</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <?php
                                        $statusMeta = getHistoryStatusPresentation((string) ($row['status'] ?? ''));
                                        $trackingNo = getShipmentTrackingNo($row);
                                        $displayAddress = !empty($row['destination_address']) ? $row['destination_address'] : ($row['specific_address'] ?? 'N/A');
                                        ?>
                                        <tr class="clickable-row" onclick="openShipmentModal(<?php echo $row['id']; ?>)">
                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($trackingNo); ?></td>
                                            <td><?php echo htmlspecialchars(strlen($displayAddress) > 38 ? substr($displayAddress, 0, 38) . '...' : $displayAddress); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars((string) $row['weight']); ?></td>
                                            <td><?php echo number_format((float) $row['price'], 2); ?></td>
                                            <td><?php echo htmlspecialchars(strtoupper((string) $row['payment_method'])); ?>
                                            </td>
                                            <td><span
                                                    class="badge <?php echo $statusMeta['badge']; ?>"><?php echo htmlspecialchars($statusMeta['label']); ?></span>
                                            </td>
                                            <td><?php echo !empty($row[$shipmentDateColumn]) ? date("M d, Y", strtotime($row[$shipmentDateColumn])) : 'N/A'; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No shipments found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shipmentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Tracking Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <svg id="barcode"></svg>
                        <h4 class="mt-2 text-primary fw-bold" id="modalTrackingNo"></h4>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="card border bg-light shadow-sm mb-3">
                                <div class="card-header bg-white fw-bold text-secondary"><i
                                        class="bi bi-geo-alt me-1"></i> Route Information</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <small class="text-uppercase text-muted fw-bold" style="font-size:0.7rem;">From
                                            (Sender)</small>
                                        <div class="fw-bold fs-5" id="modalSender"></div>
                                        <div class="text-primary small mb-1">
                                            <i class="bi bi-telephone-fill me-1"></i>
                                            <a href="#" id="modalSenderContactLink"
                                                class="text-decoration-none fw-bold"></a>
                                        </div>
                                        <div class="p-2 bg-white border rounded small text-secondary" id="modalOrigin">
                                        </div>
                                    </div>
                                    <div class="text-center my-1"><i class="bi bi-arrow-down text-muted"></i></div>
                                    <div>
                                        <small class="text-uppercase text-muted fw-bold" style="font-size:0.7rem;">To
                                            (Receiver)</small>
                                        <div class="fw-bold fs-5" id="modalReceiver"></div>
                                        <div class="text-primary small mb-1">
                                            <i class="bi bi-telephone-fill me-1"></i>
                                            <a href="#" id="modalReceiverContactLink"
                                                class="text-decoration-none fw-bold"></a>
                                        </div>
                                        <div class="p-2 bg-white border rounded small text-secondary"
                                            id="modalDestination"></div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted fw-bold">Notes:</small>
                                        <span class="fst-italic small" id="modalSpecificAddress"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3 border shadow-sm">
                                <div class="card-header bg-white fw-bold text-secondary"><i class="bi bi-box me-1"></i>
                                    Package Info</div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                                        <span class="text-muted small">Type</span>
                                        <strong id="modalPkgType"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                                        <span class="text-muted small">Weight</span>
                                        <strong><span id="modalWeight"></span> kg</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted small">Description</span>
                                        <span class="text-end small" id="modalPkgDesc"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="card border shadow-sm">
                                <div class="card-header bg-white fw-bold text-secondary"><i
                                        class="bi bi-wallet2 me-1"></i> Payment Details</div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted small">Method</span>
                                        <strong class="text-uppercase" id="modalPaymentMethod"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">Total Amount</span>
                                        <h4 class="text-success fw-bold mb-0" id="modalPrice"></h4>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-3" id="actionButtonsContainer"></div>
                        </div>

                        <div class="col-lg-7">
                            <div class="timeline-shell h-100">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                                    <div>
                                        <span class="timeline-badge"><i class="fa-solid fa-satellite-dish"></i> Live
                                            Tracking Feed</span>
                                        <h5 class="fw-bold mt-3 mb-1 text-white">Shipment Lifecycle</h5>
                                        <p class="small text-muted mb-0">Latest event first. Every milestone shows a
                                            timestamp, status, and location.</p>
                                    </div>
                                    <div class="text-lg-end">
                                        <small class="text-uppercase text-muted d-block">Current Status</small>
                                        <span class="badge bg-light text-dark mt-1"
                                            id="modalCurrentStatus">Pending</span>
                                    </div>
                                </div>

                                <div class="timeline-meta-card mb-4">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <small class="text-uppercase text-muted fw-bold d-block mb-1">Shipment
                                                Ref</small>
                                            <div class="fw-semibold text-white" id="modalTrackingSummary"></div>
                                        </div>
                                        <div class="col-sm-6">
                                            <small class="text-uppercase text-muted fw-bold d-block mb-1">Booked
                                                Timestamp</small>
                                            <div class="fw-semibold text-white" id="modalBookedDate">Awaiting update
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <ul class="timeline-vertical" id="trackingTimelineList">
                                    <li class="timeline-event">
                                        <div class="timeline-marker"><i
                                                class="fa-solid fa-spinner fa-spin text-info"></i></div>
                                        <div class="timeline-panel">
                                            <div class="timeline-status">Loading timeline...</div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div id="ratingDisplaySection" style="display:none;" class="mt-4 border-top pt-3">
                        <h6 class="fw-bold mb-2" id="feedbackTitle">Feedback</h6>
                        <div id="modalStars" class="mb-2 text-warning fs-4"></div>
                        <div class="p-3 bg-light rounded border">
                            <span id="modalFeedbackText" class="fst-italic text-dark"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button class="btn btn-outline-dark btn-sm" onclick="openWaybill()"><i
                            class="bi bi-upc-scan me-1"></i>Generate Waybill</button>
                    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Cancel Shipment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure? Please tell us why:</p>
                    <input type="hidden" id="cancelShipmentId">
                    <select class="form-select mb-2" id="cancelReason" onchange="checkReason()">
                        <option value="" selected disabled>Select Reason...</option>
                        <option value="Changed mind">Changed mind</option>
                        <option value="Found cheaper option">Found cheaper option</option>
                        <option value="Others">Others</option>
                    </select>
                    <textarea class="form-control" id="cancelFeedback" rows="3" placeholder="Specify reason..."
                        style="display:none;"></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                    <button class="btn btn-danger" onclick="submitCancellation()">Confirm Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rateModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Rate Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <form id="ratingForm">
                        <input type="hidden" id="rateShipmentId" name="shipment_id">
                        <div class="mb-3 display-6">
                            <input type="radio" name="rating" value="5" id="r5"><label for="r5" class="mx-1"
                                style="cursor:pointer">5★</label>
                            <input type="radio" name="rating" value="4" id="r4"><label for="r4" class="mx-1"
                                style="cursor:pointer">4★</label>
                            <input type="radio" name="rating" value="3" id="r3"><label for="r3" class="mx-1"
                                style="cursor:pointer">3★</label>
                            <input type="radio" name="rating" value="2" id="r2"><label for="r2" class="mx-1"
                                style="cursor:pointer">2★</label>
                            <input type="radio" name="rating" value="1" id="r1"><label for="r1" class="mx-1"
                                style="cursor:pointer">1★</label>
                        </div>
                        <textarea class="form-control" name="feedback" placeholder="How was your experience?"
                            rows="3"></textarea>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary w-100" onclick="submitRating()">Submit Review</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        if (typeof initDarkMode === 'function') initDarkMode("userThemeToggle", "userDarkMode");

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

        let currentShipmentData = null;

        function statusPresentation(status) {
            const normalized = (status || '').toString().trim().toUpperCase().replace(/[\s-]+/g, '_');

            switch (normalized) {
                case 'PENDING':
                case 'BOOKED':
                    return { label: 'Pending', badge: 'bg-warning text-dark' };
                case 'READY_TO_DISPATCH':
                case 'CONSOLIDATED':
                case 'IN_TRANSIT':
                case 'ARRIVED':
                case 'OUT_FOR_DELIVERY':
                    return { label: 'In Transit', badge: 'bg-info text-dark' };
                case 'DELIVERED':
                    return { label: 'Delivered', badge: 'bg-success' };
                case 'CANCELLED':
                    return { label: 'Cancelled', badge: 'bg-danger' };
                case 'ARCHIVED':
                    return { label: 'Archived', badge: 'bg-secondary' };
                default:
                    return { label: status || 'Update', badge: 'bg-secondary' };
            }
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char]));
        }

        function renderTimeline(events) {
            const container = document.getElementById('trackingTimelineList');
            if (!Array.isArray(events) || events.length === 0) {
                container.innerHTML = `
                    <li class="timeline-event">
                        <div class="timeline-marker"><i class="fa-solid fa-circle-info text-info"></i></div>
                        <div class="timeline-panel">
                            <div class="timeline-status">No scan events yet</div>
                            <div class="timeline-notes mt-2">This shipment has not generated a timeline entry yet.</div>
                        </div>
                    </li>
                `;
                return;
            }

            container.innerHTML = events.map(event => {
                const variantClass = event.variant === 'danger'
                    ? 'text-danger'
                    : event.variant === 'warning'
                        ? 'text-warning'
                        : event.variant === 'success'
                            ? 'text-success'
                            : 'text-info';

                return `
                    <li class="timeline-event">
                        <div class="timeline-marker"><i class="${escapeHtml(event.icon || 'fa-solid fa-location-dot')} ${variantClass}"></i></div>
                        <div class="timeline-panel">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div class="timeline-status">${escapeHtml(event.status || 'Update')}</div>
                                <div class="timeline-time">${escapeHtml(event.time || '')}</div>
                            </div>
                            <div class="timeline-location mt-2">${escapeHtml(event.location || 'Shipment network')}</div>
                            <div class="timeline-notes mt-2">${escapeHtml(event.notes || event.desc || 'Status recorded.')}</div>
                        </div>
                    </li>
                `;
            }).join('');
        }

        function openShipmentModal(id) {
            const modal = new bootstrap.Modal(document.getElementById('shipmentDetailsModal'));

            currentShipmentData = null;
            document.getElementById('ratingDisplaySection').style.display = 'none';
            document.getElementById('actionButtonsContainer').innerHTML = '';
            document.getElementById('modalCurrentStatus').className = 'badge bg-light text-dark mt-1';
            document.getElementById('modalCurrentStatus').textContent = 'Loading...';
            document.getElementById('modalTrackingSummary').textContent = 'Loading...';
            document.getElementById('modalBookedDate').textContent = 'Loading...';
            document.getElementById('trackingTimelineList').innerHTML = `
                <li class="timeline-event">
                    <div class="timeline-marker"><i class="fa-solid fa-spinner fa-spin text-info"></i></div>
                    <div class="timeline-panel">
                        <div class="timeline-status">Loading timeline...</div>
                        <div class="timeline-notes mt-2">Fetching shipment events from the tracking log.</div>
                    </div>
                </li>
            `;
            modal.show();

            const fd = new FormData();
            fd.append('action', 'get_shipment_details');
            fd.append('id', id);

            fetch(window.location.href, {
                method: 'POST',
                body: fd
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        alert(res.message);
                        return;
                    }

                    const d = res.data;
                    currentShipmentData = d;
                    const trackingNo = res.tracking_no || ("TRK" + String(id).padStart(6, '0'));
                    const currentStatus = statusPresentation(d.status);

                    document.getElementById('modalTrackingNo').innerText = trackingNo;
                    document.getElementById('modalTrackingSummary').textContent = trackingNo;
                    document.getElementById('modalBookedDate').textContent = d.created_at ? new Date(d.created_at).toLocaleString() : 'Awaiting update';
                    document.getElementById('modalCurrentStatus').className = `badge ${currentStatus.badge} mt-1`;
                    document.getElementById('modalCurrentStatus').textContent = currentStatus.label;

                    JsBarcode("#barcode", trackingNo, {
                        format: "CODE128",
                        lineColor: "#0f172a",
                        width: 2,
                        height: 40,
                        displayValue: false
                    });

                    document.getElementById('modalSender').textContent = d.sender_name || 'Unknown sender';
                    document.getElementById('modalReceiver').textContent = d.receiver_name || 'Unknown receiver';

                    const sContact = d.sender_contact || "No Contact";
                    const sLink = document.getElementById('modalSenderContactLink');
                    sLink.textContent = sContact;
                    sLink.href = sContact !== "No Contact" ? "tel:" + sContact : "#";

                    const rContact = d.receiver_contact || "No Contact";
                    const rLink = document.getElementById('modalReceiverContactLink');
                    rLink.textContent = rContact;
                    rLink.href = rContact !== "No Contact" ? "tel:" + rContact : "#";

                    document.getElementById('modalOrigin').textContent = d.origin_address || 'Origin not available';
                    document.getElementById('modalDestination').textContent = d.destination_address || 'Destination not available';
                    document.getElementById('modalSpecificAddress').textContent = d.specific_address || d.address || 'No additional notes.';

                    document.getElementById('modalPkgType').textContent = (d.package_type || 'Package').toUpperCase();
                    document.getElementById('modalWeight').textContent = d.weight || '0';
                    document.getElementById('modalPkgDesc').textContent = d.package_description || d.package || '-';
                    document.getElementById('modalPaymentMethod').textContent = (d.payment_method || 'N/A').toUpperCase();
                    document.getElementById('modalPrice').textContent = "PHP " + parseFloat(d.price || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2
                    });

                    renderTimeline(res.timeline || []);

                    const normalized = (d.status || '').toString().toLowerCase().replace(/[\s-]+/g, '_');
                    let btns = '';
                    if (normalized === 'pending' || normalized === 'booked') {
                        btns += `<button onclick="openCancelModal(${d.id})" class="btn btn-outline-danger w-100">Cancel Shipment</button>`;
                    } else if (['ready_to_dispatch', 'consolidated', 'in_transit', 'arrived', 'out_for_delivery'].includes(normalized)) {
                        btns += `<button onclick="updateStatus(${d.id}, 'Delivered')" class="btn btn-success w-100">Mark as Received</button>`;
                    } else if (normalized === 'delivered' && (!d.rating || d.rating == 0)) {
                        btns += `<button onclick="openRateModal(${d.id})" class="btn btn-warning w-100 fw-bold">Rate Service</button>`;
                    }
                    btns += `<button onclick="openWaybill()" class="btn btn-dark w-100 mt-2">Generate Waybill</button>`;
                    document.getElementById('actionButtonsContainer').innerHTML = btns;

                    const section = document.getElementById('ratingDisplaySection');
                    const title = document.getElementById('feedbackTitle');
                    const stars = document.getElementById('modalStars');
                    const text = document.getElementById('modalFeedbackText');

                    if (normalized === 'cancelled') {
                        section.style.display = 'block';
                        title.innerHTML = '<span class="text-danger">Cancellation Reason</span>';
                        stars.innerHTML = '';
                        text.textContent = d.cancel_reason || d.feedback_text || 'No reason provided.';
                    } else if (d.rating > 0) {
                        section.style.display = 'block';
                        title.innerHTML = '<span class="text-warning">Your Rating</span>';
                        let starMarkup = '';
                        for (let i = 1; i <= 5; i++) {
                            starMarkup += i <= d.rating ? '&#9733;' : '&#9734;';
                        }
                        stars.innerHTML = starMarkup;
                        text.textContent = d.feedback_text || 'No comments.';
                    }
                })
                .catch(err => console.error(err));
        }

        function openCancelModal(id) {
            bootstrap.Modal.getInstance(document.getElementById('shipmentDetailsModal')).hide();
            document.getElementById('cancelShipmentId').value = id;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }

        function checkReason() {
            const reason = document.getElementById('cancelReason').value;
            document.getElementById('cancelFeedback').style.display = reason === 'Others' ? 'block' : 'none';
        }

        function submitCancellation() {
            const id = document.getElementById('cancelShipmentId').value;
            let reason = document.getElementById('cancelReason').value;
            if (reason === 'Others') reason = document.getElementById('cancelFeedback').value;
            if (!reason) {
                alert("Reason required");
                return;
            }
            updateStatus(id, 'Cancelled', reason);
        }

        async function updateStatus(id, status, reason = null) {
            if (status !== 'Cancelled' && !confirm("Are you sure you want to update this shipment status?")) return;

            const fd = new FormData();
            fd.append('id', id);
            fd.append('status', status);
            fd.append('action', 'update_status');
            if (reason) fd.append('reason', reason);

            await fetch('update_shipment_api.php', {
                method: 'POST',
                body: fd
            });
            location.reload();
        }

        function openRateModal(id) {
            bootstrap.Modal.getInstance(document.getElementById('shipmentDetailsModal')).hide();
            document.getElementById('rateShipmentId').value = id;
            new bootstrap.Modal(document.getElementById('rateModal')).show();
        }

        async function submitRating() {
            const form = document.getElementById('ratingForm');
            const fd = new FormData(form);
            fd.append('action', 'submit_rating');
            await fetch('update_shipment_api.php', {
                method: 'POST',
                body: fd
            });
            location.reload();
        }

        function openWaybill() {
            if (!currentShipmentData) return;
            window.open('generate_waybill.php?id=' + currentShipmentData.id, '_blank');
        }
    </script>
    <script>
        // AUTO-CHECK NOTIFICATIONS EVERY 5 SECONDS
        function fetchNotifications() {
            fetch('api/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notifBadge');
                    const list = document.getElementById('notifList');

                    // 1. Update Badge Count
                    if (data.count > 0) {
                        badge.innerText = data.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }

                    // 2. Update Dropdown List
                    let html = '';
                    if (data.data.length > 0) {
                        data.data.forEach(notif => {
                            // Check if read or unread styling
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

        // Mark as Read when clicked
        function markRead() {
            fetch('api/get_notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=read_all'
            }).then(() => {
                document.getElementById('notifBadge').style.display = 'none';
            });
        }

        // Initial Call + Interval
        fetchNotifications();
        setInterval(fetchNotifications, 5000); // Check every 5 seconds
    </script>
</body>

</html>