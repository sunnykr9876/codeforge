<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$message = '';

// Fetch ALL profile data
$profileStmt = $conn->prepare("SELECT * FROM profiles WHERE user_id = :user_id");
$profileStmt->bindParam(':user_id', $user_id);
$profileStmt->execute();
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

// Strict check: Are they missing anything in their profile?
$profile_is_complete = true;
if (empty($profile['full_name']) || empty($profile['phone']) || empty($profile['course_name']) || empty($profile['profile_photo']) || empty($profile['signature_file'])) {
    $profile_is_complete = false;
}

// Handle form submission (Only Income & Certificate now!)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_application']) && $profile_is_complete) {
    $income = trim($_POST['parents_income']);
    $cert_type = trim($_POST['certificate_type']);
    $cert_number = trim($_POST['certificate_number']);
    
    $user_dir = "uploads/user_" . $user_id . "/";
    
    // Process ONLY the new certificate file
    if (!empty($_FILES["certificate_file"]["tmp_name"])) {
        $file_ext = strtolower(pathinfo($_FILES["certificate_file"]["name"], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
            $cert_file = time() . "_cert." . $file_ext;
            
            if (move_uploaded_file($_FILES["certificate_file"]["tmp_name"], $user_dir . $cert_file)) {
                
                // --- AI SCANNER ---
                $tesseract_path = '"C:\Program Files\Tesseract-OCR\tesseract.exe"'; 
                $image_path = '"C:\xampp\htdocs\scholarship_portal\\' . $user_dir . $cert_file . '"';
                
                $command = "$tesseract_path $image_path stdout --psm 11 2>&1";
                $ocr_output = shell_exec($command);
                
                $ai_status = 'pending';
                if ($ocr_output) {
                    $clean_ocr = strtoupper(preg_replace('/\s+/', '', $ocr_output));
                    $clean_input = strtoupper(preg_replace('/\s+/', '', $cert_number));
                    $ai_status = (strpos($clean_ocr, $clean_input) !== false) ? 'verified' : 'failed_review';
                }
                
                // Insert into Database. We copy the course, photo, and sig from the profile into the application history.
                $insertApp = $conn->prepare("INSERT INTO applications (user_id, parents_income, course_name, certificate_type, certificate_number, certificate_file, profile_photo, signature_file, ai_status) VALUES (:user_id, :income, :course, :type, :number, :cert_file, :photo, :sig, :ai_status)");
                
                $insertApp->execute([
                    ':user_id' => $user_id, ':income' => $income, ':course' => $profile['course_name'], 
                    ':type' => $cert_type, ':number' => $cert_number, ':cert_file' => $cert_file, 
                    ':photo' => $profile['profile_photo'], ':sig' => $profile['signature_file'], ':ai_status' => $ai_status
                ]);
                
                header("Location: my_applications.php?success=1&ai_status=" . $ai_status);
                exit();
            }
        } else {
            $message = "<div class='alert alert-danger'>Certificate must be JPG or PNG.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Please upload the certificate image.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Apply - Scholarship Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .sidebar { background-color: #2c3e50; min-height: 100vh; color: white; }
        .sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 15px 20px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background-color: #34495e; color: white; border-left: 4px solid #3498db; }
        .content-area { padding: 40px; }
        .card { border-radius: 10px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .auto-filled { background-color: #e9ecef; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 p-0 sidebar">
                <div class="p-4 text-center border-bottom border-secondary"><h5 class="fw-bold mb-0">Scholarship Portal</h5></div>
                <div class="mt-3">
                    <a href="dashboard.php">My Profile</a>
                    <a href="apply.php" class="active">Apply for Scholarship</a>
                    <a href="my_applications.php">My Applications</a>
                    <a href="logout.php" class="text-danger mt-5">Log Out</a>
                </div>
            </div>

            <div class="col-md-10 content-area">
                <h2 class="fw-bold mb-4">New Application</h2>
                
                <?php if (!$profile_is_complete): ?>
                    <div class="alert alert-danger shadow-sm border-danger">
                        <strong>Action Required:</strong> You must complete your <a href="dashboard.php" class="alert-link">Profile</a> (including Course, Photo, and Signature) before you can submit a scholarship application.
                    </div>
                <?php endif; ?>
                
                <?php echo $message; ?>

                <div class="card p-4">
                    <form action="apply.php" method="POST" enctype="multipart/form-data">
                        
                        <h5 class="card-title mb-3 border-bottom pb-2">Profile Snapshot (Auto-fetched)</h5>
                        
                        <div class="alert alert-info py-2">
                            <small>If you need to change your Course, Photo, or Signature, please update them in your <a href="dashboard.php" class="alert-link">Profile Section</a>.</small>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Full Name</label>
                                <input type="text" class="form-control auto-filled" value="<?php echo htmlspecialchars($profile['full_name'] ?? 'Not set'); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Current Course</label>
                                <input type="text" class="form-control auto-filled" value="<?php echo htmlspecialchars($profile['course_name'] ?? 'Not set'); ?>" disabled>
                            </div>
                            
                            <!-- Display Photo and Signature Images -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted d-block">Profile Photo</label>
                                <?php if(!empty($profile['profile_photo'])): ?>
                                    <span class="badge bg-success mb-2">Ready</span>
                                    <div class="d-block">
                                        <img src="uploads/user_<?php echo $user_id; ?>/<?php echo $profile['profile_photo']; ?>" class="img-thumbnail border-success" style="height: 100px; object-fit: cover;">
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-danger">Missing in Profile</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted d-block">Digital Signature</label>
                                <?php if(!empty($profile['signature_file'])): ?>
                                    <span class="badge bg-success mb-2">Ready</span>
                                    <div class="d-block">
                                        <img src="uploads/user_<?php echo $user_id; ?>/<?php echo $profile['signature_file']; ?>" class="img-thumbnail border-success" style="height: 60px; object-fit: contain;">
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-danger">Missing in Profile</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h5 class="card-title mb-3 border-bottom pb-2">Application Specific Details</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Parents' Annual Income (₹)</label>
                                <input type="number" name="parents_income" class="form-control" required <?php if(!$profile_is_complete) echo 'disabled'; ?>>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Certificate Type</label>
                                <select name="certificate_type" class="form-select" required <?php if(!$profile_is_complete) echo 'disabled'; ?>>
                                    <option value="Income Certificate">Income Certificate</option>
                                    <option value="Academic Transcript">Academic Transcript</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-semibold">Certificate Number (For AI Scan)</label>
                                <input type="text" name="certificate_number" class="form-control" required <?php if(!$profile_is_complete) echo 'disabled'; ?>>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-semibold">Upload Certificate Image (JPG/PNG)</label>
                                <input type="file" name="certificate_file" class="form-control" accept=".jpg,.jpeg,.png" required <?php if(!$profile_is_complete) echo 'disabled'; ?>>
                            </div>
                        </div>

                        <button type="submit" name="submit_application" class="btn btn-success px-5 py-2" <?php if (!$profile_is_complete) echo 'disabled'; ?>>Submit Application</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>