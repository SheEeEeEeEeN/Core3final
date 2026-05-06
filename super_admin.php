<?php
include('connection.php');
include('session.php');
include_once('activity_helpers.php');

if ((!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) && !empty($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT id, role, email FROM accounts WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($restoredId, $restoredRole, $restoredEmail);
    if ($stmt->fetch()) {
        $_SESSION['user_id'] = $restoredId;
        $_SESSION['role'] = $restoredRole;
        $_SESSION['email'] = $restoredEmail ?? ($_SESSION['email'] ?? '');
    }
    $stmt->close();
}

include('darkmode.php');
include('loading.html');
requireRoles(['super admin', 'super_admin']);

cleanupActivityTables($conn);
$retentionDays = getActivityRetentionDays();
$maintenanceActive = file_exists(__DIR__ . '/maintenance.flag');

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function getCountValue($conn, $sql)
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int) ($row['total'] ?? 0);
}

function formatRoleLabel($role)
{
    $role = trim((string) $role);
    if ($role === 'super_admin') {
        return 'Super Admin';
    }

    return ucwords(str_replace('_', ' ', $role));
}

$totalAccounts = getCountValue($conn, "SELECT COUNT(*) AS total FROM accounts");
$totalUsers = getCountValue($conn, "SELECT COUNT(*) AS total FROM accounts WHERE role = 'user'");
$totalAdmins = getCountValue($conn, "SELECT COUNT(*) AS total FROM accounts WHERE role = 'admin'");
$totalSuperAdmins = getCountValue($conn, "SELECT COUNT(*) AS total FROM accounts WHERE role IN ('super admin', 'super_admin')");
$userLogins = getCountValue($conn, "SELECT COUNT(*) AS total FROM activity_log al LEFT JOIN accounts a ON a.id = al.user_id WHERE al.login_time >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY) AND a.role = 'user'");
$adminLogins = getCountValue($conn, "SELECT COUNT(*) AS total FROM activity_log al LEFT JOIN accounts a ON a.id = al.user_id WHERE al.login_time >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY) AND a.role IN ('admin', 'super admin', 'super_admin')");
$adminActions = getCountValue($conn, "SELECT COUNT(*) AS total FROM admin_activity WHERE `date` >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)");
$activeAccounts = getCountValue($conn, "SELECT COUNT(DISTINCT user_id) AS total FROM activity_log WHERE login_time >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)");

$topUsers = [];
$topUsersResult = $conn->query("
    SELECT
        al.username,
        COALESCE(a.role, 'unknown') AS role,
        COUNT(*) AS login_count,
        MAX(al.login_time) AS last_login
    FROM activity_log al
    LEFT JOIN accounts a ON a.id = al.user_id
    WHERE al.login_time >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)
    GROUP BY al.user_id, al.username, a.role
    ORDER BY login_count DESC, last_login DESC
    LIMIT 8
");

if ($topUsersResult) {
    while ($row = $topUsersResult->fetch_assoc()) {
        $topUsers[] = $row;
    }
}

$moduleSummary = [];
$adminActionItems = [];
$moduleResult = $conn->query("
    SELECT module, COUNT(*) AS total
    FROM admin_activity
    WHERE `date` >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)
    GROUP BY module
    ORDER BY total DESC, module ASC
    LIMIT 8
");

if ($moduleResult) {
    while ($row = $moduleResult->fetch_assoc()) {
        $moduleSummary[] = $row;
    }
}

$combinedFeed = [];

$loginFeedResult = $conn->query("
    SELECT
        al.username,
        COALESCE(a.role, 'unknown') AS role,
        al.ip_address,
        al.login_time
    FROM activity_log al
    LEFT JOIN accounts a ON a.id = al.user_id
    WHERE al.login_time >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)
    ORDER BY al.login_time DESC
    LIMIT 20
");

if ($loginFeedResult) {
    while ($row = $loginFeedResult->fetch_assoc()) {
        $combinedFeed[] = [
            'date' => $row['login_time'],
            'actor' => $row['username'],
            'role' => formatRoleLabel($row['role']),
            'category' => 'Login',
            'detail' => 'Logged in from IP ' . ($row['ip_address'] ?: 'Unknown'),
            'status' => 'Success',
            'is_admin_action' => false,
            'summary' => '',
        ];
    }
}

$adminFeedResult = $conn->query("
    SELECT `date`, module, activity, status
    FROM admin_activity
    WHERE `date` >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)
    ORDER BY `date` DESC
    LIMIT 20
");

if ($adminFeedResult) {
    while ($row = $adminFeedResult->fetch_assoc()) {
        $parsed = splitAdminActivityMessage($row['activity']);
        $summary = summarizeAdminActivity($parsed['actor'], $row['module'], $parsed['activity'], $row['status']);
        $adminActionItems[] = [
            'date' => $row['date'],
            'actor' => $parsed['actor'],
            'module' => $row['module'],
            'detail' => $parsed['activity'],
            'status' => $row['status'],
            'summary' => $summary,
        ];
        $combinedFeed[] = [
            'date' => $row['date'],
            'actor' => $parsed['actor'],
            'role' => 'Admin Activity',
            'category' => $row['module'],
            'detail' => $parsed['activity'],
            'status' => $row['status'],
            'is_admin_action' => true,
            'summary' => $summary,
        ];
    }
}

usort($combinedFeed, function ($left, $right) {
    return strcmp($right['date'], $left['date']);
});

$combinedFeed = array_slice($combinedFeed, 0, 16);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bs-primary: #222831;
      --bs-primary-rgb: 34, 40, 49;
      --sidebar-width: 260px;
      --primary-color: #222831;
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
    }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: var(--secondary-color); color: var(--text-main); overflow-x: hidden; }
    .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; left: 0; top: 0; background: #ffffff; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; z-index: 1040; transition: all 0.3s ease; }
    .content { margin-left: var(--sidebar-width); padding: 24px; min-height: 100vh; transition: all 0.3s ease; }
    .sidebar.collapsed { margin-left: calc(var(--sidebar-width) * -1); }
    .content.expanded { margin-left: 0; }
    .sidebar nav a { font-weight: 500; font-size: 0.95rem; color: var(--text-secondary) !important; transition: all 0.2s ease; margin-bottom: 4px; border-radius: var(--radius-md); padding: 10px 16px; display: flex; align-items: center; text-decoration: none; }
    .sidebar nav a:hover { color: var(--text-main) !important; background: var(--secondary-color) !important; }
    .sidebar nav a.active { color: #ffffff !important; background: var(--primary-color) !important; font-weight: 600; }
    .sidebar nav a i { font-size: 1.1rem; margin-right: 12px; }
    .card { border: none; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); }
    .top-header { border: 1px solid rgba(148, 137, 121, 0.3); }
    .summary-card { background: linear-gradient(135deg, rgba(34, 40, 49, 0.98), rgba(57, 62, 70, 0.92)); color: #fff; }
    .summary-card.soft { background: #ffffff; color: var(--text-main); }
    .summary-card-link { cursor: pointer; }
    .summary-value { font-size: 2rem; font-weight: 700; line-height: 1; }
    .summary-label { font-size: 0.9rem; color: rgba(255, 255, 255, 0.72); }
    .summary-card.soft .summary-label { color: var(--text-secondary); }
    .table-responsive { max-height: 460px; overflow: auto; }
    .badge-soft { background-color: rgba(34, 40, 49, 0.08); color: var(--text-main); }
    .section-title { font-size: 1.05rem; font-weight: 700; }
    .clickable-admin-action { cursor: pointer; transition: background-color 0.2s ease; }
    .clickable-admin-action:hover { background-color: rgba(34, 40, 49, 0.04); }
    .modal-summary-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-secondary); }
    .modal-summary-box { border: 1px solid rgba(148, 137, 121, 0.25); border-radius: var(--radius-md); padding: 14px; background: rgba(34, 40, 49, 0.03); }
    .action-list-item { border: 1px solid rgba(148, 137, 121, 0.22); border-radius: var(--radius-md); padding: 14px; margin-bottom: 12px; }
    body:not(.dark-mode) .sidebar img[alt="Logo"] { filter: brightness(0); }
    body.dark-mode { background-color: var(--dark-bg) !important; color: var(--dark-text-main); --bs-card-bg: var(--dark-card) !important; --bs-body-bg: var(--dark-bg); --bs-border-color: var(--dark-border); --bs-body-color: var(--dark-text-main); }
    body.dark-mode .sidebar { background: var(--dark-card); border-right: 1px solid var(--dark-border); }
    body.dark-mode .top-header, body.dark-mode .card, body.dark-mode .modal-content { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border: 1px solid var(--dark-border) !important; }
    body.dark-mode .summary-card.soft { background-color: var(--dark-card) !important; }
    body.dark-mode .summary-card.soft .summary-label, body.dark-mode .text-muted, body.dark-mode .text-secondary { color: var(--dark-text-sec) !important; }
    body.dark-mode .bg-light, body.dark-mode .bg-white { background-color: var(--dark-bg) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }
    body.dark-mode table, body.dark-mode tbody tr, body.dark-mode td, body.dark-mode th { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }
    body.dark-mode .table thead th { background-color: var(--dark-bg) !important; color: #fff !important; }
    body.dark-mode .sidebar nav a { color: var(--dark-text-sec) !important; }
    body.dark-mode .sidebar nav a:hover { color: var(--dark-text-main) !important; background: var(--dark-border) !important; }
    body.dark-mode .sidebar nav a.active { color: var(--dark-bg) !important; background: var(--border-color) !important; }
    body.dark-mode .badge-soft { background-color: rgba(223, 208, 184, 0.12); color: var(--dark-text-main); }
    body.dark-mode .clickable-admin-action:hover { background-color: rgba(223, 208, 184, 0.06); }
    body.dark-mode .modal-summary-label { color: var(--dark-text-sec); }
    body.dark-mode .modal-summary-box { background-color: rgba(223, 208, 184, 0.06); border-color: var(--dark-border); }
    body.dark-mode .action-list-item { border-color: var(--dark-border); background-color: rgba(223, 208, 184, 0.04); }
    body.dark-mode .btn-close { filter: invert(1); }
    @media (max-width: 768px) {
      .sidebar { margin-left: calc(var(--sidebar-width) * -1); }
      .sidebar.show { margin-left: 0; }
      .content { margin-left: 0; padding: 15px; }
      .content.mobile-expanded { margin-left: var(--sidebar-width); }
    }
  </style>
</head>
<body>
<div class="sidebar flex-shrink-0 p-3" id="sidebar">
  <div class="text-center mb-4 mt-2">
    <img src="Remorig.png" alt="Logo" class="img-fluid mb-2" style="max-width: 120px; transition: 0.3s ease;">
    <h6 class="fw-semibold text-uppercase text-muted mb-0" style="letter-spacing: 1px; font-size: 0.75rem;">SUPER ADMIN</h6>
  </div>
  <hr class="border-secondary opacity-25">
  <nav class="nav flex-column mb-auto mt-2">
    <a href="super_admin.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="logout.php" class="border-top mt-4 pt-4 text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</div>
<div class="content" id="mainContent">
  <header class="top-header d-flex align-items-center justify-content-between sticky-top mb-4 p-3 bg-white shadow-sm rounded-3">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
      <div>
        <h5 class="fw-bold mb-0 text-primary">Super Admin Activity Dashboard</h5>
        <small class="text-muted">Summary of user logins and admin actions for the last <?= $retentionDays ?> days</small>
      </div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
        <label class="form-check-label text-danger fw-bold small" for="maintenanceToggle" style="letter-spacing: 0.5px;">MAINTENANCE</label>
        <input class="form-check-input m-0" type="checkbox" role="switch" id="maintenanceToggle" <?= $maintenanceActive ? 'checked' : '' ?>>
      </div>
      <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
        <label class="form-check-label text-muted" for="adminThemeToggle"><i class="bi bi-moon-stars"></i></label>
        <input class="form-check-input m-0" type="checkbox" role="switch" id="adminThemeToggle">
      </div>
      <span class="badge badge-soft rounded-pill px-3 py-2"><?= h($_SESSION['username'] ?? 'Super Admin') ?></span>
    </div>
  </header>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3"><div class="card summary-card h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="summary-label">Total Accounts</div><div class="summary-value"><?= $totalAccounts ?></div></div><i class="bi bi-people fs-3"></i></div></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="card summary-card soft h-100"><div class="card-body"><div class="summary-label">User Logins</div><div class="summary-value"><?= $userLogins ?></div><small class="text-muted"><?= $totalUsers ?> user accounts</small></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="card summary-card soft h-100"><div class="card-body"><div class="summary-label">Admin Logins</div><div class="summary-value"><?= $adminLogins ?></div><small class="text-muted"><?= $totalAdmins ?> admin and <?= $totalSuperAdmins ?> super admin accounts</small></div></div></div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="card summary-card soft h-100 summary-card-link" data-bs-toggle="modal" data-bs-target="#adminActionsOverviewModal">
        <div class="card-body">
          <div class="summary-label">Admin Actions</div>
          <div class="summary-value"><?= $adminActions ?></div>
          <small class="text-muted"><?= $activeAccounts ?> active accounts this week</small>
        </div>
      </div>
    </div>
  </div>
  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-5">
      <div class="card h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
          <span class="section-title">Most Active Accounts</span>
          <span class="text-muted small">By login count</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead><tr><th class="ps-3">Name</th><th>Role</th><th>Logins</th><th>Last Login</th></tr></thead>
              <tbody>
              <?php if ($topUsers): foreach ($topUsers as $row): ?>
                <tr>
                  <td class="ps-3 fw-semibold"><?= h($row['username']) ?></td>
                  <td><span class="badge badge-soft rounded-pill"><?= h(formatRoleLabel($row['role'])) ?></span></td>
                  <td><?= (int) $row['login_count'] ?></td>
                  <td><?= h($row['last_login']) ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No login activity found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-7">
      <div class="card h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
          <span class="section-title">Admin Module Summary</span>
          <span class="text-muted small">What admins are working on</span>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <?php if ($moduleSummary): foreach ($moduleSummary as $row): ?>
              <div class="col-12 col-md-6">
                <div class="border rounded-3 p-3 h-100">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= h($row['module']) ?></span>
                    <span class="badge bg-dark rounded-pill"><?= (int) $row['total'] ?></span>
                  </div>
                  <small class="text-muted">Recorded actions in the last <?= $retentionDays ?> days</small>
                </div>
              </div>
            <?php endforeach; else: ?>
              <div class="col-12"><div class="text-center text-muted py-4">No admin actions found.</div></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
      <span class="section-title">Recent Combined Activity</span>
      <span class="text-muted small">Easy view of who did what. Click an admin action to see details.</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th class="ps-3">Date</th><th>Name</th><th>Role</th><th>Activity Type</th><th>Details</th><th>Status</th></tr></thead>
          <tbody>
          <?php if ($combinedFeed): foreach ($combinedFeed as $item): ?>
            <tr
              class="<?= $item['is_admin_action'] ? 'clickable-admin-action' : '' ?>"
              <?php if ($item['is_admin_action']): ?>
                data-bs-toggle="modal"
                data-bs-target="#adminActionModal"
                data-actor="<?= h($item['actor']) ?>"
                data-role="<?= h($item['role']) ?>"
                data-category="<?= h($item['category']) ?>"
                data-detail="<?= h($item['detail']) ?>"
                data-status="<?= h($item['status']) ?>"
                data-date="<?= h($item['date']) ?>"
                data-summary="<?= h($item['summary']) ?>"
              <?php endif; ?>
            >
              <td class="ps-3"><?= h($item['date']) ?></td>
              <td class="fw-semibold"><?= h($item['actor']) ?></td>
              <td><span class="badge badge-soft rounded-pill"><?= h($item['role']) ?></span></td>
              <td><?= h($item['category']) ?></td>
              <td><?= h($item['detail']) ?></td>
              <td>
                <?php
                $statusClass = 'secondary';
                if (strcasecmp($item['status'], 'Success') === 0 || strcasecmp($item['status'], 'Restored') === 0) {
                    $statusClass = 'success';
                } elseif (strcasecmp($item['status'], 'Failed') === 0 || strcasecmp($item['status'], 'Deleted') === 0) {
                    $statusClass = 'danger';
                } elseif (strcasecmp($item['status'], 'Pending Review') === 0) {
                    $statusClass = 'warning text-dark';
                }
                ?>
                <span class="badge bg-<?= $statusClass ?>"><?= h($item['status']) ?></span>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No recent activity found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminActionsOverviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold mb-1">Admin Actions Overview</h5>
          <small class="text-muted">Recent admin actions recorded in the last <?= $retentionDays ?> days</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <?php if ($adminActionItems): ?>
          <?php foreach ($adminActionItems as $action): ?>
            <div class="action-list-item">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
                <div>
                  <div class="fw-semibold"><?= h($action['actor']) ?></div>
                  <div class="text-muted small"><?= h($action['module']) ?> module</div>
                </div>
                <div class="text-end">
                  <div class="small text-muted"><?= h($action['date']) ?></div>
                  <?php
                  $overviewStatusClass = 'secondary';
                  if (strcasecmp($action['status'], 'Success') === 0 || strcasecmp($action['status'], 'Restored') === 0) {
                      $overviewStatusClass = 'success';
                  } elseif (strcasecmp($action['status'], 'Failed') === 0 || strcasecmp($action['status'], 'Deleted') === 0) {
                      $overviewStatusClass = 'danger';
                  } elseif (strcasecmp($action['status'], 'Pending Review') === 0) {
                      $overviewStatusClass = 'warning text-dark';
                  }
                  ?>
                  <span class="badge bg-<?= $overviewStatusClass ?> mt-1"><?= h($action['status']) ?></span>
                </div>
              </div>
              <div class="modal-summary-label mb-1">Action</div>
              <div class="fw-semibold mb-2"><?= h($action['detail']) ?></div>
              <div class="modal-summary-label mb-1">Summary</div>
              <div><?= h($action['summary']) ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-center text-muted py-4">No admin actions found in the last <?= $retentionDays ?> days.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adminActionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold mb-1">Admin Action Summary</h5>
          <small class="text-muted">Detailed view of the selected admin activity</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <div class="modal-summary-box mb-4">
          <div class="modal-summary-label mb-2">Summary</div>
          <div id="modalActionSummary" class="fw-semibold">No summary available.</div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="modal-summary-label mb-1">Admin Name</div>
            <div id="modalActionActor" class="fw-semibold"></div>
          </div>
          <div class="col-md-6">
            <div class="modal-summary-label mb-1">Status</div>
            <div><span id="modalActionStatus" class="badge bg-secondary"></span></div>
          </div>
          <div class="col-md-6">
            <div class="modal-summary-label mb-1">Module</div>
            <div id="modalActionCategory" class="fw-semibold"></div>
          </div>
          <div class="col-md-6">
            <div class="modal-summary-label mb-1">Recorded At</div>
            <div id="modalActionDate" class="fw-semibold"></div>
          </div>
          <div class="col-12">
            <div class="modal-summary-label mb-1">Exact Activity</div>
            <div id="modalActionDetail" class="fw-semibold"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  initDarkMode("adminThemeToggle", "superAdminDarkMode");
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

  const maintenanceToggle = document.getElementById('maintenanceToggle');
  if (maintenanceToggle) {
    maintenanceToggle.addEventListener('change', function() {
      const isEnabled = this.checked;

      // Ask for password
      const pass = window.prompt("Please enter the maintenance password:");
      if (pass !== 'core3') {
        alert("Incorrect password! Action cancelled.");
        this.checked = !isEnabled; // revert the toggle
        return;
      }

      const formData = new FormData();
      formData.append('action', isEnabled ? 'enable' : 'disable');

      fetch('api/toggle_maintenance.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          alert('Failed to toggle maintenance mode.');
          this.checked = !isEnabled;
        }
      })
      .catch(err => {
        console.error(err);
        alert('Network error.');
        this.checked = !isEnabled;
      });
    });
  }

  const adminActionModal = document.getElementById('adminActionModal');
  if (adminActionModal) {
    adminActionModal.addEventListener('show.bs.modal', (event) => {
      const trigger = event.relatedTarget;
      if (!trigger) return;

      const status = trigger.getAttribute('data-status') || '';
      let statusClass = 'bg-secondary';
      if (status.toLowerCase() === 'success' || status.toLowerCase() === 'restored') {
        statusClass = 'bg-success';
      } else if (status.toLowerCase() === 'failed' || status.toLowerCase() === 'deleted') {
        statusClass = 'bg-danger';
      } else if (status.toLowerCase() === 'pending review') {
        statusClass = 'bg-warning text-dark';
      }

      document.getElementById('modalActionSummary').textContent = trigger.getAttribute('data-summary') || 'No summary available.';
      document.getElementById('modalActionActor').textContent = trigger.getAttribute('data-actor') || '';
      document.getElementById('modalActionCategory').textContent = trigger.getAttribute('data-category') || '';
      document.getElementById('modalActionDate').textContent = trigger.getAttribute('data-date') || '';
      document.getElementById('modalActionDetail').textContent = trigger.getAttribute('data-detail') || '';

      const statusBadge = document.getElementById('modalActionStatus');
      statusBadge.className = 'badge ' + statusClass;
      statusBadge.textContent = status;
    });
  }
</script>
</body>
</html>
