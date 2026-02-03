<?php
include("darkmode.php");
include("connection.php");
include('session.php');
include('loading.html');
requireRole('user');

$account_id = $_SESSION['account_id'];

/* --- HANDLE FEEDBACK SUBMISSION --- */
if (isset($_POST['Send_feedback'])) {
    $comment = trim($_POST['comment']);
    $account_id = $_SESSION['account_id'];
    $attachment = null;

    if (!empty($comment)) {
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
            $file_ext = strtolower(pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_ext, $allowed_types)) {
                $new_filename = "ticket_" . time() . "_" . rand(100,999) . "." . $file_ext;
                $target_file = $target_dir . $new_filename;
                if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
                    $attachment = $new_filename;
                }
            }
        }
        $stmt = $conn->prepare("INSERT INTO feedback (account_id, comment, attachment, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $account_id, $comment, $attachment);
        
        if ($stmt->execute()) {
            $success = "Ticket submitted successfully!";
            // NOTIFY ADMINS
            $adminQuery = $conn->query("SELECT id FROM accounts WHERE role = 'admin'");
            if ($adminQuery) {
                $notif_title = "New Support Ticket";
                $notif_msg = "User submitted a concern.";
                $link = "customer_feedback.php";
                $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                while ($adminRow = $adminQuery->fetch_assoc()) {
                    $notifStmt->bind_param("isss", $adminRow['id'], $notif_title, $notif_msg, $link);
                    $notifStmt->execute();
                }
                $notifStmt->close();
            }
        } else { $error = "Database error: " . $conn->error; }
        $stmt->close();
    } else { $error = "Please enter description."; }
}

// Fetch Tickets
$feedbacks = [];
$sql = "SELECT f.id, f.comment, f.attachment, f.created_at, a.username FROM feedback f JOIN accounts a ON f.account_id = a.id WHERE f.account_id = ? ORDER BY f.created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $replySql = "SELECT r.reply_message, r.created_at, u.username AS admin_name FROM replies r JOIN accounts u ON r.admin_id = u.id WHERE r.feedback_id = ? ORDER BY r.created_at ASC";
        $stmt2 = $conn->prepare($replySql);
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $replies = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();
        $row['replies'] = $replies;
        $feedbacks[] = $row;
    }
}
$stmt->close();

$username = $_SESSION['username'];
$query = mysqli_query($conn, "SELECT * FROM accounts WHERE username = '$username'");
$user = mysqli_fetch_assoc($query);
$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 'user.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support & Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- GOOGLE FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
          --sidebar-width: 260px;
          --primary-color: #4361ee;
          /* Vibrant Blue */
          --secondary-color: #f3f4f6;
          --accent-color: #3f37c9;
          --text-main: #2b2d42;
          --text-secondary: #8d99ae;

          /* Dark Mode Variables */
          --dark-bg: #0f172a;
          /* Slate 900 */
          --dark-card: #1e293b;
          /* Slate 800 */
          --dark-border: #334155;
          /* Slate 700 */
          --dark-text-main: #f8fafc;
          --dark-text-sec: #94a3b8;

          --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
          --shadow-md: 0 5px 15px rgba(0, 0, 0, 0.08);
          --radius-md: 12px;
          --radius-lg: 16px;
        }

        /* --- GLOBAL RESETS --- */
        * {
          box-sizing: border-box;
        }

        body {
          font-family: 'Poppins', sans-serif;
          background-color: var(--secondary-color);
          color: var(--text-main);
          overflow-x: hidden;
        }

        /* --- SIDEBAR (UNCHANGED COLOR as requested) --- */
        .sidebar {
          width: var(--sidebar-width);
          height: 100vh;
          position: fixed;
          left: 0;
          top: 0;
          background: #2c3e50;
          /* PRESERVED COLOR */
          color: white;
          z-index: 1040;
          transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
          box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar.collapsed {
          transform: translateX(-100%);
        }

        .sidebar-header {
          padding: 2rem 1.5rem 1rem;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .content {
          margin-left: var(--sidebar-width);
          padding: 30px;
          transition: margin-left .3s cubic-bezier(0.4, 0, 0.2, 1);
          min-height: 100vh;
        }

        .content.expanded {
          margin-left: 0;
        }

        /* --- NAVIGATION --- */
        .nav-link {
          font-weight: 500;
          color: rgba(255, 255, 255, 0.7) !important;
          transition: all 0.2s;
          margin-bottom: 5px;
          border-radius: 8px;
        }

        .nav-link:hover,
        .nav-link.active {
          color: #fff !important;
          background: rgba(255, 255, 255, 0.1);
          transform: translateX(5px);
        }

        .nav-link i {
          margin-right: 10px;
        }

        /* --- CARDS & PANELS --- */
        .panel,
        .card {
          background: white;
          border-radius: var(--radius-lg);
          border: none;
          box-shadow: var(--shadow-md);
          transition: transform 0.2s;
        }

        .panel {
          padding: 2rem;
          margin-bottom: 24px;
        }

        .header {
          background: white;
          border-radius: var(--radius-lg);
          box-shadow: var(--shadow-sm);
          padding: 1rem 1.5rem;
          margin-bottom: 30px;
          border: 1px solid rgba(0, 0, 0, 0.02);
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 992px) {
          .sidebar {
            left: calc(var(--sidebar-width) * -1);
          }

          .sidebar.mobile-open {
            left: 0;
          }

          .content {
            margin-left: 0 !important;
            padding: 15px;
          }
        }

        .sidebar-overlay {
          position: fixed;
          top: 0;
          left: 0;
          width: 100vw;
          height: 100vh;
          background: rgba(0, 0, 0, 0.5);
          z-index: 1030;
          display: none;
          opacity: 0;
          transition: opacity 0.3s;
        }

        .sidebar-overlay.show {
          display: block;
          opacity: 1;
        }

        /* =========================================
           DARK MODE OVERRIDES (COMPREHENSIVE) 
           ========================================= */
        body.dark-mode {
          background-color: var(--dark-bg);
          color: var(--dark-text-main);
        }

        /* Components */
        body.dark-mode .panel,
        body.dark-mode .card,
        body.dark-mode .header,
        body.dark-mode .modal-content,
        body.dark-mode .dropdown-menu {
          background-color: var(--dark-card) !important;
          color: var(--dark-text-main);
          border-color: var(--dark-border);
          box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        /* Fix Bootstrap Utilities in Dark Mode */
        body.dark-mode .bg-white {
          background-color: var(--dark-card) !important;
        }

        body.dark-mode .bg-light {
          background-color: #1e293b !important;
        }

        /* Darker slate for bg-light */
        body.dark-mode .bg-light-subtle {
          background-color: #334155 !important;
        }

        /* Slate 700 */
        body.dark-mode .text-muted {
          color: var(--dark-text-sec) !important;
        }

        body.dark-mode .text-dark {
          color: #fff !important;
        }

        /* Forms */
        body.dark-mode .form-control,
        body.dark-mode .form-select,
        body.dark-mode .input-group-text,
        body.dark-mode textarea {
          background-color: #0f172a !important;
          /* Extremely dark bg for inputs */
          border-color: var(--dark-border) !important;
          color: #fff !important;
        }

        body.dark-mode .form-control:focus {
          border-color: var(--primary-color) !important;
          box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.3);
        }

        body.dark-mode .list-group-item {
          background-color: var(--dark-card);
          border-color: var(--dark-border);
          color: var(--dark-text-main);
        }

        /* Buttons */
        body.dark-mode .btn-close {
          filter: invert(1);
        }

        body.dark-mode .btn-outline-secondary {
          color: #cbd5e1;
          border-color: #cbd5e1;
        }

        body.dark-mode .btn-outline-secondary:hover {
          color: #000;
          background-color: #cbd5e1;
        }


        /* --- FEEDBACK SPECIFIC STYLES (Preserved) --- */
        .feedback-textarea { background-color: #f8f9fc; border: 2px solid #e3e6f0; border-radius: 10px; padding: 1rem; resize: none; }
        .ticket-item { background: #fff; margin-bottom: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border: 1px solid #eee; border-left: 4px solid var(--primary-color); }
        .ticket-header { padding: 1rem; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; }
        .ticket-body { padding: 1.2rem; }
        .admin-reply-box { background-color: #f1f5f9; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
        .badge-ticket { background: rgba(67, 97, 238, 0.1); color: var(--primary-color); padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .attachment-preview { max-width: 100px; height: auto; border-radius: 5px; cursor: pointer; border: 1px solid #ddd; margin-top: 10px; }

        /* Dark Mode Overrides for Feedback */
        body.dark-mode .ticket-item, body.dark-mode .ticket-header { background-color: #16213e !important; color: #f8f9fa !important; border-color: #2a3a5a !important; }
        body.dark-mode .feedback-textarea { background-color: #243355; border-color: #2c3e50; color: white; }
        body.dark-mode .admin-reply-box { background-color: #243355; }
    </style>
</head>

<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="sidebar d-flex flex-column flex-shrink-0 p-3" id="sidebar">
        <div class="text-center mb-4 mt-2">
            <img src="Remorig.png" alt="Freight Logo" class="img-fluid mb-2" style="max-width: 140px;">
            <h6 class="fw-semibold text-uppercase text-light-50 mb-0" style="font-size: 0.85rem;">Core Transaction 3</h6>
        </div>

        <hr class="text-light opacity-25">

        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item mb-2"><a href="user.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-house-door-fill fs-5"></i><span>Dashboard</span></a></li>
            <li class="nav-item mb-2"><a href="bookshipment.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-truck fs-5"></i><span>Book Shipment</span></a></li>
            <ul class="nav nav-pills flex-column mb-auto">

            </ul>
            <li class="nav-item mb-2"><a href="My_shipment.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-truck fs-5"></i><span>My Shipments</span></a></li>
            <li class="nav-item mb-2"><a href="shiphistory.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-clock-history fs-5"></i><span>Shipment History</span></a></li>
            <li class="nav-item mb-2"><a href="feedback.php" class="active nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-chat-dots fs-5"></i><span>Helpdesk Support</span></a></li>
            <li class="nav-item mb-2"><a href="rate_shipment.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-star-fill fs-5"></i><span>Reviews</span></a></li>
        </ul>
    </div>

    <div class="content" id="mainContent">
    <header class="header d-flex align-items-center justify-content-between px-4 py-3 mb-4 bg-white shadow-sm rounded-3 sticky-top">
        <div class="d-flex align-items-center gap-3">
            <button class="hamburger btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
            <div>
                <h5 class="fw-semibold mb-0">Helpdesk Support</h5>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="dropdown me-3">
                <a href="#" class="text-dark position-relative" id="notifDropdown" data-bs-toggle="dropdown" onclick="markRead()">
                    <i class="bi bi-bell fs-4"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge" style="display: none;">
                        0
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm p-0" style="width: 300px; max-height: 400px; overflow-y: auto;">
                    <li class="p-2 border-bottom fw-bold bg-light">Notifications</li>
                    <div id="notifList">
                        <li class="text-center p-3 text-muted small">No new notifications</li>
                    </div>
                    <li><a class="dropdown-item text-center small text-primary p-2 border-top" href="feedback.php">View All</a></li>
                </ul>
            </div>
            <div class="dropdown">

                <a href="#" class="d-flex align-items-center text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="<?php echo $profileImage; ?>" alt="Profile" class="rounded-circle" width="40" height="40" style="object-fit:cover;">
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="user-profile.php">Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                </ul>
            </div>
            <div class="form-check form-switch mb-0">
                <label class="form-check-label" for="userThemeToggle">🌙</label>
                <input class="form-check-input" type="checkbox" role="switch" id="userThemeToggle">
            </div>
        </div>
    </header>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
                        <i class="bi bi-pencil-square text-primary fs-5"></i>
                        <h6 class="fw-bold mb-0">Create New Ticket</h6>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)) echo "<div class='alert alert-success py-2 small'><i class='bi bi-check-circle me-1'></i> $success</div>"; ?>
                        <?php if (isset($error)) echo "<div class='alert alert-danger py-2 small'><i class='bi bi-exclamation-circle me-1'></i> $error</div>"; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Describe your concern</label>
                                <textarea class="form-control feedback-textarea" name="comment" rows="5" placeholder="Details..." required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Attach Proof (Optional)</label>
                                <input type="file" name="attachment" class="form-control form-control-sm" accept="image/*">
                                <small class="text-muted" style="font-size: 0.7rem;">Max 2MB (JPG, PNG)</small>
                            </div>
                            <button type="submit" name="Send_feedback" class="btn btn-primary w-100 py-2 fw-bold">
                                <i class="bi bi-send-fill me-2"></i> Submit Ticket
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-secondary mb-0">Recent Tickets</h6>
                    <span class="badge bg-light text-dark border">Latest 5</span>
                </div>

                <?php if (!empty($feedbacks)): ?>
                    <?php foreach ($feedbacks as $fb): ?>
                        <div class="ticket-item">
                            <div class="ticket-header">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge-ticket">TICKET #<?= $fb['id'] ?></span>
                                    <span class="small text-muted"><i class="bi bi-clock me-1"></i> <?= date("M d, Y h:i A", strtotime($fb['created_at'])) ?></span>
                                </div>
                                <div class="small fw-bold">
                                    <?php echo !empty($fb['replies']) ? '<span class="text-success"><i class="bi bi-check-all"></i> Replied</span>' : '<span class="text-warning"><i class="bi bi-hourglass-split"></i> Pending</span>'; ?>
                                </div>
                            </div>
                            <div class="ticket-body">
                                <div class="d-flex gap-3">
                                    <div class="flex-shrink-0"><img src="<?php echo $profileImage; ?>" class="rounded-circle" width="35" height="35" alt="User"></div>
                                    <div>
                                        <h6 class="fw-bold mb-1">You</h6>
                                        <p class="mb-0 text-break"><?= nl2br(htmlspecialchars($fb['comment'])) ?></p>
                                        <?php if(!empty($fb['attachment'])): ?>
                                            <div class="mt-2"><small class="text-muted d-block"><i class="bi bi-paperclip"></i> Attachment:</small><img src="uploads/<?= $fb['attachment'] ?>" class="attachment-preview" onclick="viewImage('uploads/<?= $fb['attachment'] ?>')" alt="Attachment"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($fb['replies'])): ?>
                                    <?php foreach ($fb['replies'] as $r): ?>
                                        <div class="admin-reply-box ms-md-5">
                                            <div class="d-flex gap-3">
                                                <div class="flex-shrink-0"><div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:35px; height:35px;"><i class="bi bi-headset"></i></div></div>
                                                <div class="w-100">
                                                    <div class="d-flex justify-content-between mb-1"><span class="fw-bold text-primary">Support Team (<?= htmlspecialchars($r['admin_name']) ?>)</span><small class="text-muted"><?= date("M d, h:i A", strtotime($r['created_at'])) ?></small></div>
                                                    <p class="mb-0 small text-break"><?= nl2br(htmlspecialchars($r['reply_message'])) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted card"><h6>No support tickets found</h6></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body text-center position-relative">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 bg-white" data-bs-dismiss="modal"></button>
                    <img src="" id="modalImage" class="img-fluid rounded shadow-lg" style="max-height: 90vh;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (typeof initDarkMode === 'function') initDarkMode("userThemeToggle", "userDarkMode");
        
        // SIDEBAR TOGGLE LOGIC
        document.getElementById('hamburger').addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('mainContent');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth > 768) {
                // Desktop: Toggle collapsed class
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            } else {
                // Mobile: Toggle mobile-open class
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('show');
            }
        });

        document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('mobile-open');
            document.getElementById('sidebarOverlay').classList.remove('show');
        });

        function viewImage(src) {
            document.getElementById('modalImage').src = src;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        // --- NOTIFICATION SCRIPT ---
        function fetchNotifications() {
            fetch('api/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('notifBadge');
                const list = document.getElementById('notifList');
                if (data.count > 0) { badge.innerText = data.count; badge.style.display = 'inline-block'; } 
                else { badge.style.display = 'none'; }
                let html = '';
                if (data.data.length > 0) {
                    data.data.forEach(notif => {
                        let bgClass = notif.is_read == 0 ? 'bg-light' : '';
                        let icon = notif.is_read == 0 ? 'bi-circle-fill text-primary' : 'bi-check-circle text-muted';
                        html += `<li><a class="dropdown-item ${bgClass} p-2 border-bottom" href="${notif.link}"><div class="d-flex align-items-start"><i class="bi ${icon} me-2 mt-1" style="font-size: 10px;"></i><div><small class="fw-bold d-block">${notif.title}</small><small class="text-muted text-wrap">${notif.message}</small><br><small class="text-secondary" style="font-size: 0.7rem;">${new Date(notif.created_at).toLocaleString()}</small></div></div></a></li>`;
                    });
                } else { html = '<li class="text-center p-3 text-muted small">No notifications</li>'; }
                list.innerHTML = html;
            });
        }
        function markRead() {
            fetch('api/get_notifications.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=read_all' })
            .then(() => { document.getElementById('notifBadge').style.display = 'none'; });
        }
        fetchNotifications();
        setInterval(fetchNotifications, 5000);
    </script>
</body>
</html>