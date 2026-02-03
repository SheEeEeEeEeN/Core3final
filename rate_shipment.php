<?php
include("darkmode.php");
include("connection.php");
include("session.php");
include('loading.html');
requireRole('user');

// -- USER INFO --
$username = $_SESSION['email']; 
$q_user = mysqli_query($conn, "SELECT id, profile_image FROM accounts WHERE email = '$username'");
$user = mysqli_fetch_assoc($q_user);
$userId = $user['id'];
$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 'user.png';

$shipment_id = isset($_GET['shipment_id']) ? intval($_GET['shipment_id']) : 0;
$error = "";
$success = "";

// -- HUB LOGIC: DASHBOARD vs FORM --
$is_form = ($shipment_id > 0);

// -- CHECK ELIGIBILITY (If Form) --
if($is_form) {
    // Check if delivered
    $check = mysqli_query($conn, "SELECT * FROM shipments WHERE id=$shipment_id AND user_id=$userId");
    $shipment = mysqli_fetch_assoc($check);
    
    if (!$shipment) {
        $error = "Shipment not found or access denied.";
        $is_form = false;
    } elseif ($shipment['status'] !== 'Delivered') {
        $error = "This shipment is not delivered yet.";
        $is_form = false;
    } else {
        // Check if already rated
        $exists = mysqli_query($conn, "SELECT id FROM shipment_ratings WHERE shipment_id=$shipment_id AND user_id=$userId");
        if (mysqli_num_rows($exists) > 0) {
            $error = "You have already rated this shipment.";
            $is_form = false;
        }
    }
}

// -- HANDLE SUBMIT --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating']) && $is_form) {
    $rating = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    // Detailed Ratings
    $r_speed = intval($_POST['rating_speed']);
    $r_handling = intval($_POST['rating_handling']);
    $r_comm = intval($_POST['rating_communication']);
    $r_driver = intval($_POST['rating_driver']);
    $r_vehicle = intval($_POST['rating_vehicle']);
    $r_company = intval($_POST['rating_company']);
    $r_support = !empty($_POST['rating_support']) ? intval($_POST['rating_support']) : 'NULL';
    $is_anon  = isset($_POST['is_anonymous']) ? 1 : 0;

    $insert = "INSERT INTO shipment_ratings 
    (shipment_id, user_id, rating, comment, rating_speed, rating_handling, rating_communication, rating_driver, rating_vehicle, rating_company, rating_support, is_anonymous) 
    VALUES ('$shipment_id', '$userId', '$rating', '$comment', '$r_speed', '$r_handling', '$r_comm', '$r_driver', '$r_vehicle', '$r_company', $r_support, '$is_anon')";
    
    if (mysqli_query($conn, $insert)) {
        $success = "Review submitted successfully!";
        // Redirect to dashboard view
        echo "<script>setTimeout(function(){ window.location.href = 'rate_shipment.php'; }, 2000);</script>";
        $is_form = false; // Hide form
    } else {
        $error = "Error saving rating: " . mysqli_error($conn);
    }
}

// -- FETCH DASHBOARD DATA --
if (!$is_form) {
    // Pending
    $q_pending = "SELECT s.* FROM shipments s 
                  LEFT JOIN shipment_ratings r ON s.id = r.shipment_id 
                  WHERE s.user_id = '$userId' 
                  AND s.status = 'Delivered' 
                  AND r.id IS NULL 
                  ORDER BY s.created_at DESC";
    $res_pending = mysqli_query($conn, $q_pending);

    // History
    $q_history = "SELECT r.*, s.contract_number, s.sender_name 
                  FROM shipment_ratings r 
                  JOIN shipments s ON r.shipment_id = s.id 
                  WHERE r.user_id = '$userId' 
                  ORDER BY r.created_at DESC";
    $res_history = mysqli_query($conn, $q_history);

    // Stats
    $total_reviews = mysqli_num_rows($res_history);
    $avg_rating = 0;
    if ($total_reviews > 0) {
        $data_hist = mysqli_fetch_all($res_history, MYSQLI_ASSOC);
        mysqli_data_seek($res_history, 0);
        $sum = array_sum(array_column($data_hist, 'rating'));
        $avg_rating = number_format($sum / $total_reviews, 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
  <title>Reviews & Ratings | Core3</title>
  
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
      --secondary-color: #f3f4f6;
      --text-main: #2b2d42;
      --text-secondary: #8d99ae;
      --dark-bg: #0f172a;
      --dark-card: #1e293b;
      --dark-border: #334155;
      --dark-text-main: #f8fafc;
      --dark-text-sec: #94a3b8;
      --radius-lg: 16px;
      --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
      --shadow-md: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    * { box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background-color: var(--secondary-color); color: var(--text-main); overflow-x: hidden; }
    
    /* SIDEBAR */
    .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; left: 0; top: 0; background: #2c3e50; color: white; z-index: 1040; transition: all .3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1); }
    .sidebar.collapsed { transform: translateX(-100%); }
    .sidebar-header { padding: 2rem 1.5rem 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    
    .content { margin-left: var(--sidebar-width); padding: 30px; transition: margin-left .3s cubic-bezier(0.4, 0, 0.2, 1); min-height: 100vh; }
    .content.expanded { margin-left: 0; }
    .nav-link { font-weight: 500; color: rgba(255, 255, 255, 0.7) !important; transition: all 0.2s; margin-bottom: 5px; border-radius: 8px; }
    .nav-link:hover, .nav-link.active { color: #fff !important; background: rgba(255, 255, 255, 0.1); transform: translateX(5px); }
    .nav-link i { margin-right: 10px; }

    /* CONTENT STYLES */
    .card { background: white; border-radius: var(--radius-lg); border: none; box-shadow: var(--shadow-md); transition: transform 0.2s; padding: 2rem; }
    .header { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: 1rem 1.5rem; margin-bottom: 30px; border: 1px solid rgba(0, 0, 0, 0.02); }
    
    /* RATING STARS */
    .rating-wrapper { direction: rtl; display: inline-flex; gap: 5px; justify-content: center; width: 100%; }
    .rating-wrapper input { display: none; }
    .rating-wrapper label { font-size: 2rem; color: #ddd; cursor: pointer; transition: color 0.2s; }
    .rating-wrapper input:checked ~ label, .rating-wrapper label:hover, .rating-wrapper label:hover ~ label { color: #ffc107; }

    /* Mini Rating Stars */
    .rating-mini { direction: rtl; display: inline-flex; gap: 2px; }
    .rating-mini input { display: none; }
    .rating-mini label { font-size: 1.2rem; color: #e9ecef; cursor: pointer; }
    .rating-mini input:checked ~ label, .rating-mini label:hover, .rating-mini label:hover ~ label { color: #ffc107; }

    /* RESPONSIVE */
    @media (max-width: 992px) {
      .sidebar { left: calc(var(--sidebar-width) * -1); }
      .sidebar.mobile-open { left: 0; }
      .content { margin-left: 0 !important; padding: 15px; }
    }
    .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); z-index: 1030; display: none; opacity: 0; transition: opacity 0.3s; }
    .sidebar-overlay.show { display: block; opacity: 1; }

    /* DARK MODE */
    body.dark-mode { background-color: var(--dark-bg); color: var(--dark-text-main); }
    body.dark-mode .card, body.dark-mode .header, body.dark-mode .form-control { background-color: var(--dark-card) !important; color: var(--dark-text-main); border-color: var(--dark-border); }
    body.dark-mode .text-muted { color: var(--dark-text-sec) !important; }
  </style>
</head>
<body>

  <!-- SIDEBAR -->
  <div class="sidebar d-flex flex-column flex-shrink-0 p-3" id="sidebar">
    <div class="text-center mb-4 mt-2">
      <img src="Remorig.png" alt="Freight Logo" class="img-fluid mb-2" style="max-width: 140px;">
      <h6 class="fw-semibold text-uppercase text-light-50 mb-0" style="font-size: 0.85rem;">Core Transaction 3</h6>
    </div>
    <hr class="text-light opacity-25">
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item mb-2"><a href="user.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-house-door-fill fs-5"></i><span>Dashboard</span></a></li>
      <li class="nav-item mb-2"><a href="bookshipment.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-truck fs-5"></i><span>Book Shipment</span></a></li>
      <ul class="nav nav-pills flex-column mb-auto"></ul>
      <li class="nav-item mb-2"><a href="My_shipment.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-truck fs-5"></i><span>My Shipments</span></a></li>
      <li class="nav-item mb-2"><a href="shiphistory.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-clock-history fs-5"></i><span>Shipment History</span></a></li>
      <li class="nav-item mb-2"><a href="feedback.php" class="nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-chat-dots fs-5"></i><span>Helpdesk Support</span></a></li>
      <li class="nav-item mb-2"><a href="rate_shipment.php" class="active nav-link text-white d-flex align-items-center gap-2 px-3 py-2 rounded-3 hover-link"><i class="bi bi-star-fill fs-5"></i><span>Rate Shipments</span></a></li>
    </ul>
  </div>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- CONTENT -->
  <div class="content" id="mainContent">
    
    <!-- HEADER -->
    <header class="header d-flex align-items-center justify-content-between px-4 py-3 mb-4 bg-white shadow-sm rounded-3 sticky-top">
        <div class="d-flex align-items-center gap-3">
            <button class="hamburger btn btn-light border-0 p-2" id="hamburger"><i class="bi bi-list fs-4"></i></button>
            <div>
                <h5 class="fw-semibold mb-0"><?php echo $is_form ? 'Submit Review' : 'Reviews & Ratings'; ?></h5>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
             <div class="dropdown me-3">
                <a href="#" class="text-dark position-relative" id="notifDropdown" data-bs-toggle="dropdown" onclick="markRead()">
                    <i class="bi bi-bell fs-4"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge" style="display: none;">0</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm p-0" style="width: 300px; max-height: 400px; overflow-y: auto;">
                    <li class="p-2 border-bottom fw-bold bg-light">Notifications</li>
                    <div id="notifList"><li class="text-center p-3 text-muted small">No new notifications</li></div>
                    <li><a class="dropdown-item text-center small text-primary p-2 border-top" href="feedback.php">View All</a></li>
                </ul>
            </div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="<?php echo $profileImage; ?>" alt="Profile" class="rounded-circle" width="40" height="40" style="object-fit:cover;">
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="user-profile.php">Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                </ul>
            </div>
            <div class="form-check form-switch mb-0">
                <label class="form-check-label" for="userThemeToggle">🌙</label>
                <input class="form-check-input" type="checkbox" role="switch" id="userThemeToggle">
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <?php if($is_form): ?>
            <!-- === RATING FORM === -->
            <div class="row justify-content-center">
                <div class="col-md-7">
                    <div class="card shadow-lg border-0 rounded-4">
                        <div class="card-body p-4">
                            <?php if($error): ?>
                                <div class="alert alert-danger text-center"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?></div>
                                <div class="text-center"><a href="rate_shipment.php" class="btn btn-outline-secondary">Back to Reviews</a></div>
                            <?php else: ?>
                                
                                <div class="text-center mb-4">
                                    <div class="badge bg-success bg-opacity-10 text-success mb-2 px-3 py-2 rounded-pill">Completed Shipment</div>
                                    <h4 class="fw-bold text-primary">How was your delivery?</h4>
                                    <p class="text-muted small">Shipment #<?php echo str_pad($shipment_id, 6, "0", STR_PAD_LEFT); ?> • <?php echo htmlspecialchars($shipment['sender_name']); ?></p>
                                </div>

                                <form method="POST">
                                    <!-- Overall Rating -->
                                    <div class="mb-4 text-center border-bottom pb-4">
                                        <label class="form-label fw-bold mb-2">Overall Satisfaction</label>
                                        <div class="rating-wrapper">
                                            <input type="radio" name="rating" id="star5" value="5" required/><label for="star5"><i class="bi bi-star-fill"></i></label>
                                            <input type="radio" name="rating" id="star4" value="4"/><label for="star4"><i class="bi bi-star-fill"></i></label>
                                            <input type="radio" name="rating" id="star3" value="3"/><label for="star3"><i class="bi bi-star-fill"></i></label>
                                            <input type="radio" name="rating" id="star2" value="2"/><label for="star2"><i class="bi bi-star-fill"></i></label>
                                            <input type="radio" name="rating" id="star1" value="1"/><label for="star1"><i class="bi bi-star-fill"></i></label>
                                        </div>
                                    </div>

                                    <!-- Detailed Ratings -->
                                    <div class="mb-4">
                                        <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-sliders me-2"></i>Rate Categories</h6>
                                        <div class="row g-3">
                                            <!-- Speed -->
                                            <div class="col-md-6 d-flex align-items-center justify-content-between">
                                                <span class="small fw-semibold">Delivery Speed</span>
                                                <div class="rating-mini">
                                                    <?php for($i=5;$i>=1;$i--) echo "<input type='radio' name='rating_speed' id='s$i' value='$i'/><label for='s$i'>★</label>"; ?>
                                                </div>
                                            </div>
                                            <!-- Handling -->
                                            <div class="col-md-6 d-flex align-items-center justify-content-between">
                                                <span class="small fw-semibold">Cargo Handling</span>
                                                <div class="rating-mini">
                                                    <?php for($i=5;$i>=1;$i--) echo "<input type='radio' name='rating_handling' id='h$i' value='$i'/><label for='h$i'>★</label>"; ?>
                                                </div>
                                            </div>
                                            <!-- Communication -->
                                            <div class="col-md-6 d-flex align-items-center justify-content-between">
                                                <span class="small fw-semibold">Communication</span>
                                                <div class="rating-mini">
                                                    <?php for($i=5;$i>=1;$i--) echo "<input type='radio' name='rating_communication' id='c$i' value='$i'/><label for='c$i'>★</label>"; ?>
                                                </div>
                                            </div>
                                            <!-- Support -->
                                            <div class="col-md-6 d-flex align-items-center justify-content-between">
                                                <span class="small fw-semibold">Dispatcher Support</span>
                                                <div class="rating-mini">
                                                    <?php for($i=5;$i>=1;$i--) echo "<input type='radio' name='rating_support' id='sp$i' value='$i'/><label for='sp$i'>★</label>"; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Specific Entities -->
                                    <div class="mb-4">
                                        <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-people-fill me-2"></i>Rate Personnel</h6>
                                        <div class="row g-3">
                                            <!-- Driver -->
                                            <div class="col-md-6 d-flex align-items-center justify-content-between">
                                                <span class="small fw-semibold">Driver</span>
                                                <div class="rating-mini">
                                                     <?php for($i=5;$i>=1;$i--) echo "<input type='radio' name='rating_driver' id='d$i' value='$i'/><label for='d$i'>★</label>"; ?>
                                                </div>
                                            </div>
                                            <!-- Vehicle -->
                                            <div class="col-md-6 d-flex align-items-center justify-content-between">
                                                <span class="small fw-semibold">Vehicle Condition</span>
                                                <div class="rating-mini">
                                                     <?php for($i=5;$i>=1;$i--) echo "<input type='radio' name='rating_vehicle' id='v$i' value='$i'/><label for='v$i'>★</label>"; ?>
                                                </div>
                                            </div>
                                            <!-- Company -->
                                            <div class="col-md-12 d-flex align-items-center justify-content-between">
                                                <span class="small fw-semibold">Freight Service / Company</span>
                                                <div class="rating-mini">
                                                     <?php for($i=5;$i>=1;$i--) echo "<input type='radio' name='rating_company' id='fc$i' value='$i'/><label for='fc$i'>★</label>"; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Comment -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-muted">Additional Comments</label>
                                        <textarea name="comment" class="form-control bg-light" rows="3" placeholder="Tell us more about your experience..."></textarea>
                                    </div>

                                    <!-- Options -->
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" name="is_anonymous" id="anonCheck">
                                        <label class="form-check-label small text-muted" for="anonCheck">
                                            Submit anonymously to driver
                                        </label>
                                    </div>

                                    <button type="submit" name="submit_rating" class="btn btn-primary w-100 py-2 fw-bold text-uppercase shadow-sm">Submit Complete Review</button>
                                    <a href="rate_shipment.php" class="btn btn-link text-decoration-none w-100 mt-2 text-muted small">Cancel</a>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- === HUB DASHBOARD === -->
            
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center gap-3 h-100">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary"><i class="bi bi-star-fill fs-4"></i></div>
                        <div><h6 class="text-muted small mb-0 fw-bold text-uppercase">Avg. Rating</h6><h3 class="fw-bold mb-0"><?php echo $avg_rating; ?> <small class="text-muted fs-6">/ 5</small></h3></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center gap-3 h-100">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success"><i class="bi bi-check-all fs-4"></i></div>
                        <div><h6 class="text-muted small mb-0 fw-bold text-uppercase">Reviewed</h6><h3 class="fw-bold mb-0"><?php echo $total_reviews; ?></h3></div>
                    </div>
                </div>
            </div>

            <!-- Pending -->
            <h5 class="fw-bold mb-3">Pending Reviews</h5>
            <?php if(mysqli_num_rows($res_pending) > 0): ?>
                <div class="row g-3 mb-5">
                    <?php while($row = mysqli_fetch_assoc($res_pending)): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-success">Delivered</span>
                                    <small class="text-muted"><?php echo date('M d', strtotime($row['created_at'])); ?></small>
                                </div>
                                <h6 class="fw-bold mb-1">TRK<?php echo str_pad($row['id'], 6, "0", STR_PAD_LEFT); ?></h6>
                                <p class="small text-muted mb-3"><?php echo $row['sender_name']; ?> → <?php echo $row['receiver_name']; ?></p>
                                <a href="rate_shipment.php?shipment_id=<?php echo $row['id']; ?>" class="btn btn-primary w-100 mt-auto"><i class="bi bi-star me-2"></i>Rate Now</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="card p-4 text-center text-muted mb-5 bg-light border-dashed">No pending reviews. good job!</div>
            <?php endif; ?>

            <!-- History -->
            <h5 class="fw-bold mb-3">Rating History</h5>
            <div class="card p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Shipment</th>
                                <th>Overall</th>
                                <th class="d-none d-md-table-cell">Details</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($res_history) > 0): ?>
                                <?php while($h = mysqli_fetch_assoc($res_history)): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold">TRK<?php echo str_pad($h['shipment_id'], 6, "0", STR_PAD_LEFT); ?></div>
                                            <small class="text-muted"><?php echo $h['sender_name']; ?></small>
                                        </td>
                                        <td><span class="text-warning fw-bold"><i class="bi bi-star-fill"></i> <?php echo $h['rating']; ?></span></td>
                                        <td class="d-none d-md-table-cell"><small class="text-muted text-truncate d-inline-block" style="max-width: 200px;"><?php echo htmlspecialchars($h['comment']); ?></small></td>
                                        <td class="small text-muted"><?php echo date('M d, Y', strtotime($h['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
        if (typeof initDarkMode === 'function') initDarkMode("userThemeToggle", "userDarkMode");
        
        // SIDEBAR TOGGLE
        document.getElementById('hamburger').addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('mainContent');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            } else {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('show');
            }
        });

        document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('mobile-open');
            document.getElementById('sidebarOverlay').classList.remove('show');
        });

        // NOTIFICATIONS
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
