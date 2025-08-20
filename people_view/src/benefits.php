<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();
$user = getCurrentUser();

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM benefits WHERE user_id = ? ORDER BY start_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user['id']]);
$benefits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Portal - Benefits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0 !important; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; }
        .benefit-card { transition: transform 0.2s ease; }
        .benefit-card:hover { transform: translateY(-2px); }
        .status-active { color: #28a745; }
        .status-pending { color: #ffc107; }
        .status-expired { color: #dc3545; }
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
                    <h2><i class="fas fa-heart"></i> My Benefits</h2>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <!-- Benefits Overview -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h5>Active Benefits</h5>
                                <h3><?php echo count(array_filter($benefits, fn($b) => $b['status'] === 'active')); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                                <h5>Pending Benefits</h5>
                                <h3><?php echo count(array_filter($benefits, fn($b) => $b['status'] === 'pending')); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                                <h5>Expired Benefits</h5>
                                <h3><?php echo count(array_filter($benefits, fn($b) => $b['status'] === 'expired')); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Benefits List -->
                <div class="row">
                    <?php if ($benefits): ?>
                        <?php foreach ($benefits as $benefit): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card benefit-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title"><?php echo htmlspecialchars($benefit['benefit_type']); ?></h5>
                                            <span class="badge bg-<?php echo $benefit['status'] === 'active' ? 'success' : ($benefit['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($benefit['status']); ?>
                                            </span>
                                        </div>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($benefit['description']); ?></p>
                                        <div class="mt-auto">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-alt"></i> 
                                                <?php echo date('M j, Y', strtotime($benefit['start_date'])); ?> - 
                                                <?php echo date('M j, Y', strtotime($benefit['end_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <button class="btn btn-outline-primary btn-sm w-100" onclick="viewBenefitDetails(<?php echo $benefit['id']; ?>)">
                                            <i class="fas fa-info-circle"></i> View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-heart fa-4x text-muted mb-3"></i>
                                    <h4>No Benefits Found</h4>
                                    <p class="text-muted">You don't have any benefits assigned yet. Contact HR for more information.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewBenefitDetails(benefitId) {
            alert('Benefit details would be shown here for benefit ID: ' + benefitId);
        }
    </script>
</body>
</html>
