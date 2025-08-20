<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();
$user = getCurrentUser();

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'list';
$job_id = $_GET['id'] ?? null;
$error = '';
$success = '';


$is_hr = ($user['department'] === 'HR' || $user['username'] === 'admin');

function generate_file_name(){
    return round(hexdec(uniqid()) / 100000);
}

if ($_POST) {
    try {
        if (isset($_POST['add_job']) || isset($_POST['edit_job'])) {
            if (!$is_hr) {
                throw new Exception('You do not have permission to manage job postings');
            }
            
            $title = trim($_POST['title'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $job_type = $_POST['job_type'] ?? 'full-time';
            $salary_range = trim($_POST['salary_range'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $requirements = trim($_POST['requirements'] ?? '');
            $responsibilities = trim($_POST['responsibilities'] ?? '');
            $benefits = trim($_POST['benefits'] ?? '');
            $closing_date = $_POST['closing_date'] ?? null;
            
            if (empty($title) || empty($department) || empty($description)) {
                throw new Exception('Title, department, and description are required');
            }
            
            if (isset($_POST['add_job'])) {
                $query = "INSERT INTO job_postings (title, department, location, job_type, salary_range, description, requirements, responsibilities, benefits, posted_by, closing_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [$title, $department, $location, $job_type, $salary_range, $description, $requirements, $responsibilities, $benefits, $user['id'], $closing_date];
                $database->executeQuery($query, $params);
                $success = 'Job posting created successfully!';
            } else {
                $query = "UPDATE job_postings SET title=?, department=?, location=?, job_type=?, salary_range=?, description=?, requirements=?, responsibilities=?, benefits=?, closing_date=? WHERE id=?";
                $params = [$title, $department, $location, $job_type, $salary_range, $description, $requirements, $responsibilities, $benefits, $closing_date, $job_id];
                $database->executeQuery($query, $params);
                $success = 'Job posting updated successfully!';
            }
        }
        
        if (isset($_POST['apply_job'])) {
            $cover_letter = trim($_POST['cover_letter'] ?? '');
            
            if (empty($cover_letter)) {
                throw new Exception('Cover letter is required');
            }
            
      
            $check_query = "SELECT id FROM job_applications WHERE job_id = ? AND applicant_id = ?";
            $check_stmt = $database->executeQuery($check_query, [$job_id, $user['id']]);
            
            if ($check_stmt->fetch()) {
                throw new Exception('You have already applied for this position');
            }
            

            $resume_path = null;
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['resume']['size'] > 1024 * 1024) {
                    throw new Exception('Resume file size must be less than 1 MB');
                }
                $upload_dir = 'uploads/resumes/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['pdf', 'doc', 'docx'];
                
                if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                    throw new Exception('Only PDF, DOC, and DOCX files are allowed');
                }
                
                $filename = generate_file_name() . '_' . $user['id'] . '.' . $file_extension;
                $resume_path = $upload_dir . $filename;
                
                if (!move_uploaded_file($_FILES['resume']['tmp_name'], $resume_path)) {
                    throw new Exception('Failed to upload resume');
                }
            }
            
            $query = "INSERT INTO job_applications (job_id, applicant_id, cover_letter, resume_path) VALUES (?, ?, ?, ?)";
            $database->executeQuery($query, [$job_id, $user['id'], $cover_letter, $resume_path]);
            $success = 'Application submitted successfully!';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}


$job = null;
if ($job_id && ($action === 'edit' || $action === 'apply')) {
    $query = "SELECT * FROM job_postings WHERE id = ?";
    $stmt = $database->executeQuery($query, [$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        header('Location: jobs.php');
        exit();
    }
}


$query = "SELECT j.*, u.full_name as posted_by_name FROM job_postings j 
          LEFT JOIN users u ON j.posted_by = u.id 
          WHERE j.status = 'active' 
          ORDER BY j.posted_date DESC";
$stmt = $database->executeQuery($query);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);


$query = "SELECT ja.*, j.title as job_title, j.department FROM job_applications ja 
          JOIN job_postings j ON ja.job_id = j.id 
          WHERE ja.applicant_id = ? 
          ORDER BY ja.applied_date DESC";
$stmt = $database->executeQuery($query, [$user['id']]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);


$all_applications = [];
if ($is_hr) {
    $query = "SELECT ja.*, j.title as job_title, j.department, u.full_name as applicant_name, u.email as applicant_email 
              FROM job_applications ja 
              JOIN job_postings j ON ja.job_id = j.id 
              JOIN users u ON ja.applicant_id = u.id 
              ORDER BY ja.applied_date DESC";
    $stmt = $database->executeQuery($query);
    $all_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Portal - Jobs</title>
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
        .job-card {
            border-left: 4px solid #667eea;
        }
        .status-badge {
            font-size: 0.8rem;
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
                        <a class="nav-link" href="dashboard.php">
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
                        <a class="nav-link active" href="jobs.php">
                            <i class="fas fa-briefcase"></i> Jobs
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2><i class="fas fa-briefcase"></i> Job Postings</h2>
                            <?php if ($is_hr): ?>
                                <a href="?action=add" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Job Posting
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($action === 'add' || $action === 'edit'): ?>
                    <!-- Add/Edit Job Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-edit"></i> <?php echo $action === 'add' ? 'Add New Job' : 'Edit Job'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Job Title *</label>
                                            <input type="text" class="form-control" name="title" 
                                                   value="<?php echo htmlspecialchars($job['title'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Department *</label>
                                            <input type="text" class="form-control" name="department" 
                                                   value="<?php echo htmlspecialchars($job['department'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Location</label>
                                            <input type="text" class="form-control" name="location" 
                                                   value="<?php echo htmlspecialchars($job['location'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Job Type</label>
                                            <select class="form-control" name="job_type">
                                                <option value="full-time" <?php echo ($job['job_type'] ?? '') === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                                                <option value="part-time" <?php echo ($job['job_type'] ?? '') === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                                                <option value="contract" <?php echo ($job['job_type'] ?? '') === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                                <option value="internship" <?php echo ($job['job_type'] ?? '') === 'internship' ? 'selected' : ''; ?>>Internship</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Salary Range</label>
                                    <input type="text" class="form-control" name="salary_range" 
                                           value="<?php echo htmlspecialchars($job['salary_range'] ?? ''); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Job Description *</label>
                                    <textarea class="form-control" name="description" rows="4" required><?php echo htmlspecialchars($job['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Requirements</label>
                                    <textarea class="form-control" name="requirements" rows="3"><?php echo htmlspecialchars($job['requirements'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Responsibilities</label>
                                    <textarea class="form-control" name="responsibilities" rows="3"><?php echo htmlspecialchars($job['responsibilities'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Benefits</label>
                                    <textarea class="form-control" name="benefits" rows="3"><?php echo htmlspecialchars($job['benefits'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Closing Date</label>
                                    <input type="date" class="form-control" name="closing_date" 
                                           value="<?php echo $job['closing_date'] ?? ''; ?>">
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="<?php echo $action === 'add' ? 'add_job' : 'edit_job'; ?>" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create Job' : 'Update Job'; ?>
                                    </button>
                                    <a href="jobs.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'apply' && $job): ?>
                    <!-- Apply for Job Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-paper-plane"></i> Apply for: <?php echo htmlspecialchars($job['title']); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label">Cover Letter *</label>
                                            <textarea class="form-control" name="cover_letter" rows="6" required 
                                                      placeholder="Tell us why you're interested in this position and why you'd be a great fit..."></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Resume (PDF, DOC, DOCX)</label>
                                            <input type="file" class="form-control" name="resume" accept=".pdf,.doc,.docx">
                                            <small class="text-muted">Optional - you can upload your resume</small>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="apply_job" class="btn btn-primary">
                                                <i class="fas fa-paper-plane"></i> Submit Application
                                            </button>
                                            <a href="jobs.php" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6>Job Details</h6>
                                            <p><strong>Department:</strong> <?php echo htmlspecialchars($job['department']); ?></p>
                                            <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                                            <p><strong>Type:</strong> <?php echo ucfirst(str_replace('-', ' ', $job['job_type'])); ?></p>
                                            <p><strong>Salary:</strong> <?php echo htmlspecialchars($job['salary_range']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Job Listings -->
                    <div class="row">
                        <?php foreach ($jobs as $job): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card job-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title"><?php echo htmlspecialchars($job['title']); ?></h5>
                                            <span class="badge bg-primary status-badge"><?php echo ucfirst($job['job_type']); ?></span>
                                        </div>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['department']); ?>
                                            <?php if ($job['location']): ?>
                                                <br><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="card-text"><?php echo htmlspecialchars(substr($job['description'], 0, 150)) . '...'; ?></p>
                                        <?php if ($job['salary_range']): ?>
                                            <p class="text-success mb-2"><strong><?php echo htmlspecialchars($job['salary_range']); ?></strong></p>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Posted: <?php echo date('M j, Y', strtotime($job['posted_date'])); ?></small>
                                            <a href="?action=apply&id=<?php echo $job['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-paper-plane"></i> Apply
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- My Applications -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-list"></i> My Applications</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($applications): ?>
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Job Title</th>
                                                        <th>Department</th>
                                                        <th>Applied Date</th>
                                                        <th>Status</th>
                                                        <th>Resume</th> <!-- New column for PDF URL -->
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($applications as $app): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                                            <td><?php echo htmlspecialchars($app['department']); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($app['applied_date'])); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php 
                                                                    echo $app['status'] === 'pending' ? 'warning' : 
                                                                        ($app['status'] === 'shortlisted' ? 'info' : 
                                                                        ($app['status'] === 'hired' ? 'success' : 'danger')); 
                                                                ?>">
                                                                    <?php echo ucfirst($app['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($app['resume_path'])): ?>
                                                                    <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View PDF</a>
                                                                <?php else: ?>
                                                                    <span class="text-muted">N/A</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">You haven't applied for any jobs yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($is_hr): ?>
                        <!-- All Applications (HR Only) -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-users"></i> All Applications</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($all_applications): ?>
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Applicant</th>
                                                            <th>Job Title</th>
                                                            <th>Department</th>
                                                            <th>Applied Date</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($all_applications as $app): ?>
                                                            <tr>
                                                                <td>
                                                                    <div><?php echo htmlspecialchars($app['applicant_name']); ?></div>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($app['applicant_email']); ?></small>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                                                <td><?php echo htmlspecialchars($app['department']); ?></td>
                                                                <td><?php echo date('M j, Y', strtotime($app['applied_date'])); ?></td>
                                                                <td>
                                                                    <span class="badge bg-<?php 
                                                                        echo $app['status'] === 'pending' ? 'warning' : 
                                                                            ($app['status'] === 'shortlisted' ? 'info' : 
                                                                            ($app['status'] === 'hired' ? 'success' : 'danger')); 
                                                                    ?>">
                                                                        <?php echo ucfirst($app['status']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#applicationModal<?php echo $app['id']; ?>">
                                                                        <i class="fas fa-eye"></i> View
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">No applications received yet.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Application Detail Modals -->
    <?php if ($is_hr): ?>
        <?php foreach ($all_applications as $app): ?>
            <div class="modal fade" id="applicationModal<?php echo $app['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Application Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Applicant Information</h6>
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($app['applicant_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($app['applicant_email']); ?></p>
                                    <p><strong>Applied:</strong> <?php echo date('M j, Y H:i', strtotime($app['applied_date'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Job Information</h6>
                                    <p><strong>Position:</strong> <?php echo htmlspecialchars($app['job_title']); ?></p>
                                    <p><strong>Department:</strong> <?php echo htmlspecialchars($app['department']); ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?php 
                                            echo $app['status'] === 'pending' ? 'warning' : 
                                                ($app['status'] === 'shortlisted' ? 'info' : 
                                                ($app['status'] === 'hired' ? 'success' : 'danger')); 
                                        ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <hr>
                            <h6>Cover Letter</h6>
                            <div class="border p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?>
                            </div>
                            <?php if ($app['resume_path']): ?>
                                <div class="mt-3">
                                    <h6>Resume</h6>
                                    <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-download"></i> Download Resume
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
