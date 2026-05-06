<?php
// admin_shipments.php - SLA CONTROL TOWER
include("connection.php");
include("darkmode.php");
include('session.php');
requireRole('admin');

// 1. FETCH SHIPMENTS
$query = "SELECT * FROM shipments ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

// 2. FETCH SLA RULES
$rulesArr = [];
$rQ = mysqli_query($conn, "SELECT * FROM sla_policies WHERE contract_id = 0"); 
while($r = mysqli_fetch_assoc($rQ)) {
    $rulesArr[$r['origin_group']][$r['destination_group']] = $r['max_days'];
}

// 3. KPI VARIABLES
$total_shipments = 0; $total_delayed = 0; $total_ontime = 0; $total_penalty = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SLA Control Tower | Admin</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">

  <style>
    :root {
      --bs-primary: #222831; --bs-primary-rgb: 34, 40, 49; --sidebar-width: 260px;
      --primary-color: #222831; --primary-hover: #393E46; --secondary-color: #DFD0B8;
      --text-main: #222831; --text-secondary: #393E46; --border-color: #948979;
      --dark-bg: #1c2027; --dark-card: #222831; --dark-border: #393E46;
      --dark-text-main: #DFD0B8; --dark-text-sec: #948979;
      --shadow-sm: 0 1px 2px 0 rgb(34 40 49 / 0.05); --shadow-md: 0 4px 6px -1px rgb(34 40 49 / 0.1);
      --radius-md: 8px; --radius-lg: 12px;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: var(--secondary-color); color: var(--text-main); overflow-x: hidden; -webkit-font-smoothing: antialiased; }
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
    body:not(.dark-mode) .sidebar img[alt="Logo"] { filter: brightness(0); }
    
    body.dark-mode { background-color: var(--dark-bg); color: var(--dark-text-main); --bs-card-bg: var(--dark-card); --bs-body-bg: var(--dark-bg); --bs-border-color: var(--dark-border); --bs-body-color: var(--dark-text-main); }
    body.dark-mode .sidebar { background: var(--dark-card); border-right: 1px solid var(--dark-border); color: var(--dark-text-main); }
    body.dark-mode .top-header { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border: 1px solid var(--dark-border); }
    body.dark-mode .card, body.dark-mode .ticket-card, body.dark-mode .modal-content { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border: 1px solid var(--dark-border) !important; }
    body.dark-mode .text-primary { color: var(--dark-text-main) !important; }
    body.dark-mode .text-muted, body.dark-mode .text-secondary { color: var(--dark-text-sec) !important; }
    body.dark-mode .bg-light { background-color: var(--dark-bg) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }
    body.dark-mode table, body.dark-mode tbody tr, body.dark-mode td, body.dark-mode th { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }
    body.dark-mode .table { --bs-table-bg: var(--dark-card); --bs-table-color: var(--dark-text-main); --bs-table-border-color: var(--dark-border); border-color: var(--dark-border) !important; }
    body.dark-mode .table thead th, body.dark-mode .table-light, body.dark-mode .table-light th { background-color: var(--dark-bg) !important; color: white !important; border-color: var(--dark-border) !important; }
    body.dark-mode .sidebar nav a { color: var(--dark-text-sec) !important; }
    body.dark-mode .sidebar nav a:hover { color: var(--dark-text-main) !important; background: var(--dark-border) !important; }
    body.dark-mode .sidebar nav a.active { color: var(--dark-bg) !important; background: var(--border-color) !important; }
    body.dark-mode .form-control, body.dark-mode .form-select { background-color: var(--dark-bg); color: white; border-color: var(--dark-border); }
    .font-mono { font-family: 'Roboto Mono', monospace; letter-spacing: -0.5px; }
    .text-xxs { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
    .kpi-card-body { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; }
    .kpi-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; opacity: 0.8; }
    .badge-soft { padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .bg-soft-success { background-color: rgba(28, 200, 138, 0.15); color: #1cc88a; }
    .bg-soft-warning { background-color: rgba(246, 194, 62, 0.15); color: #f6c23e; }
    .bg-soft-danger { background-color: rgba(231, 74, 59, 0.15); color: #e74a3b; }
    .bg-soft-primary { background-color: rgba(78, 115, 223, 0.15); color: #4e73df; }
    .bg-soft-info { background-color: rgba(54, 185, 204, 0.15); color: #36b9cc; }
    .bg-soft-secondary { background-color: rgba(133, 135, 150, 0.15); color: #858796; }
    .route-visual { position: relative; padding-left: 15px; border-left: 2px solid #e3e6f0; }
    .route-visual::before { content: ''; position: absolute; left: -5px; top: 0; width: 8px; height: 8px; border-radius: 50%; background: #1cc88a; }
    .route-visual::after { content: ''; position: absolute; left: -5px; bottom: 0; width: 8px; height: 8px; border-radius: 50%; background: #e74a3b; }
    body.dark-mode .route-visual { border-left-color: var(--dark-border); }
        
</style>
</head>

<body>
<div class="sidebar flex-shrink-0 p-3" id="sidebar">
    <div class="text-center mb-4 mt-2">
      <img src="Remorig.png" alt="Logo" class="img-fluid mb-2" style="max-width: 120px; transition: 0.3s ease;">
      <h6 class="fw-semibold text-uppercase text-muted mb-0 logo-title" style="letter-spacing: 1px; font-size: 0.75rem;">CORE ADMIN</h6>
    </div>
    <hr class="border-secondary opacity-25">
    <nav class="nav flex-column mb-auto mt-2">
        <a href="admin.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="#crmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people"></i> CRM</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse " id="crmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
            <a href="CRM.php" class="ps-5"><i class="bi bi-dot"></i> Dashboard</a>
            <a href="customer_feedback.php" class="ps-5 "><i class="bi bi-dot"></i> Feedback</a>
        </div>
        <a href="#csmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-file-text"></i> Contract & SLA</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse show" id="csmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
            <a href="admin_contracts.php" class="ps-5 "><i class="bi bi-dot"></i> Contracts</a>
            <a href="Admin_shipments.php" class="ps-5 active"><i class="bi bi-dot"></i> SLA Monitor</a>
        </div>
        <a href="E-Doc.php" class=""><i class="bi bi-folder2-open"></i> E-Docs</a>
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
            <button class="btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
            <div>
                <h5 class="fw-bold mb-0 text-primary">SLA Control Tower</h5>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
                <label class="form-check-label text-muted" for="adminThemeToggle"><i class="bi bi-moon-stars"></i></label>
                <input class="form-check-input m-0" type="checkbox" role="switch" id="adminThemeToggle">
            </div>
            <div class="dropdown mx-1">
                <a href="#" class="text-dark position-relative" id="notifDropdown" data-bs-toggle="dropdown" onclick="markRead()">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;"><i class="bi bi-bell"></i></div>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm border border-white" id="notifBadge" style="display: none; padding: 0.25em 0.5em; font-size: 0.65em;">0</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow p-0" style="width: 320px; max-height: 480px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                    <li class="p-3 border-bottom fw-bold bg-light d-flex justify-content-between align-items-center"><span>Notifications</span></li>
                    <div id="notifList"><li class="text-center p-4 text-muted small">Checking...</li></div>
                </ul>
            </div>
            <div class="dropdown">
                <a href="#" data-bs-toggle="dropdown" class="d-block link-dark text-decoration-none" style="cursor: pointer;">
                    <img src="user.png" alt="Profile" width="36" height="36" class="rounded-circle object-fit-cover border border-2 border-primary">
                </a>
                <ul class="dropdown-menu dropdown-menu-end text-small shadow" style="border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                    <li><h6 class="dropdown-header">Admin Actions</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
<div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-4 border-primary">
                <div class="kpi-card-body">
                    <div>
                        <div class="text-xxs fw-bold text-primary mb-1">TOTAL SHIPMENTS</div>
                        <div class="h3 mb-0 fw-bold text-gray-800" id="kpi-total">0</div>
                    </div>
                    <div class="kpi-icon bg-soft-primary"><i class="bi bi-box-seam text-primary"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-4 border-success">
                <div class="kpi-card-body">
                    <div>
                        <div class="text-xxs fw-bold text-success mb-1">ON TIME DELIVERY</div>
                        <div class="h3 mb-0 fw-bold text-gray-800" id="kpi-ontime">0</div>
                    </div>
                    <div class="kpi-icon bg-soft-success"><i class="bi bi-check-lg text-success"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-4 border-danger">
                <div class="kpi-card-body">
                    <div>
                        <div class="text-xxs fw-bold text-danger mb-1">SLA BREACHED</div>
                        <div class="h3 mb-0 fw-bold text-gray-800" id="kpi-delayed">0</div>
                    </div>
                    <div class="kpi-icon bg-soft-danger"><i class="bi bi-exclamation-triangle text-danger"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 border-start border-4 border-warning">
                <div class="kpi-card-body">
                    <div>
                        <div class="text-xxs fw-bold text-warning mb-1">TOTAL PENALTIES</div>
                        <div class="h3 mb-0 fw-bold text-gray-800" id="kpi-penalty">₱0</div>
                    </div>
                    <div class="kpi-icon bg-soft-warning"><i class="bi bi-cash-stack text-warning"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-transparent border-bottom d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-table me-2"></i>Live Shipment Status</h6>
            <button class="btn btn-sm btn-light border" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
        <div class="table-responsive p-0">
            <table class="table table-hover align-middle mb-0 w-100">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 text-xxs text-secondary opacity-7">Tracking ID / Sender</th>
                        <th class="text-xxs text-secondary opacity-7">Route (Origin → Dest)</th>
                        <th class="text-xxs text-secondary opacity-7">Timeline</th>
                        <th class="text-xxs text-secondary opacity-7">Status</th>
                        <th class="text-xxs text-secondary opacity-7">SLA Health</th>
                        <th class="text-end pe-4 text-xxs text-secondary opacity-7">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): 
                            $total_shipments++;
                            $current_status = strtoupper($row['status']); 
                            
                            // Calculate SLA
                            $origin = $row['origin_island'] ?? 'Luzon';
                            $dest = $row['destination_island'] ?? 'Visayas';
                            $sla_days = $rulesArr[$origin][$dest] ?? 7; 
                            $book_date = strtotime($row['created_at']);
                            $target_date = strtotime("+$sla_days days", $book_date);
                            $is_delivered = ($current_status == 'DELIVERED');
                            $actual_end = $is_delivered ? strtotime($row['updated_at'] ?? $row['created_at']) : time();
                            
                            $sla_status = "";
                            $penalty = 0;

                            if ($actual_end > $target_date) {
                                $sla_status = '<span class="badge-soft bg-soft-danger">BREACHED</span>';
                                $total_delayed++;
                                $penalty = ($row['price'] ?? 0) * 0.10; 
                                $total_penalty += $penalty;
                            } elseif (!$is_delivered && (ceil(($target_date - $actual_end)/86400) <= 1)) {
                                $sla_status = '<span class="badge-soft bg-soft-warning">AT RISK</span>';
                                $total_ontime++; 
                            } else {
                                $sla_status = '<span class="badge-soft bg-soft-success">ON TRACK</span>';
                                $total_ontime++;
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-3 text-secondary" style="width: 35px; height: 35px;">
                                        <i class="bi bi-box"></i>
                                    </div>
                                    <div>
                                        <span class="d-block fw-bold text-primary font-mono"><?php echo $row['contract_number']; ?></span>
                                        <small class="text-muted"><?php echo $row['sender_name']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="route-visual">
                                    <div class="text-xs fw-bold text-dark"><?php echo $row['origin_address']; ?></div>
                                    <div class="text-xs text-muted mt-1"><?php echo $row['destination_address']; ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <small class="text-muted" style="font-size: 11px;">Booked: <?php echo date('M d', $book_date); ?></small>
                                    <small class="fw-bold text-dark" style="font-size: 11px;">Target: <?php echo date('M d', $target_date); ?></small>
                                    <small class="text-muted fst-italic" style="font-size: 10px;"><?php echo $sla_days; ?> Days SLA</small>
                                </div>
                            </td>
                            <td>
                                <?php 
                                    $badgeClass = 'bg-soft-secondary';
                                    if ($current_status == 'DELIVERED') $badgeClass = 'bg-soft-success';
                                    elseif ($current_status == 'CONSOLIDATED') $badgeClass = 'bg-soft-info';
                                    elseif ($current_status == 'IN_TRANSIT' || $current_status == 'DISPATCH') $badgeClass = 'bg-soft-warning';
                                    elseif ($current_status == 'CANCELLED') $badgeClass = 'bg-soft-danger';
                                    
                                    echo "<span class='badge-soft $badgeClass'>$current_status</span>";
                                ?>
                            </td>
                            <td><?php echo $sla_status; ?></td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <button onclick="syncStatus('<?php echo $row['contract_number']; ?>', this)" class="btn btn-sm btn-outline-primary rounded-pill px-3" title="Sync Status from Core 1">
                                        <i class="bi bi-arrow-repeat me-1"></i> Sync
                                    </button>
                                    
                                    <?php if($penalty > 0): ?>
                                        <button class="btn btn-sm btn-outline-danger ms-1 rounded-pill" 
                                                title="Issue Penalty" 
                                                onclick="issuePenalty(<?php echo $penalty; ?>, '<?php echo $row['contract_number']; ?>')">
                                            <i class="bi bi-cash"></i> -₱<?php echo number_format($penalty, 0); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No shipments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    // 1. UI Initialization
    if (typeof initDarkMode === 'function') initDarkMode("adminThemeToggle", "adminDarkMode");
    
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

    // 2. Update KPI UI with Values Calculated in PHP
    document.getElementById('kpi-total').innerText = "<?php echo $total_shipments; ?>";
    document.getElementById('kpi-ontime').innerText = "<?php echo $total_ontime; ?>";
    document.getElementById('kpi-delayed').innerText = "<?php echo $total_delayed; ?>";
    document.getElementById('kpi-penalty').innerText = "₱<?php echo number_format($total_penalty, 2); ?>";

    // 3. Sync Status Logic (UPDATED WITH BETTER ALERTS)
    function syncStatus(trackingCode, btn) {
        // Show Loading Modal
        Swal.fire({
            title: 'Syncing Status',
            text: 'Retrieving latest tracking data...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();
        formData.append('action', 'sync_core1');
        formData.append('tracking_code', trackingCode);

        fetch('update_shipment_api.php', { 
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                // Success Alert with Timer
                Swal.fire({
                    icon: 'success',
                    title: 'Synced!',
                    text: 'Shipment status updated successfully.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload(); 
                });
            } else {
                // Error Alert
                Swal.fire({
                    icon: 'error',
                    title: 'Sync Failed',
                    text: data.message
                });
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Could not connect to the server. Check console.'
            });
        });
    }

    // 4. Issue Penalty Logic (NEW ADDITION)
    function issuePenalty(amount, ref) {
        Swal.fire({
            title: 'Issue Penalty?',
            text: `You are about to issue a ₱${amount} penalty for shipment ${ref} due to SLA breach.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'Yes, Issue Penalty'
        }).then((result) => {
            if (result.isConfirmed) {
                // Simulate penalty issuance (Connect to backend here if needed)
                Swal.fire(
                    'Issued!',
                    'Penalty has been recorded in the accounting ledger.',
                    'success'
                );
            }
        });
    }

    // 5. Notification System
    function fetchNotifications() {
        fetch('api/get_notifications.php') // Adjust path if needed
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
                    html += `<li class="border-bottom ${bg}"><a class="dropdown-item p-3 text-wrap" href="#">
                        <small class="fw-bold d-block text-dark">${n.title}</small>
                        <small class="text-muted">${n.message}</small>
                        <br><small class="text-secondary opacity-50" style="font-size:10px">${new Date(n.created_at).toLocaleString()}</small>
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