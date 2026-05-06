<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }
        .maintenance-card {
            background: #fff;
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .icon {
            font-size: 6rem;
            color: #f6c23e;
            margin-bottom: 20px;
        }
        h1 {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 15px;
            color: #222831;
        }
        p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn-custom {
            padding: 10px 30px;
            border-radius: 50px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="maintenance-card">
        <i class="bi bi-tools icon"></i>
        <h1>System Under Maintenance</h1>
        <p>We're currently performing scheduled maintenance to improve our services. Please check back later. We apologize for the inconvenience.</p>
        <a href="logout.php" class="btn btn-outline-danger btn-custom"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
    </div>

</body>
</html>
