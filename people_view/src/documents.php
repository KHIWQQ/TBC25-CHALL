<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();
$user = getCurrentUser();

$database = new Database();
$db = $database->getConnection();

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$query = "SELECT d.*, u.full_name as uploaded_by_name FROM documents d 
          LEFT JOIN users u ON d.uploaded_by = u.id 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (d.title LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $query .= " AND d.category = ?";
    $params[] = $category;
}

$query .= " ORDER BY d.upload_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT DISTINCT category FROM documents WHERE category IS NOT NULL";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Portal - Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0 !important; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; }
        .document-item { transition: transform 0.2s ease; }
        .document-item:hover { transform: translateY(-2px); }
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
                    <h2><i class="fas fa-file-alt"></i> Documents</h2>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search documents..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                                <?php echo $category === $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Documents Grid -->
                <div class="row">
                    <?php if ($documents): ?>
                        <?php foreach ($documents as $doc): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card document-item h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title"><?php echo htmlspecialchars($doc['title']); ?></h5>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($doc['category']); ?></span>
                                        </div>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($doc['description']); ?></p>
                                        <div class="mt-auto">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($doc['uploaded_by_name']); ?><br>
                                                <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($doc['upload_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <button class="btn btn-primary btn-sm w-100" onclick="viewDocument('<?php echo htmlspecialchars($doc['title']); ?>')">
                                            <i class="fas fa-eye"></i> View Document
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                                    <h4>No Documents Found</h4>
                                    <p class="text-muted">No documents match your search criteria.</p>
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
        function viewDocument(title) {
            alert('Document viewer would open here for: ' + title);
        }
    </script>
</body>
</html>
