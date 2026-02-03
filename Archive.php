<?php
include('connection.php');
include("darkmode.php");
include('session.php');
include('loading.html');
requireRole('admin');

// 1. AUTO-DELETE (Older than 1 Year)
// Retrieve files to delete physically first
$old_files_q = $conn->query("SELECT filename FROM archive_doc WHERE archived_on < NOW() - INTERVAL 1 YEAR");
while($f = $old_files_q->fetch_assoc()){
    $filepath = "uploads/archive/" . $f['filename'];
    if(file_exists($filepath)) { @unlink($filepath); }
}
// Clean DB
$conn->query("DELETE FROM archive_doc WHERE archived_on < NOW() - INTERVAL 1 YEAR");

// 2. PERMANENT DELETE
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    // Get filename to delete physical file
    $dq = $conn->query("SELECT filename FROM archive_doc WHERE id = $id");
    if($dq->num_rows > 0){
        $drow = $dq->fetch_assoc();
        $filepath = "uploads/archive/" . $drow['filename'];
        if(file_exists($filepath)) { @unlink($filepath); }
    }
    
    $conn->query("DELETE FROM archive_doc WHERE id = $id");
    $_SESSION['alert'] = ['title' => 'Deleted!', 'text' => 'Document permanently deleted.', 'icon' => 'success'];
    header("Location: Archive.php");
    exit;
}

// Handle Archive Request (Moved from E-Doc.php)
if (isset($_GET['archive_track']) && isset($_GET['archive_file'])) {
    $track_num = mysqli_real_escape_string($conn, $_GET['archive_track']);
    $file_name = mysqli_real_escape_string($conn, $_GET['archive_file']);
    
    // fetch details including file_name to ensure we have the extension
    $is_virtual = isset($_GET['virtual']);
    $title = $track_num;
    $dtype = $_GET['dtype'] ?? ''; // Pass dtype for virtual
    $fname = $file_name;

    if (!$is_virtual) {
        // NORMAL FILE LOGIC
        $q = $conn->query("SELECT * FROM shipment_documents WHERE tracking_number='$track_num' AND file_name='$file_name'");
        if($q->num_rows > 0){
            $data = $q->fetch_assoc();
            $title = $data['tracking_number'];
            $dtype = $data['doc_type'];
            $fname = $data['file_name'];
            
            // Insert to Archive
            $ins = $conn->prepare("INSERT INTO archive_doc (title, doc_type, filename, archived_on) VALUES (?, ?, ?, NOW())");
            $ins->bind_param("sss", $title, $dtype, $fname);
            
            if($ins->execute()){
                // Move File
                $src = "uploads/" . $fname;
                $dst = "uploads/archive/" . $fname;
                if(!is_dir('uploads/archive')){ mkdir('uploads/archive', 0777, true); }
                
                if(file_exists($src)){
                    rename($src, $dst);
                }
                
                // Delete from Main
                $conn->query("DELETE FROM shipment_documents WHERE tracking_number='$track_num' AND file_name='$fname'");
                
                $module = "E-Documentation";
                $activity = "Archived document: " . $title . " (" . $fname . ")";
                $status = "Archived";
                $conn->query("INSERT INTO admin_activity (`module`, `activity`, `status`, `date`) 
                            VALUES ('$module', '$activity', '$status', NOW())");

                $_SESSION['alert'] = ['title' => 'Archived!', 'text' => 'Document archived successfully.', 'icon' => 'success'];
            } else {
                $_SESSION['alert'] = ['title' => 'Error!', 'text' => 'Database error during archiving.', 'icon' => 'error'];
            }
        }
    } else {
        // VIRTUAL ARCHIVE LOGIC (System Docs)
        $ins = $conn->prepare("INSERT INTO archive_doc (title, doc_type, filename, archived_on) VALUES (?, ?, ?, NOW())");
        $ins->bind_param("sss", $title, $dtype, $fname);
        
        if($ins->execute()){
             $module = "E-Documentation";
             $activity = "Archived system document: " . $title . " (" . $dtype . ")";
             $status = "Archived";
             $conn->query("INSERT INTO admin_activity (`module`, `activity`, `status`, `date`) 
                         VALUES ('$module', '$activity', '$status', NOW())");
 
             // $_SESSION['alert'] = ['title' => 'Archived!', 'text' => 'System document archived successfully.', 'icon' => 'success'];
        }
    }
    header("Location: E-Doc.php?msg=archived");
    exit;
}


// Handle restore
if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);

    $res = $conn->query("SELECT * FROM archive_doc WHERE id = $id");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();

        if (strpos($row['filename'], '_print_invoice') !== false || strpos($row['filename'], '_Contract') !== false) {
             // Virtual restore (just delete)
             $conn->query("DELETE FROM archive_doc WHERE id = $id");
        } else {
            // Physical restore
            $stmt = $conn->prepare("INSERT INTO shipment_documents (tracking_number, doc_type, file_name, uploaded_by, uploaded_at) VALUES (?, ?, ?, 'System - Restored', NOW())");
            $stmt->bind_param("sss", $row['title'], $row['doc_type'], $row['filename']);
            $stmt->execute();
    
            $archiveFile = "uploads/archive/" . $row['filename'];
            $uploadsFile = "uploads/" . $row['filename'];
            if (file_exists($archiveFile)) {
                rename($archiveFile, $uploadsFile);
            }
    
            $conn->query("DELETE FROM archive_doc WHERE id = $id");
        }

        $module = "E-Documentation";
        $activity = "Restored document: " . $row['title'];
        $status = "Restored";
        $conn->query("INSERT INTO admin_activity (`module`, `activity`, `status`, `date`) 
                    VALUES ('$module', '$activity', '$status', NOW())");

        $_SESSION['alert'] = ['title' => 'Restored!', 'text' => 'Document restored successfully.', 'icon' => 'success'];
        header("Location: Archive.php");
        exit;
    }
}

$result = $conn->query("SELECT * FROM archive_doc ORDER BY archived_on DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Archived Documents</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
      --border-radius: 1rem;
      --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
        background-color: #f8f9fa;
    }

    body.dark-mode {
        background-color: #1a1a2e;
        color: #f8f9fa;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      height: 100vh;
     background: #2c3e50;
      color: white;
      position: fixed;
      top: 0;
      left: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: all 0.3s ease;
      z-index: 1000;
    }

    .sidebar.collapsed {
      transform: translateX(-100%);
    }

    .sidebar .logo {
      text-align: center;
      padding: 1.2rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .sidebar .logo img {
      width: 120px;
    }

    .sidebar a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.85rem 1.2rem;
      text-decoration: none;
      color: rgba(255, 255, 255, 0.9);
      transition: 0.2s;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background: rgba(255, 255, 255, 0.2);
      color: #fff;
      border-left: 4px solid #fff;
    }
    
    /* Dropdown */
.dropdown-container .dropdown-toggle2 {
  cursor: pointer;
}

.dropdown-content {
  display: none;
  flex-direction: column;
  margin-left: 15px;
  border-left: 2px solid #444;
  margin-top: 5px;
  padding-left: 10px;
}

.dropdown-content a {
  font-size: 0.9rem;
  padding: 8px 10px;
  color: #aaa;
}

.dropdown-content a:hover {
  color: #fff;
}

.dropdown-content.show {
  display: flex;
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

    /* Main Content */
        .content {
          margin-left: var(--sidebar-width);
          padding: 2rem;
          transition: margin-left .3s;
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
            margin-left: 0.5rem;
        }

        .system-title {
            color: var(--primary-color);
            font-size: 1rem;
        }


    .theme-toggle-container {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .theme-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
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
        border-radius: 26px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #4e73df;
    }

    input:checked + .slider:before {
        transform: translateX(24px);
    }

    .table {
  overflow: hidden;
  background-color: #ffffff;
  color: #212529;
}

.table thead {
  background-color: #4e73df;
  color: #ffffff;
}
.table th {
    background-color: var(--primary-color);
            color: var(--text-light);
}

.table-hover tbody tr:hover {
  background-color: #f1f1f1;
}


/* Buttons */
.btn {
  border-radius: 0.3rem;
}
body.dark-mode .btn-primary {
  background-color: #3b55d9;
  border-color: #3b55d9;
}
body.dark-mode .btn-success {
  background-color: #1cc88a;
  border-color: #1cc88a;
}
body.dark-mode .btn:hover {
  opacity: 0.9;
}

/* 🌙 DARK MODE TABLE STYLES */
body.dark-mode .card {
  background-color: #16213e;
  color: #f8f9fa;
  border: 1px solid rgba(255,255,255,0.1);
  box-shadow: 0 0 10px rgba(0,0,0,0.3);
}

body.dark-mode .table {
  background-color: #1e2a47;
  color: #f8f9fa;
  border-collapse: separate;
  border-spacing: 0;
}

body.dark-mode .table thead {
  background-color: #4e73df;
  color: #ffffff;  
  letter-spacing: 0.03em;
}

body.dark-mode .table-hover tbody tr:hover {
  background-color: rgba(255,255,255,0.1);
  transition: background-color 0.2s ease-in-out;
}

body.dark-mode th,
body.dark-mode td {
  background-color: var(--primary-color);
            color: var(--text-light);
}

body.dark-mode td {
  background-color: transparent;
}


/* Buttons inside the table */
body.dark-mode .btn {
  color: #fff !important;
  border: none;
}

body.dark-mode .btn-primary {
  background-color: #3b55d9;
}

body.dark-mode .btn-success {
  background-color: #1cc88a;
}

body.dark-mode .btn-primary:hover {
  background-color: #324ac0;
}

body.dark-mode .btn-success:hover {
  background-color: #17b17a;
}

/* Empty table message */
body.dark-mode .table td.text-center {
  color: #cfd4e3;
}

    
    /* Theme Toggle */
    .theme-toggle-container {
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }

    .theme-switch {
      width: 50px;
      height: 25px;
      position: relative;
      display: inline-block;
    }

    .theme-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      inset: 0;
      background-color: #ccc;
      border-radius: 34px;
      transition: .4s;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 4px;
      bottom: 3px;
      background-color: white;
      border-radius: 50%;
      transition: .4s;
    }

    input:checked+.slider {
      background-color: var(--primary-color);
    }

    input:checked+.slider:before {
      transform: translateX(24px);
    }

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
    <div class="sidebar" id="sidebar">
    <div>
      <div class="text-center p-3 border-bottom border-secondary">
        <img src="Remorig.png" alt="Logo" style="width: 100px;">
        <h6 class="mt-2 mb-0 text-light">CORE ADMIN</h6>
      </div>
      <nav class="mt-3">
         <nav class="mt-3" id="sidebarAccordion">
        <a href="admin.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      
        <a href="#crmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between">
            <span><i class="bi bi-people"></i> CRM</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse" id="crmSubmenu" data-bs-parent="#sidebarAccordion" style="background: rgba(0,0,0,0.2);">
            <a href="CRM.php" class="ps-4"><i class="bi bi-dot"></i> CRM Dashboard</a>
            <a href="customer_feedback.php" class="ps-4"><i class="bi bi-dot"></i> Customer Feedback</a>
            <a href="admin_ratings.php" class="ps-4"><i class="bi bi-dot"></i> Shipment Ratings</a>
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
        <a href="admin_reports.php">
       <i class="bi bi-file-earmark-bar-graph"></i> Reports Generation
        </a>
        <a href="activity-log.php"><i class="bi bi-clock-history"></i> Activity Log</a>
        
        <a href="#archiveSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between">
            <span><i class="bi bi-archive"></i> Archived</span> <i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse show" id="archiveSubmenu" data-bs-parent="#sidebarAccordion" style="background: rgba(0,0,0,0.2);">
            <a href="Archive.php" class="ps-4 active"><i class="bi bi-dot"></i> Documents</a>
            <a href="Archive_CRM.php" class="ps-4"><i class="bi bi-dot"></i> Customers</a>
        </div>

        <a href="logout.php" class="border-top mt-3"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </nav>
      </nav>
    </div>
  </div>

      <div class="content" id="mainContent">
    <div class="header">
      <div class="d-flex align-items-center gap-3">
        <div class="hamburger" id="hamburger">☰</div>
        <h4 class="fw-bold mb-0">Archived<span class="text-primary"> | Documents</span></h4>
      </div>
      <div class="theme-toggle-container">
        <small>Dark Mode</small>
        <label class="theme-switch">
          <input type="checkbox" id="adminThemeToggle">
          <span class="slider"></span>
        </label>
      </div>
    </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>TITLE</th>
                            <th>TYPE</th>
                            <th>FILENAME</th>
                            <th>ARCHIVED ON</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $id = intval($row['id']);
                                $title = htmlspecialchars($row['title'], ENT_QUOTES);
                                $type = htmlspecialchars($row['doc_type'], ENT_QUOTES);
                                $filename = htmlspecialchars($row['filename'], ENT_QUOTES);
                                $archived_on = htmlspecialchars($row['archived_on'], ENT_QUOTES);

                                ?>
                                <tr>
                                    <td><?php echo $title; ?></td>
                                    <td><?php echo $type; ?></td>
                                    <td><?php echo $filename; ?></td>
                                    <td><?php echo $archived_on; ?></td>
                                    <td>
                                        <?php if(strpos($filename, '_print_invoice') !== false): 
                                            // Extract ID from TRK-000001_print_invoice
                                            $parts = explode('_', $filename);
                                            $trk_part = $parts[0]; // TRK-000001
                                            $id_part = (int)str_replace('TRK-', '', $trk_part);
                                        ?>
                                            <a class='btn btn-primary btn-sm' href='print_invoice.php?id=<?php echo $id_part; ?>' target="_blank">
                                                <i class='bi bi-printer'></i> Print
                                            </a>
                                        <?php elseif(strpos($filename, '_Contract') !== false): 
                                             $parts = explode('_', $filename);
                                             $contract_num = $parts[0];
                                        ?>
                                            <a class='btn btn-primary btn-sm' href='contract_print.php?number=<?php echo $contract_num; ?>' target="_blank">
                                                <i class='bi bi-printer'></i> Print
                                            </a>
                                        <?php else: ?>
                                            <a class='btn btn-primary btn-sm' href='uploads/archive/<?php echo $filename; ?>' download>
                                                <i class='bi bi-download'></i> Download
                                            </a>
                                        <?php endif; ?>
                                        <a class='btn btn-success btn-sm restore' href='Archive.php?restore=<?php echo $id; ?>'>
                                            <i class='bi bi-arrow-counterclockwise'></i> Restore
                                        </a>
                                        <a class='btn btn-danger btn-sm delete-btn' href='Archive.php?delete_id=<?php echo $id; ?>'>
                                            <i class='bi bi-trash'></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php

                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center'>No archived documents found</td></tr>";
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
    document.getElementById("hamburger").addEventListener("click", function () {
        document.getElementById("sidebar").classList.toggle("collapsed");
        document.getElementById("mainContent").classList.toggle("expanded");
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

// ✅ Keep dropdown open on current subpage
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


// SweetAlert delete confirmation
    document.querySelectorAll(".delete-btn").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            const url = this.href;
            Swal.fire({
                title: 'Delete Permanently?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    });

    // SweetAlert confirmation
    document.querySelectorAll(".restore").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            const url = this.href;
            Swal.fire({
                title: 'Restore Document?',
                text: "This will move the document back to main records.",
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

