<?php

require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();
$user = getCurrentUser();

$database = new Database();
$db = $database->getConnection();


$query = "SELECT * FROM documents ORDER BY upload_date DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);


$query = "SELECT * FROM benefits WHERE user_id = ? ORDER BY start_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user['id']]);
$user_benefits = $stmt->fetchAll(PDO::FETCH_ASSOC);


$query = "SELECT * FROM performance_reviews WHERE user_id = ? ORDER BY review_date DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([$user['id']]);
$latest_review = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Portal - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
        }
        .sidebar {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .nav-link {
            color: #495057;
            border-radius: 10px;
            margin: 2px 0;
        }
        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-building"></i> HR Portal
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="sidebar p-3">
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link" href="documents.php">
                            <i class="fas fa-file-alt"></i> Documents
                        </a>
                        <a class="nav-link" href="benefits.php">
                            <i class="fas fa-heart"></i> Benefits
                        </a>
                        <a class="nav-link" href="performance.php">
                            <i class="fas fa-chart-line"></i> Performance
                        </a>
                        <a class="nav-link" href="jobs.php">
                            <i class="fas fa-briefcase"></i> Jobs
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h2><i class="fas fa-sun"></i> Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
                                <p class="mb-0">Here's what's happening in your HR portal today.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-file-alt fa-3x text-primary mb-3"></i>
                                <h5>Documents</h5>
                                <h3><?php echo count($recent_documents); ?></h3>
                                <small class="text-muted">Available documents</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-heart fa-3x text-success mb-3"></i>
                                <h5>Benefits</h5>
                                <h3><?php echo count($user_benefits); ?></h3>
                                <small class="text-muted">Active benefits</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-star fa-3x text-warning mb-3"></i>
                                <h5>Performance</h5>
                                <h3><?php echo $latest_review ? number_format($latest_review['overall_rating'], 1) : 'N/A'; ?></h3>
                                <small class="text-muted">Latest rating</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Documents -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-file-alt"></i> Recent Documents</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($recent_documents): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_documents as $doc): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($doc['title']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($doc['description']); ?></small>
                                                </div>
                                                <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($doc['category']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="documents.php" class="btn btn-primary">View All Documents</a>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No documents available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <a href="documents.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-file-alt"></i><br>View Documents
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="benefits.php" class="btn btn-outline-success w-100">
                                            <i class="fas fa-heart"></i><br>Manage Benefits
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="performance.php" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-chart-line"></i><br>Performance
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="profile.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-user-edit"></i><br>Edit Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
