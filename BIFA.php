<?php
include("connection.php");
include("darkmode.php");
include('session.php');
requireRole('admin');
include('loading.html');

// ==========================================================
// 1. DATA FETCHING
// ==========================================================

// A. KPI TOTALS (Dito lang tayo magfi-filter para sa Revenue, pero sa SLA Chart isasama natin lahat)
$totals = $conn->query("SELECT COUNT(*) as vol, COALESCE(SUM(price),0) as rev FROM shipments WHERE status != 'Cancelled'")->fetch_assoc();
$totalVolume = $totals['vol'];
$totalRevenue = $totals['rev'];

// ------------------------------------------------------------------
// B. SLA PERFORMANCE (UPDATED: EXACT MATCH SA ADMIN_SHIPMENTS.PHP)
// ------------------------------------------------------------------
$slaMet = 0;
$slaBreached = 0;

// 1. Kunin ang Rules (Master Template)
$rulesArr = [];
$rQ = $conn->query("SELECT * FROM sla_policies WHERE contract_id = 0");
while ($r = $rQ->fetch_assoc()) {
    $rulesArr[$r['origin_group']][$r['destination_group']] = $r['max_days'];
}

// 2. QUERY NA WALANG FILTER (Gaya ng admin_shipments.php - SELECT ALL)
$query = "SELECT * FROM shipments ORDER BY created_at DESC";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    // BES, TINANGGAL KO NA YUNG 'CONTINUE IF CANCELLED'.
    // Ngayon, bibilangin na niya lahat para mag-match sa Admin Page.

    $origin = $row['origin_island'] ?? 'Metro Manila';
    $dest = $row['destination_island'] ?? 'Visayas';
    $days = $rulesArr[$origin][$dest] ?? 7;

    $created = strtotime($row['created_at']);
    $target = strtotime("+$days days", $created);
    $now = time();

    $is_delivered = ($row['status'] == 'Delivered');

    // Logic galing admin_shipments.php
    // Kung Cancelled siya, hindi siya delivered, so gagamitin niya ang NOW().
    // Dahil luma na ang date, magiging > Target siya, so bibilangin siyang BREACHED/DELAYED.
    $actual_end = $is_delivered ? strtotime($row['updated_at'] ?? $row['created_at']) : $now;

    if ($actual_end > $target) {
        $slaBreached++; // BREACHED / DELAYED
    } else {
        $slaMet++; // ON TRACK / MET
    }
}

// Compute Percentage
$totalEvaluated = $slaMet + $slaBreached;
$onTimeRate = ($totalEvaluated > 0) ? round(($slaMet / $totalEvaluated) * 100, 1) : 100;
// ------------------------------------------------------------------


// C. REVENUE & VOLUME TREND
$months = [];
$revData = [];
$volData = [];
for ($i = 5; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("-$i months"));
    $mEnd = date('Y-m-t', strtotime("-$i months"));
    $months[] = date('M', strtotime("-$i months"));

    $q = "SELECT SUM(price) as rev, COUNT(*) as vol 
          FROM shipments 
          WHERE created_at BETWEEN '$mStart 00:00:00' AND '$mEnd 23:59:59' 
          AND status != 'Cancelled'";
    $res = $conn->query($q)->fetch_assoc();
    $revData[] = $res['rev'] ?? 0;
    $volData[] = $res['vol'] ?? 0;
}

// D. TOP DESTINATIONS
$locLabels = [];
$locCounts = [];
$locColors = [];
$topRegion = "None";
$topPercent = 0;

$locSql = "SELECT destination_island, COUNT(*) as c 
           FROM shipments 
           WHERE destination_island IS NOT NULL AND destination_island != ''
           GROUP BY destination_island 
           ORDER BY c DESC";
$locQ = $conn->query($locSql);

$islandStats = [];

while ($r = $locQ->fetch_assoc()) {
    $label = $r['destination_island'];
    $count = $r['c'];
    $percent = ($totalVolume > 0) ? round(($count / $totalVolume) * 100, 1) : 0;

    $color = '#858796';
    if ($label == 'Luzon')
        $color = '#4e73df';
    if ($label == 'Visayas')
        $color = '#f6c23e';
    if ($label == 'Mindanao')
        $color = '#e74a3b';

    if ($count > 0 && empty($locLabels)) {
        $topRegion = $label;
        $topPercent = $percent;
    }

    $locLabels[] = $label;
    $locCounts[] = $count;
    $locColors[] = $color;

    $islandStats[] = [
        'name' => $label,
        'count' => $count,
        'percent' => $percent,
        'color' => $color
    ];
}

// E. SHIPMENT STATUS
$statPending = $conn->query("SELECT COUNT(*) FROM shipments WHERE status='Pending'")->fetch_row()[0];
$statTransit = $conn->query("SELECT COUNT(*) FROM shipments WHERE status='In Transit'")->fetch_row()[0];
$statDelivered = $conn->query("SELECT COUNT(*) FROM shipments WHERE status='Delivered'")->fetch_row()[0];
$statCancelled = $conn->query("SELECT COUNT(*) FROM shipments WHERE status='Cancelled'")->fetch_row()[0];
$statusData = [$statPending, $statTransit, $statDelivered, $statCancelled];

// F. CANCELLATION ANALYSIS (NEW)
$cancelLabels = [];
$cancelCounts = [];
$cQ = $conn->query("SELECT cancel_reason, COUNT(*) as c FROM shipments WHERE status='Cancelled' AND cancel_reason IS NOT NULL AND cancel_reason != '' GROUP BY cancel_reason ORDER BY c DESC");
while ($row = $cQ->fetch_assoc()) {
    $cancelLabels[] = $row['cancel_reason'];
    $cancelCounts[] = $row['c'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Freight Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>

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

        .lock-screen {
            max-width: 400px;
            margin: 5rem auto;
            text-align: center;
        }

        .lock-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        body.dark-mode .lock-screen .card {
            border: 1px solid var(--dark-border) !important;
            background-color: var(--dark-card) !important;
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
            <a href="BIFA.php" class="active"><i class="bi bi-graph-up"></i> BI & Analytics</a>
            <a href="admin_reports.php" class=""><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
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
                    <h5 class="fw-bold mb-0 text-primary">Freight Analytics</h5>
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
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card h-100">
                    <h5 class="fw-bold mb-3"><i class="bi bi-graph-up-arrow"></i> Revenue & Volume (Last 6 Months)</h5>
                    <div style="height: 300px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart-fill"></i> Shipment Status</h5>
                    <div style="height: 300px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">

            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-geo-alt-fill text-danger"></i> Top Destinations</h5>
                    </div>

                    <div class="row">
                        <div class="col-md-7">
                            <div style="height: 250px;">
                                <canvas id="locChart"></canvas>
                            </div>
                        </div>

                        <div class="col-md-5 d-flex flex-column justify-content-center">
                            <?php if ($topRegion != 'None'): ?>
                                <div class="alert alert-light border-start border-4 border-primary shadow-sm p-2 mb-3">
                                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Dominant
                                        Region</small>
                                    <div class="d-flex align-items-center gap-2">
                                        <h5 class="mb-0 fw-bold text-dark"><?php echo $topRegion; ?></h5>
                                        <span class="badge bg-primary rounded-pill"><?php echo $topPercent; ?>% share</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <ul class="list-group list-group-flush small">
                                <?php if (!empty($islandStats)):
                                    foreach ($islandStats as $stat): ?>
                                        <li
                                            class="list-group-item d-flex align-items-center justify-content-between px-0 border-0 pb-1">
                                            <div class="d-flex align-items-center gap-2" style="width: 50%;">
                                                <span class="rounded-circle"
                                                    style="width:10px; height:10px; background-color: <?php echo $stat['color']; ?>;"></span>
                                                <span class="fw-semibold"><?php echo $stat['name']; ?></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="text-muted"><?php echo $stat['count']; ?> ship</span>
                                                <span class="fw-bold"><?php echo $stat['percent']; ?>%</span>
                                            </div>
                                        </li>
                                        <div class="progress progress-thin mb-2 bg-light">
                                            <div class="progress-bar"
                                                style="width: <?php echo $stat['percent']; ?>%; background-color: <?php echo $stat['color']; ?>;">
                                            </div>
                                        </div>
                                    <?php endforeach; else: ?>
                                    <div class="text-center text-muted">No data available</div>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card h-100">
                    <h5 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> On-Time Performance</h5>
                    <div style="height: 200px;">
                        <canvas id="slaChart"></canvas>
                    </div>
                    <div class="mt-4 text-center">
                        <div class="d-flex justify-content-center gap-4">
                            <div class="text-center">
                                <h4 class="mb-0 fw-bold text-success"><?php echo $slaMet; ?></h4>
                                <small class="text-muted">On Time</small>
                            </div>
                            <div class="border-end"></div>
                            <div class="text-center">
                                <h4 class="mb-0 fw-bold text-secondary"><?php echo $slaBreached; ?></h4>
                                <small class="text-muted">Late</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            initDarkMode("adminThemeToggle", "adminDarkMode");
            document.getElementById('hamburger').addEventListener('click', () => {
                document.getElementById('sidebar').classList.toggle('collapsed');
                document.getElementById('mainContent').classList.toggle('expanded');
            });

            // DATA INJECTION
            const months = <?php echo json_encode($months); ?>;
            const revData = <?php echo json_encode($revData); ?>;
            const volData = <?php echo json_encode($volData); ?>;
            const locLabels = <?php echo json_encode($locLabels); ?>;
            const locData = <?php echo json_encode($locCounts); ?>;
            const locColors = <?php echo json_encode($locColors); ?>;
            const statData = <?php echo json_encode($statusData); ?>;

            // UPDATED SLA DATA FOR PIE CHART
            const slaData = [<?php echo $slaMet; ?>, <?php echo $slaBreached; ?>];

            // --- MODERN CHART CONFIG ---
            Chart.defaults.global.defaultFontFamily = "'Segoe UI', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
            Chart.defaults.global.defaultFontColor = '#858796';

            const MODERN_COLORS = {
                primary: '#4e73df',
                success: '#1cc88a',
                info: '#36b9cc',
                warning: '#f6c23e',
                danger: '#e74a3b',
                secondary: '#858796',
                light: '#f8f9fc',
                dark: '#5a5c69'
            };

            // Common Options for Clean Look
            const commonOptions = {
                maintainAspectRatio: false,
                layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                scales: {
                    xAxes: [{
                        gridLines: { display: false, drawBorder: false },
                        ticks: { maxTicksLimit: 7 }
                    }],
                    yAxes: [{
                        ticks: { maxTicksLimit: 5, padding: 10, callback: function (value) { return value; } },
                        gridLines: { color: "rgb(234, 236, 244)", zeroLineColor: "rgb(234, 236, 244)", drawBorder: false, borderDash: [2], zeroLineBorderDash: [2] }
                    }],
                },
                legend: { display: false },
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    titleMarginBottom: 10,
                    titleFontColor: '#6e707e',
                    titleFontSize: 14,
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    intersect: false,
                    mode: 'index',
                    caretPadding: 10,
                }
            };

            // 1. TREND CHART (Area Style)
            var ctxTrend = document.getElementById("trendChart").getContext('2d');
            var gradientRev = ctxTrend.createLinearGradient(0, 0, 0, 400);
            gradientRev.addColorStop(0, 'rgba(78, 115, 223, 0.5)'); // Primary fade
            gradientRev.addColorStop(1, 'rgba(78, 115, 223, 0.05)');

            new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: "Revenue (₱)",
                            lineTension: 0.3,
                            backgroundColor: gradientRev,
                            borderColor: MODERN_COLORS.primary,
                            pointRadius: 3,
                            pointBackgroundColor: MODERN_COLORS.primary,
                            pointBorderColor: MODERN_COLORS.primary,
                            pointHoverRadius: 3,
                            pointHoverBackgroundColor: MODERN_COLORS.primary,
                            pointHoverBorderColor: MODERN_COLORS.primary,
                            pointHitRadius: 10,
                            pointBorderWidth: 2,
                            data: revData,
                            yAxisID: 'y-axis-1'
                        },
                        {
                            label: "Volume",
                            type: 'bar',
                            backgroundColor: MODERN_COLORS.info,
                            hoverBackgroundColor: "#2c9faf",
                            data: volData,
                            yAxisID: 'y-axis-2',
                            barThickness: 20
                        }
                    ]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        xAxes: commonOptions.scales.xAxes,
                        yAxes: [
                            { id: 'y-axis-1', type: 'linear', position: 'left', ticks: { beginAtZero: true, callback: function (value) { return '₱' + value; } }, gridLines: commonOptions.scales.yAxes[0].gridLines },
                            { id: 'y-axis-2', type: 'linear', position: 'right', ticks: { beginAtZero: true }, gridLines: { display: false } }
                        ]
                    }
                }
            });

            // 2. STATUS CHART (Doughnut)
            new Chart("statusChart", {
                type: 'doughnut',
                data: {
                    labels: ["Pending", "In Transit", "Delivered", "Cancelled"],
                    datasets: [{
                        data: statData,
                        backgroundColor: [MODERN_COLORS.warning, MODERN_COLORS.info, MODERN_COLORS.success, MODERN_COLORS.danger],
                        hoverBackgroundColor: [MODERN_COLORS.warning, MODERN_COLORS.info, MODERN_COLORS.success, MODERN_COLORS.danger],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    tooltips: { ...commonOptions.tooltips, mode: 'nearest' },
                    legend: { display: true, position: 'bottom', labels: { usePointStyle: true } },
                    cutoutPercentage: 75,
                },
            });

            // 3. TOP DESTINATIONS (Horizontal Bar)
            new Chart("locChart", {
                type: 'horizontalBar',
                data: {
                    labels: locLabels,
                    datasets: [{
                        label: "Shipments",
                        backgroundColor: locColors, // Kept dynamic colors
                        hoverBackgroundColor: locColors,
                        borderColor: "#fff",
                        data: locData,
                        barThickness: 20
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                    scales: {
                        xAxes: [{ ticks: { beginAtZero: true }, gridLines: { display: false, drawBorder: false } }],
                        yAxes: [{ gridLines: { display: false, drawBorder: false }, ticks: { mirror: true, padding: -10, fontColor: '#5a5c69', fontStyle: 'bold' } }] // Mirror labels inside
                    },
                    legend: { display: false },
                    tooltips: commonOptions.tooltips
                },
            });

            // 4. SLA CHART (Pie)
            new Chart("slaChart", {
                type: 'pie',
                data: {
                    labels: ["On Time", "Late"],
                    datasets: [{
                        data: slaData,
                        backgroundColor: [MODERN_COLORS.success, MODERN_COLORS.danger],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    tooltips: { ...commonOptions.tooltips, mode: 'nearest' },
                    legend: { display: false }, // Custom HTML legend used
                },
            });

            // 5. CANCELLATION REASON CHART (Gradient Bar)
            var ctxCancel = document.getElementById("cancelChart").getContext('2d');
            var gradientCancel = ctxCancel.createLinearGradient(0, 0, 0, 400);
            gradientCancel.addColorStop(0, '#e74a3b');
            gradientCancel.addColorStop(1, '#be2617');

            new Chart(ctxCancel, {
                type: 'bar',
                data: {
                    labels: cancelLabels,
                    datasets: [{
                        label: "Cancellations",
                        backgroundColor: gradientCancel,
                        hoverBackgroundColor: "#be2617",
                        borderColor: "#fff",
                        data: cancelCounts,
                        barPercentage: 0.6
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                    scales: {
                        xAxes: [{ gridLines: { display: false, drawBorder: false }, ticks: { maxTicksLimit: 6 } }],
                        yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: commonOptions.scales.yAxes[0].gridLines }]
                    },
                    legend: { display: false },
                    tooltips: commonOptions.tooltips
                },
            });
        </script>

        <script>
            // --- GLOBAL NOTIFICATION SCRIPT ---
            function fetchNotifications() {
                // Siguraduhing tama ang path ng API mo relative sa file location
                // Kung nasa root folder ka (gaya ng user.php), gamitin ang 'api/get_notifications.php'
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
                                let bgClass = notif.is_read == 0 ? 'bg-light' : '';
                                let icon = notif.is_read == 0 ? 'bi-circle-fill text-primary' : 'bi-check-circle text-muted';

                                // Adjust link if needed based on user role
                                let link = notif.link ? notif.link : '#';

                                html += `
                    <li>
                        <a class="dropdown-item ${bgClass} p-2 border-bottom" href="${link}">
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
                            html = '<li class="text-center p-3 text-muted small">No new notifications</li>';
                        }
                        list.innerHTML = html;
                    })
                    .catch(err => console.error("Notif Error:", err));
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

            // Run immediately and every 5 seconds
            fetchNotifications();
            setInterval(fetchNotifications, 5000);
        </script>
</body>

</html>