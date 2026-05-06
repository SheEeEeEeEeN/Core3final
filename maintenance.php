<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance - Core Transaction 3</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #0f172a;
            --secondary-bg: #1e293b;
            --accent: #3b82f6;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #dddad6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            color: var(--text-main);
            overflow: hidden;
            position: relative;
        }

        /* Abstract Background Elements */
        .bg-shape {
            position: absolute;
            filter: blur(120px);
            z-index: 0;
            opacity: 0.3;
            animation: pulse-glow 8s ease-in-out infinite alternate;
        }

        .shape-1 {
            width: 500px;
            height: 500px;
            background: var(--accent);
            top: -150px;
            left: -150px;
            border-radius: 50%;
        }

        .shape-2 {
            width: 600px;
            height: 600px;
            background: #6366f1;
            bottom: -250px;
            right: -150px;
            border-radius: 50%;
            animation-delay: -4s;
        }

        @keyframes pulse-glow {
            0% {
                transform: scale(0.8);
                opacity: 0.2;
            }

            100% {
                transform: scale(1.1);
                opacity: 0.4;
            }
        }

        .maintenance-container {
            position: relative;
            z-index: 10;
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 3.5rem 3rem;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            text-align: center;
            max-width: 580px;
            width: 90%;
        }

        .icon-container {
            position: relative;
            display: inline-block;
            margin-bottom: 2rem;
        }

        .icon-ring {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 130px;
            height: 130px;
            border: 1px dashed rgba(59, 130, 246, 0.4);
            border-radius: 50%;
            animation: rotate 15s linear infinite;
        }

        .icon-ring-inner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100px;
            height: 100px;
            border: 2px solid rgba(59, 130, 246, 0.1);
            border-top-color: var(--accent);
            border-left-color: rgba(99, 102, 241, 0.8);
            border-radius: 50%;
            animation: rotate 4s linear infinite reverse;
        }

        @keyframes rotate {
            0% {
                transform: translate(-50%, -50%) rotate(0deg);
            }

            100% {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        .icon {
            font-size: 3.5rem;
            color: var(--accent);
            line-height: 1;
            padding: 24px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 50%;
            position: relative;
            z-index: 2;
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2);
        }

        h1 {
            font-weight: 700;
            font-size: 2.25rem;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #f8fafc 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: var(--text-muted);
            font-size: 1.05rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .status-box {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .status-item:last-child {
            margin-bottom: 0;
        }

        .status-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
        }

        .status-value {
            font-weight: 500;
            font-size: 0.95rem;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-custom {
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-main);
        }

        .btn-custom:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            box-shadow: 0 10px 20px -10px rgba(0, 0, 0, 0.3);
        }

        .btn-danger-custom {
            background: rgba(239, 68, 68, 0.05);
            border-color: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }

        .btn-danger-custom:hover {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
            color: #fff;
            box-shadow: 0 10px 20px -10px rgba(239, 68, 68, 0.2);
        }

        .brand-footer {
            margin-top: 2.5rem;
            font-size: 0.75rem;
            color: rgba(148, 163, 184, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
    </style>
</head>

<body>

    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <div class="maintenance-container">
        <div class="icon-container">
            <div class="icon-ring"></div>
            <div class="icon-ring-inner"></div>
            <i class="bi bi-hdd-stack icon"></i>
        </div>

        <h1>System Maintenance </h1>
        <b>Please Contact </b>


        <div class="status-box">
            <div class="status-item">
                <span class="status-label"><i class="bi bi-activity me-2"></i>System Status</span>
                <span class="status-value text-warning d-flex align-items-center gap-2">
                    <span class="spinner-grow spinner-grow-sm" role="status" style="width: 10px; height: 10px;"></span>
                    Deploying Updates
                </span>
            </div>
            <div class="status-item">
                <span class="status-label"><i class="bi bi-clock-history me-2"></i>Est. Duration</span>
                <span class="status-value text-light">1 - 2 Hours</span>
            </div>
            <div class="status-item">
                <span class="status-label"><i class="bi bi-shield-check me-2"></i>Security Impact</span>
                <span class="status-value text-info">Data Integrity Secured</span>
            </div>
        </div>

        <div class="action-buttons">
            <a href="#" class="btn-custom" onclick="alert('message -> Roy Valle'); return false;">
                <i class="bi bi-headset"></i> Contact IT Support
            </a>
            <a href="logout.php" class="btn-custom btn-danger-custom">
                <i class="bi bi-box-arrow-right"></i> Secure Logout
            </a>
        </div>

        <div class="brand-footer">
            <i class="bi bi-shield-lock-fill"></i> Core 3 &copy; <?php echo date("Y"); ?>
        </div>
    </div>

</body>

</html>