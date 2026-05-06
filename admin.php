<?php
// C:\xampp\htdocs\last\admin.php
include("connection.php");
include("darkmode.php");
include('session.php');
requireRole('admin');
include('loading.html');

// HELPER FUNCTION: Time Elapsed (e.g., "2 hours ago")
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'yr', 'm' => 'mon', 'w' => 'wk',
        'd' => 'day', 'h' => 'hr', 'i' => 'min', 's' => 'sec',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// =========================================================
// 1. DATA FETCHING (DATABASE CONNECTIONS)
// =========================================================

// CARD 1: Total Revenue (Delivered Shipments)
$sql = "SELECT SUM(price) as total FROM shipments WHERE status = 'Delivered'";
$revData = $conn->query($sql)->fetch_assoc();
$totalRevenue = $revData['total'] ? $revData['total'] : 0;

// CARD 2: Active Shipments
$sql = "SELECT COUNT(*) as total FROM shipments WHERE status IN ('Pending', 'In Transit', 'Out for Delivery', 'Processing')";
$activeShipments = $conn->query($sql)->fetch_assoc()['total'];

// CARD 3: Pending Request
$sql = "SELECT COUNT(*) as total FROM shipments WHERE status = 'Pending'";
$pendingCount = $conn->query($sql)->fetch_assoc()['total'];

// CARD 4: SLA Performance (ALIGNED with admin_shipments.php)
// 1. Fetch Shipments
$qSLA = $conn->query("SELECT * FROM shipments");
$total_shipments_sla = 0; 
$total_ontime_sla = 0;

// 2. Fetch Rules
$rulesArr = [];
$rQ = $conn->query("SELECT * FROM sla_policies WHERE contract_id = 0"); 
while($r = $rQ->fetch_assoc()) {
    $rulesArr[$r['origin_group']][$r['destination_group']] = $r['max_days'];
}

// 3. Calculate Real-Time Status
while($row = $qSLA->fetch_assoc()){
    $total_shipments_sla++;
    $origin = $row['origin_island'] ?? 'Luzon';
    $dest = $row['destination_island'] ?? 'Visayas';
    $sla_days = $rulesArr[$origin][$dest] ?? 7; 
    
    $book_date = strtotime($row['created_at']);
    $target_date = strtotime("+$sla_days days", $book_date);
    $is_delivered = ($row['status'] == 'Delivered');
    $actual_end = $is_delivered ? strtotime($row['updated_at'] ?? $row['created_at']) : time();

    if ($actual_end <= $target_date) {
        $total_ontime_sla++;
    }
}

// 4. Compute Percentage
$slaRate = ($total_shipments_sla > 0) ? round(($total_ontime_sla / $total_shipments_sla) * 100, 1) : 100;

// CHART 1: Revenue & Volume Trend (Last 7 Days)
$chartLabels = [];
$chartRevenue = [];
$chartVolume = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime($date));
    
    // Daily Revenue (Delivered)
    $qRev = "SELECT SUM(price) as t FROM shipments WHERE DATE(created_at) = '$date' AND status = 'Delivered'";
    $rRev = $conn->query($qRev)->fetch_assoc();
    $chartRevenue[] = $rRev['t'] ? $rRev['t'] : 0;

    // Daily Volume
    $qVol = "SELECT COUNT(*) as c FROM shipments WHERE DATE(created_at) = '$date'";
    $rVol = $conn->query($qVol)->fetch_assoc();
    $chartVolume[] = $rVol['c'] ? $rVol['c'] : 0;
}

// CHART 2: Shipment Status Distribution
$statusCounts = [0, 0, 0, 0]; // Pending, In Transit, Delivered, Cancelled
$qStat = "SELECT status, COUNT(*) as c FROM shipments GROUP BY status";
$rStat = $conn->query($qStat);
while($row = $rStat->fetch_assoc()){
    if($row['status'] == 'Pending') $statusCounts[0] = $row['c'];
    if($row['status'] == 'In Transit' || $row['status'] == 'Out for Delivery') $statusCounts[1] += $row['c'];
    if($row['status'] == 'Delivered') $statusCounts[2] = $row['c'];
    if($row['status'] == 'Cancelled') $statusCounts[3] = $row['c'];
}

// TABLE: Recent Shipments (Limit 5)
$recShip = $conn->query("SELECT id, user_id, destination_address, status, price, created_at 
                          FROM shipments ORDER BY created_at DESC LIMIT 5");

// FEEDBACK: Latest Reviews
$feedbacks = $conn->query("SELECT s.rating, s.feedback_text, s.created_at, a.username 
                           FROM shipments s 
                           JOIN accounts a ON s.user_id = a.id 
                           WHERE s.rating > 0 
                           ORDER BY s.created_at DESC LIMIT 4");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Logistics Core</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
  
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

            --dark-bg: #1c2027;
            --dark-card: #222831;
            --dark-border: #393E46;
            --dark-text-main: #DFD0B8;
            --dark-text-sec: #948979;

            --shadow-sm: 0 1px 2px 0 rgb(34 40 49 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(34 40 49 / 0.1), 0 2px 4px -2px rgb(34 40 49 / 0.1);
            --radius-md: 8px;
            --radius-lg: 12px;
        }

        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--secondary-color); color: var(--text-main); overflow-x: hidden; -webkit-font-smoothing: antialiased; }

        /* SIDEBAR */
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; left: 0; top: 0; background: #ffffff; color: var(--text-main); z-index: 1040; transition: all 0.3s ease; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .content { margin-left: var(--sidebar-width); padding: 24px; transition: all 0.3s ease; min-height: 100vh; }
        .sidebar.collapsed { margin-left: calc(var(--sidebar-width) * -1); }
        .content.expanded { margin-left: 0; }

        @media (max-width: 768px) {
            .sidebar { margin-left: calc(var(--sidebar-width) * -1); }
            .sidebar.show { margin-left: 0; }
            .content { margin-left: 0; padding: 15px; }
            .content.expanded { margin-left: 0; }
            .content.mobile-expanded { margin-left: var(--sidebar-width); }
        }

        .sidebar nav a, .sidebar nav div a { font-weight: 500; font-size: 0.95rem; color: var(--text-secondary) !important; transition: all 0.2s ease; margin-bottom: 4px; border-radius: var(--radius-md); padding: 10px 16px; display: flex; align-items: center; white-space: nowrap; text-decoration: none; border: none; }
        .sidebar nav a:hover { color: var(--text-main) !important; background: var(--secondary-color) !important; }
        .sidebar nav a.active { color: #ffffff !important; background: var(--primary-color) !important; font-weight: 600; }
        .sidebar nav a i { font-size: 1.1rem; margin-right: 12px; }
        
        /* Table Styling Overrides */
        .table-responsive { overflow-x: auto; }
        .text-xxs { font-size: 0.75rem !important; }

        body:not(.dark-mode) .sidebar img[alt="Logo"] { filter: brightness(0); }

        /* Components */
        .card { border: none; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); }
        .card-header { border-bottom: 1px solid var(--border-color) !important; }

        /* Dark Mode Overrides */
        body.dark-mode { background-color: var(--dark-bg); color: var(--dark-text-main); --bs-card-bg: var(--dark-card); --bs-body-bg: var(--dark-bg); --bs-border-color: var(--dark-border); --bs-body-color: var(--dark-text-main); }
        body.dark-mode .sidebar { background: var(--dark-card); border-right: 1px solid var(--dark-border); color: var(--dark-text-main); }
        body.dark-mode .top-header { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border: 1px solid var(--dark-border); }
        body.dark-mode .card,
        body.dark-mode .list-group-item { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border: 1px solid var(--dark-border) !important; border-bottom: 1px solid var(--dark-border) !important; }
        body.dark-mode .text-muted, body.dark-mode .text-secondary { color: var(--dark-text-sec) !important; }
        body.dark-mode .bg-light { background-color: var(--dark-bg) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }

        body.dark-mode table, body.dark-mode tbody tr, body.dark-mode td, body.dark-mode th { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }
        body.dark-mode .table { --bs-table-bg: var(--dark-card); --bs-table-color: var(--dark-text-main); --bs-table-border-color: var(--dark-border); }
        body.dark-mode .table thead th { background: var(--dark-bg) !important; color: white !important; border: none; }
        body.dark-mode .text-dark { color: var(--dark-text-main) !important; }

        body.dark-mode .sidebar nav a { color: var(--dark-text-sec) !important; }
        body.dark-mode .sidebar nav a:hover { color: var(--dark-text-main) !important; background: var(--dark-border) !important; }
        body.dark-mode .sidebar nav a.active { color: var(--dark-bg) !important; background: var(--border-color) !important; }
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
        <a href="admin.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        
        <a href="#crmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people"></i> CRM</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="crmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
            <a href="CRM.php" class="ps-5"><i class="bi bi-dot"></i> Dashboard</a>
            <a href="customer_feedback.php" class="ps-5"><i class="bi bi-dot"></i> Feedback</a>
        </div>

        <a href="#csmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-file-text"></i> Contract & SLA</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="csmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
            <a href="admin_contracts.php" class="ps-5"><i class="bi bi-dot"></i> Contracts</a>
            <a href="admin_shipments.php" class="ps-5"><i class="bi bi-dot"></i> SLA Monitor</a>
        </div>

        <a href="E-Doc.php"><i class="bi bi-folder2-open"></i> E-Docs</a>
        <a href="admin_completed.php"><i class="bi bi-check-circle-fill"></i> Completed Trans.</a>
        <a href="BIFA.php"><i class="bi bi-graph-up"></i> BI & Analytics</a>
        <a href="admin_reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
        <a href="activity-log.php"><i class="bi bi-clock-history"></i> Activity Log</a>
        <a href="Archive.php"><i class="bi bi-archive"></i> Archives</a>
        <a href="logout.php" class="border-top mt-4 pt-4 text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<div class="content" id="mainContent">
    <header class="top-header d-flex align-items-center justify-content-between sticky-top mb-4 p-3 bg-white shadow-sm rounded-3">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light border-0 p-2" id="hamburger"><i
                    class="bi bi-list fs-4"></i></button>
            <div>
                <h5 class="fw-bold mb-0 text-primary">Executive Dashboard</h5>
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
                    <li><h6 class="dropdown-header">Admin Actions</h6></li>
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
      <div class="col-xl-3 col-md-6">
        <div class="card h-100 py-2 border-start border-4 border-primary">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Revenue (Delivered)</div>
                <div class="h5 mb-0 fw-bold text-gray-800">₱<?php echo number_format($totalRevenue); ?></div>
              </div>
              <div class="col-auto"><i class="bi bi-currency-dollar fs-2 text-primary opacity-50"></i></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6">
        <div class="card h-100 py-2 border-start border-4 border-success">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs fw-bold text-success text-uppercase mb-1">Active Shipments</div>
                <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $activeShipments; ?></div>
              </div>
              <div class="col-auto"><i class="bi bi-truck fs-2 text-success opacity-50"></i></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6">
        <div class="card h-100 py-2 border-start border-4 border-warning">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Requests</div>
                <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $pendingCount; ?></div>
              </div>
              <div class="col-auto"><i class="bi bi-clipboard-data fs-2 text-warning opacity-50"></i></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6">
        <div class="card h-100 py-2 border-start border-4 border-info">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs fw-bold text-info text-uppercase mb-1">SLA Compliance</div>
                <div class="row no-gutters align-items-center">
                  <div class="col-auto">
                    <div class="h5 mb-0 mr-3 fw-bold text-gray-800"><?php echo $slaRate; ?>%</div>
                  </div>
                  <div class="col ps-2">
                    <div class="progress progress-sm mr-2" style="height: 5px;">
                      <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $slaRate; ?>%"></div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-auto"><i class="bi bi-speedometer2 fs-2 text-info opacity-50"></i></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between bg-transparent border-bottom align-items-center">
                    <h6 class="m-0 fw-bold text-primary">Revenue Overview (7 Days)</h6>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-transparent border-bottom">
                    <h6 class="m-0 fw-bold text-primary">Shipment Status</h6>
                </div>
                <div class="card-body">
                      <div style="position: relative; height: 250px; width: 100%;">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="mt-3 text-center small">
                        <span class="me-2"><i class="bi bi-circle-fill text-success"></i> Delivered</span>
                        <span class="me-2"><i class="bi bi-circle-fill text-warning"></i> Pending</span>
                        <span><i class="bi bi-circle-fill text-info"></i> Transit</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-transparent border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">Recent Transactions</h6>
                    <a href="shiphistory.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-4">Tracking ID</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Destination</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Amount</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($recShip->num_rows > 0): while($row = $recShip->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-3 text-primary" style="width: 35px; height: 35px;">
                                            <i class="bi bi-box-seam"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block text-sm">
                                                <?php echo 'SHIP-'.str_pad($row['id'], 5, "0", STR_PAD_LEFT); ?>
                                            </span>
                                            <small class="text-muted" style="font-size: 11px;">
                                                <?php echo $row['user_id']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center text-secondary">
                                        <i class="bi bi-geo-alt me-1 small"></i>
                                        <span class="text-sm fw-bold"><?php echo substr($row['destination_address'], 0, 18) . '...'; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $st = $row['status'];
                                        // Soft Badge Logic (BG Opacity)
                                        $badgeClass = 'bg-secondary text-secondary bg-opacity-10'; 
                                        if($st=='Delivered') $badgeClass = 'bg-success text-success bg-opacity-10';
                                        else if($st=='Pending') $badgeClass = 'bg-warning text-warning bg-opacity-10';
                                        else if($st=='In Transit' || $st=='Out for Delivery') $badgeClass = 'bg-info text-info bg-opacity-10';
                                        else if($st=='Cancelled') $badgeClass = 'bg-danger text-danger bg-opacity-10';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> px-3 py-2 rounded-pill border border-0 fw-bold">
                                        <?php echo $st; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-dark">₱<?php echo number_format($row['price']); ?></span>
                                        <small class="text-muted" style="font-size: 10px;">
                                            <?php echo date('M d', strtotime($row['created_at'])); ?>
                                        </small>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <small class="text-muted d-block mb-1" style="font-size: 10px;">
                                        <?php echo time_elapsed_string($row['created_at']); ?>
                                    </small>
                                    <a href="view_shipment.php?id=<?php echo $row['id']; ?>" class="text-secondary text-hover-primary">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No recent transactions.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-transparent border-bottom">
                    <h6 class="m-0 fw-bold text-primary">Customer Voices</h6>
                </div>
                <div class="list-group list-group-flush">
                    <?php if($feedbacks->num_rows > 0): while($f = $feedbacks->fetch_assoc()): ?>
                    <div class="list-group-item border-bottom-0 pb-3 pt-3">
                        <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 fw-bold text-dark small"><?php echo htmlspecialchars($f['username']); ?></h6>
                            <small class="text-muted" style="font-size: 10px;"><?php echo time_elapsed_string($f['created_at']); ?></small>
                        </div>
                        <div class="text-warning small mb-2" style="font-size: 10px;">
                            <?php for($i=0; $i<$f['rating']; $i++) echo '<i class="bi bi-star-fill"></i> '; ?>
                        </div>
                        <p class="mb-0 small text-secondary bg-light p-2 rounded fst-italic">"<?php echo $f['feedback_text'] ?: 'No comment'; ?>"</p>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="text-center p-4 text-muted small">No feedback received yet.</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center bg-transparent border-top-0 pb-3">
                    <a href="customer_feedback.php" class="small text-decoration-none fw-bold">View All Reviews</a>
                </div>
            </div>
        </div>
    </div>

  </div> 
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // 1. UI INIT
    initDarkMode("adminThemeToggle", "adminDarkMode");
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

    // 2. REVENUE CHART
    const ctxRev = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctxRev, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [
                {
                    label: 'Revenue (₱)',
                    data: <?php echo json_encode($chartRevenue); ?>,
                    
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    pointRadius: 4,
                    pointBackgroundColor: '#4e73df',
                    yAxisID: 'y1'
                },
                {
                    label: 'Volume',
                    data: <?php echo json_encode($chartVolume); ?>,
                    backgroundColor: '#1cc88a',
                    yAxisID: 'y2',
                    barThickness: 20
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            tooltips: { mode: 'index', intersect: false },
            scales: {
                yAxes: [
                    { id: 'y1', position: 'left', ticks: { beginAtZero: true, callback: v => '₱' + v } },
                    { id: 'y2', position: 'right', gridLines: { display: false }, ticks: { beginAtZero: true } }
                ],
                xAxes: [{ gridLines: { display: false } }]
            }
        }
    });

    // 3. STATUS CHART
    const ctxStat = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStat, {
        type: 'doughnut',
        data: {
            labels: ["Pending", "Transit/Out", "Delivered", "Cancelled"],
            datasets: [{
                data: <?php echo json_encode($statusCounts); ?>,
                backgroundColor: ['#f6c23e', '#36b9cc', '#1cc88a', '#e74a3b'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            cutoutPercentage: 75,
        }
    });

    // 4. NOTIFICATIONS
    function fetchNotifications() {
        fetch('api/get_notifications.php')
        .then(res => res.json())
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
                    html += `<li class="border-bottom ${bg}"><a class="dropdown-item p-2 text-wrap" href="#">
                        <small class="fw-bold d-block">${n.title}</small>
                        <small class="text-muted">${n.message}</small>
                    </a></li>`;
                });
            } else {
                html = '<li class="text-center p-3 text-muted small">No new notifications</li>';
            }
            list.innerHTML = html;
        }).catch(e => console.error(e));
    }

    function markRead() {
        fetch('api/get_notifications.php', { method: 'POST', body: 'action=read_all', headers: {'Content-Type': 'application/x-www-form-urlencoded'} })
        .then(() => { document.getElementById('notifBadge').style.display = 'none'; });
    }

    fetchNotifications();
    setInterval(fetchNotifications, 5000);
  </script>
</body>
</html>