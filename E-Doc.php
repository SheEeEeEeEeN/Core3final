<?php
// E-Doc.php (SECURE VAULT EDITION) 🔒✨
include("connection.php");
include("darkmode.php");
include('session.php');
include_once('activity_helpers.php');
requireRoles(['admin', 'super admin', 'super_admin']);
include('loading.html');
include('file_handler.php');

// =========================================================
// 🔐 SECURITY CONFIGURATION (AUTO-LOCK SYSTEM)
// =========================================================
$VAULT_PASSWORD = "core3";
$TIMEOUT_DURATION = 10; // 10 Seconds Timeout

// Handle Unlock Request
$vault_error = "";
if (isset($_POST['btn_unlock'])) {
    $input_pass = $_POST['vault_pass'];
    if ($input_pass === $VAULT_PASSWORD) {
        $_SESSION['edocs_unlocked'] = true;
        $_SESSION['edocs_last_act'] = time(); // Set timer
        header("Location: E-Doc.php");
        exit;
    } else {
        $vault_error = "Incorrect password. Access denied.";
    }
}

// Handle Manual Lock
if (isset($_GET['action']) && $_GET['action'] == 'lock') {
    unset($_SESSION['edocs_unlocked']);
    unset($_SESSION['edocs_last_act']);
    header("Location: E-Doc.php");
    exit;
}

// AUTO-LOCK CHECK (Server Side)
if (isset($_SESSION['edocs_unlocked']) && $_SESSION['edocs_unlocked'] === true) {
    if (isset($_SESSION['edocs_last_act']) && (time() - $_SESSION['edocs_last_act'] > $TIMEOUT_DURATION)) {
        // Time expired
        unset($_SESSION['edocs_unlocked']);
        unset($_SESSION['edocs_last_act']);
    } else {
        // Active - Refresh Timer
        $_SESSION['edocs_last_act'] = time();
    }
}

// Final Status
$is_unlocked = isset($_SESSION['edocs_unlocked']) && $_SESSION['edocs_unlocked'] === true;

// (Auto-Lock logic moved above)

// =========================================================
// 📂 DOCUMENT LOGIC (EXECUTE ONLY IF UNLOCKED)
// =========================================================
$all_docs = [];
$shipment_list = null;
$msg = "";
$msgType = "";
$missing_pod_list = [];
$search = "";
$current_page_docs = [];
$total_pages = 1;
$page = 1;
$total_results = 0;

function logEDocAdminActivity($conn, $activity, $status)
{
    $module = 'E-Documentation';
    $activity = formatAdminActivityMessage($activity);
    $stmt = $conn->prepare("INSERT INTO admin_activity (`module`, `activity`, `status`, `date`) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $module, $activity, $status);
    $stmt->execute();
    $stmt->close();
}

if ($is_unlocked) {
    // --- UPLOAD LOGIC ---
    if (isset($_POST['btn_upload'])) {
        $tracking_num = mysqli_real_escape_string($conn, $_POST['tracking_number']);
        $doc_type = mysqli_real_escape_string($conn, $_POST['doc_type']);
        $uploader = $_SESSION['username'] ?? 'Admin';
        $result = uploadDocument($conn, $tracking_num, $doc_type, $_FILES['doc_file'], $uploader);
        $msg = $result['message'];
        $msgType = $result['status'];
        $activityStatus = 'Success';
        if ($msgType === 'warning') {
            $activityStatus = 'Warning';
        } elseif ($msgType === 'danger') {
            $activityStatus = 'Failed';
        }
        logEDocAdminActivity($conn, "Uploaded $doc_type for tracking #$tracking_num. Result: $msg", $activityStatus);
    }

    // --- FETCH DOCUMENTS ---
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

    // 1. MANUAL
    $sql_manual = "SELECT d.*, s.sender_name FROM shipment_documents d LEFT JOIN shipments s ON d.tracking_number = s.id";
    if ($search != '') {
        $sql_manual .= " WHERE d.tracking_number LIKE '%$search%' OR d.doc_type LIKE '%$search%'";
    }
    $res_manual = $conn->query($sql_manual);
    while ($row = $res_manual->fetch_assoc()) {
        $ext = pathinfo($row['file_name'], PATHINFO_EXTENSION);
        $all_docs[] = [
            'category' => 'Manual',
            'ref_id' => $row['tracking_number'],
            'name' => $row['sender_name'],
            'doc_type' => $row['doc_type'],
            'file_name' => $row['file_name'],
            'file_ext' => strtolower($ext),
            'uploader' => $row['uploaded_by'],
            'date' => $row['uploaded_at'],
            'link' => $row['file_path'],
            'is_virtual' => false
        ];
    }

    // 2. print_invoiceS
    $sql_ship = "SELECT id, sender_name, created_at FROM shipments";
    if ($search != '') {
        $sql_ship .= " WHERE id LIKE '%$search%' OR sender_name LIKE '%$search%'";
    }
    $sql_ship .= " ORDER BY created_at DESC LIMIT 100";
    $res_ship = $conn->query($sql_ship);
    while ($row = $res_ship->fetch_assoc()) {
        $trk = "TRK-" . str_pad($row['id'], 6, "0", STR_PAD_LEFT);
        $all_docs[] = [
            'category' => 'System',
            'ref_id' => $trk,
            'name' => $row['sender_name'],
            'doc_type' => 'print_invoice',
            'file_name' => $trk . '_print_invoice.pdf',
            'file_ext' => 'pdf',
            'uploader' => 'System',
            'date' => $row['created_at'],
            'link' => 'print_invoice.php?id=' . $row['id'],
            'is_virtual' => true
        ];
    }

    // 3. CONTRACTS
    $sql_cont = "SELECT c.id as contract_id, c.contract_number, c.client_name, c.created_at 
                 FROM contracts c 
                 WHERE c.contract_number IS NOT NULL AND c.contract_number != ''";
    if ($search != '') {
        $sql_cont .= " AND (c.contract_number LIKE '%$search%' OR c.client_name LIKE '%$search%')";
    }
    $sql_cont .= " ORDER BY c.created_at DESC LIMIT 100";
    $res_cont = $conn->query($sql_cont);
    while ($row = $res_cont->fetch_assoc()) {
        $all_docs[] = [
            'category' => 'System',
            'ref_id' => $row['contract_number'],
            'name' => $row['client_name'],
            'doc_type' => 'Contract',
            'file_name' => $row['contract_number'] . '_Contract.pdf',
            'file_ext' => 'pdf',
            'uploader' => 'System',
            'date' => $row['created_at'],
            'link' => 'contract_print.php?number=' . $row['contract_number'],
            'is_virtual' => true
        ];
    }

    usort($all_docs, function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // PAGINATION
    $limit = 10;
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1)
        $page = 1;
    $total_results = count($all_docs);
    $total_pages = ceil($total_results / $limit);
    $offset = ($page - 1) * $limit;
    $current_page_docs = array_slice($all_docs, $offset, $limit);

    $shipment_list = $conn->query("SELECT id, sender_name FROM shipments ORDER BY created_at DESC");

    // COMPLIANCE
    $q_check = $conn->query("SELECT id, sender_name FROM shipments WHERE status='Delivered'");
    while ($s = $q_check->fetch_assoc()) {
        $sid = "TRK" . str_pad($s['id'], 6, "0", STR_PAD_LEFT);
        $chk = $conn->query("SELECT id FROM shipment_documents WHERE tracking_number='{$s['id']}' AND doc_type='Proof of Delivery'");
        if ($chk->num_rows == 0) {
            $missing_pod_list[] = $sid;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secured Documents | Core Admin</title>

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
                <a href="CRM.php" class="ps-5"><i class="bi bi-dot"></i> Dashboard</a>
                <a href="customer_feedback.php" class="ps-5 "><i class="bi bi-dot"></i> Feedback</a>
            </div>
            <a href="#csmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-text"></i> Contract & SLA</span><i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse " id="csmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
                <a href="admin_contracts.php" class="ps-5 "><i class="bi bi-dot"></i> Contracts</a>
                <a href="Admin_shipments.php" class="ps-5 "><i class="bi bi-dot"></i> SLA Monitor</a>
            </div>
            <a href="E-Doc.php" class="active"><i class="bi bi-folder2-open"></i> E-Docs</a>
            <a href="admin_completed.php"><i class="bi bi-check-circle-fill"></i> Completed Trans.</a>
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
                    <h5 class="fw-bold mb-0 text-primary">Centralized Documentation</h5>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if ($is_unlocked): ?>
                    <a href="?action=lock" class="btn btn-outline-danger btn-sm rounded-pill px-3 me-3">
                        <i class="bi bi-lock-fill me-1"></i> Lock Vault
                    </a>
                <?php endif; ?>
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
                        <h4 class="fw-bold mb-1">Restricted Access</h4>
                        <p class="text-muted small mb-4">Enter vault password to view sensitive documents.</p>

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
                                    Unlock E-Docs <i class="bi bi-arrow-right-short"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>



            <?php if ($msg != ''): ?>
                <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show"><?php echo $msg; ?><button
                        type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i class="bi bi-cloud-upload"></i> Upload Document
                        </button>
                        <form class="d-flex gap-2" method="GET">
                            <input type="text" name="search" class="form-control" placeholder="Search ID or Name..."
                                value="<?php echo $search; ?>">
                            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th>Reference ID</th>
                                    <th>Client / Sender</th>
                                    <th>Document Type</th>
                                    <th>File Name</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($current_page_docs) > 0): ?>
                                    <?php foreach ($current_page_docs as $doc): ?>
                                        <tr>
                                            <td>
                                                <?php if ($doc['category'] == 'Manual'): ?>
                                                    <span class="badge bg-secondary">Manual</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Auto-Gen</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-primary"><?php echo $doc['ref_id']; ?></td>
                                            <td><?php echo $doc['name']; ?></td>
                                            <td>
                                                <?php
                                                $badge = 'secondary';
                                                if ($doc['doc_type'] == 'print_invoice')
                                                    $badge = 'info text-dark';
                                                if ($doc['doc_type'] == 'Contract')
                                                    $badge = 'warning text-dark';
                                                ?>
                                                <span class="badge bg-<?php echo $badge; ?>"><?php echo $doc['doc_type']; ?></span>
                                            </td>
                                            <td class="small font-monospace"><?php echo $doc['file_name']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($doc['date'])); ?></td>
                                            <td class="text-end">
                                                <?php if ($doc['is_virtual']): ?>
                                                    <button onclick="window.open('<?php echo $doc['link']; ?>', '_blank')"
                                                        class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-printer-fill"></i> View
                                                    </button>
                                                <?php else: ?>
                                                    <a href="<?php echo $doc['link']; ?>" target="_blank"
                                                        class="btn btn-sm btn-outline-dark">
                                                        <i class="bi bi-download"></i> DL
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No documents found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link"
                                            href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <div class="text-center small text-muted">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?> (Total <?php echo $total_results; ?> docs)
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <div class="modal fade" id="uploadModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Upload Document</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Select Shipment</label>
                                    <input class="form-control" list="shipmentOptions" name="tracking_number"
                                        placeholder="Search Tracking #..." required>
                                    <datalist id="shipmentOptions">
                                        <?php
                                        if ($shipment_list && $shipment_list->num_rows > 0) {
                                            while ($s = $shipment_list->fetch_assoc()) {
                                                echo "<option value='" . $s['id'] . "'>" . $s['sender_name'] . "</option>";
                                            }
                                        }
                                        ?>
                                    </datalist>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Document Type</label>
                                    <select name="doc_type" class="form-select" required>
                                        <option value="">Select Type</option>
                                        <option value="Proof of Delivery">Proof of Delivery (POD)</option>
                                        <option value="print_invoice">print_invoice / BOL</option>
                                        <option value="Commercial Invoice">Commercial Invoice</option>
                                        <option value="Packing List">Packing List</option>
                                        <option value="Permit">Permit / Certificate</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Select File</label>
                                    <input type="file" name="doc_file" class="form-control" required
                                        accept=".jpg,.jpeg,.png,.pdf,.docx">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" name="btn_upload" class="btn btn-primary">Upload Now</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark Mode
        const toggle = document.getElementById('adminThemeToggle');
        const body = document.body;
        if (localStorage.getItem('theme') === 'dark') { body.classList.add('dark-mode'); toggle.checked = true; }
        toggle.addEventListener('change', () => {
            if (toggle.checked) { body.classList.add('dark-mode'); localStorage.setItem('theme', 'dark'); }
            else { body.classList.remove('dark-mode'); localStorage.setItem('theme', 'light'); }
        });

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

        // Notifications
        function fetchNotifications() {
            fetch('api/get_notifications.php').then(r => r.json()).then(data => {
                const badge = document.getElementById('notifBadge');
                const list = document.getElementById('notifList');
                if (data.count > 0) { badge.innerText = data.count; badge.style.display = 'inline-block'; }
                else { badge.style.display = 'none'; }
                let html = '';
                if (data.data.length > 0) {
                    data.data.forEach(notif => {
                        let bgClass = notif.is_read == 0 ? 'bg-light' : '';
                        html += `<li><a class="dropdown-item ${bgClass} p-2 border-bottom" href="#"><small class="fw-bold d-block">${notif.title}</small><small class="text-muted">${notif.message}</small></a></li>`;
                    });
                } else { html = '<li class="text-center p-3 text-muted small">Checking...</li>'; }
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

            // Increment idle time every second
            setInterval(() => {
                idleTime++;
                if (idleTime >= 10) { // 10 Seconds Limit
                    window.location.href = '?action=lock';
                }
            }, 1000);

            // Reset idle time on activity
            function resetTimer() { idleTime = 0; }
            window.onload = resetTimer;
            window.onmousemove = resetTimer;
            window.onmousedown = resetTimer;
            window.ontouchstart = resetTimer;
            window.onclick = resetTimer;
            window.onkeypress = resetTimer;
        <?php endif; ?>
    </script>


</body>

</html>