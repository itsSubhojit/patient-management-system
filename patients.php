<?php
require_once 'includes/config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'add') {
        // Add new patient
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $date_of_birth = sanitize($_POST['date_of_birth']);
        $gender = sanitize($_POST['gender']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        $emergency_contact_name = sanitize($_POST['emergency_contact_name']);
        $emergency_contact_phone = sanitize($_POST['emergency_contact_phone']);
        $blood_type = sanitize($_POST['blood_type']);
        $allergies = sanitize($_POST['allergies']);
        $insurance_number = sanitize($_POST['insurance_number']);
        $insurance_provider = sanitize($_POST['insurance_provider']);

        if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($gender) || empty($phone) || empty($address)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                $patient_id = generateId('PAT', 'patients', 'patient_id');
                
                $stmt = $pdo->prepare("INSERT INTO patients (patient_id, first_name, last_name, date_of_birth, gender, phone, email, address, emergency_contact_name, emergency_contact_phone, blood_type, allergies, insurance_number, insurance_provider, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([$patient_id, $first_name, $last_name, $date_of_birth, $gender, $phone, $email, $address, $emergency_contact_name, $emergency_contact_phone, $blood_type, $allergies, $insurance_number, $insurance_provider, $_SESSION['user_id']]);
                
                showAlert('Patient registered successfully! Patient ID: ' . $patient_id, 'success');
                redirect('patients.php');
            } catch (PDOException $e) {
                $error = 'Error adding patient: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'edit' && isset($_GET['id'])) {
        // Edit existing patient
        $patient_id = (int)$_GET['id'];
        
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $date_of_birth = sanitize($_POST['date_of_birth']);
        $gender = sanitize($_POST['gender']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        $emergency_contact_name = sanitize($_POST['emergency_contact_name']);
        $emergency_contact_phone = sanitize($_POST['emergency_contact_phone']);
        $blood_type = sanitize($_POST['blood_type']);
        $allergies = sanitize($_POST['allergies']);
        $insurance_number = sanitize($_POST['insurance_number']);
        $insurance_provider = sanitize($_POST['insurance_provider']);

        if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($gender) || empty($phone) || empty($address)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE patients SET first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, phone = ?, email = ?, address = ?, emergency_contact_name = ?, emergency_contact_phone = ?, blood_type = ?, allergies = ?, insurance_number = ?, insurance_provider = ?, updated_at = NOW() WHERE id = ?");
                
                $stmt->execute([$first_name, $last_name, $date_of_birth, $gender, $phone, $email, $address, $emergency_contact_name, $emergency_contact_phone, $blood_type, $allergies, $insurance_number, $insurance_provider, $patient_id]);
                
                showAlert('Patient updated successfully!', 'success');
                redirect('patients.php');
            } catch (PDOException $e) {
                $error = 'Error updating patient: ' . $e->getMessage();
            }
        }
    }
}

// Get patient data for editing
if ($action == 'edit' && isset($_GET['id'])) {
    $patient_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch();
        
        if (!$patient) {
            showAlert('Patient not found', 'error');
            redirect('patients.php');
        }
    } catch (PDOException $e) {
        showAlert('Error fetching patient data', 'error');
        redirect('patients.php');
    }
}

// Search and filter patients
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(patient_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM patients $where_clause");
    $count_stmt->execute($params);
    $total_patients = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_patients / $limit);
    
    // Get patients
    $stmt = $pdo->prepare("SELECT * FROM patients $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching patients: ' . $e->getMessage();
    $patients = [];
    $total_patients = 0;
    $total_pages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if ($action == 'list'): ?>
                <!-- Patients List -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-users"></i> Patients Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="patients.php?action=add" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add New Patient
                        </a>
                    </div>
                </div>

                <?php displayAlert(); ?>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" placeholder="Search by Patient ID, Name, Phone, or Email..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if ($search): ?>
                                <a href="patients.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Patients Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Patients List (<?php echo number_format($total_patients); ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($patients)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No patients found</h5>
                                <?php if ($search): ?>
                                    <p>Try adjusting your search criteria</p>
                                <?php else: ?>
                                    <p>Start by <a href="patients.php?action=add">adding your first patient</a></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Patient ID</th>
                                            <th>Name</th>
                                            <th>Age/Gender</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Blood Type</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patients as $patient): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($patient['patient_id']); ?></strong>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>
                                                <?php if ($patient['allergies']): ?>
                                                    <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Has allergies</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
                                                echo $age . 'y / ' . htmlspecialchars($patient['gender']);
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                            <td>
                                                <?php if ($patient['blood_type']): ?>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($patient['blood_type']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($patient['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="patients.php?action=view&id=<?php echo $patient['id']; ?>" class="btn btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" class="btn btn-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="appointments.php?action=add&patient_id=<?php echo $patient['id']; ?>" class="btn btn-success" title="Schedule Appointment">
                                                        <i class="fas fa-calendar-plus"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Patients pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <!-- Add/Edit Patient Form -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-<?php echo $action == 'add' ? 'user-plus' : 'user-edit'; ?>"></i> 
                        <?php echo $action == 'add' ? 'Add New Patient' : 'Edit Patient'; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="patients.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="first_name">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($patient['first_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="last_name">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($patient['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="date_of_birth">Date of Birth *</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                               value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="gender">Gender *</label>
                                        <select class="form-control" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo (isset($patient['gender']) && $patient['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo (isset($patient['gender']) && $patient['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo (isset($patient['gender']) && $patient['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="blood_type">Blood Type</label>
                                        <select class="form-control" id="blood_type" name="blood_type">
                                            <option value="">Select Blood Type</option>
                                            <?php
                                            $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                            foreach ($blood_types as $type):
                                            ?>
                                                <option value="<?php echo $type; ?>" <?php echo (isset($patient['blood_type']) && $patient['blood_type'] == $type) ? 'selected' : ''; ?>><?php echo $type; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="phone">Phone Number *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="email">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="address">Address *</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="emergency_contact_name">Emergency Contact Name</label>
                                        <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                               value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                        <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                               value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="allergies">Allergies</label>
                                <textarea class="form-control" id="allergies" name="allergies" rows="2" placeholder="List any known allergies..."><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="insurance_provider">Insurance Provider</label>
                                        <input type="text" class="form-control" id="insurance_provider" name="insurance_provider" 
                                               value="<?php echo htmlspecialchars($patient['insurance_provider'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="insurance_number">Insurance Number</label>
                                        <input type="text" class="form-control" id="insurance_number" name="insurance_number" 
                                               value="<?php echo htmlspecialchars($patient['insurance_number'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add Patient' : 'Update Patient'; ?>
                                </button>
                                <a href="patients.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>