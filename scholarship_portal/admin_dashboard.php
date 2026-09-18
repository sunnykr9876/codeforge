<?php
session_start();
// 1. Strict Security: Kick out anyone who is not logged in OR is not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php"); // Send students back to their dashboard
    exit();
}

require_once 'includes/db_connect.php';

// Handle Manual Approve/Reject actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action_id = $_GET['id'];
    $new_status = ($_GET['action'] == 'approve') ? 'Approved' : 'Rejected';
    
    // We add a new 'final_decision' column logic here. 
    // AI Status (verified/failed) is what the robot thinks. 
    // Final Decision (approved/rejected) is what the human decides.
    $updateStmt = $conn->prepare("UPDATE applications SET final_decision = :decision WHERE id = :id");
    $updateStmt->bindParam(':decision', $new_status);
    $updateStmt->bindParam(':id', $action_id);
    $updateStmt->execute();
    
    header("Location: admin_dashboard.php?msg=updated");
    exit();
}

// Fetch all applications, joining with the profiles table to get the student's name
$query = "SELECT a.*, p.full_name, p.profile_photo 
          FROM applications a 
          JOIN profiles p ON a.user_id = p.user_id 
          ORDER BY a.submitted_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Scholarship Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background-color: #1a252f; min-height: 100vh; color: white; }
        .sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 15px 20px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background-color: #2c3e50; color: white; border-left: 4px solid #e74c3c; }
        .content-area { padding: 40px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 p-0 sidebar">
                <div class="p-4 text-center border-bottom border-secondary">
                    <h5 class="fw-bold mb-0 text-danger">Admin Portal</h5>
                </div>
                <div class="mt-3">
                    <a href="admin_dashboard.php" class="active">All Applications</a>
                    <a href="logout.php" class="text-danger mt-5">Log Out</a>
                </div>
            </div>

            <div class="col-md-10 content-area">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold m-0">Application Master Review</h2>
                    <span class="badge bg-primary fs-6">Admin User: <?php echo $_SESSION['user_id']; ?></span>
                </div>

                <?php if(isset($_GET['msg'])): ?>
                    <div class="alert alert-success">Application status updated successfully.</div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="py-3 px-4">Applicant</th>
                                        <th>Course & Income</th>
                                        <th>Certificate Uploaded</th>
                                        <th>AI Scan Result</th>
                                        <th>Final Decision</th>
                                        <th class="text-end px-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($applications) > 0): ?>
                                        <?php foreach($applications as $app): ?>
                                        <tr>
                                            <td class="px-4">
                                                <div class="d-flex align-items-center">
                                                    <?php if(!empty($app['profile_photo'])): ?>
                                                        <img src="uploads/user_<?php echo $app['user_id']; ?>/<?php echo $app['profile_photo']; ?>" class="rounded-circle me-3 border" style="width: 45px; height: 45px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary me-3 d-flex justify-content-center align-items-center text-white" style="width: 45px; height: 45px;">?</div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                                        <div class="text-muted small">ID: #<?php echo $app['id']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($app['course_name']); ?></div>
                                                <div class="text-success small">₹<?php echo number_format($app['parents_income'], 2); ?>/yr</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($app['certificate_type']); ?></div>
                                                <a href="uploads/user_<?php echo $app['user_id']; ?>/<?php echo $app['certificate_file']; ?>" target="_blank" class="small text-decoration-none">View Document &rarr;</a>
                                            </td>
                                            <td>
                                                <?php 
                                                    if($app['ai_status'] == 'verified') echo "<span class='badge bg-success bg-opacity-10 text-success border border-success'>Pass: Verified</span>";
                                                    else echo "<span class='badge bg-warning bg-opacity-10 text-dark border border-warning'>Review Needed</span>";
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $decision = $app['final_decision'] ?? 'Pending';
                                                    if($decision == 'Approved') echo "<span class='badge bg-success px-3 py-2'>APPROVED</span>";
                                                    elseif($decision == 'Rejected') echo "<span class='badge bg-danger px-3 py-2'>REJECTED</span>";
                                                    else echo "<span class='badge bg-secondary px-3 py-2'>PENDING</span>";
                                                ?>
                                            </td>
                                            <td class="text-end px-4">
                                                <?php if($decision == 'Pending'): ?>
                                                    <a href="admin_dashboard.php?action=approve&id=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-success me-1">Approve</a>
                                                    <a href="admin_dashboard.php?action=reject&id=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-danger">Reject</a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Decision Final</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center py-5 text-muted">No applications submitted yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>