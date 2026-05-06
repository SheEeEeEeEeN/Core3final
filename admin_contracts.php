<?php
// admin_contracts.php - PROFESSIONAL UI VERSION (UPDATED SIDEBAR)
include("connection.php");
include("darkmode.php");
include('session.php');
requireRole('admin');

// 1. FETCH MASTER RULES (Contract ID = 0)
$masterRulesQ = mysqli_query($conn, "SELECT * FROM sla_policies WHERE contract_id = 0");

// 2. FETCH CLIENT CONTRACTS (Generated ones)
$contractsQ = mysqli_query($conn, "SELECT c.*, a.email, a.username, a.profile_image 
                                   FROM contracts c 
                                   LEFT JOIN accounts a ON c.user_id = a.id 
                                   WHERE c.id != 0 ORDER BY c.id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Management | Core Transaction</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
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
        .nav-tabs { border-bottom: 2px solid var(--border-color); margin-bottom: 1.5rem; }
        .nav-link { color: #64748b; font-weight: 600; padding: 10px 20px; border: none; border-bottom: 3px solid transparent; }
        .nav-link.active { color: var(--primary-color); background: transparent; border-bottom-color: var(--primary-color); }
        .nav-link:hover { border-color: transparent; color: var(--primary-color); }
        .client-avatar { width: 35px; height: 35px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #64748b; }
        body.dark-mode .nav-link { color: var(--dark-text-sec); }
        body.dark-mode .nav-link:hover { color: white; }
        body.dark-mode .nav-tabs { border-bottom-color: var(--dark-border); }
        body.dark-mode .client-avatar { background: var(--dark-bg); color: white; border: 1px solid var(--dark-border); }
        
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
            <a href="admin_contracts.php" class="ps-5 active"><i class="bi bi-dot"></i> Contracts</a>
            <a href="Admin_shipments.php" class="ps-5 "><i class="bi bi-dot"></i> SLA Monitor</a>
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
                <h5 class="fw-bold mb-0 text-primary">Contract Registry</h5>
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
<div class="card border-0 shadow-none bg-transparent p-0">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="contracts-tab" data-bs-toggle="tab" data-bs-target="#contracts-pane" type="button" role="tab">
                        <i class="bi bi-folder2-open me-2"></i> Client Contracts
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-pane" type="button" role="tab">
                        <i class="bi bi-sliders me-2"></i> Master SLA Configuration
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="myTabContent">
                
                <div class="tab-pane fade show active" id="contracts-pane" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold text-secondary m-0">Active Agreements</h5>
                                <button class="btn btn-primary" onclick="syncUsers()">
                                    <i class="bi bi-arrow-repeat me-1"></i> Sync / Generate All
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Contract Ref</th>
                                            <th>Client Name</th>
                                            <th>Validity</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(mysqli_num_rows($contractsQ) > 0): ?>
                                            <?php while($row = mysqli_fetch_assoc($contractsQ)): ?>
                                            <tr>
                                                <td class="fw-bold text-primary font-monospace">
                                                    <?php echo $row['contract_number']; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="client-avatar">
                                                            <?php echo strtoupper(substr($row['client_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo $row['client_name']; ?></div>
                                                            <div class="small text-muted"><?php echo $row['email']; ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="small">
                                                    <div>From: <?php echo date('M d, Y', strtotime($row['start_date'])); ?></div>
                                                    <div class="text-muted">To: <?php echo date('M d, Y', strtotime($row['end_date'])); ?></div>
                                                </td>
                                                <td><span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Active</span></td>
                                                <td class="text-end">
                                                    <a href="contract_print.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                                        <i class="bi bi-printer me-1"></i> Print
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted">No contracts found. Click Sync to generate.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="settings-pane" role="tabpanel">
                    <div class="card border-primary border-opacity-25">
                        <div class="card-body">
                            <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                                <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                                <div>
                                    <strong>Master Configuration:</strong> Rules set here will be automatically applied to <u>ALL</u> newly generated contracts.
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold m-0">Standard Shipping Rules</h6>
                                <button class="btn btn-outline-primary btn-sm" onclick="openRulesModal()">
                                    <i class="bi bi-plus-lg"></i> Add New Rule
                                </button>
                            </div>

                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Origin Group</th>
                                        <th>Destination Group</th>
                                        <th>Committed Lead Time</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($masterRulesQ) > 0): ?>
                                        <?php while($rule = mysqli_fetch_assoc($masterRulesQ)): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo $rule['origin_group']; ?></td>
                                            <td><i class="bi bi-arrow-right text-muted me-2"></i> <?php echo $rule['destination_group']; ?></td>
                                            <td><span class="badge bg-warning text-dark"><?php echo $rule['max_days']; ?> Days</span></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-light text-danger" onclick="deleteRule(<?php echo $rule['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center py-3 text-muted">No global rules configured.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="rulesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Configure Global Rule</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addRuleForm">
                        <input type="hidden" name="action" value="add_rule">
                        <input type="hidden" name="contract_id" value="0">
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Origin</label>
                                <select class="form-select" name="origin_group" required>
                                    <option value="Metro Manila">Metro Manila</option>
                                    <option value="Luzon">Luzon</option>
                                    <option value="Visayas">Visayas</option>
                                    <option value="Mindanao">Mindanao</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Destination</label>
                                <select class="form-select" name="destination_group" required>
                                    <option value="Metro Manila">Metro Manila</option>
                                    <option value="Luzon">Luzon</option>
                                    <option value="Visayas">Visayas</option>
                                    <option value="Mindanao">Mindanao</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="small fw-bold text-muted">Max Lead Time (Days)</label>
                                <input type="number" class="form-control" name="max_days" value="3" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary w-100" onclick="submitRule()">Save Configuration</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Init UI
        if (typeof initDarkMode === 'function') initDarkMode("adminThemeToggle", "adminDarkMode");
        
        // Sidebar Toggle
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

        // Rules Logic
        const rulesModal = new bootstrap.Modal(document.getElementById('rulesModal'));

        function openRulesModal() { rulesModal.show(); }

        function submitRule() {
            const form = document.getElementById('addRuleForm');
            if(!form.checkValidity()) { form.reportValidity(); return; }

            const fd = new FormData(form);
            
            fetch('api/admin_contracts_api.php', { method: 'POST', body: fd })
            .then(r => {
                if (!r.ok) { throw new Error("HTTP error " + r.status); }
                return r.json();
            })
            .then(d => {
                if(d.success) { 
                    // BETTER SUCCESS ALERT
                    Swal.fire({
                        icon: 'success',
                        title: 'Rule Added!',
                        text: 'The configuration has been saved successfully.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                }
                else { 
                    // BETTER ERROR ALERT
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: d.error
                    });
                }
            })
            .catch(e => {
                console.error(e);
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Check console for details.'
                });
            });
        }

        function syncUsers() {
            // BETTER CONFIRMATION
            Swal.fire({
                title: 'Sync Users?',
                text: "Generate contracts for ALL new users based on Master Rules?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4e73df', // Your primary color
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, sync now!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'sync_all_users');
                    
                    // Show loading state
                    Swal.fire({
                        title: 'Syncing...',
                        text: 'Please wait while contracts are generated.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    fetch('api/admin_contracts_api.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sync Complete',
                            text: d.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    });
                }
            });
        }

        function deleteRule(id) {
            // BETTER DELETE CONFIRMATION
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('action', 'delete_rule');
                    fd.append('rule_id', id);
                    
                    fetch('api/admin_contracts_api.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => { 
                        if(d.success) {
                            Swal.fire(
                                'Deleted!',
                                'The rule has been removed.',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>