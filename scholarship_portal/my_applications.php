<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];

// Fetch all applications for this user
$stmt = $conn->prepare("SELECT * FROM applications WHERE user_id = :user_id ORDER BY submitted_at DESC");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Applications - Scholarship Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .sidebar { background-color: #2c3e50; min-height: 100vh; color: white; }
        .sidebar a { color: #adb5bd; text-decoration: none; display: block; padding: 15px 20px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background-color: #34495e; color: white; border-left: 4px solid #3498db; }
        .content-area { padding: 40px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 p-0 sidebar">
                <div class="p-4 text-center border-bottom border-secondary">
                    <h5 class="fw-bold mb-0">Scholarship Portal</h5>
                </div>
                <div class="mt-3">
                    <a href="dashboard.php">My Profile</a>
                    <a href="apply.php">Apply for Scholarship</a>
                    <a href="my_applications.php" class="active">My Applications</a>
                    <a href="logout.php" class="text-danger mt-5">Log Out</a>
                </div>
            </div>

            <div class="col-md-10 content-area">
                <h2 class="fw-bold mb-4">Submitted Applications</h2>
                
                <?php if (count($applications) > 0): ?>
<div class="table-responsive bg-white p-4 rounded shadow-sm">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Course</th>
                                    <th>Certificate</th>
                                    <th>Date Submitted</th>
                                    <th>AI Scan</th>
                                    <th>Final Decision</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($applications as $app): ?>
                                <tr>
                                    <td>#<?php echo $app['id']; ?></td>
                                    <td><?php echo htmlspecialchars($app['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['certificate_type']); ?></td>
                                    <td><?php echo date('M d, Y - h:i A', strtotime($app['submitted_at'])); ?></td>
                                    <td>
                                        <!-- AI Status (The Robot's View) -->
                                        <?php 
                                            if($app['ai_status'] == 'verified') echo "<span class='badge bg-success bg-opacity-10 text-success border border-success'>Verified</span>";
                                            elseif($app['ai_status'] == 'failed_review') echo "<span class='badge bg-warning bg-opacity-10 text-dark border border-warning'>Review Required</span>";
                                            else echo "<span class='badge bg-secondary bg-opacity-10 text-secondary border border-secondary'>Pending Scan</span>";
                                        ?>
                                    </td>
                                    <td>
                                        <!-- Final Decision (The Admin's View) -->
                                        <?php 
                                            $decision = $app['final_decision'] ?? 'Pending';
                                            if($decision == 'Approved') echo "<span class='badge bg-success px-3 py-2'>APPROVED</span>";
                                            elseif($decision == 'Rejected') echo "<span class='badge bg-danger px-3 py-2'>REJECTED</span>";
                                            else echo "<span class='badge bg-secondary px-3 py-2'>PENDING</span>";
                                        ?>
                                    </td>
                                    <td>
                                        <!-- Only allow editing if the Admin hasn't made a final decision yet! -->
                                        <?php if($decision == 'Pending'): ?>
                                            <a href="edit_application.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-primary">Edit / View</a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>Locked</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">You have not submitted any scholarship applications yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- AI Verification Popup Modal -->
    <?php if(isset($_GET['ai_status'])): ?>
    <div class="modal fade" id="aiStatusModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <!-- Change header color based on AI success/fail -->
                <div class="modal-header <?php echo ($_GET['ai_status'] == 'verified') ? 'bg-success text-white' : 'bg-warning text-dark'; ?>">
                    <h5 class="modal-title fw-bold">
                        <?php echo ($_GET['ai_status'] == 'verified') ? '🎉 AI Verification Successful' : '⚠️ Manual Review Required'; ?>
                    </h5>
                    <button type="button" class="btn-close <?php echo ($_GET['ai_status'] == 'verified') ? 'btn-close-white' : ''; ?>" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4 text-center">
                    <?php if($_GET['ai_status'] == 'verified'): ?>
                        <h4 class="text-success mb-3 fw-bold">Document Match Found!</h4>
                        <p class="text-muted">Our AI engine successfully read your document and verified the certificate number. Your application is fully verified and has been fast-tracked in the system.</p>
                    <?php else: ?>
                        <h4 class="text-warning text-dark mb-3 fw-bold">Verification Pending</h4>
                        <p class="text-muted">The AI scanner could not clearly read the certificate number you typed from the uploaded image. <br><br><strong>Don't worry!</strong> Your application was saved successfully. An administrator will review your document manually.</p>
                    <?php endif; ?>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Close & View Applications</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Required Bootstrap JS to make the modal pop up automatically -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var aiModal = new bootstrap.Modal(document.getElementById('aiStatusModal'));
            aiModal.show();
            
            // Clean up the URL so the popup doesn't show again if they refresh the page
            window.history.replaceState({}, document.title, "my_applications.php");
        });
    </script>
    <?php endif; ?>
</body>
</html>