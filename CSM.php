<?php
include 'connection.php';
include 'session.php';
requireRole('admin');

/* Contract Stats API for AJAX — do this before any output */
if (isset($_GET['action']) && $_GET['action'] === 'stats') {
    // Make sure nothing else leaked into output
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    $getCount = function (string $sql) use ($conn): int {
        $res = $conn->query($sql);
        if (!$res)
            return 0;
        $row = $res->fetch_assoc();
        return (int) array_values($row)[0];
    };

    $totalContracts = $getCount("SELECT COUNT(*) FROM csm");
    $totalActive = $getCount("SELECT COUNT(*) FROM csm WHERE status = 'Active'");
    $expiringSoon = $getCount("SELECT COUNT(*) FROM csm WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)");
    $totalCompliant = $getCount("SELECT COUNT(*) FROM csm WHERE sla_compliance = 'Compliant'");

    echo json_encode([
        'totalContracts' => $totalContracts,
        'totalActive' => $totalActive,
        'expiringSoon' => $expiringSoon,
        'totalCompliant' => $totalCompliant,
    ]);
    exit;
}

/* Initial stats for first render (optional; used by PHP echoes) */
$sql = "SELECT COUNT(*) AS total FROM csm";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalContracts = (int) $row['total'];

$sql = "SELECT COUNT(*) AS total_active FROM csm WHERE status = 'Active'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalActive = (int) $row['total_active'];

$sql = "SELECT COUNT(*) AS expiring_soon 
        FROM csm 
        WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$expiringSoon = (int) $row['expiring_soon'];

$sql = "SELECT COUNT(*) AS total_compliant 
        FROM csm 
        WHERE sla_compliance = 'Compliant'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalCompliant = (int) $row['total_compliant'];

/* Add contract */
$contract_limit = 100;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contract'])) {
    // read safely (no undefined index warnings) and trim
    $contract_id = trim($_POST['contract_id'] ?? '');
    $client_name = trim($_POST['client_name'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $sla_compliance = trim($_POST['sla_compliance'] ?? '');

    // simple validation
    $errors = [];
    if ($contract_id === '')
        $errors[] = 'Contract ID';
    if ($client_name === '')
        $errors[] = 'Client Name';
    if ($start_date === '')
        $errors[] = 'Start Date';
    if ($end_date === '')
        $errors[] = 'End Date';
    if ($status === '')
        $errors[] = 'Status';
    if ($sla_compliance === '')
        $errors[] = 'SLA Compliance';

    if (!empty($errors)) {
        $err = implode(', ', $errors);
        echo "<script>alert('Please fill the required fields: $err');</script>";
    } else {
        // Check total contracts
        $check_sql = "SELECT COUNT(*) AS total FROM csm";
        $res_check = $conn->query($check_sql);
        $row_check = $res_check->fetch_assoc();
        $total_contracts = (int) ($row_check['total'] ?? 0);

        // Delete oldest if limit reached
        if ($total_contracts >= $contract_limit) {
            $oldest_sql = "SELECT contract_id, client_name FROM csm ORDER BY start_date ASC LIMIT 1";
            $res_old = $conn->query($oldest_sql);
            $oldest = $res_old ? $res_old->fetch_assoc() : null;

            $delete_sql = "DELETE FROM csm ORDER BY start_date ASC LIMIT 1";
            $conn->query($delete_sql);

            if ($oldest) {
                $activity = "Deleted oldest contract (limit $contract_limit reached): {$oldest['contract_id']} - {$oldest['client_name']}";
                $stmtLog = $conn->prepare("INSERT INTO admin_activity (module, activity, status) VALUES (?, ?, ?)");
                if ($stmtLog) {
                    $module = 'CSM';
                    $statusLog = 'Success';
                    $stmtLog->bind_param('sss', $module, $activity, $statusLog);
                    $stmtLog->execute();
                    $stmtLog->close();
                }
            }
        }

        // Use prepared statement to insert safely
        $stmt = $conn->prepare("INSERT INTO csm (contract_id, client_name, start_date, end_date, status, sla_compliance) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssss', $contract_id, $client_name, $start_date, $end_date, $status, $sla_compliance);
            if ($stmt->execute()) {
                // Log add (prepared)
                $activity = "Added new contract: $contract_id - $client_name";
                $stmtLog = $conn->prepare("INSERT INTO admin_activity (module, activity, status) VALUES (?, ?, ?)");
                if ($stmtLog) {
                    $module = 'CSM';
                    $statusLog = 'Success';
                    $stmtLog->bind_param('sss', $module, $activity, $statusLog);
                    $stmtLog->execute();
                    $stmtLog->close();
                }

                // show success and trigger dashboard refresh once page loads
                echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Contract Added',
                html: 'Contract \"$contract_id - $client_name\" added successfully!',
                customClass: {
                    popup: 'swal-small'
                },
                timer: 2000,
                showConfirmButton: false,
                timerProgressBar: true
            }).then(() => {
                if (typeof updateDashboard === 'function') updateDashboard();
            });
        });
    </script>";
            } else {
                $errorMsg = $stmt->error;
                // log failure
                $stmtLog = $conn->prepare("INSERT INTO admin_activity (module, activity, status) VALUES (?, ?, ?)");
                if ($stmtLog) {
                    $module = 'CSM';
                    $activity = "Failed to add contract: $contract_id";
                    $statusLog = 'Failed';
                    $stmtLog->bind_param('sss', $module, $activity, $statusLog);
                    $stmtLog->execute();
                    $stmtLog->close();
                }
                echo "<div style='color:red'>Error inserting contract: " . htmlspecialchars($errorMsg) . "</div>";
            }
            $stmt->close();
        } else {
            echo "<div style='color:red'>Prepare failed: " . htmlspecialchars($conn->error) . "</div>";
        }
    }
}


/* Fetch all contracts */
$result = $conn->query("SELECT * FROM csm ORDER BY start_date DESC");

/* Load anything that might output HTML/JS AFTER the stats handler */
include("darkmode.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CORE3 Customer Relationship & Business Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --dark-bg: #1a1a2e;
            --dark-card: #16213e;
            --text-light: #f8f9fa;
            --text-dark: #212529;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --border-radius: 0.35rem;
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            overflow-x: hidden;
            background-color: var(--secondary-color);
            color: var(--text-dark);
        }

        body.dark-mode {
            --secondary-color: var(--dark-bg);
            background-color: var(--secondary-color);
            color: var(--text-light);
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #2c3e50;
            color: white;
            padding: 0;
            transition: all 0.3s ease;
            z-index: 1000;
            transform: translateX(0);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar .logo {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo img {
            max-width: 100%;
            height: auto;
        }

        .system-name {
            padding: 0.5rem 1.5rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
        }

        .sidebar a {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 3px solid white;
        }


        /* Make SweetAlert smaller */
        .swal-small {
            width: 400px !important;
            /* smaller width */
            font-size: 0.8rem !important;
            /* smaller text */
            padding: 1rem !important;
            /* less padding */
        }

        .swal-small .swal2-title {
            font-size: 0.85rem !important;
            /* smaller title */
        }

        .swal-small .swal2-html-container {
            font-size: 0.8rem !important;
            /* smaller body text */
        }



        .admin-feature {
            background-color: rgba(0, 0, 0, 0.1);
        }

        /* Main Content */
        .content {
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        .content.expanded {
            margin-left: 0;
        }

        /* Header */
        .header {
            background-color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dark-mode .header {
            background-color: var(--dark-card);
            color: var(--text-light);
        }

        .hamburger {
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        .system-title {
            color: var(--primary-color);
            font-size: 1rem;
        }

        /* Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card {
            background-color: white;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
        }

        .dark-mode .card {
            background-color: var(--dark-card);
            color: var(--text-light);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.75rem 0 rgba(58, 59, 69, 0.2);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        /* Select Section */
        .Select-section {
            background-color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        .Contract-content {
            display: flex;
        }

        .contract-form {
            text-align: start;
            justify-content: center;
        }

        .contract-form .btn {
            display: block;
            margin: 1rem auto 0 auto;
        }

        .C-form {
            display: flex;
            margin-top: 1rem;
        }

        .contract-form input,
        .contract-form select {
            width: 590px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            background-color: white;
            margin: 0.5rem 1rem 0 0;
        }

        .dark-mode .contract-form input,
        .dark-mode .contract-form select {
            background-color: #2a3a5a;
            border-color: #3a4b6e;
            color: var(--text-light);
        }

        .btn {
            width: 260px;
            padding: 0.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 1rem;
        }

        .addcontract {
            background-color: var(--primary-color);
            color: white;
        }

        .addcontract:hover {
            background-color: #3a5bc7;
        }

        .form input,
        .form select {
            width: 280px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            background-color: white;
        }

        .dark-mode .form input,
        .dark-mode .form select {
            background-color: #2a3a5a;
            border-color: #3a4b6e;
            color: var(--text-light);
        }

        .dark-mode .Select-section {
            background-color: var(--dark-card);
            color: var(--text-light);
        }

        /* Table Section */
        .table-section1 {
            background-color: white;
            text-align: center;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        .dark-mode .table-section1 {
            background-color: var(--dark-card);
            color: var(--text-light);
        }

        .table-section2 {
            background-color: white;
            text-align: center;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .dark-mode .table-section2 {
            background-color: var(--dark-card);
            color: var(--text-light);
        }

        table {
            width: 100%;
            margin-top: 1rem;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .dark-mode th,
        .dark-mode td {
            border-bottom-color: #3a4b6e;
        }

        thead {
            background-color: var(--primary-color);
            color: white;
        }

        /* Theme Toggle */
        .theme-toggle-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--primary-color);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
     <!-- sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="Remorig.png" alt="SLATE Logo">
        </div>
        <h4 class="mb-3" style="text-align: center;">CORE TRANSACTION 3</h4>
        <a href="admin.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a href="CRM.php" ><i class="bi bi-people me-2"></i> CRM</a>
        <a href="CSM.php"><i class="bi bi-file-text me-2"></i> Contract & SLA</a>
        <a href="E-Doc.php"><i class="bi bi-folder2-open me-2"></i> E-Docs</a>
        <a href="BIFA.php"><i class="bi bi-graph-up me-2"></i> BI & Freight</a>
        <a href="CPN.php"><i class="bi bi-globe me-2"></i> Customer Portal</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
    </div>

    <div class="content" id="mainContent">
        <div class="header">
            <div class="hamburger" id="hamburger">☰</div>
            <div>
                <h1>Contract & SLA Monitoring</h1>
            </div>
            <div class="theme-toggle-container">
                <span class="theme-label">Dark Mode</span>
                <label class="theme-switch">
                    <input type="checkbox" id="adminThemeToggle">
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <h3>Total Contracts</h3>
                <div class="stat-value" id="totalContracts"><?= $totalContracts; ?></div>
            </div>
            <div class="card">
                <h3>Active Contracts</h3>
                <div class="stat-value" id="totalActive"><?= $totalActive; ?></div>
            </div>
            <div class="card">
                <h3>Expiring Soon</h3>
                <div class="stat-value" id="expiringSoon"><?= $expiringSoon; ?></div>
            </div>
            <div class="card">
                <h3>SLA Compliance</h3>
                <div class="stat-value" id="totalCompliant"><?= $totalCompliant; ?></div>
            </div>
        </div>


        <!-- Add Contract Form -->
        <div class="Select-section">
            <h3>Add New Contract</h3>
            <div class="Contract-content">
                <form method="POST" class="contract-form row g-3">
                    <div class="col-md-6">
                        <label for="contractIdInput" class="form-label">Contract ID</label>
                        <input type="text" name="contract_id" id="contractIdInput" class="form-control" placeholder="Contract ID" required readonly>
                    </div>
                    <script>

                    </script>
                    <div class="col-md-6">
                        <label for="clientNameSelect" class="form-label">Client</label>
                        <select name="client_name" id="clientNameSelect" class="form-select" required>
                            <option value="">-- Select Client --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="startDateInput" class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="startDateInput" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="endDateInput" class="form-label">End Date</label>
                        <input type="date" name="end_date" id="endDateInput" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="statusSelect" class="form-label">Status</label>
                        <select name="status" id="statusSelect" class="form-select" required>
                            <option value="">-- Select Status --</option>
                            <option value="Active">Active</option>
                            <option value="Expired">Expired</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="slaComplianceSelect" class="form-label">SLA Compliance</label>
                        <select name="sla_compliance" id="slaComplianceSelect" class="form-select" required>
                            <option value="">-- SLA Compliance --</option>
                            <option value="Compliant">Compliant</option>
                            <option value="Non-Compliant">Non-Compliant</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_contract" class="btn btn-primary w-100">Add Contract</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Contracts List -->
        <div class="table-section1">
            <h3>Contracts List</h3>
            <table id="contractsTable" class="table-selection1">
                <thead>
                    <tr>
                        <th>Contract ID</th>
                        <th>Client</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>SLA Compliance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['contract_id']); ?></td>
                            <td><?= htmlspecialchars($row['client_name']); ?></td>
                            <td><?= htmlspecialchars($row['start_date']); ?></td>
                            <td><?= htmlspecialchars($row['end_date']); ?></td>
                            <td><?= htmlspecialchars($row['status']); ?></td>
                            <td><?= htmlspecialchars($row['sla_compliance']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        initDarkMode("adminThemeToggle", "adminDarkMode");

        document.getElementById('hamburger').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });

        function updateDashboard() {
            fetch('?action=stats')
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    document.getElementById('totalContracts').textContent = data.totalContracts ?? 0;
                    document.getElementById('totalActive').textContent = data.totalActive ?? 0;
                    document.getElementById('expiringSoon').textContent = data.expiringSoon ?? 0;
                    document.getElementById('totalCompliant').textContent = data.totalCompliant ?? 0;
                })
                .catch(err => console.error('Error fetching stats:', err));
        }

        // Update immediately and every 5 seconds
        updateDashboard();
        setInterval(updateDashboard, 5000);




        fetch('/api/accounts/profile.php')
            .then(r => r.json())
            .then(j => {
                if (j.success) {
                    document.getElementById('profileImg').src = j.data.profile_image;
                    // use other profile data as needed
                }
            });


        // stats
        fetch('/api/csm/stats.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('totalContracts').textContent = data.totalContracts;
                    document.getElementById('totalActive').textContent = data.totalActive;
                    // ...
                }
            });

        //   list contract
        fetch('/api/csm/contracts.php')
            .then(r => r.json())
            .then(console.log);


        // add contact to admin
        fetch('/api/csm/contracts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                contract_id: 'ABC123',
                client_name: 'Client A',
                start_date: '2025-09-01',
                end_date: '2025-10-01',
                status: 'Active',
                sla_compliance: 'Compliant'
            })
        }).then(r => r.json()).then(console.log);
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const clientSelect = document.getElementById('clientNameSelect');

            fetch('/Core3/api/crm/accounts.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        data.data.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.username; // you can also use user.id if you prefer
                            option.textContent = user.username;
                            clientSelect.appendChild(option);
                        });
                    }
                })
                .catch(err => console.error('Error fetching clients:', err));
        });



        document.addEventListener('DOMContentLoaded', function() {
            // Generate contract ID: CSM-YYYYMMDD-HHMMSS-RND
            function generateContractId() {
                const now = new Date();
                const pad = n => n.toString().padStart(2, '0');
                const dateStr = now.getFullYear() +
                    pad(now.getMonth() + 1) +
                    pad(now.getDate()) + '-' +
                    pad(now.getHours()) +
                    pad(now.getMinutes()) +
                    pad(now.getSeconds());
                const rand = Math.floor(Math.random() * 9000) + 1000;
                return `CSM-${dateStr}-${rand}`;
            }
            document.getElementById('contractIdInput').value = generateContractId();
        });
    </script>

</body>

</html>