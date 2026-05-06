<?php
// admin_completed.php - SECURE VAULT EDITION 🔒
include("connection.php");
include("darkmode.php");
include('session.php');

if (function_exists('requireRole')) {
    requireRole('admin');
}

// =========================================================
// 🔐 SECURITY CONFIGURATION (AUTO-LOCK SYSTEM)
// =========================================================
$VAULT_PASSWORD = "core3";
$TIMEOUT_DURATION = 10; // 10s Timeout

// Handle Unlock
$vault_error = "";
if (isset($_POST['btn_unlock'])) {
    $input_pass = $_POST['vault_pass'];
    if ($input_pass === $VAULT_PASSWORD) {
        $_SESSION['completed_unlocked'] = true;
        $_SESSION['completed_last_act'] = time();
        header("Location: admin_completed.php");
        exit;
    } else {
        $vault_error = "Incorrect password. Access denied.";
    }
}

// Handle Lock
if (isset($_GET['action']) && $_GET['action'] == 'lock') {
    unset($_SESSION['completed_unlocked']);
    unset($_SESSION['completed_last_act']);
    header("Location: admin_completed.php");
    exit;
}

// AUTO-LOCK CHECK
if (isset($_SESSION['completed_unlocked']) && $_SESSION['completed_unlocked'] === true) {
    if (isset($_SESSION['completed_last_act']) && (time() - $_SESSION['completed_last_act'] > $TIMEOUT_DURATION)) {
        unset($_SESSION['completed_unlocked']);
        unset($_SESSION['completed_last_act']);
    } else {
        $_SESSION['completed_last_act'] = time();
    }
}

$is_unlocked = isset($_SESSION['completed_unlocked']) && $_SESSION['completed_unlocked'] === true;

// ==========================================================
// 1. DATA FETCHING (EXECUTE ONLY IF UNLOCKED)
// ==========================================================
$result = null;
if ($is_unlocked) {
    $sql = "SELECT 
                s.id AS shipment_id, 
                p.invoice_number,
                p.amount,
                p.payment_date,
                p.method,
                s.contract_number,
                s.sender_name,
                s.status as delivery_status,
                a.username
            FROM payments p
            JOIN shipments s ON p.shipment_id = s.id
            LEFT JOIN accounts a ON p.user_id = a.id
            WHERE s.status = 'Delivered' 
            ORDER BY p.payment_date DESC";
    $result = mysqli_query($conn, $sql);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Transactions | Secured</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

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
            <a href="admin_completed.php" class="active"><i class="bi bi-check-circle-fill"></i> Completed Trans.</a>
            <a href="BIFA.php" class=""><i class="bi bi-graph-up"></i> BI & Analytics</a>
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
                    <h5 class="fw-bold mb-0 text-primary">✅ Completed Deliveries</h5>
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
        <?php if (!$is_unlocked): ?>
            <div class="lock-screen fade-in">
                <div class="card shadow-lg border-0 p-4">
                    <div class="card-body">
                        <i class="bi bi-shield-lock-fill lock-icon"></i>
                        <h4 class="fw-bold mb-1">Financial Records Locked</h4>
                        <p class="text-muted small mb-4">Authorized personnel only. Enter password to view.</p>

                        <?php if ($vault_error): ?>
                            <div class="alert alert-danger py-2 small"><?php echo $vault_error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="input-group mb-3">
                                <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                <input type="password" name="vault_pass" class="form-control" placeholder="Enter Password"
                                    required autofocus>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="btn_unlock" class="btn btn-primary fw-bold py-2">
                                    Unlock Records <i class="bi bi-arrow-right-short"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>OR Number</th>
                                    <th>Date Paid</th>
                                    <th>Tracking No.</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td class="fw-bold text-danger"><?php echo $row['invoice_number']; ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?></td>
                                            <td>
                                                <small class="text-muted">ID: <?php echo $row['shipment_id']; ?></small><br>
                                                <?php echo $row['contract_number']; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo $row['sender_name']; ?></strong><br>
                                                <small class="text-muted">User: <?php echo $row['username']; ?></small>
                                            </td>
                                            <td class="fw-bold text-success">₱<?php echo number_format($row['amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-success">DELIVERED</span>
                                                <span class="badge bg-primary">PAID</span>
                                            </td>
                                            <td class="text-center">
                                                <button onclick="openReceipt(<?php echo $row['shipment_id']; ?>)"
                                                    class="btn btn-primary btn-sm rounded-pill px-3" title="View Official Receipt">
                                                    <i class="bi bi-eye-fill"></i> View OR
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No completed transactions found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg" style="max-width: 900px;">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title"><i class="bi bi-receipt"></i> Official Receipt Preview</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0" style="background: #555;">
                            <iframe id="receiptFrame" src=""
                                style="width: 100%; height: 80vh; border: none; display: block;"></iframe>
                        </div>
                        <div class="modal-footer bg-light">
                            <small class="text-muted me-auto">Note: Use the print button inside the receipt to save as
                                PDF.</small>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        <?php if ($is_unlocked): ?>
            // 1. RECEIPT MODAL FUNCTION (Only needed if unlocked)
            function openReceipt(id) {
                const frame = document.getElementById('receiptFrame');
                frame.src = "print_invoice.php?id=" + id;
                const myModal = new bootstrap.Modal(document.getElementById('receiptModal'));
                myModal.show();
            }
        <?php endif; ?>

        // 2. DARK MODE INIT
        const toggle = document.getElementById('adminThemeToggle');
        const body = document.body;
        if (localStorage.getItem('theme') === 'dark') {
            body.classList.add('dark-mode');
            toggle.checked = true;
        }
        toggle.addEventListener('change', () => {
            if (toggle.checked) {
                body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            } else {
                body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            }
        });

        // 3. SIDEBAR TOGGLE
        document.getElementById('hamburger').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });

        // 4. NOTIFICATION SYSTEM
        function fetchNotifications() {
            fetch('api/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notifBadge');
                    const list = document.getElementById('notifList');
                    if (data.count > 0) {
                        badge.innerText = data.count;
                        badge.style.display = 'inline-block';
                    } else { badge.style.display = 'none'; }

                    let html = '';
                    if (data.data.length > 0) {
                        data.data.forEach(notif => {
                            let bgClass = notif.is_read == 0 ? 'bg-light' : '';
                            let icon = notif.is_read == 0 ? 'bi-circle-fill text-primary' : 'bi-check-circle text-muted';
                            html += `<li><a class="dropdown-item ${bgClass} p-2 border-bottom" href="#">
                        <div class="d-flex align-items-start"><i class="bi ${icon} me-2 mt-1" style="font-size: 10px;"></i>
                        <div><small class="fw-bold d-block">${notif.title}</small><small class="text-muted text-wrap">${notif.message}</small></div>
                        </div></a></li>`;
                        });
                    } else { html = '<li class="text-center p-3 text-muted small">No new notifications</li>'; }
                    list.innerHTML = html;
                });
        }
        function markRead() {
            fetch('api/get_notifications.php', { method: 'POST', body: 'action=read_all', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })
                .then(() => { document.getElementById('notifBadge').style.display = 'none'; });
        }
        fetchNotifications();
        setInterval(fetchNotifications, 5000);

        // --- CLIENT SIDE INACTIVITY LOCK (10s) ---
        <?php if ($is_unlocked): ?>
            let idleTime = 0;
            setInterval(() => {
                idleTime++;
                if (idleTime >= 10) { window.location.href = '?action=lock'; }
            }, 1000);
            function resetTimer() { idleTime = 0; }
            window.onload = resetTimer;
            window.onmousemove = resetTimer;
            window.onkeypress = resetTimer;
            window.onclick = resetTimer;
        <?php endif; ?>
    </script>
</body>

</html>