<?php
require_once 'includes/config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$success = '';
$error = '';
$view_patient = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['bulk_delete'])) {
        // Bulk delete selected patients
        if (!empty($_POST['selected_patients'])) {
            $ids = array_map('intval', $_POST['selected_patients']);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                try {
                    // First delete all appointments associated with these patients
                    $stmt = $pdo->prepare("DELETE FROM appointments WHERE patient_id IN ($placeholders)");
                    $stmt->execute($ids);
                    
                    // Then delete the patients
                    $stmt = $pdo->prepare("DELETE FROM patients WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    showAlert(count($ids) . ' patient(s) and their appointments deleted successfully!', 'success');
                    redirect('patients.php');
                } catch (PDOException $e) {
                    $error = 'Error deleting patients: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Please select at least one patient to delete.';
        }
    } elseif ($action == 'add') {
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
    } elseif (isset($_POST['delete_patient']) && $action == 'delete' && isset($_GET['id'])) {
        // Delete patient
        $patient_id = (int)$_GET['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            
            showAlert('Patient deleted successfully!', 'success');
            redirect('patients.php');
        } catch (PDOException $e) {
            $error = 'Error deleting patient: ' . $e->getMessage();
        }
    }
}

// Get patient data for editing
if (($action == 'edit' || $action == 'view') && isset($_GET['id'])) {
    $patient_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch();
        if (!$patient) {
            showAlert('Patient not found', 'error');
            redirect('patients.php');
        }
        if ($action == 'view') {
            $view_patient = $patient;
            // Appointment summary for details view
            $appt_stats = [
                'total' => 0,
                'scheduled' => 0,
                'last' => null,
                'next' => null
            ];
            try {
                // Total appointments
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?");
                $stmt->execute([$patient_id]);
                $appt_stats['total'] = $stmt->fetch()['total'];
                // Scheduled appointments
                $stmt = $pdo->prepare("SELECT COUNT(*) as scheduled FROM appointments WHERE patient_id = ? AND status = 'scheduled'");
                $stmt->execute([$patient_id]);
                $appt_stats['scheduled'] = $stmt->fetch()['scheduled'];
                // Last appointment
                $stmt = $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC, appointment_time DESC LIMIT 1");
                $stmt->execute([$patient_id]);
                $appt_stats['last'] = $stmt->fetch();
                // Next appointment
                $stmt = $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE() AND status = 'scheduled' ORDER BY appointment_date ASC, appointment_time ASC LIMIT 1");
                $stmt->execute([$patient_id]);
                $appt_stats['next'] = $stmt->fetch();
            } catch (PDOException $e) {}
            $view_patient['appt_stats'] = $appt_stats;

            // Fetch medical history for this patient
            try {
                $stmt = $pdo->prepare("SELECT mh.*, d.specialization, d.doctor_id AS doc_id, u.full_name AS doctor_name FROM medical_history mh JOIN doctors d ON mh.doctor_id = d.id JOIN users u ON d.user_id = u.id WHERE mh.patient_id = ? ORDER BY mh.visit_date ASC, mh.id ASC");
                $stmt->execute([$patient_id]);
                $view_patient['medical_history'] = $stmt->fetchAll();
            } catch (PDOException $e) {
                $view_patient['medical_history'] = [];
            }
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
        <div class="dashboard-wrapper">
            <main class="dashboard-main">
                <?php if ($action == 'list'): ?>
                <!-- Patients List -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="dashboard-title display-6 fw-bold mb-0 d-flex align-items-center gap-2">
                        Patients Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if (!hasRole('doctor')): ?>
                        <a href="patients.php?action=add" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add New Patient
                        </a>
                        <button type="button" class="btn btn-danger" style="background-color:#e53935; border:none; font-weight:bold; margin-left:8px;" onclick="deleteSelectedPatients()">
                            <i class="fas fa-trash"></i> Delete Patient
                        </button>
                        <?php endif; ?>
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
                            <form method="POST" id="patientsTableForm" action="patients.php">
                                <input type="hidden" name="bulk_delete" value="1">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
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
                                                    <input type="checkbox" name="selected_patients[]" value="<?php echo $patient['id']; ?>">
                                                </td>
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
                                                    <a href="patients.php?action=view&id=<?php echo $patient['id']; ?>" class="btn btn-info btn-action-icon" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (!hasRole('doctor')): ?>
                                                    <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" class="btn btn-warning btn-action-icon" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="appointments.php?action=add&patient_id=<?php echo $patient['id']; ?>" class="btn btn-success btn-action-icon" title="Schedule Appointment">
                                                        <i class="fas fa-calendar-plus"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
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

                <?php elseif ($action == 'view' && $view_patient): ?>
                <!-- View Patient Details -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2 fw-bold mb-0">
                        Patient Details
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="patients.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
                <div class="card shadow">
                    <div class="card-body p-4" style="border-radius: 1rem; background: #f8f9fa; box-shadow: 0 2px 16px rgba(0,0,0,0.07);">
                        <div class="row g-4 align-items-center">
                            <div class="col-md-6 mb-3">
                                <h5><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($view_patient['patient_id']); ?></h5>
                                <p class="mb-1"><span class="fw-semibold text-secondary">Name:</span> <span class="fs-5 fw-bold text-dark"><?php echo htmlspecialchars($view_patient['first_name'] . ' ' . $view_patient['last_name']); ?></span></p>
                                <p class="mb-1"><span class="fw-semibold text-secondary">Date of Birth:</span> <?php echo htmlspecialchars($view_patient['date_of_birth']); ?></p>
                                <p class="mb-1"><span class="fw-semibold text-secondary">Gender:</span> <?php echo htmlspecialchars($view_patient['gender']); ?></p>
                                <p class="mb-1"><span class="fw-semibold text-secondary">Phone:</span> <?php echo htmlspecialchars($view_patient['phone']); ?></p>
                                <p class="mb-1"><span class="fw-semibold text-secondary">Email:</span> <?php echo htmlspecialchars($view_patient['email']); ?></p>
                                <p class="mb-1"><span class="fw-semibold text-secondary">Address:</span> <?php echo htmlspecialchars($view_patient['address']); ?></p>
                                <hr class="my-3">
                                <div class="d-flex flex-wrap gap-3 align-items-center">
                                    <span class="fw-semibold text-secondary">Total Appointments:</span> <span class="badge rounded-pill bg-primary px-3 py-2 fs-6"><?php echo (int)($view_patient['appt_stats']['total'] ?? 0); ?></span>
                                    <span class="fw-semibold text-secondary">Currently Scheduled:</span> <span class="badge rounded-pill bg-<?php echo ((int)($view_patient['appt_stats']['scheduled'] ?? 0) > 0) ? 'success' : 'secondary'; ?> px-3 py-2 fs-6"><?php echo ((int)($view_patient['appt_stats']['scheduled'] ?? 0) > 0) ? 'Scheduled' : 'Not Scheduled'; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p><strong>Blood Type:</strong> <?php echo htmlspecialchars($view_patient['blood_type'] ?: '-'); ?></p>
                                <p><strong>Allergies:</strong> <?php echo htmlspecialchars($view_patient['allergies'] ?: '-'); ?></p>
                                <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($view_patient['emergency_contact_name'] ?: '-'); ?> (<?php echo htmlspecialchars($view_patient['emergency_contact_phone'] ?: '-'); ?>)</p>
                                <p><strong>Insurance Provider:</strong> <?php echo htmlspecialchars($view_patient['insurance_provider'] ?: '-'); ?></p>
                                <p><strong>Insurance Number:</strong> <?php echo htmlspecialchars($view_patient['insurance_number'] ?: '-'); ?></p>
                                <p><strong>Registered:</strong> <?php echo formatDate($view_patient['created_at']); ?></p>
                                <hr>
                                <p><strong>Last Appointment:</strong><br>
                                    <?php if (!empty($view_patient['appt_stats']['last'])): ?>
                                        <?php echo formatDate($view_patient['appt_stats']['last']['appointment_date']); ?>
                                        at <?php echo date('g:i A', strtotime($view_patient['appt_stats']['last']['appointment_time'])); ?>
                                        (<?php echo ucfirst($view_patient['appt_stats']['last']['status']); ?>)
                                    <?php else: ?>
                                        <span class="text-muted">No previous appointments</span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Next Appointment:</strong><br>
                                    <?php 
                                    // Find the most recent future follow_up_date from medical_history
                                    $next_doc_appt = null;
                                    if (!empty($view_patient['medical_history'])) {
                                        foreach ($view_patient['medical_history'] as $history) {
                                            if (!empty($history['follow_up_date']) && $history['follow_up_date'] !== '0000-00-00' && $history['follow_up_date'] >= date('Y-m-d')) {
                                                if ($next_doc_appt === null || $history['follow_up_date'] < $next_doc_appt) {
                                                    $next_doc_appt = $history['follow_up_date'];
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                    <?php if ($next_doc_appt): ?>
                                        <?php echo formatDate($next_doc_appt); ?> <span class="badge bg-info">Set by Doctor</span>
                                    <?php elseif (!empty($view_patient['appt_stats']['next'])): ?>
                                        <?php echo formatDate($view_patient['appt_stats']['next']['appointment_date']); ?>
                                        at <?php echo date('g:i A', strtotime($view_patient['appt_stats']['next']['appointment_time'])); ?>
                                        (<?php echo ucfirst($view_patient['appt_stats']['next']['status']); ?>)
                                    <?php else: ?>
                                        <span class="text-muted">No upcoming scheduled appointment</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical History Section -->
                <div class="card shadow mt-4">
                    <div class="card-header bg-gradient text-white" style="background: linear-gradient(90deg, #4e54c8 0%, #8f94fb 100%); border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                        <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i> Medical History</h5>
                    </div>
                    <div class="card-body p-4" style="border-radius: 0 0 1rem 1rem; background: #f8f9fa;">
                        <?php if (!empty($view_patient['medical_history'])): ?>
                            <ol class="list-group list-group-numbered">
                                <?php foreach ($view_patient['medical_history'] as $history): ?>
                                    <li class="list-group-item mb-3 shadow-sm rounded-3 border-0" style="background: #fff;">
                                        <div class="fw-bold text-primary mb-1">Date: <?php echo htmlspecialchars($history['visit_date']); ?></div>
                                        <div class="mb-1"><span class="fw-semibold text-secondary">Doctor:</span> <?php echo htmlspecialchars($history['doctor_name']); ?> <span class="badge bg-light text-dark border ms-2"><?php echo htmlspecialchars($history['specialization']); ?></span></div>
                                        <div class="mb-1"><span class="fw-semibold text-secondary">Diagnosis:</span> <?php echo nl2br(htmlspecialchars($history['diagnosis'])); ?></div>
                                        <?php if (!empty($history['treatment'])): ?>
                                            <div class="mb-1"><span class="fw-semibold text-secondary">Treatment:</span> <?php echo nl2br(htmlspecialchars($history['treatment'])); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($history['prescribed_medications'])): ?>
                                            <div class="mb-1"><span class="fw-semibold text-secondary">Medications:</span> <?php echo nl2br(htmlspecialchars($history['prescribed_medications'])); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($history['notes'])): ?>
                                            <div class="mb-1"><span class="fw-semibold text-secondary">Notes:</span> <?php echo nl2br(htmlspecialchars($history['notes'])); ?></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else: ?>
                            <div class="text-muted">No medical history found for this patient.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <!-- Add/Edit Patient Form -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2 fw-bold mb-0">
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
    <script>
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_patients[]"]');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        function deleteSelectedPatients() {
            const checkboxes = document.querySelectorAll('input[name="selected_patients[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one patient to delete.');
                return false;
            }
            if (confirm('Are you sure you want to delete the selected patient(s)? This action cannot be undone.')) {
                document.getElementById('patientsTableForm').submit();
            }
        }
    </script>
</body>
</html>