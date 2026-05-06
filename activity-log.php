<?php
include('connection.php');
include("darkmode.php");
include('session.php');
include('loading.html');
requireRole('admin');

$retentionDays = 7;

// Keep only the last 7 days of activity data in the database.
$conn->query("DELETE FROM activity_log WHERE login_time < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)");
$conn->query("DELETE FROM admin_activity WHERE `date` < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)");

$result = $conn->query("SELECT * FROM activity_log WHERE login_time >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY) ORDER BY login_time DESC");
$activityResult = $conn->query("SELECT * FROM admin_activity WHERE `date` >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY) ORDER BY `date` DESC LIMIT 100");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
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

        .table-section {
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            background-color: white;
            box-shadow: var(--shadow-sm);
        }

        .table-section1 {
            margin-bottom: 0.5rem;
        }

        .UAL {
            margin-bottom: 1.5rem;
        }

        .RA {
            margin: 1.5rem 0 1.5rem 0;
        }

        .table-scroll-container {
            max-height: 410px;
            overflow-y: auto;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }

        .table-scroll {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 0;
        }

        .table-scroll th,
        .table-scroll td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color) !important;
            word-wrap: break-word;
        }

        .table-scroll thead th {
            position: sticky;
            top: 0;
            background-color: var(--primary-color) !important;
            color: white !important;
            z-index: 2;
            margin: 0;
            padding-top: 0.5rem;
            border-color: transparent !important;
        }

        .table-scroll-container::-webkit-scrollbar {
            width: 10px;
        }

        .table-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            height: 42.5px;
        }

        .table-scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        body.dark-mode .table-section {
            background-color: var(--dark-card);
            color: var(--dark-text-main);
        }

        body.dark-mode .table-scroll-container::-webkit-scrollbar-thumb {
            background: #4e73df;
        }

        body.dark-mode .table-scroll-container::-webkit-scrollbar-track {
            background: var(--dark-bg);
        }

        body.dark-mode .table-scroll th,
        body.dark-mode .table-scroll td {
            border-bottom-color: var(--dark-border);
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
            <a href="admin_reports.php" class=""><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
            <a href="activity-log.php" class="active"><i class="bi bi-clock-history"></i> Activity Log</a>

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
                    <h5 class="fw-bold mb-0 text-primary">Activity Log</h5>
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
                            <span>Notifications</span></li>
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
        <div class="table-section">
            <h3 class="fw-bold UAL">User Activity Log</h3>
            <div class="table-scroll-container">
                <table class="table-scroll">
                    <colgroup>
                        <col style="width: 10%;">
                        <col style="width: 20%;">
                        <col style="width: 20%;">
                        <col style="width: 30%;">
                        <col style="width: 20%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                            <th>Login Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= htmlspecialchars($row['ip_address']) ?></td>
                                <td><?= htmlspecialchars($row['user_agent']) ?></td>
                                <td><?= htmlspecialchars($row['login_time']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <h3 class="fw-bold RA">Recent Activity</h3>
            <div class="table-scroll-container">
                <table class="table-scroll">
                    <colgroup>
                        <col style="width: 20%;">
                        <col style="width: 20%;">
                        <col style="width: 40%;">
                        <col style="width: 20%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Module</th>
                            <th>Activity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($activityResult && $activityResult->num_rows > 0): ?>
                            <?php while ($row = $activityResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['date']); ?></td>
                                    <td><?= htmlspecialchars($row['module']); ?></td>
                                    <td><?= htmlspecialchars($row['activity']); ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'Success'): ?>
                                            <span class="badge bg-success"><?= htmlspecialchars($row['status']); ?></span>
                                        <?php elseif ($row['status'] === 'Failed'): ?>
                                            <span class="badge bg-danger"><?= htmlspecialchars($row['status']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($row['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No recent activity found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>





    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Sidebar accordion behavior
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                const currentMenu = toggle.nextElementSibling;

                // Close all others
                document.querySelectorAll('.dropdown-content').forEach(menu => {
                    if (menu !== currentMenu) menu.classList.remove('show');
                });

                // Toggle current
                currentMenu.classList.toggle('show');
            });
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