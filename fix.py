import re

with open('E-Doc.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix the first split (lines 230 to 415)
start_marker = "    /* STANDARD CSS */"
end_marker = "    <?php else: ?>"

start_idx = content.find(start_marker)
end_idx = content.find(end_marker) + len(end_marker)

if start_idx != -1 and content.find(end_marker) != -1:
    new_content = """        </div>
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
            <?php if($is_unlocked): ?>
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
                        <span>Notifications</span></li>
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
                            <input type="password" name="vault_pass" class="form-control" placeholder="Enter Password" required autofocus>
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
    <?php else: ?>"""
    content = content[:start_idx] + new_content + content[end_idx:]

# Fix the second split (lines 576 to 592)
start_marker2 = "<<<<<<< HEAD"
end_marker2 = ">>>>>>> 83d39d8a6f3153cee1a9566f6e29044bf30ee3ff\n    });"

start_idx2 = content.find(start_marker2)
end_idx2 = content.find(end_marker2) + len(end_marker2)

if start_idx2 != -1 and content.find(end_marker2) != -1:
    new_content2 = """        document.getElementById('hamburger').addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('mainContent');
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            } else {
                sidebar.classList.toggle('show');
                content.classList.toggle('mobile-expanded');
            }
        });"""
    content = content[:start_idx2] + new_content2 + content[end_idx2:]

with open('E-Doc.php', 'w', encoding='utf-8') as f:
    f.write(content)
