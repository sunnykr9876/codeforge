<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$message = '';

// Helper function for profile images
function uploadProfileImage($input_name, $target_dir) {
    if (empty($_FILES[$input_name]["tmp_name"])) return null;
    $file_ext = strtolower(pathinfo($_FILES[$input_name]["name"], PATHINFO_EXTENSION));
    if (!in_array($file_ext, ['jpg', 'jpeg', 'png'])) return 'invalid_format';
    
    $new_name = time() . "_" . $input_name . "." . $file_ext;
    if (move_uploaded_file($_FILES[$input_name]["tmp_name"], $target_dir . $new_name)) return $new_name;
    return false;
}

// Handle Profile Update & Clear Old Files
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $dob = trim($_POST['dob']);
    $address = trim($_POST['address']);
    $course = trim($_POST['course_name']);
    
    $user_dir = "uploads/user_" . $user_id . "/";
    if (!file_exists($user_dir)) mkdir($user_dir, 0777, true);

    $currStmt = $conn->prepare("SELECT profile_photo, signature_file FROM profiles WHERE user_id = :user_id");
    $currStmt->bindParam(':user_id', $user_id);
    $currStmt->execute();
    $curr = $currStmt->fetch(PDO::FETCH_ASSOC);

    $new_photo = uploadProfileImage('profile_photo', $user_dir);
    $new_sig = uploadProfileImage('signature_file', $user_dir);

    if ($new_photo === 'invalid_format' || $new_sig === 'invalid_format') {
        $message = "<div class='alert alert-danger'>Only JPG and PNG images are allowed for Photo and Signature.</div>";
    } else {
        $final_photo = $new_photo ? $new_photo : $curr['profile_photo'];
        $final_sig = $new_sig ? $new_sig : $curr['signature_file'];

        // Delete old files to save space
        if ($new_photo && !empty($curr['profile_photo']) && file_exists($user_dir . $curr['profile_photo'])) {
            unlink($user_dir . $curr['profile_photo']); 
        }
        if ($new_sig && !empty($curr['signature_file']) && file_exists($user_dir . $curr['signature_file'])) {
            unlink($user_dir . $curr['signature_file']); 
        }

        $updateStmt = $conn->prepare("UPDATE profiles SET full_name=:name, phone=:phone, dob=:dob, address=:address, course_name=:course, profile_photo=:photo, signature_file=:sig WHERE user_id=:user_id");
        $updateStmt->execute([
            ':name' => $full_name, ':phone' => $phone, ':dob' => $dob, ':address' => $address, 
            ':course' => $course, ':photo' => $final_photo, ':sig' => $final_sig, ':user_id' => $user_id
        ]);
        $message = "<div class='alert alert-success'>Profile updated successfully!</div>";
    }
}

// Fetch current profile data to display
$profileStmt = $conn->prepare("SELECT * FROM profiles WHERE user_id = :user_id");
$profileStmt->bindParam(':user_id', $user_id);
$profileStmt->execute();
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Scholarship Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .sidebar { background-color: #2c3e50; min-height: 100vh; color: white; }
        .sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 15px 20px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background-color: #34495e; color: white; border-left: 4px solid #3498db; }
        .content-area { padding: 40px; }
        .card { border-radius: 10px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 p-0 sidebar">
                <div class="p-4 text-center border-bottom border-secondary"><h5 class="fw-bold mb-0">Scholarship Portal</h5></div>
                <div class="mt-3">
                    <a href="dashboard.php" class="active">My Profile</a>
                    <a href="apply.php">Apply for Scholarship</a>
                    <a href="my_applications.php">My Applications</a>
                    <a href="logout.php" class="text-danger mt-5">Log Out</a>
                </div>
            </div>

            <div class="col-md-10 content-area">
                <h2 class="fw-bold mb-4">My Profile</h2>
                <?php echo $message; ?>

                <div class="card p-4">
                    <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                        <h5 class="card-title mb-4 border-bottom pb-2">Personal & Academic Details</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Full Name</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Phone Number</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($profile['dob'] ?? ''); ?>" required></div>
                        </div>

                        <div class="mb-3"><label class="form-label fw-semibold">Home Address</label><textarea name="address" class="form-control" required><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea></div>
                        <div class="mb-4"><label class="form-label fw-semibold">Current Course / Degree</label><input type="text" name="course_name" class="form-control" placeholder="e.g. B.Tech Computer Science" value="<?php echo htmlspecialchars($profile['course_name'] ?? ''); ?>" required></div>

                        <!-- THIS IS THE CLEANED UP DOCUMENTS SECTION -->
                        <h5 class="card-title mb-3 mt-4 border-bottom pb-2">Documents</h5>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Profile Photo (JPG/PNG)</label>
                                <?php if(!empty($profile['profile_photo'])): ?> 
                                    <div class="mb-2 mt-1">
                                        <img src="uploads/user_<?php echo $user_id; ?>/<?php echo $profile['profile_photo']; ?>" alt="Profile Photo" class="img-thumbnail shadow-sm" style="height: 120px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="profile_photo" class="form-control mt-2" accept=".jpg,.jpeg,.png">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Digital Signature (JPG/PNG)</label>
                                <?php if(!empty($profile['signature_file'])): ?> 
                                    <div class="mb-2 mt-1">
                                        <img src="uploads/user_<?php echo $user_id; ?>/<?php echo $profile['signature_file']; ?>" alt="Signature" class="img-thumbnail shadow-sm" style="height: 80px; object-fit: contain;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="signature_file" class="form-control mt-2" accept=".jpg,.jpeg,.png">
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary px-4 py-2">Save Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>