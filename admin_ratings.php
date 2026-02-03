<?php
include("connection.php");
include("darkmode.php");
include("session.php");
requireRole('admin');
include('loading.html');

// FETCH RATINGS
// Joining shipments to get details, accounts to get username
$query = "
    SELECT r.*, u.username, s.id as shipment_real_id, s.sender_name, s.destination_address
    FROM shipment_ratings r
    JOIN accounts u ON r.user_id = u.id
    JOIN shipments s ON r.shipment_id = s.id
    ORDER BY r.created_at DESC
";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("SQL Error: " . mysqli_error($conn));
}

// Calculate Stats
$avgQuery = mysqli_query($conn, "
    SELECT AVG(r.rating) as avg_rate, COUNT(*) as total 
    FROM shipment_ratings r
    JOIN accounts u ON r.user_id = u.id
    JOIN shipments s ON r.shipment_id = s.id
");
$stats = mysqli_fetch_assoc($avgQuery);
$avgRating = number_format($stats['avg_rate'], 1);
$totalRatings = $stats['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shipment Ratings | Admin</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  
  <style>
    :root {
      --primary: #4e73df; --secondary: #f8f9fc; --dark-bg: #1e1e2f; --dark-card: #2b2b40;
      --light-text: #f8f9fa; --radius: 0.8rem; --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--secondary); transition: 0.3s; }
    body.dark-mode { background-color: var(--dark-bg); color: var(--light-text); }
    
    /* Sidebar & Layout */
    .sidebar { width: 250px; height: 100vh; background: #2c3e50; color: white; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; transition: 0.3s; z-index: 1000; }
    .sidebar.collapsed { transform: translateX(-100%); }
    .content { margin-left: 250px; padding: 2rem; transition: 0.3s; }
    .content.expanded { margin-left: 0; }
    
    .sidebar a { display: flex; align-items: center; gap: 10px; padding: 12px 20px; text-decoration: none; color: rgba(255,255,255,0.8); border-left: 4px solid transparent; transition: 0.2s; }
    .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); border-left-color: #fff; color: white; }
    
    /* Components */
    .header { background: white; border-radius: var(--radius); box-shadow: var(--shadow); padding: 1rem 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
    .card { border: none; border-radius: var(--radius); background: white; box-shadow: var(--shadow); transition: 0.3s; }
    
    /* Dark Mode Overrides */
    body.dark-mode .header, body.dark-mode .card, body.dark-mode .list-group-item { background: var(--dark-card); color: var(--light-text); border-color: #444; }
    body.dark-mode .table { color: var(--light-text); }
    body.dark-mode .table thead th { background: #3a3b45; color: white; border: none; }
    body.dark-mode .table td { border-color: #444; }
    
    /* Toggle Switch */
    .theme-switch { position: relative; display: inline-block; width: 40px; height: 20px; }
    .theme-switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; border-radius: 34px; transition: .4s; }
    .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background-color: white; border-radius: 50%; transition: .4s; }
    input:checked+.slider { background-color: var(--primary); }
    input:checked+.slider:before { transform: translateX(20px); }
  </style>
</head>

<body>

  <!-- SIDEBAR (Matching admin.php) -->
  <div class="sidebar" id="sidebar">
    <div>
      <div class="text-center p-3 border-bottom border-secondary">
        <img src="Remorig.png" alt="Logo" style="width: 100px;">
        <h6 class="mt-2 mb-0 text-light">CORE ADMIN</h6>
      </div>
      <nav class="mt-3" id="sidebarAccordion">
        <a href="admin.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      
        <a href="#crmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between">
            <span><i class="bi bi-people"></i> CRM</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse show" id="crmSubmenu" data-bs-parent="#sidebarAccordion" style="background: rgba(0,0,0,0.2);">
            <a href="CRM.php" class="ps-4"><i class="bi bi-dot"></i> CRM Dashboard</a>clear
            <a href="customer_feedback.php" class="ps-4"><i class="bi bi-dot"></i> Customer Feedback</a>
            <a href="admin_ratings.php" class="ps-4 active"><i class="bi bi-dot"></i> Shipment Ratings</a>
        </div>

        <a href="#csmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between">
            <span><i class="bi bi-file-text"></i> Contract & SLA</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="csmSubmenu" data-bs-parent="#sidebarAccordion" style="background: rgba(0,0,0,0.2);">
            <a href="admin_contracts.php" class="ps-4"><i class="bi bi-dot"></i> Manage Contracts</a>
            <a href="admin_shipments.php" class="ps-4"><i class="bi bi-dot"></i> SLA Monitoring</a>
        </div>

        <a href="E-Doc.php"><i class="bi bi-folder2-open"></i> E-Documentation</a>
        <a href="admin_completed.php"><i class="bi bi-check-circle-fill"></i> Completed Trans.</a>
        <a href="BIFA.php"><i class="bi bi-graph-up"></i> BI & Freight Analytics</a>
        <a href="admin_reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports Generation</a>
        <a href="activity-log.php"><i class="bi bi-clock-history"></i> Activity Log</a>
        
        <a href="#archiveSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between">
            <span><i class="bi bi-archive"></i> Archived</span> <i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="archiveSubmenu" data-bs-parent="#sidebarAccordion" style="background: rgba(0,0,0,0.2);">
            <a href="Archive.php" class="ps-4"><i class="bi bi-dot"></i> Documents</a>
            <a href="Archive_CRM.php" class="ps-4"><i class="bi bi-dot"></i> Customers</a>
        </div>

        <a href="logout.php" class="border-top mt-3"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </nav>
    </div>
  </div>

  <div class="content" id="mainContent">
    
    <!-- HEADER -->
    <div class="header">
      <div class="d-flex align-items-center gap-3">
        <i class="bi bi-list fs-4" id="hamburger" style="cursor: pointer;"></i>
        <h5 class="fw-bold mb-0">Shipment Ratings</h5>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-moon-fill small"></i>
            <label class="theme-switch">
              <input type="checkbox" id="adminThemeToggle"><span class="slider"></span>
            </label>
        </div>
      </div>
    </div>

    <!-- STATS ROW -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card p-3 border-start border-4 border-warning h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-uppercase text-muted fw-bold">Avg. Rating</small>
                        <h4 class="mb-0 fw-bold"><?= $avgRating ?> <small class="fs-6 text-muted">/ 5</small></h4>
                    </div>
                    <i class="bi bi-star-fill text-warning fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card p-3 border-start border-4 border-primary h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-uppercase text-muted fw-bold">Total Reviews</small>
                        <h4 class="mb-0 fw-bold"><?= $totalRatings ?></h4>
                    </div>
                    <i class="bi bi-chat-left-text-fill text-primary fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- RATINGS TABLE -->
    <div class="card shadow">
        <div class="card-header bg-transparent py-3 border-bottom">
            <h6 class="m-0 fw-bold text-primary">All Reviews</h6>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Shipment ID</th>
                        <th>User</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Details</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-light text-dark border">
                                SHIP-<?= str_pad($row['shipment_real_id'], 5, "0", STR_PAD_LEFT) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-secondary rounded-circle text-white d-flex align-items-center justify-content-center" style="width:30px;height:30px;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div>
                                    <span class="d-block fw-bold small">
                                        <?= $row['is_anonymous'] ? 'Anonymous' : htmlspecialchars($row['username']) ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="text-warning small">
                                <?php 
                                for($i=1; $i<=5; $i++) {
                                    echo ($i <= $row['rating']) ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                                }
                                ?>
                            </div>
                            <small class="fw-bold text-muted"><?= $row['rating'] ?>.0</small>
                        </td>
                        <td>
                            <p class="mb-0 small text-wrap" style="max-width: 300px;">
                                <?= !empty($row['comment']) ? htmlspecialchars($row['comment']) : '<em class="text-muted">No comment</em>' ?>
                            </p>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-info rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?= $row['id'] ?>">
                                View Breakdown
                            </button>
                            <div class="collapse mt-2 p-2 bg-light rounded" id="details-<?= $row['id'] ?>">
                                <div class="d-flex flex-wrap gap-3 small text-muted">
                                    <span><i class="bi bi-truck"></i> Speed: <?= $row['rating_speed'] ?></span>
                                    <span><i class="bi bi-box-seam"></i> Handling: <?= $row['rating_handling'] ?></span>
                                    <span><i class="bi bi-chat"></i> Comm: <?= $row['rating_communication'] ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small class="text-muted"><?= date('M d, Y', strtotime($row['created_at'])) ?></small>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php if(mysqli_num_rows($result) == 0): ?>
                <div class="text-center p-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                    No ratings found.
                </div>
            <?php endif; ?>
        </div>
    </div>

  </div> 
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // UI INIT
    if(typeof initDarkMode === 'function') initDarkMode("adminThemeToggle", "adminDarkMode");
    
    document.getElementById('hamburger').addEventListener('click', () => {
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.getElementById('mainContent').classList.toggle('expanded');
    });
  </script>
</body>
</html>
