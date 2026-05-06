<?php
include('connection.php');
include("darkmode.php");
include('session.php');
include('loading.html');
requireRole('admin');

// Handle restore
if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);
    $res = $conn->query("SELECT * FROM archive_crm WHERE id = $id");

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();

        $stmt = $conn->prepare("INSERT INTO accounts (username, email, phone_number, gender, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $row['username'], $row['email'], $row['phone_number'], $row['gender'], $row['role']);
        $stmt->execute();

        $conn->query("DELETE FROM archive_crm WHERE id = $id");

        $_SESSION['alert'] = ['title' => 'Restored!', 'text' => 'Customer restored successfully.', 'icon' => 'success'];
       header("Location: Archive_CRM.php");
exit;

        
    }
}

$result = $conn->query("SELECT * FROM archive_crm ORDER BY archived_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Archived CRM Customers</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
    
    body.dark-mode { background-color: var(--dark-bg) !important; color: var(--dark-text-main); --bs-card-bg: var(--dark-card) !important; --bs-body-bg: var(--dark-bg); --bs-border-color: var(--dark-border); --bs-body-color: var(--dark-text-main); }
    body.dark-mode .sidebar { background: var(--dark-card); border-right: 1px solid var(--dark-border); color: var(--dark-text-main); }
    body.dark-mode .top-header { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border: 1px solid var(--dark-border); }
    body.dark-mode .card, body.dark-mode .ticket-card, body.dark-mode .modal-content { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border: 1px solid var(--dark-border) !important; }
    body.dark-mode .text-primary { color: var(--dark-text-main) !important; }
    body.dark-mode .text-muted, body.dark-mode .text-secondary { color: var(--dark-text-sec) !important; }
    body.dark-mode .bg-light, body.dark-mode .bg-white { background-color: var(--dark-bg) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }
    body.dark-mode table, body.dark-mode tbody tr, body.dark-mode td, body.dark-mode th { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }
    body.dark-mode .table { --bs-table-bg: var(--dark-card); --bs-table-color: var(--dark-text-main); --bs-table-border-color: var(--dark-border); border-color: var(--dark-border) !important; }
    body.dark-mode .table thead th, body.dark-mode .table-light, body.dark-mode .table-light th { background-color: var(--dark-bg) !important; color: white !important; border-color: var(--dark-border) !important; }
    body.dark-mode .sidebar nav a { color: var(--dark-text-sec) !important; }
    body.dark-mode .sidebar nav a:hover { color: var(--dark-text-main) !important; background: var(--dark-border) !important; }
    body.dark-mode .sidebar nav a.active { color: var(--dark-bg) !important; background: var(--border-color) !important; }
    body.dark-mode .form-control, body.dark-mode .form-select { background-color: var(--dark-bg); color: white; border-color: var(--dark-border); }
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
        <a href="activity-log.php" class=""><i class="bi bi-clock-history"></i> Activity Log</a>
        
        <a href="#archiveSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-archive"></i> Archives</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse show" id="archiveSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
            <a href="Archive.php" class="ps-5 "><i class="bi bi-dot"></i> Documents</a>
            <a href="Archive_CRM.php" class="ps-5 active"><i class="bi bi-dot"></i> Customers</a>
        </div>

        <a href="logout.php" class="border-top mt-4 pt-4 text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<div class="content" id="mainContent">
    <header class="top-header d-flex align-items-center justify-content-between sticky-top mb-4 p-3 bg-white shadow-sm rounded-3">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
            <div>
                <h5 class="fw-bold mb-0 text-primary">Archived Customers</h5>
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
<div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>USERNAME</th>
                            <th>EMAIL</th>
                            <th>PHONE</th>
                            <th>GENDER</th>
                            <th>ROLE</th>
                            <th>ARCHIVED ON</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                    <td>" . htmlspecialchars($row['username']) . "</td>
                                    <td>" . htmlspecialchars($row['email']) . "</td>
                                    <td>" . htmlspecialchars($row['phone_number']) . "</td>
                                    <td>" . htmlspecialchars($row['gender']) . "</td>
                                    <td>" . htmlspecialchars($row['role']) . "</td>
                                    <td>" . htmlspecialchars($row['archived_at']) . "</td>
                                    <td>
                                       <a href='Archive_CRM.php?restore=" . $row['id'] . "' class='btn btn-success btn-sm restore'>
                                            <i class='bi bi-arrow-clockwise'></i> Restore
                                        </a>

                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center'>No archived customers found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script>
initDarkMode("adminThemeToggle", "adminDarkMode");
// Sidebar toggle
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

    // Close others
    document.querySelectorAll('.dropdown-content').forEach(menu => {
      if (menu !== currentMenu) menu.classList.remove('show');
    });

    // Toggle current dropdown
    currentMenu.classList.toggle('show');
  });
});

// keep dropdown open on current subpage
const path = window.location.pathname.split("/").pop();

// CRM pages
if (path === "CRM.php" || path === "customer_feedback.php") {
  const crmMenu = document.querySelectorAll('.dropdown-content')[0];
  const crmToggle = document.querySelectorAll('.dropdown-toggle')[0];
  crmMenu.classList.add('show');
  crmToggle.classList.add('active');
  crmMenu.querySelectorAll('a').forEach(link => {
    if (link.getAttribute('href') === path) link.classList.add('active');
  });
}

// Archived pages
if (path === "Archive.php" || path === "Archive_CRM.php") {
  const arcMenu = document.querySelectorAll('.dropdown-content')[1];
  const arcToggle = document.querySelectorAll('.dropdown-toggle')[1];
  arcMenu.classList.add('show');
  arcToggle.classList.add('active');
  arcMenu.querySelectorAll('a').forEach(link => {
    if (link.getAttribute('href') === path) link.classList.add('active');
  });
}

// Highlight main nav link (non-dropdown)
document.querySelectorAll('.sidebar > div nav > a').forEach(link => {
  if (link.getAttribute('href') === path) link.classList.add('active');
});


// SweetAlert restore confirmation
document.querySelectorAll(".restore").forEach(btn => {
    btn.addEventListener("click", function(e) {
        e.preventDefault();
        const url = this.href;
        Swal.fire({
            title: 'Restore Customer?',
            text: "This will move the customer back to the CRM table.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, restore it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });
});

// Session alert
<?php if (isset($_SESSION['alert'])):
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']); ?>
    Swal.fire({
        title: '<?= $alert['title'] ?>',
        text: '<?= $alert['text'] ?>',
        icon: '<?= $alert['icon'] ?>',
        confirmButtonColor: '#4e73df'
    });
<?php endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
