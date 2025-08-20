<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();
$user = getCurrentUser();

$database = new Database();
$db = $database->getConnection();

$query = "SELECT pr.*, u.full_name as reviewer_name 
          FROM performance_reviews pr 
          LEFT JOIN users u ON pr.reviewer_id = u.id 
          WHERE pr.user_id = ? 
          ORDER BY pr.review_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user['id']]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Portal - Performance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0 !important; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; }
        .rating-stars { color: #ffc107; }
        .performance-card { transition: transform 0.2s ease; }
        .performance-card:hover { transform: translateY(-2px); }
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
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-chart-line"></i> Performance Reviews</h2>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <!-- Performance Overview -->
                <?php if ($reviews): ?>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-star fa-3x text-warning mb-3"></i>
                                    <h5>Average Rating</h5>
                                    <h3><?php echo number_format(array_sum(array_column($reviews, 'overall_rating')) / count($reviews), 1); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-calendar fa-3x text-info mb-3"></i>
                                    <h5>Total Reviews</h5>
                                    <h3><?php echo count($reviews); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-trophy fa-3x text-success mb-3"></i>
                                    <h5>Latest Rating</h5>
                                    <h3><?php echo number_format($reviews[0]['overall_rating'], 1); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reviews List -->
                <div class="row">
                    <?php if ($reviews): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="col-12 mb-4">
                                <div class="card performance-card">
                                    <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h5 class="mb-0">
                                                    <i class="fas fa-calendar-alt"></i> 
                                                    <?php echo htmlspecialchars($review['review_period']); ?>
                                                </h5>
                                            </div>
                                            <div class="col-md-6 text-md-end">
                                                <div class="rating-stars">
                                                    <?php 
                                                    $rating = $review['overall_rating'];
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $rating) {
                                                            echo '<i class="fas fa-star"></i>';
                                                        } elseif ($i - 0.5 <= $rating) {
                                                            echo '<i class="fas fa-star-half-alt"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star"></i>';
                                                        }
                                                    }
                                                    ?>
                                                    <span class="ms-2"><?php echo number_format($rating, 1); ?>/5.0</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-bullseye"></i> Goals</h6>
                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($review['goals'])); ?></p>
                                                
                                                <h6><i class="fas fa-trophy"></i> Achievements</h6>
                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($review['achievements'])); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-arrow-up"></i> Areas for Improvement</h6>
                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($review['areas_for_improvement'])); ?></p>
                                                
                                                <h6><i class="fas fa-comments"></i> Comments</h6>
                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($review['comments'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> Reviewed by: <?php echo htmlspecialchars($review['reviewer_name']); ?> | 
                                            <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($review['review_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                                    <h4>No Performance Reviews</h4>
                                    <p class="text-muted">You don't have any performance reviews yet. Your first review will appear here once completed.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
