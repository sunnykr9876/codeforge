<?php
// Start a session to handle potential future login redirects
session_start();

// Include our database connection
require_once 'includes/db_connect.php';

$message = ''; // Variable to hold our success or error alerts

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Basic validation
    if (!empty($email) && !empty($password)) {
        
        // 1. Check if the email already exists in the database
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $message = "<div class='alert alert-danger'>This email is already registered. Please login.</div>";
        } else {
            // 2. Hash the password for security (never store plain text!)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                // Begin a database transaction (ensures both queries succeed or fail together)
                $conn->beginTransaction();
                
                // 3. Insert the new user credentials
                $insertUser = $conn->prepare("INSERT INTO users (email, password_hash, role) VALUES (:email, :password, 'student')");
                $insertUser->bindParam(':email', $email);
                $insertUser->bindParam(':password', $hashed_password);
                $insertUser->execute();
                
                // Get the newly created user's ID
                $new_user_id = $conn->lastInsertId();
                
                // 4. Initialize an empty profile for this new user
                $insertProfile = $conn->prepare("INSERT INTO profiles (user_id) VALUES (:user_id)");
                $insertProfile->bindParam(':user_id', $new_user_id);
                $insertProfile->execute();
                
                // Commit the transaction
                $conn->commit();
                
                $message = "<div class='alert alert-success'>Registration successful! You can now <a href='login.php' class='alert-link'>Sign In here</a>.</div>";
                
            } catch (Exception $e) {
                // If anything fails, roll back the changes
                $conn->rollBack();
                $message = "<div class='alert alert-danger'>Registration failed: " . $e->getMessage() . "</div>";
            }
        }
    } else {
        $message = "<div class='alert alert-warning'>Please fill in all fields.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Scholarship Portal</title>
    <!-- Bootstrap 5 CDN for professional, responsive styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f6;
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        .register-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card register-card p-4">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold text-primary">Student Sign Up</h3>
                            <p class="text-muted">Create your scholarship portal account</p>
                        </div>
                        
                        <!-- Output success or error messages here -->
                        <?php echo $message; ?>
                        
                        <form action="register.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email address</label>
                                <input type="email" name="email" class="form-control form-control-lg" placeholder="name@example.com" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Password</label>
                                <input type="password" name="password" class="form-control form-control-lg" placeholder="Create a secure password" required minlength="6">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Register</button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none fw-bold">Sign in here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>