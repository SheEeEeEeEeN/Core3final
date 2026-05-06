<?php
// admin_reports.php - PREMIUM UI EDITIONS
include("connection.php");
include("darkmode.php");
include('session.php');
requireRole('admin');
include('loading.html');

// 1. FILTER DEFAULTS (First day of month to Now)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'All';

// 2. BUILD QUERY
$sql = "SELECT * FROM shipments WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'";
if ($statusFilter != 'All') {
    $sql .= " AND status = '$statusFilter'";
}
$sql .= " ORDER BY created_at DESC";
$result = $conn->query($sql);

// 3. COMPUTE TOTALS (PHP Side Calculation to avoid multiple queries)
$totalSales = 0;
$count = 0;
$paidCount = 0;
$avgOrderValue = 0;

// Need to iterate once to show in table, but also calculate totals.
// Store result in array to iterate twice.
$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
    $totalSales += $r['price'];
    $count++;
    if ($r['payment_status'] == 'Paid')
        $paidCount++;
}

if ($count > 0)
    $avgOrderValue = $totalSales / $count;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Center | Core Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            --shadow-md: 0 4px 6px -1px rgb(34 40 49 / 0.1);
            --radius-md: 8px;
            --radius-lg: 12px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-main);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

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
                padding: 15px;
            }

            .content.expanded {
                margin-left: 0;
            }

            .content.mobile-expanded {
                margin-left: var(--sidebar-width);
            }
        }

        .sidebar nav a,
        .sidebar nav div a {
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--text-secondary) !important;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            border-radius: var(--radius-md);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            white-space: nowrap;
            text-decoration: none;
            border: none;
        }

        .sidebar nav a:hover {
            color: var(--text-main) !important;
            background: var(--secondary-color) !important;
        }

        .sidebar nav a.active {
            color: #ffffff !important;
            background: var(--primary-color) !important;
            font-weight: 600;
        }

        .sidebar nav a i {
            font-size: 1.1rem;
            margin-right: 12px;
        }

        body:not(.dark-mode) .sidebar img[alt="Logo"] {
            filter: brightness(0);
        }

        body.dark-mode {
            background-color: var(--dark-bg) !important;
            color: var(--dark-text-main);
            --bs-card-bg: var(--dark-card) !important;
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

        body.dark-mode .card,
        body.dark-mode .ticket-card,
        body.dark-mode .modal-content {
            background-color: var(--dark-card) !important;
            color: var(--dark-text-main) !important;
            border: 1px solid var(--dark-border) !important;
        }

        body.dark-mode .text-primary {
            color: var(--dark-text-main) !important;
        }

        body.dark-mode .text-muted,
        body.dark-mode .text-secondary {
            color: var(--dark-text-sec) !important;
        }

        body.dark-mode .bg-light,
        body.dark-mode .bg-white {
            background-color: var(--dark-bg) !important;
            color: var(--dark-text-main) !important;
            border-color: var(--dark-border) !important;
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
            border-color: var(--dark-border) !important;
        }

        body.dark-mode .table thead th,
        body.dark-mode .table-light,
        body.dark-mode .table-light th {
            background-color: var(--dark-bg) !important;
            color: white !important;
            border-color: var(--dark-border) !important;
        }

        body.dark-mode .sidebar nav a {
            color: var(--dark-text-sec) !important;
        }

        body.dark-mode .sidebar nav a:hover {
            color: var(--dark-text-main) !important;
            background: var(--dark-border) !important;
        }

        body.dark-mode .sidebar nav a.active {
            color: var(--dark-bg) !important;
            background: var(--border-color) !important;
        }

        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: var(--dark-bg);
            color: white;
            border-color: var(--dark-border);
        }

        .stat-card {
            border: none;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .badge-soft-success {
            background-color: rgba(28, 200, 138, 0.15);
            color: #1cc88a;
        }

        .badge-soft-warning {
            background-color: rgba(246, 194, 62, 0.15);
            color: #f6c23e;
        }

        .badge-soft-danger {
            background-color: rgba(231, 74, 59, 0.15);
            color: #e74a3b;
        }

        .badge-soft-primary {
            background-color: rgba(78, 115, 223, 0.15);
            color: #4e73df;
        }

        .badge-soft-secondary {
            background-color: rgba(133, 135, 150, 0.15);
            color: #858796;
        }
    </style>
</head>

<body>
    <div class="sidebar flex-shrink-0 p-3" id="sidebar">
        <div class="text-center mb-4 mt-2">
            <img src="Remorig.png" alt="Logo" class="img-fluid mb-2" style="max-width: 120px; transition: 0.3s ease;">
            <h6 class="fw-semibold text-uppercase text-muted mb-0 logo-title"
                style="letter-spacing: 1px; font-size: 0.75rem;">CORE ADMIN</h6>
        </div>
        <hr class="border-secondary opacity-25">
        <nav class="nav flex-column mb-auto mt-2">
            <a href="admin.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="#crmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people"></i> CRM</span><i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse " id="crmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
                <a href="CRM.php" class="ps-5 "><i class="bi bi-dot"></i> Dashboard</a>
                <a href="customer_feedback.php" class="ps-5 "><i class="bi bi-dot"></i> Feedback</a>
            </div>
            <a href="#csmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-text"></i> Contract & SLA</span><i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse " id="csmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
                <a href="Admin_contracts.php" class="ps-5 "><i class="bi bi-dot"></i> Contracts</a>
                <a href="Admin_shipments.php" class="ps-5 "><i class="bi bi-dot"></i> SLA Monitor</a>
            </div>
            <a href="E-Doc.php" class=""><i class="bi bi-folder2-open"></i> E-Docs</a>
            <a href="admin_completed.php" class=""><i class="bi bi-check-circle-fill"></i> Completed Trans.</a>
            <a href="BIFA.php" class=""><i class="bi bi-graph-up"></i> BI & Analytics</a>
            <a href="admin_reports.php" class="active"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
            <a href="activity-log.php" class=""><i class="bi bi-clock-history"></i> Activity Log</a>

            <a href="#archiveSubmenu" data-bs-toggle="collapse"
                class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-archive"></i> Archives</span><i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse " id="archiveSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
                <a href="Archive.php" class="ps-5 "><i class="bi bi-dot"></i> Documents</a>
                <a href="Archive_CRM.php" class="ps-5 "><i class="bi bi-dot"></i> Customers</a>
            </div>

            <a href="logout.php" class="border-top mt-4 pt-4 text-danger"><i class="bi bi-box-arrow-right"></i>
                Logout</a>
        </nav>
    </div>

    <div class="content" id="mainContent">
        <header
            class="top-header d-flex align-items-center justify-content-between sticky-top mb-4 p-3 bg-white shadow-sm rounded-3">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
                <div>
                    <h5 class="fw-bold mb-0 text-primary">Financial Reports</h5>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
                    <label class="form-check-label text-muted" for="adminThemeToggle"><i
                            class="bi bi-moon-stars"></i></label>
                    <input class="form-check-input m-0" type="checkbox" role="switch" id="adminThemeToggle">
                </div>
                <div class="dropdown mx-1">
                    <a href="#" class="text-dark position-relative" id="notifDropdown" data-bs-toggle="dropdown"
                        onclick="markRead()">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center"
                            style="width:36px;height:36px;"><i class="bi bi-bell"></i></div>
                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm border border-white"
                            id="notifBadge" style="display: none; padding: 0.25em 0.5em; font-size: 0.65em;">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow p-0"
                        style="width: 320px; max-height: 480px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <li
                            class="p-3 border-bottom fw-bold bg-light d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                        </li>
                        <div id="notifList">
                            <li class="text-center p-4 text-muted small">Checking...</li>
                        </div>
                    </ul>
                </div>
                <div class="dropdown">
                    <a href="#" data-bs-toggle="dropdown" class="d-block link-dark text-decoration-none"
                        style="cursor: pointer;">
                        <img src="user.png" alt="Profile" width="36" height="36"
                            class="rounded-circle object-fit-cover border border-2 border-primary">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end text-small shadow"
                        style="border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <li>
                            <h6 class="dropdown-header">Admin Actions</h6>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i
                                    class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <!-- SUMMARY CARDS -->
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="stat-card card h-100 border-start border-4 border-primary">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Revenue (Filtered)</div>
                            <div class="h3 mb-0 fw-bold text-gray-800">₱<?php echo number_format($totalSales, 2); ?>
                            </div>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="stat-card card h-100 border-start border-4 border-success">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Shipments</div>
                            <div class="h3 mb-0 fw-bold text-gray-800"><?php echo number_format($count); ?></div>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="stat-card card h-100 border-start border-4 border-info">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">Avg. Order Value</div>
                            <div class="h3 mb-0 fw-bold text-gray-800">₱<?php echo number_format($avgOrderValue, 2); ?>
                            </div>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER SECTION -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label text-muted small text-uppercase">From Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>"
                            required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small text-uppercase">To Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>"
                            required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small text-uppercase">Shipment Status</label>
                        <select name="status" class="form-select">
                            <option value="All" <?php if ($statusFilter == 'All')
                                echo 'selected'; ?>>All Status</option>
                            <option value="Delivered" <?php if ($statusFilter == 'Delivered')
                                echo 'selected'; ?>>Delivered
                            </option>
                            <option value="In Transit" <?php if ($statusFilter == 'In Transit')
                                echo 'selected'; ?>>In
                                Transit</option>
                            <option value="Pending" <?php if ($statusFilter == 'Pending')
                                echo 'selected'; ?>>Pending
                            </option>
                            <option value="Cancelled" <?php if ($statusFilter == 'Cancelled')
                                echo 'selected'; ?>>Cancelled
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            <i class="bi bi-funnel-fill me-1"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- REPORT TABLE -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                <h6 class="m-0 fw-bold text-primary"><i class="bi bi-table me-2"></i>Detailed Transaction Report</h6>
                <a href="print_report.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&status=<?php echo $statusFilter; ?>"
                    target="_blank" class="btn btn-sm btn-success fw-bold shadow-sm">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Export PDF
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Tracking ID</th>
                            <th>Date Created</th>
                            <th>Details (Sender / Dest)</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th class="text-end pe-4">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) > 0): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace text-primary">
                                        TRK-<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span
                                                class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                                            <small
                                                class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="text-dark fw-bold"><?php echo $row['sender_name']; ?></span>
                                            <small class="text-muted"><i
                                                    class="bi bi-geo-alt me-1"></i><?php echo $row['destination_island']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $s = $row['status'];
                                        $badgeClass = 'badge-soft-secondary';
                                        if ($s == 'Delivered')
                                            $badgeClass = 'badge-soft-success';
                                        if ($s == 'Cancelled')
                                            $badgeClass = 'badge-soft-danger';
                                        if ($s == 'Pending')
                                            $badgeClass = 'badge-soft-warning';
                                        if ($s == 'In Transit')
                                            $badgeClass = 'badge-soft-primary';
                                        echo "<span class='badge $badgeClass px-3 py-2 rounded-pill fw-bold'>$s</span>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $p = $row['payment_status'] ?? 'Pending';
                                        echo ($p == 'Paid') ? '<span class="text-success fw-bold small"><i class="bi bi-check-circle-fill"></i> Paid</span>' : '<span class="text-secondary small">Unpaid</span>';
                                        ?>
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-dark">
                                        ₱<?php echo number_format($row['price'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                                    No records found for this period.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (count($rows) > 0): ?>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="5" class="text-end fw-bold text-uppercase py-3 pe-3">Total Sales Period:</td>
                                <td class="text-end fw-bold text-success fs-5 py-3 pe-4">
                                    ₱<?php echo number_format($totalSales, 2); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // DARK MODE INIT
        if (typeof initDarkMode === 'function') {
            initDarkMode("adminThemeToggle", "adminDarkMode");
        } else {
            // Fallback if darkmode.php func not loaded
            const toggle = document.getElementById('adminThemeToggle');
            if (localStorage.getItem('adminDarkMode') === 'enabled') {
                document.body.classList.add('dark-mode');
                toggle.checked = true;
            }
            toggle.addEventListener('change', () => {
                if (toggle.checked) {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('adminDarkMode', 'enabled');
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('adminDarkMode', 'disabled');
                }
            });
        }

        document.getElementById('hamburger').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });

        // NOTIFICATIONS
        function fetchNotifications() {
            fetch('api/get_notifications.php')
                .then(r => r.json())
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
                        data.data.forEach(n => {
                            let bg = n.is_read == 0 ? 'bg-light' : '';
                            html += `<li class="border-bottom ${bg} p-2"><a class="dropdown-item small text-wrap" href="#"><b>${n.title}</b><br>${n.message}</a></li>`;
                        });
                    } else {
                        html = '<li class="text-center p-3 text-muted small">No new notifications</li>';
                    }
                    list.innerHTML = html;
                });
        }
        function markRead() {
            fetch('api/get_notifications.php', { method: 'POST', body: 'action=read_all', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })
                .then(() => { document.getElementById('notifBadge').style.display = 'none'; });
        }
        fetchNotifications();
        setInterval(fetchNotifications, 5000);
    </script>
</body>

</html>