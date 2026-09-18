<?php
session_start();

// If already logged in, route them to their correct dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

require_once 'includes/db_connect.php';

$message = ''; 
$active_tab = 'student'; // Default to showing the student tab

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $login_type = $_POST['login_type']; // 'student' or 'admin'
    
    $active_tab = $login_type; // Keep them on the tab they tried to use if it fails

    if (!empty($email) && !empty($password)) {
        
        // SECURITY UPDATE: We now strictly require the 'role' to match the tab they used!
        $stmt = $conn->prepare("SELECT id, password_hash, role FROM users WHERE email = :email AND role = :role");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $login_type);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $message = "<div class='alert alert-danger'>Incorrect password.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>No " . ucfirst($login_type) . " account found with that email.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Please enter both email and password.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Scholarship Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background-color: #f4f7f6; display: flex; align-items: center; min-height: 100vh; }
        .login-card { border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; overflow: hidden; }
        .nav-tabs .nav-link { font-weight: 600; color: #6c757d; border: none; padding: 15px; border-bottom: 3px solid transparent; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; background: transparent; }
        .nav-tabs .nav-link.admin-tab.active { color: #dc3545; border-bottom: 3px solid #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card login-card bg-white">
                    
                    <!-- The Tabs Navigation -->
                    <ul class="nav nav-tabs nav-fill mb-4 bg-light" id="loginTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo ($active_tab == 'student') ? 'active' : ''; ?>" id="student-tab" data-bs-toggle="tab" data-bs-target="#student" type="button" role="tab">Student Portal</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link admin-tab <?php echo ($active_tab == 'admin') ? 'active' : ''; ?>" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button" role="tab">Admin Portal</button>
                        </li>
                    </ul>

                    <div class="card-body px-4 pb-5 pt-0">
                        <?php echo $message; ?>
                        
                        <div class="tab-content" id="loginTabsContent">
                            
                            <!-- STUDENT LOGIN TAB -->
                            <div class="tab-pane fade <?php echo ($active_tab == 'student') ? 'show active' : ''; ?>" id="student" role="tabpanel">
                                <div class="text-center mb-4">
                                    <h4 class="fw-bold text-primary">Student Sign In</h4>
                                </div>
                                <form action="login.php" method="POST">
                                    <input type="hidden" name="login_type" value="student">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Email address</label>
                                        <input type="email" name="email" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Password</label>
                                        <input type="password" name="password" class="form-control form-control-lg" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-lg w-100">Sign In as Student</button>
                                </form>
                                <div class="text-center mt-4">
                                    <p class="mb-0">New student? <a href="register.php" class="text-decoration-none fw-bold">Register here</a></p>
                                </div>
                            </div>

                            <!-- ADMIN LOGIN TAB -->
                            <div class="tab-pane fade <?php echo ($active_tab == 'admin') ? 'show active' : ''; ?>" id="admin" role="tabpanel">
                                <div class="text-center mb-4">
                                    <h4 class="fw-bold text-danger">Admin Access</h4>
                                    <p class="text-muted small">Authorized personnel only</p>
                                </div>
                                <form action="login.php" method="POST">
                                    <input type="hidden" name="login_type" value="admin">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Admin ID / Email</label>
                                        <input type="email" name="email" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Admin Password</label>
                                        <input type="password" name="password" class="form-control form-control-lg" required>
                                    </div>
                                    <button type="submit" class="btn btn-danger btn-lg w-100">Login to Admin Dashboard</button>
                                </form>
                                <!-- Notice there is NO sign-up link here! -->
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>