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
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = strtolower(pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_ext, $allowed_types)) {
                $new_filename = "ticket_" . time() . "_" . rand(100, 999) . "." . $file_ext;
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
        } else {
            $error = "Database error: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "Please enter description.";
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

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

        /* SIDEBAR */
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

        .nav-link {
            font-weight: 500;
            color: var(--text-secondary) !important;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            border-radius: var(--radius-md);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .nav-link:hover {
            color: var(--text-main) !important;
            background: var(--secondary-color);
        }

        .nav-link.active {
            color: #ffffff !important;
            background: var(--primary-color);
            font-weight: 600;
        }

        .nav-link i {
            font-size: 1.1rem;
            margin-right: 12px;
        }

        body:not(.dark-mode) .sidebar img[alt="Logo"] {
            filter: brightness(0);
        }

        /* Components */
        .card {
            border: none;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }

        /* Feedback Specific */
        .feedback-textarea {
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            resize: none;
            color: var(--text-main);
        }

        .ticket-item {
            background: #ffffff;
            margin-bottom: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--primary-color);
        }

        .ticket-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafafa;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
        }

        .ticket-body {
            padding: 1.2rem;
        }

        .admin-reply-box {
            background-color: var(--secondary-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid var(--border-color);
        }

        .badge-ticket {
            background: var(--secondary-color);
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .attachment-preview {
            max-width: 100px;
            height: auto;
            border-radius: 5px;
            cursor: pointer;
            border: 1px solid var(--border-color);
            margin-top: 10px;
        }

        /* Dark Mode Overrides */
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
        body.dark-mode .ticket-item {
            background-color: var(--dark-card) !important;
            color: var(--dark-text-main) !important;
            border: 1px solid var(--dark-border) !important;
        }

        body.dark-mode .text-muted {
            color: var(--dark-text-sec) !important;
        }

        body.dark-mode .modal-content {
            background-color: var(--dark-card);
            color: var(--dark-text-main);
            border: 1px solid var(--dark-border);
        }

        body.dark-mode .btn-close {
            filter: invert(1);
        }

        body.dark-mode .feedback-textarea {
            background-color: var(--dark-bg);
            border-color: var(--dark-border);
            color: var(--dark-text-main);
        }

        body.dark-mode .admin-reply-box {
            background-color: var(--dark-bg);
            border-color: var(--dark-border);
        }

        body.dark-mode .bg-light {
            background-color: var(--dark-bg) !important;
            color: white;
            border-color: var(--dark-border) !important;
        }

        body.dark-mode .ticket-header {
            background-color: var(--dark-bg);
            border-color: var(--dark-border);
        }

        body.dark-mode .nav-link {
            color: var(--dark-text-sec) !important;
        }

        body.dark-mode .nav-link:hover {
            color: var(--dark-text-main) !important;
            background: var(--dark-border);
        }

        body.dark-mode .nav-link.active {
            color: var(--dark-bg) !important;
            background: var(--border-color);
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
                style="letter-spacing: 1px; font-size: 0.75rem;">Core Transaction 3</h6>
        </div>
        <hr class="border-secondary opacity-25">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="user.php" class="nav-link"><i class="bi bi-grid-1x2"></i> <span
                        class="sidebar-text">Dashboard</span></a>
            </li>
            <li class="nav-item">
                <a href="bookshipment.php" class="nav-link"><i class="bi bi-box-seam"></i> <span
                        class="sidebar-text">Book Shipment</span></a>
            </li>
            <li class="nav-item">
                <a href="My_shipment.php" class="nav-link"><i class="bi bi-truck"></i> <span class="sidebar-text">My
                        Shipments</span></a>
            </li>
            <li class="nav-item">
                <a href="shiphistory.php" class="nav-link"><i class="bi bi-clock-history"></i> <span
                        class="sidebar-text">History</span></a>
            </li>
            <li class="nav-item">
                <a href="feedback.php" class="nav-link active"><i class="bi bi-chat-square-text"></i> <span
                        class="sidebar-text">Feedback</span></a>
            </li>
        </ul>
    </div>

    <div class="content" id="mainContent">
        <header
            class="top-header d-flex align-items-center justify-content-between sticky-top mb-4 p-3 bg-white shadow-sm rounded-3">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
                <div>
                    <h5 class="fw-bold mb-0">Helpdesk & Feedback</h5>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
                    <label class="form-check-label text-muted" for="userThemeToggle"><i
                            class="bi bi-moon-stars"></i></label>
                    <input class="form-check-input m-0" type="checkbox" role="switch" id="userThemeToggle">
                </div>

                <div class="dropdown mx-1">
                    <a href="#" class="text-dark position-relative" id="notifDropdown" data-bs-toggle="dropdown"
                        onclick="markRead()">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center"
                            style="width:36px;height:36px;">
                            <i class="bi bi-bell"></i>
                        </div>
                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm border border-white"
                            id="notifBadge" style="display: none; padding: 0.25em 0.5em; font-size: 0.65em;">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow p-0"
                        style="width: 320px; max-height: 480px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <li
                            class="p-3 border-bottom fw-bold bg-light d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <small class="text-primary cursor-pointer text-decoration-none" style="cursor:pointer;"
                                onclick="location.href='feedback.php'">View All</small>
                        </li>
                        <div id="notifList">
                            <li class="text-center p-4 text-muted small">No new notifications</li>
                        </div>
                    </ul>
                </div>

                <div class="dropdown">
                    <a href="#" data-bs-toggle="dropdown" class="d-block link-dark text-decoration-none"
                        style="cursor: pointer;">
                        <img src="<?php echo $profileImage ?? 'default-avatar.png'; ?>" alt="Profile" width="36"
                            height="36" class="rounded-circle object-fit-cover border border-2 border-primary">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end text-small shadow"
                        style="border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <li><a class="dropdown-item" href="user-profile.php"><i
                                    class="bi bi-person me-2"></i>Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i
                                    class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
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
                        <?php if (isset($success))
                            echo "<div class='alert alert-success py-2 small'><i class='bi bi-check-circle me-1'></i> $success</div>"; ?>
                        <?php if (isset($error))
                            echo "<div class='alert alert-danger py-2 small'><i class='bi bi-exclamation-circle me-1'></i> $error</div>"; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Describe your concern</label>
                                <textarea class="form-control feedback-textarea" name="comment" rows="5"
                                    placeholder="Details..." required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Attach Proof (Optional)</label>
                                <input type="file" name="attachment" class="form-control form-control-sm"
                                    accept="image/*">
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
                                    <span class="small text-muted"><i class="bi bi-clock me-1"></i>
                                        <?= date("M d, Y h:i A", strtotime($fb['created_at'])) ?></span>
                                </div>
                                <div class="small fw-bold">
                                    <?php echo !empty($fb['replies']) ? '<span class="text-success"><i class="bi bi-check-all"></i> Replied</span>' : '<span class="text-warning"><i class="bi bi-hourglass-split"></i> Pending</span>'; ?>
                                </div>
                            </div>
                            <div class="ticket-body">
                                <div class="d-flex gap-3">
                                    <div class="flex-shrink-0"><img src="<?php echo $profileImage; ?>" class="rounded-circle"
                                            width="35" height="35" alt="User"></div>
                                    <div>
                                        <h6 class="fw-bold mb-1">You</h6>
                                        <p class="mb-0 text-break"><?= nl2br(htmlspecialchars($fb['comment'])) ?></p>
                                        <?php if (!empty($fb['attachment'])): ?>
                                            <div class="mt-2"><small class="text-muted d-block"><i class="bi bi-paperclip"></i>
                                                    Attachment:</small><img src="uploads/<?= $fb['attachment'] ?>"
                                                    class="attachment-preview"
                                                    onclick="viewImage('uploads/<?= $fb['attachment'] ?>')" alt="Attachment"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($fb['replies'])): ?>
                                    <?php foreach ($fb['replies'] as $r): ?>
                                        <div class="admin-reply-box ms-md-5">
                                            <div class="d-flex gap-3">
                                                <div class="flex-shrink-0">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                                                        style="width:35px; height:35px;"><i class="bi bi-headset"></i></div>
                                                </div>
                                                <div class="w-100">
                                                    <div class="d-flex justify-content-between mb-1"><span
                                                            class="fw-bold text-primary">Support Team
                                                            (<?= htmlspecialchars($r['admin_name']) ?>)</span><small
                                                            class="text-muted"><?= date("M d, h:i A", strtotime($r['created_at'])) ?></small>
                                                    </div>
                                                    <p class="mb-0 small text-break"><?= nl2br(htmlspecialchars($r['reply_message'])) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted card">
                        <h6>No support tickets found</h6>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body text-center position-relative">
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 bg-white"
                        data-bs-dismiss="modal"></button>
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

            if (window.innerWidth > 768) {
                // Desktop: Toggle collapsed class
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            } else {
                sidebar.classList.toggle('show');
                content.classList.toggle('mobile-expanded');
            }
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