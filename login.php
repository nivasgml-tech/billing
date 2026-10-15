<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= isset($settings['shop_name']) ? htmlspecialchars($settings['shop_name']) : 'Furniture ERP' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.4);
        }

        body {
            background: url('assets/img/login_bg.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            margin: 0;
            overflow: hidden;
        }

        /* Overlay for background */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.4) 0%, rgba(15, 23, 42, 0.6) 100%);
            z-index: 1;
        }

        .login-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-header {
            padding: 40px 30px 20px;
            text-align: center;
        }

        .login-header .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            margin: 0 auto 15px;
            font-size: 2rem;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.5);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        .login-header h4 {
            font-weight: 800;
            color: #1e293b;
            letter-spacing: -0.5px;
        }

        .login-body {
            padding: 10px 40px 40px;
        }

        .form-label {
            color: #475569;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .input-group {
            background: rgba(255, 255, 255, 0.5);
            border-radius: 14px;
            border: 1.5px solid #e2e8f0;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .input-group:focus-within {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            background: white;
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding-left: 15px;
        }

        .form-control {
            background: transparent;
            border: none;
            padding: 12px 15px;
            font-weight: 500;
            color: #1e293b;
        }

        .form-control:focus {
            box-shadow: none;
            background: transparent;
        }

        .btn-login {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border: none;
            border-radius: 14px;
            padding: 12px;
            font-weight: 700;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.4);
            color: white;
            filter: brightness(1.1);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Glass placeholder */
        ::placeholder {
            color: #94a3b8 !important;
            font-weight: 400;
        }

        .alert {
            border-radius: 14px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 25px;
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

    <div class="login-container">
       
        <div class="card login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <i class="fas fa-couch"></i>
                </div>
                <h4><?= isset($settings['shop_name']) ? htmlspecialchars($settings['shop_name']) : 'Furniture ERP' ?></h4>
                <p class="text-muted small">Elevate your workspace management</p>
            </div>
            <div class="login-body">
                <div id="loginAlert"></div>
                <form id="loginForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required placeholder="Enter username">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Enter password">
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-login" id="loginBtn">
                            Login into Account <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="text-center pb-4">
                <p class="text-muted small mb-0">&copy; <?= date('Y') ?> Advanced Furniture ERP</p>
            </div>
            
        
        </div>
             <div class="text-center mb-4">
            <p class="text-white-50 small fw-bold mb-0" style="letter-spacing: 1px; text-transform: uppercase;">Powered by 2B Softwares</p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#loginForm').submit(function(e) {
                e.preventDefault();
                $('#loginBtn').html('<i class="fas fa-spinner fa-spin"></i> Logging in...').attr('disabled', true);

                $.ajax({
                    url: 'ajax/login_action.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#loginAlert').html('<div class="alert alert-success">Login successful. Redirecting...</div>');
                            setTimeout(function() {
                                window.location.href = 'index.php';
                            }, 1000);
                        } else {
                            $('#loginAlert').html('<div class="alert alert-danger">' + response.message + '</div>');
                            $('#loginBtn').html('<i class="fas fa-sign-in-alt me-2"></i> Login').attr('disabled', false);
                        }
                    },
                    error: function() {
                        $('#loginAlert').html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
                        $('#loginBtn').html('<i class="fas fa-sign-in-alt me-2"></i> Login').attr('disabled', false);
                    }
                });
            });
        });
    </script>
</body>

</html>