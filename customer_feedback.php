<?php
include("darkmode.php");
include("connection.php");
include('session.php');
include('loading.html');
requireRole('admin');

$admin_id = $_SESSION['account_id'];

// --- 1. HANDLE REPLY SUBMISSION ---
if (isset($_POST['send_reply'])) {
    $feedback_id = $_POST['feedback_id'];
    $reply_msg = trim($_POST['reply_message']);

    if (!empty($reply_msg)) {
        $stmt = $conn->prepare("INSERT INTO replies (feedback_id, admin_id, reply_message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $feedback_id, $admin_id, $reply_msg);

        if ($stmt->execute()) {
            $success_msg = "Reply sent successfully!";

            // NOTIFY THE USER
            $get_user = $conn->query("SELECT account_id FROM feedback WHERE id = '$feedback_id'");
            if ($row = $get_user->fetch_assoc()) {
                $user_to_notify = $row['account_id'];
                $n_title = "Support Update";
                $n_msg = "Admin replied to ticket #$feedback_id";
                $n_link = "feedback.php";
                $conn->query("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES ('$user_to_notify', '$n_title', '$n_msg', '$n_link', NOW())");
            }

        } else {
            $error_msg = "Error sending reply.";
        }
        $stmt->close();
    }
}

// --- 2. FETCH TICKETS ---
$sql = "SELECT f.id, f.comment, f.attachment, f.created_at, a.username, a.email, a.profile_image 
        FROM feedback f 
        JOIN accounts a ON f.account_id = a.id 
        ORDER BY f.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Support & Feedback</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

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

        .ticket-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-left: 5px solid var(--primary-color);
        }

        .ticket-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ticket-body {
            padding: 20px;
        }

        .ticket-footer {
            padding: 15px 20px;
            background-color: #f8f9fc;
            border-top: 1px solid #eee;
            border-radius: 0 0 10px 10px;
        }

        .reply-thread {
            margin-top: 15px;
            padding: 15px;
            background-color: #f1f5f9;
            border-radius: 8px;
            border-left: 3px solid #1cc88a;
        }

        .reply-input {
            resize: none;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .attachment-img {
            max-width: 150px;
            height: auto;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 10px;
        }

        .attachment-img:hover {
            opacity: 0.8;
            border-color: var(--primary-color);
        }

        body.dark-mode .ticket-footer {
            background-color: var(--dark-bg);
            border-color: var(--dark-border);
        }

        body.dark-mode .reply-thread {
            background-color: var(--dark-bg);
            border-color: #1cc88a;
            color: white !important;
        }

        body.dark-mode .ticket-header {
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
                <a href="CRM.php" class="ps-5"><i class="bi bi-dot"></i> Dashboard</a>
                <a href="customer_feedback.php" class="ps-5 active"><i class="bi bi-dot"></i> Feedback</a>
            </div>
            <a href="#csmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-text"></i> Contract & SLA</span><i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse " id="csmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
                <a href="admin_contracts.php" class="ps-5 "><i class="bi bi-dot"></i> Contracts</a>
                <a href="Admin_shipments.php" class="ps-5 "><i class="bi bi-dot"></i> SLA Monitor</a>
            </div>
            <a href="E-Doc.php" class=""><i class="bi bi-folder2-open"></i> E-Docs</a>
            <a href="admin_completed.php"><i class="bi bi-check-circle-fill"></i> Completed Trans.</a>
            <a href="BIFA.php"><i class="bi bi-graph-up"></i> BI & Analytics</a>
            <a href="admin_reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
            <a href="activity-log.php"><i class="bi bi-clock-history"></i> Activity Log</a>
            <a href="Archive.php"><i class="bi bi-archive"></i> Archives</a>
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
                    <h5 class="fw-bold mb-0 text-primary">Helpdesk & Support Tickets</h5>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
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
        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="container-fluid p-0">

            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($ticket = $result->fetch_assoc()): ?>

                    <?php
                    // FETCH REPLIES
                    $repliesQ = $conn->query("SELECT r.*, a.username as admin_name FROM replies r JOIN accounts a ON r.admin_id = a.id WHERE r.feedback_id = " . $ticket['id'] . " ORDER BY r.created_at ASC");
                    $has_reply = ($repliesQ->num_rows > 0);
                    ?>

                    <div class="ticket-card">
                        <div class="ticket-header">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= !empty($ticket['profile_image']) ? $ticket['profile_image'] : 'user.png' ?>"
                                    class="rounded-circle border" width="40" height="40" style="object-fit:cover;">
                                <div>
                                    <h6 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($ticket['username']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($ticket['email']) ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-secondary mb-1">Ticket #<?= $ticket['id'] ?></span>
                                <br>
                                <small class="text-muted"
                                    style="font-size: 0.75rem;"><?= date("M d, Y h:i A", strtotime($ticket['created_at'])) ?></small>
                            </div>
                        </div>

                        <div class="ticket-body">
                            <p class="mb-0 fs-6">
                                <i class="bi bi-chat-quote-fill text-muted me-2"></i>
                                <?= nl2br(htmlspecialchars($ticket['comment'])) ?>
                            </p>

                            <?php if (!empty($ticket['attachment'])): ?>
                                <div class="mt-3">
                                    <small class="text-muted fw-bold d-block"><i class="bi bi-paperclip"></i> Attached
                                        Proof:</small>
                                    <img src="uploads/<?= $ticket['attachment'] ?>" class="attachment-img"
                                        onclick="viewImage('uploads/<?= $ticket['attachment'] ?>')" alt="User Attachment">
                                </div>
                            <?php endif; ?>

                            <?php if ($has_reply): ?>
                                <div class="mt-4">
                                    <small class="text-uppercase fw-bold text-muted" style="font-size:0.7rem;">Conversation
                                        History</small>
                                    <?php while ($reply = $repliesQ->fetch_assoc()): ?>
                                        <div class="reply-thread">
                                            <div class="d-flex justify-content-between mb-1">
                                                <strong class="text-success small"><i class="bi bi-headset me-1"></i> Admin
                                                    <?= htmlspecialchars($reply['admin_name']) ?></strong>
                                                <small class="text-muted"
                                                    style="font-size:0.7rem;"><?= date("M d, h:i A", strtotime($reply['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-0 small text-dark"><?= nl2br(htmlspecialchars($reply['reply_message'])) ?></p>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="mt-3">
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Pending
                                        Reply</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="ticket-footer">
                            <form method="POST">
                                <input type="hidden" name="feedback_id" value="<?= $ticket['id'] ?>">
                                <div class="input-group">
                                    <textarea name="reply_message" class="form-control reply-input" rows="1"
                                        placeholder="Type your response here..." required></textarea>
                                    <button type="submit" name="send_reply" class="btn btn-primary"><i
                                            class="bi bi-send-fill"></i> Reply</button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <h4 class="text-muted">No tickets found.</h4>
                </div>
            <?php endif; ?>

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
        initDarkMode("adminThemeToggle", "adminDarkMode");
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
            document.getElementById('hamburger').addEventListener('click', function () {
                document.getElementById('sidebar').classList.toggle('collapsed');
                document.getElementById('mainContent').classList.toggle('expanded');
            });

            function viewImage(src) {
                document.getElementById('modalImage').src = src;
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            }

            // --- ADMIN NOTIFICATION SCRIPT ---
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