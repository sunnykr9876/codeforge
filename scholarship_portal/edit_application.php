<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$message = '';

// Check if a valid ID was passed in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my_applications.php");
    exit();
}
$app_id = $_GET['id'];

// Securely fetch ONLY the application belonging to this user
$stmt = $conn->prepare("SELECT * FROM applications WHERE id = :app_id AND user_id = :user_id");
$stmt->bindParam(':app_id', $app_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    die("Application not found or access denied.");
}

// Handle Form Submission for Edits
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_application'])) {
    $income = trim($_POST['parents_income']);
    $course = trim($_POST['course_name']);
    $cert_type = trim($_POST['certificate_type']);
    $cert_number = trim($_POST['certificate_number']);
    
    $user_dir = "uploads/user_" . $user_id . "/";
    $cert_file = $application['certificate_file']; // Keep old file by default
    $ai_status = $application['ai_status']; // Keep old status by default

    // Did they upload a NEW certificate? If yes, handle it and run AI again.
    if (!empty($_FILES["certificate_file"]["tmp_name"])) {
        $file_name = basename($_FILES["certificate_file"]["name"]);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
            $new_cert_name = time() . "_certificate." . $file_ext;
            $target_file = $user_dir . $new_cert_name;
            
            if (move_uploaded_file($_FILES["certificate_file"]["tmp_name"], $target_file)) {
                
                // THE FIX: Delete the old certificate from the folder to save space
                if (!empty($application['certificate_file']) && file_exists($user_dir . $application['certificate_file'])) {
                    unlink($user_dir . $application['certificate_file']);
                }
                
                $cert_file = $new_cert_name; // Update variable to new filename
                
                // --- RERUN AI SCANNER ON NEW IMAGE ---
                $tesseract_path = '"C:\Program Files\Tesseract-OCR\tesseract.exe"'; 
                $image_path = '"C:\xampp\htdocs\scholarship_portal\\' . $user_dir . $cert_file . '"';
                
                $command = "$tesseract_path $image_path stdout --psm 11 2>&1";
                $ocr_output = shell_exec($command);
                
                if ($ocr_output) {
                    $clean_ocr = strtoupper(preg_replace('/\s+/', '', $ocr_output));
                    $clean_input = strtoupper(preg_replace('/\s+/', '', $cert_number));
                    $ai_status = (strpos($clean_ocr, $clean_input) !== false) ? 'verified' : 'failed_review';
                }
            }
        } else {
            $message = "<div class='alert alert-danger'>Invalid file type. Only JPG/PNG allowed.</div>";
        }
    }

    try {
        $updateApp = $conn->prepare("UPDATE applications SET parents_income = :income, course_name = :course, certificate_type = :type, certificate_number = :number, certificate_file = :cert_file, ai_status = :ai_status WHERE id = :app_id AND user_id = :user_id");
        
        $updateApp->bindParam(':income', $income);
        $updateApp->bindParam(':course', $course);
        $updateApp->bindParam(':type', $cert_type);
        $updateApp->bindParam(':number', $cert_number);
        $updateApp->bindParam(':cert_file', $cert_file);
        $updateApp->bindParam(':ai_status', $ai_status);
        $updateApp->bindParam(':app_id', $app_id);
        $updateApp->bindParam(':user_id', $user_id);
        
        $updateApp->execute();
        
        // Refresh the page to show new data
        header("Location: edit_application.php?id=$app_id&success=1");
        exit();
        
    } catch(PDOException $e) {
        $message = "<div class='alert alert-danger'>Database error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Application - Scholarship Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5 mb-5">
        <a href="my_applications.php" class="btn btn-outline-secondary mb-4">&larr; Back to My Applications</a>
        
        <div class="card shadow-sm">
            <div class="card-header bg-white p-4 text-center">
                <h3 class="mb-0 fw-bold">Edit Application #<?php echo $application['id']; ?></h3>
                
                <?php if($application['ai_status'] == 'verified'): ?>
                    <span class='badge bg-success mt-2 fs-6'>AI Verified</span>
                <?php else: ?>
                    <span class='badge bg-warning text-dark mt-2 fs-6'>Manual Review Required</span>
                <?php endif; ?>
            </div>
            
            <div class="card-body p-4 p-md-5">
                <?php 
                    if(isset($_GET['success'])) echo "<div class='alert alert-success'>Application updated successfully!</div>";
                    echo $message; 
                ?>
                
                <form action="edit_application.php?id=<?php echo $app_id; ?>" method="POST" enctype="multipart/form-data">
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Current Course / Degree</label>
                            <input type="text" name="course_name" class="form-control" value="<?php echo htmlspecialchars($application['course_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Parents' Annual Income (₹)</label>
                            <input type="number" name="parents_income" class="form-control" value="<?php echo htmlspecialchars($application['parents_income']); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Certificate Type</label>
                            <select name="certificate_type" class="form-select" required>
                                <option value="Income Certificate" <?php if($application['certificate_type'] == 'Income Certificate') echo 'selected'; ?>>Income Certificate</option>
                                <option value="Academic Transcript" <?php if($application['certificate_type'] == 'Academic Transcript') echo 'selected'; ?>>Academic Transcript</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Certificate Number</label>
                            <input type="text" name="certificate_number" class="form-control" value="<?php echo htmlspecialchars($application['certificate_number']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4 p-3 bg-light border rounded">
                        <label class="form-label fw-semibold">Update Certificate Image</label>
                        <p class="small text-muted mb-2">Current file: <a href="uploads/user_<?php echo $user_id; ?>/<?php echo $application['certificate_file']; ?>" target="_blank">View Document</a></p>
                        <input type="file" name="certificate_file" class="form-control" accept=".jpg,.jpeg,.png">
                        <small class="text-primary mt-1 d-block">Only upload a new file if you want to overwrite the old one. If you upload a new image, the AI will scan it again.</small>
                    </div>

                    <div class="text-end">
                        <button type="submit" name="update_application" class="btn btn-primary px-4 py-2">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>