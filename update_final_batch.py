import os
import re

c1 = """    :root {
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
    
    body.dark-mode { background-color: var(--dark-bg) !important; color: var(--dark-text-main); --bs-card-bg: var(--dark-card) !important; --bs-body-bg: var(--dark-bg); --bs-border-color: var(--dark-border); --bs-body-color: var(--dark-text-main); }
    body.dark-mode .sidebar { background: var(--dark-card); border-right: 1px solid var(--dark-border); color: var(--dark-text-main); }
    body.dark-mode .top-header { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border: 1px solid var(--dark-border); }
    body.dark-mode .card, body.dark-mode .ticket-card, body.dark-mode .modal-content { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border: 1px solid var(--dark-border) !important; }
    body.dark-mode .text-primary { color: var(--dark-text-main) !important; }
    body.dark-mode .text-muted, body.dark-mode .text-secondary { color: var(--dark-text-sec) !important; }
    body.dark-mode .bg-light, body.dark-mode .bg-white { background-color: var(--dark-bg) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }
    body.dark-mode table, body.dark-mode tbody tr, body.dark-mode td, body.dark-mode th { background-color: var(--dark-card) !important; color: var(--dark-text-main) !important; border-color: var(--dark-border) !important; }
    body.dark-mode .table { --bs-table-bg: var(--dark-card); --bs-table-color: var(--dark-text-main); --bs-table-border-color: var(--dark-border); border-color: var(--dark-border) !important; }
    body.dark-mode .table thead th, body.dark-mode .table-light, body.dark-mode .table-light th { background-color: var(--dark-bg) !important; color: white !important; border-color: var(--dark-border) !important; }
    body.dark-mode .sidebar nav a { color: var(--dark-text-sec) !important; }
    body.dark-mode .sidebar nav a:hover { color: var(--dark-text-main) !important; background: var(--dark-border) !important; }
    body.dark-mode .sidebar nav a.active { color: var(--dark-bg) !important; background: var(--border-color) !important; }
    body.dark-mode .form-control, body.dark-mode .form-select { background-color: var(--dark-bg); color: white; border-color: var(--dark-border); }"""

c2 = """<body>
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
        <div class="collapse __CRM_SHOW__" id="crmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
            <a href="CRM.php" class="ps-5 __CRM_ACTIVE__"><i class="bi bi-dot"></i> Dashboard</a>
            <a href="customer_feedback.php" class="ps-5 __FEEDBACK_ACTIVE__"><i class="bi bi-dot"></i> Feedback</a>
        </div>
        <a href="#csmSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-file-text"></i> Contract & SLA</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse __SLA_SHOW__" id="csmSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
            <a href="Admin_contracts.php" class="ps-5 __CONTRACTS_ACTIVE__"><i class="bi bi-dot"></i> Contracts</a>
            <a href="Admin_shipments.php" class="ps-5 __SLA_ACTIVE__"><i class="bi bi-dot"></i> SLA Monitor</a>
        </div>
        <a href="E-Doc.php" class="__EDOC_ACTIVE__"><i class="bi bi-folder2-open"></i> E-Docs</a>
        <a href="admin_completed.php" class="__COMPLETED_ACTIVE__"><i class="bi bi-check-circle-fill"></i> Completed Trans.</a>
        <a href="BIFA.php" class="__BIFA_ACTIVE__"><i class="bi bi-graph-up"></i> BI & Analytics</a>
        <a href="admin_reports.php" class="__REPORTS_ACTIVE__"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
        <a href="activity-log.php" class="__ACTIVITY_ACTIVE__"><i class="bi bi-clock-history"></i> Activity Log</a>
        
        <a href="#archiveSubmenu" data-bs-toggle="collapse" class="d-flex justify-content-between align-items-center">
            <span><i class="bi bi-archive"></i> Archives</span><i class="bi bi-chevron-down small"></i>
        </a>
        <div class="collapse __ARCHIVE_SHOW__" id="archiveSubmenu" style="background: rgba(0,0,0,0.03); border-radius: 8px;">
            <a href="Archive.php" class="ps-5 __ARCHIVE_DOC_ACTIVE__"><i class="bi bi-dot"></i> Documents</a>
            <a href="Archive_CRM.php" class="ps-5 __ARCHIVE_CRM_ACTIVE__"><i class="bi bi-dot"></i> Customers</a>
        </div>

        <a href="logout.php" class="border-top mt-4 pt-4 text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<div class="content" id="mainContent">
    <header class="top-header d-flex align-items-center justify-content-between sticky-top mb-4 p-3 bg-white shadow-sm rounded-3">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
            <div>
                <h5 class="fw-bold mb-0 text-primary">__PAGE_TITLE__</h5>
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
    </header>"""

c3 = """    document.getElementById('hamburger').addEventListener('click', () => {
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

files_to_update = {
    'c:/xampp/htdocs/core3/admin_completed.php': {
        'title': '✅ Completed Deliveries',
        'replace_marks': [('__COMPLETED_ACTIVE__', 'active')],
        'content_start_marker': '<?php if (!$is_unlocked): ?>',
        'extra_css': '''
    .lock-screen { max-width: 400px; margin: 5rem auto; text-align: center; }
    .lock-icon { font-size: 4rem; color: var(--primary-color); margin-bottom: 1rem; }
    body.dark-mode .lock-screen .card { border: 1px solid var(--dark-border) !important; background-color: var(--dark-card) !important; }
        '''
    },
    'c:/xampp/htdocs/core3/BIFA.php': {
        'title': 'BI & Freight Analytics',
        'replace_marks': [('__BIFA_ACTIVE__', 'active')],
        'content_start_marker': '<div class="row g-3',
        'extra_css': '''
        .stat-value { font-size: 2rem; font-weight: 800; }
        .progress-thin { height: 6px; border-radius: 3px; }
        '''
    },
    'c:/xampp/htdocs/core3/admin_reports.php': {
        'title': 'Financial Reports',
        'replace_marks': [('__REPORTS_ACTIVE__', 'active')],
        'content_start_marker': '<!-- SUMMARY CARDS -->',
        'extra_css': '''
        .stat-card { border: none; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .badge-soft-success { background-color: rgba(28, 200, 138, 0.15); color: #1cc88a; }
        .badge-soft-warning { background-color: rgba(246, 194, 62, 0.15); color: #f6c23e; }
        .badge-soft-danger { background-color: rgba(231, 74, 59, 0.15); color: #e74a3b; }
        .badge-soft-primary { background-color: rgba(78, 115, 223, 0.15); color: #4e73df; }
        .badge-soft-secondary { background-color: rgba(133, 135, 150, 0.15); color: #858796; }
        '''
    },
    'c:/xampp/htdocs/core3/activity-log.php': {
        'title': 'Activity Log',
        'replace_marks': [('__ACTIVITY_ACTIVE__', 'active')],
        'content_start_marker': '<div class="table-section">',
        'extra_css': '''
        .table-section{ border-radius: var(--radius-md); padding: 1.5rem; text-align: center; background-color: white; box-shadow: var(--shadow-sm); }
        .table-section1 { margin-bottom: 0.5rem; }
        .UAL { margin-bottom: 1.5rem; }
        .RA{ margin: 1.5rem 0 1.5rem 0; }  
        .table-scroll-container { max-height: 410px; overflow-y: auto; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); }
        .table-scroll { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 0; }
        .table-scroll th, .table-scroll td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border-color) !important; word-wrap: break-word; }
        .table-scroll thead th { position: sticky; top: 0; background-color: var(--primary-color) !important; color: white !important; z-index: 2; margin: 0; padding-top: 0.5rem; border-color: transparent !important; }
        .table-scroll-container::-webkit-scrollbar { width: 10px; }
        .table-scroll-container::-webkit-scrollbar-thumb { background: var(--primary-color); height: 42.5px; }
        .table-scroll-container::-webkit-scrollbar-track { background: #f1f1f1; }
        
        body.dark-mode .table-section{ background-color: var(--dark-card); color: var(--dark-text-main); }
        body.dark-mode .table-scroll-container::-webkit-scrollbar-thumb { background: #4e73df; }
        body.dark-mode .table-scroll-container::-webkit-scrollbar-track { background: var(--dark-bg); }
        body.dark-mode .table-scroll th, body.dark-mode .table-scroll td { border-bottom-color: var(--dark-border); }
        '''
    },
    'c:/xampp/htdocs/core3/Archive.php': {
        'title': 'Archived Documents',
        'replace_marks': [('__ARCHIVE_SHOW__', 'show'), ('__ARCHIVE_DOC_ACTIVE__', 'active')],
        'content_start_marker': '<div class="card shadow-sm">',
        'extra_css': ''
    },
    'c:/xampp/htdocs/core3/Archive_CRM.php': {
        'title': 'Archived Customers',
        'replace_marks': [('__ARCHIVE_SHOW__', 'show'), ('__ARCHIVE_CRM_ACTIVE__', 'active')],
        'content_start_marker': '<div class="card shadow-sm">',
        'extra_css': ''
    }
}

for path, config in files_to_update.items():
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # 1. Replace CSS
    idx_style_start = content.find('<style>')
    idx_style_end = content.find('</style>')
    if idx_style_start != -1 and idx_style_end != -1:
        css = c1 + config['extra_css']
        old_style = content[idx_style_start:idx_style_end+8]
        content = content.replace(old_style, '<style>\n' + css + '\n</style>')

    # 2. Replace Body (Sidebar + Header)
    idx_body = content.find('<body>')
    if idx_body != -1:
        idx_content_start = content.find(config['content_start_marker'])
        
        if idx_content_start != -1:
            body_new = c2.replace('__PAGE_TITLE__', config['title'])
            
            # Clean up all missing markers to effectively hide/deactivate them
            marks = ['__CRM_SHOW__', '__CRM_ACTIVE__', '__FEEDBACK_ACTIVE__', '__SLA_SHOW__', '__CONTRACTS_ACTIVE__', '__SLA_ACTIVE__', '__EDOC_ACTIVE__', '__COMPLETED_ACTIVE__', '__BIFA_ACTIVE__', '__REPORTS_ACTIVE__', '__ACTIVITY_ACTIVE__', '__ARCHIVE_SHOW__', '__ARCHIVE_DOC_ACTIVE__', '__ARCHIVE_CRM_ACTIVE__']
            
            from_marks = set(m for m, val in config['replace_marks'])
            for m in marks:
                if m not in from_marks:
                    body_new = body_new.replace(m, '')
            
            for mark, val in config['replace_marks']:
                body_new = body_new.replace(mark, val)
                
            old_body_part = content[idx_body:idx_content_start]
            content = content.replace(old_body_part, body_new + '\n')

    # 3. Replace JS Hamburger Event Listener
    # Instead of direct string match which varies slightly in white space, try regex replace:
    content = re.sub(r"document\.getElementById\('hamburger'\)\.addEventListener\('click',\s*(function\s*\(\)|=>|=>\s*\{).*?\}\);", c3, content, flags=re.DOTALL)
    
    # Also fix explicit `function () {` format
    content = re.sub(r"document\.getElementById\('hamburger'\)\.addEventListener\('click',\s*function\s*\(\)\s*\{.*?\}\);", c3, content, flags=re.DOTALL)

    with open(path, 'w', encoding='utf-8') as f:
         f.write(content)
