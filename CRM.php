<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include('connection.php');
include('session.php');
include_once('activity_helpers.php');
include('darkmode.php');
requireRoles(['admin', 'super admin', 'super_admin']);
include('loading.html');

// =========================================================
// 🔐 SECURITY CONFIGURATION (AUTO-LOCK SYSTEM)
// =========================================================
$VAULT_PASSWORD = "core3";
$TIMEOUT_DURATION = 60; // 60s Timeout

// Handle Unlock
$vault_error = "";
if (isset($_POST['btn_unlock'])) {
  $input_pass = $_POST['vault_pass'];
  if ($input_pass === $VAULT_PASSWORD) {
    $_SESSION['crm_unlocked'] = true;
    $_SESSION['crm_last_act'] = time();
    header("Location: CRM.php");
    exit;
  } else {
    $vault_error = "Incorrect password. Access denied.";
  }
}

// Handle Lock
if (isset($_GET['action']) && $_GET['action'] == 'lock') {
  unset($_SESSION['crm_unlocked']);
  unset($_SESSION['crm_last_act']);
  header("Location: CRM.php");
  exit;
}

// AUTO-LOCK CHECK
if (isset($_SESSION['crm_unlocked']) && $_SESSION['crm_unlocked'] === true) {
  if (isset($_SESSION['crm_last_act']) && (time() - $_SESSION['crm_last_act'] > $TIMEOUT_DURATION)) {
    unset($_SESSION['crm_unlocked']);
    unset($_SESSION['crm_last_act']);
  } else {
    $_SESSION['crm_last_act'] = time();
  }
}

$is_unlocked = isset($_SESSION['crm_unlocked']) && $_SESSION['crm_unlocked'] === true;

// --- Archive Function (Protected) ---
if (isset($_GET['archive']) && $is_unlocked) {
  $id = intval($_GET['archive']);
  $res = $conn->query("SELECT * FROM accounts WHERE id = $id");

  if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();

    // Move record to archive_crm
    $stmt = $conn->prepare("INSERT INTO archive_crm (username, email, phone_number, gender, role, archived_at)
                                VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param(
      "sssss",
      $row['username'],
      $row['email'],
      $row['phone_number'],
      $row['gender'],
      $row['role']
    );
    $stmt->execute();

    // Delete from main table
    $conn->query("DELETE FROM accounts WHERE id = $id");
    logCrmAdminActivity($conn, "Archived account: " . $row['username'], 'Archived');

    $_SESSION['alert'] = ['title' => 'Archived!', 'text' => 'Customer archived successfully.', 'icon' => 'success'];
    header("Location: CRM.php");
    exit;
  }
}

// Helper
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function logCrmAdminActivity($conn, $activity, $status = 'Success')
{
  $module = 'CRM';
  $activity = formatAdminActivityMessage($activity);
  $stmt = $conn->prepare("INSERT INTO admin_activity (`module`, `activity`, `status`, `date`) VALUES (?, ?, ?, NOW())");
  $stmt->bind_param("sss", $module, $activity, $status);
  $stmt->execute();
  $stmt->close();
}

// --- Export CSV (Protected) ---
if (isset($_GET['export']) && $_GET['export'] === '1' && $is_unlocked) {
  $base = "SELECT id, username, email, phone_number, gender, role FROM accounts";
  $conds = [];
  $params = [];
  $types = '';
  if (!empty($_GET['q'])) {
    $conds[] = "(username LIKE ? OR email LIKE ? OR phone_number LIKE ?)";
    $term = '%' . $_GET['q'] . '%';
    $params = array_merge($params, [$term, $term, $term]);
    $types .= 'sss';
  }

  if ($conds)
    $base .= " WHERE " . implode(" AND ", $conds);
  $base .= " ORDER BY id DESC";

  $stmt = $conn->prepare($base);
  if ($params)
    $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=crm_customers_export.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID', 'Username', 'Email', 'Phone', 'Gender', 'Role']);
  while ($r = $res->fetch_assoc()) {
    fputcsv($out, [$r['id'], $r['username'], $r['email'], $r['phone_number'], $r['gender'], $r['role']]);
  }
  fclose($out);
  exit;
}

// --- Handle Add / Edit for Accounts (Protected) ---
if ($is_unlocked && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];

  // Data Collection
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone_number = trim($_POST['phone_number'] ?? '');
  $gender = trim($_POST['gender'] ?? '');
  $role = trim($_POST['role'] ?? '');

  $errors = [];

  if ($username === '')
    $errors[] = 'Username is required.';
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = 'Valid email is required.';

  if (!$errors) {
    if ($action === 'add') {
      $stmt = $conn->prepare("INSERT INTO accounts (username, email, phone_number, gender, role, password) VALUES (?, ?, ?, ?, ?, SHA2('default123', 256))");
      $stmt->bind_param("sssss", $username, $email, $phone_number, $gender, $role);
      if ($stmt->execute()) {
        logCrmAdminActivity($conn, "Added account: $username with role $role");
        header("Location: CRM.php?msg=added");
        exit;
      } else {
        logCrmAdminActivity($conn, "Failed to add account: $username", 'Failed');
        $errors[] = "Insert failed: " . $stmt->error;
      }
    } elseif ($action === 'edit' && !empty($_POST['id'])) {
      $id = intval($_POST['id']);
      $oldRes = $conn->query("SELECT username, role FROM accounts WHERE id = $id");
      $oldRow = $oldRes ? $oldRes->fetch_assoc() : null;
      $stmt = $conn->prepare("UPDATE accounts SET username=?, email=?, phone_number=?, gender=?, role=? WHERE id=?");
      $stmt->bind_param("sssssi", $username, $email, $phone_number, $gender, $role, $id);

      if ($stmt->execute()) {
        // ✅ Update session if current logged-in user is edited
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
          $_SESSION['username'] = $username;
          $_SESSION['email'] = $email;
        }
        $oldUsername = $oldRow['username'] ?? ('ID ' . $id);
        $oldRole = $oldRow['role'] ?? 'unknown';
        logCrmAdminActivity($conn, "Updated account: $oldUsername to $username with role $oldRole -> $role");
        header("Location: CRM.php?msg=updated");
        exit;
      } else {
        logCrmAdminActivity($conn, "Failed to update account ID $id", 'Failed');
        $errors[] = "Update failed: " . $stmt->error;
      }
    }
  }
}

// --- Data Fetching (Protected) ---
$totalCustomers = 0;
$activeCustomers = 0;
$adminUsers = 0;
$regularUsers = 0;
$result = null;

if ($is_unlocked) {
  // Stats
  $totalCustomers = $conn->query("SELECT COUNT(*) as c FROM accounts")->fetch_assoc()['c'] ?? 0;
  $activeCustomers = $conn->query("SELECT COUNT(*) as c FROM accounts WHERE role='customer'")->fetch_assoc()['c'] ?? 0;
  $adminUsers = $conn->query("SELECT COUNT(*) as c FROM accounts WHERE role='admin'")->fetch_assoc()['c'] ?? 0;
  $regularUsers = $conn->query("SELECT COUNT(*) as c FROM accounts WHERE role='user'")->fetch_assoc()['c'] ?? 0;
  $spAdmin = $conn->query("SELECT COUNT(*) as c FROM accounts WHERE role IN ('super admin', 'super_admin')")->fetch_assoc()['c'] ?? 0;

  // List
  $where = [];
  $params = [];
  $types = '';
  $searchQ = $_GET['q'] ?? '';
  $filterRole = $_GET['role'] ?? '';

  if ($searchQ !== '') {
    $where[] = "(username LIKE ? OR email LIKE ? OR phone_number LIKE ? OR gender LIKE ?)";
    $term = '%' . $searchQ . '%';
    $params = array_merge($params, [$term, $term, $term, $term]);
    $types .= 'ssss';
  }
  if ($filterRole !== '') {
    $where[] = "role = ?";
    $params[] = $filterRole;
    $types .= 's';
  }

  $sql = "SELECT id, username, email, phone_number, gender, role FROM accounts";
  if ($where)
    $sql .= " WHERE " . implode(" AND ", $where);
  $sql .= " ORDER BY id DESC LIMIT 500";

  $stmt = $conn->prepare($sql);
  if ($params)
    $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>CRM — Customer Management</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

    .avatar-initial {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: var(--primary-color);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 0.9rem;
      margin-right: 10px;
    }

    .card {
      border: none;
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
    }

    .card-header {
      border-bottom: 1px solid var(--border-color) !important;
    }

    .stat-value {
      font-size: 1.8rem;
      font-weight: 700;
    }

    .table-responsive {
      max-height: 520px;
      overflow: auto;
    }

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
      border-bottom: 1px solid var(--dark-border) !important;
    }

    body.dark-mode .text-muted,
    body.dark-mode .text-secondary {
      color: var(--dark-text-sec) !important;
    }

    body.dark-mode .bg-light {
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
    }

    body.dark-mode .table thead th {
      background: var(--dark-bg) !important;
      color: white !important;
      border: none;
    }

    body.dark-mode .text-dark,
    body.dark-mode .text-primary {
      color: var(--dark-text-main) !important;
    }

    body.dark-mode .modal-content {
      background-color: var(--dark-card);
      color: var(--dark-text-main);
      border: 1px solid var(--dark-border);
    }

    body.dark-mode .btn-close {
      filter: invert(1);
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
      <div class="collapse show" id="crmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
        <a href="CRM.php" class="ps-5 active"><i class="bi bi-dot"></i> Dashboard</a>
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
    <header
      class="top-header d-flex align-items-center justify-content-between sticky-top mb-4 p-3 bg-white shadow-sm rounded-3">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
        <div>
          <h5 class="fw-bold mb-0 text-primary">Customer Management</h5>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
          <label class="form-check-label text-muted" for="adminThemeToggle"><i class="bi bi-moon-stars"></i></label>
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
            <li class="p-3 border-bottom fw-bold bg-light d-flex justify-content-between align-items-center">
              <span>Notifications</span>
            </li>
            <div id="notifList">
              <li class="text-center p-4 text-muted small">Checking...</li>
            </div>
          </ul>
        </div>
        <div class="dropdown">
          <a href="#" data-bs-toggle="dropdown" class="d-block link-dark text-decoration-none" style="cursor: pointer;">
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
      <!-- LOCKED STATE -->
      <div style="max-width: 400px; margin: 100px auto; text-align: center;">
        <div class="card shadow-lg border-0 p-4">
          <div class="card-body">
            <i class="bi bi-shield-lock-fill text-primary" style="font-size: 4rem;"></i>
            <h4 class="fw-bold mt-3">CRM Locked</h4>
            <p class="text-muted small mb-4">Enter password to manage customer data.</p>

            <?php if ($vault_error): ?>
              <div class="alert alert-danger py-2 small"><?php echo $vault_error; ?></div>
            <?php endif; ?>

            <form method="POST">
              <div class="input-group mb-3">
                <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                <input type="password" name="vault_pass" class="form-control" placeholder="Password" required autofocus>
              </div>
              <div class="d-grid">
                <button type="submit" name="btn_unlock" class="btn btn-primary fw-bold py-2">
                  Unlock CRM <i class="bi bi-arrow-right-short"></i>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php else: ?>

      <!-- UNLOCKED DASHBOARD -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card text-center border-top border-5 border-info h-100">
            <div class="card-body">
              <h6 class="text-muted text-uppercase fw-bold small">Total Accounts</h6>
              <div class="stat-value text-info"><?= (int) $totalCustomers ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card text-center border-top border-5 border-success h-100">
            <div class="card-body">
              <h6 class="text-muted text-uppercase fw-bold small">Active Customers</h6>
              <div class="stat-value text-success"><?= (int) $activeCustomers ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card text-center border-top border-5 border-warning h-100">
            <div class="card-body">
              <h6 class="text-muted text-uppercase fw-bold small">Admins</h6>
              <div class="stat-value text-warning"><?= (int) $adminUsers ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card text-center border-top border-5 border-secondary h-100">
            <div class="card-body">
              <h6 class="text-muted text-uppercase fw-bold small">Regular Users</h6>
              <div class="stat-value text-secondary"><?= (int) $regularUsers ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-white border-bottom-0 py-3">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>Account List</h5>
            <div class="d-flex gap-2">
              <div class="d-flex gap-2">
                <?php if ($is_unlocked): ?>
                  <a href="?action=lock" class="btn btn-outline-danger btn-sm d-flex align-items-center">
                    <i class="bi bi-lock-fill me-1"></i> Lock
                  </a>
                <?php endif; ?>
              </div>
              <div class="input-group" style="width: 200px;">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="searchBox" class="form-control border-start-0" placeholder="Search..."
                  value="<?= h($searchQ) ?>">
              </div>
              <select id="statusFilter" class="form-select w-auto">
                <option value="">All Roles</option>
                <option value="admin">Admin</option>
                <option value="super admin">Super Admin</option>
                <option value="customer">Customer</option>
                <option value="user">User</option>
              </select>
              <button class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#modalCustomer"
                onclick="openAdd()">
                <i class="bi bi-plus-lg"></i> Add
              </button>
              <a href="CRM.php?export=1" class="btn btn-outline-secondary" title="Export CSV"><i
                  class="bi bi-download"></i></a>
            </div>
          </div>
        </div>

        <div class="card-body p-0">
          <?php if (!empty($errors) || !empty($_GET['msg'])): ?>
            <div class="p-3">
              <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-2">
                  <?php foreach ($errors as $e)
                    echo "<div>" . h($e) . "</div>"; ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($_GET['msg'])):
                $m = $_GET['msg'];
                $alertClass = 'success';
                $msgText = 'Action completed.';
                if ($m == 'updated') {
                  $alertClass = 'info';
                  $msgText = 'Account updated.';
                }
                if ($m == 'added') {
                  $msgText = 'Account added successfully.';
                }
                ?>
                <div class="alert alert-<?= $alertClass ?> d-flex align-items-center mb-0">
                  <i class="bi bi-check-circle-fill me-2"></i> <?= $msgText ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="table-responsive">
            <table id="customersTable" class="table table-hover align-middle mb-0">
              <thead class="bg-light">
                <tr>
                  <th class="ps-4">User Profile</th>
                  <th>Contact Info</th>
                  <th>Gender</th>
                  <th>Role</th>
                  <th class="text-end pe-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                  <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    // Random avatar color logic
                    $initial = strtoupper(substr($row['username'], 0, 1));
                    $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];
                    $bg = $colors[array_rand($colors)];
                    ?>
                    <tr>
                      <td class="ps-4">
                        <div class="d-flex align-items-center">
                          <div class="avatar-initial" style="background-color: <?= $bg ?>;"><?= $initial ?></div>
                          <div>
                            <div class="fw-bold text-dark"><?= h($row['username']) ?></div>
                            <small class="text-muted">ID: #<?= $row['id'] ?></small>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex flex-column">
                          <span class="text-dark small"><i class="bi bi-envelope me-1 text-muted"></i>
                            <?= h($row['email']) ?></span>
                          <?php if ($row['phone_number']): ?>
                            <span class="text-muted small"><i class="bi bi-telephone me-1"></i>
                              <?= h($row['phone_number']) ?></span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td><?= h($row['gender']) ?></td>
                      <td>
                        <?php
                        $roleClass = match ($row['role']) {
                          'admin' => 'bg-danger',
                          'super admin', 'super_admin' => 'bg-dark',
                          'user' => 'bg-primary',
                          'customer' => 'bg-success',
                          default => 'bg-secondary'
                        };
                        ?>
                        <span class="badge <?= $roleClass ?> rounded-pill px-3"><?= ucfirst(h($row['role'])) ?></span>
                      </td>
                      <td class="text-end pe-4">
                        <div class="btn-group">
                          <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                            data-bs-target="#modalCustomer"
                            onclick="openEdit(<?= $row['id'] ?>, '<?= h(addslashes($row['username'])) ?>', '<?= h(addslashes($row['email'])) ?>', '<?= h(addslashes($row['phone_number'])) ?>', '<?= h(addslashes($row['gender'])) ?>', '<?= h(addslashes($row['role'])) ?>')">
                            <i class="bi bi-pencil-fill"></i>
                          </button>
                          <a href="CRM.php?archive=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary archive-btn"
                            title="Archive">
                            <i class="bi bi-archive-fill"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="text-center py-4 text-muted">No accounts found or CRM Locked.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="card-footer bg-light py-2">
            <small class="text-muted">Total Records: <?= $result ? $result->num_rows : 0 ?></small>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="modal fade" id="modalCustomer" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="modalForm" method="post" action="CRM.php">
          <div class="modal-header">
            <h5 class="modal-title fw-bold" id="modalTitle">Add Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" id="frmAction" value="add">
            <input type="hidden" name="id" id="frmId" value="">

            <div class="mb-3">
              <label class="form-label">Username</label>
              <input name="username" id="frmUsername" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input name="email" id="frmEmail" class="form-control" type="email" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label">Phone</label>
                <input name="phone_number" id="frmPhone" class="form-control">
              </div>
              <div class="col-6">
                <label class="form-label">Gender</label>
                <select name="gender" id="frmGender" class="form-select" required>
                  <option value="">Select</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Role</label>
              <select name="role" id="frmRole" class="form-select" required>
                <option value="customer">Customer</option>
                <option value="user">User</option>
                <option value="admin">Admin</option>
                <option value="super admin">Super Admin</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" type="submit">Save</button>
          </div>
        </form>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      initDarkMode("adminThemeToggle", "adminDarkMode");

      document.getElementById('hamburger').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('mainContent').classList.toggle('expanded');
      });

      // Sidebar Accordion Logic (Original)
      const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
      dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
          e.preventDefault();
          const currentMenu = toggle.nextElementSibling;
          document.querySelectorAll('.dropdown-content').forEach(menu => {
            if (menu !== currentMenu) menu.classList.remove('show');
          });
          currentMenu.classList.toggle('show');
        });
      });

      // Keep dropdown open based on URL
      const path = window.location.pathname.split("/").pop();
      if (path === "CRM.php" || path === "customer_feedback.php") {
        const crmMenu = document.querySelector('#crmSubmenu');
        if (crmMenu) crmMenu.classList.add('show');
      }

      // Filter Table Logic
      const searchBox = document.getElementById('searchBox');
      const statusFilter = document.getElementById('statusFilter');
      let tableRows = Array.from(document.querySelectorAll('#customersTable tbody tr'));

      function filterTable() {
        const search = searchBox.value.toLowerCase();
        const role = statusFilter.value.toLowerCase();
        tableRows.forEach(row => {
          const text = row.textContent.toLowerCase();
          // Role badge text check
          const roleCell = row.querySelector('.badge').textContent.toLowerCase();

          const matchesSearch = text.includes(search);
          const matchesRole = !role || roleCell.includes(role);
          row.style.display = (matchesSearch && matchesRole) ? '' : 'none';
        });
      }

      searchBox.addEventListener('input', filterTable);
      statusFilter.addEventListener('change', filterTable);

      // Modal Helpers
      function openAdd() {
        document.getElementById('modalTitle').textContent = "Add Account";
        document.getElementById('frmAction').value = "add";
        document.getElementById('frmId').value = "";
        document.getElementById('frmUsername').value = "";
        document.getElementById('frmEmail').value = "";
        document.getElementById('frmPhone').value = "";
        document.getElementById('frmGender').value = "";
        document.getElementById('frmRole').value = "customer";
      }

      function openEdit(id, username, email, phone, gender, role) {
        document.getElementById('modalTitle').textContent = "Edit Account";
        document.getElementById('frmAction').value = "edit";
        document.getElementById('frmId').value = id;
        document.getElementById('frmUsername').value = username;
        document.getElementById('frmEmail').value = email;
        document.getElementById('frmPhone').value = phone;
        document.getElementById('frmGender').value = gender;
        document.getElementById('frmRole').value = role;
      }

      // Archive Confirmation
      document.querySelectorAll(".archive-btn").forEach(btn => {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          const url = this.href;
          Swal.fire({
            title: 'Archive Account?',
            text: "Move this user to archive?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, archive'
          }).then((result) => {
            if (result.isConfirmed) window.location.href = url;
          });
        });
      });
    </script>

    <script>
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
            if (data.data && data.data.length > 0) {
              data.data.forEach(notif => {
                let bgClass = notif.is_read == 0 ? 'bg-light' : '';
                let icon = notif.is_read == 0 ? 'bi-circle-fill text-primary' : 'bi-check-all text-muted';
                let link = notif.link ? notif.link : '#';
                html += `<li><a class="dropdown-item ${bgClass} p-2 border-bottom d-flex gap-2" href="${link}">
                            <i class="bi ${icon} mt-1" style="font-size: 8px;"></i>
                            <div><span class="fw-bold d-block small">${notif.title}</span><small class="text-muted">${notif.message}</small></div>
                        </a></li>`;
              });
            } else {
              html = '<li class="text-center p-3 small text-muted">No notifications</li>';
            }
            list.innerHTML = html;
          }).catch(err => { });
      }
      function markRead() {
        fetch('api/get_notifications.php', { method: 'POST', body: 'action=read_all', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })
          .then(() => { document.getElementById('notifBadge').style.display = 'none'; });
      }

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
      fetchNotifications();
      setInterval(fetchNotifications, 5000);
    </script>
</body>

</html>