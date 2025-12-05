<?php
require_once 'includes/config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'add') {
        // Add new medical record
        $patient_id = (int)$_POST['patient_id'];
        $doctor_id = (int)$_POST['doctor_id'];
        $visit_date = sanitize($_POST['visit_date']);
        $chief_complaint = sanitize($_POST['chief_complaint']);
        $diagnosis = sanitize($_POST['diagnosis']);
        $treatment = sanitize($_POST['treatment']);
        $prescribed_medications = sanitize($_POST['prescribed_medications']);
        $follow_up_date = sanitize($_POST['follow_up_date']) ?: null;
        $notes = sanitize($_POST['notes']);
        
        // Vital signs JSON
        $vital_signs = [
            'blood_pressure' => sanitize($_POST['blood_pressure']),
            'temperature' => sanitize($_POST['temperature']),
            'heart_rate' => sanitize($_POST['heart_rate']),
            'weight' => sanitize($_POST['weight']),
            'height' => sanitize($_POST['height']),
            'respiratory_rate' => sanitize($_POST['respiratory_rate'])
        ];
        
        if (empty($patient_id) || empty($doctor_id) || empty($visit_date) || empty($chief_complaint)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO medical_history (patient_id, doctor_id, visit_date, chief_complaint, diagnosis, treatment, prescribed_medications, follow_up_date, vital_signs, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([$patient_id, $doctor_id, $visit_date, $chief_complaint, $diagnosis, $treatment, $prescribed_medications, $follow_up_date, json_encode($vital_signs), $notes]);
                
                showAlert('Medical record added successfully!', 'success');
                redirect('medical-history.php');
            } catch (PDOException $e) {
                $error = 'Error adding medical record: ' . $e->getMessage();
            }
        }
    }
}

// Get doctors for dropdown
try {
    $doctors_stmt = $pdo->query("SELECT d.id, d.doctor_id, u.full_name, d.specialization FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.is_active = 1 ORDER BY u.full_name");
    $doctors = $doctors_stmt->fetchAll();
} catch (PDOException $e) {
    $doctors = [];
}

// Get patients for dropdown
try {
    $patients_stmt = $pdo->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name, last_name");
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $e) {
    $patients = [];
}

// Search and filter medical history
$search = $_GET['search'] ?? '';
$patient_filter = $_GET['patient'] ?? '';
$doctor_filter = $_GET['doctor'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ? OR mh.chief_complaint LIKE ? OR mh.diagnosis LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($patient_filter) {
    $where_conditions[] = "mh.patient_id = ?";
    $params[] = $patient_filter;
}

if ($doctor_filter) {
    $where_conditions[] = "mh.doctor_id = ?";
    $params[] = $doctor_filter;
}

if ($date_from) {
    $where_conditions[] = "mh.visit_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "mh.visit_date <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get medical history records
try {
    $count_query = "SELECT COUNT(*) as total FROM medical_history mh 
                    JOIN patients p ON mh.patient_id = p.id 
                    JOIN doctors d ON mh.doctor_id = d.id 
                    JOIN users u ON d.user_id = u.id 
                    $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    $query = "SELECT mh.*, p.patient_id, p.first_name, p.last_name, p.date_of_birth,
                     d.doctor_id, u.full_name as doctor_name, d.specialization
              FROM medical_history mh
              JOIN patients p ON mh.patient_id = p.id
              JOIN doctors d ON mh.doctor_id = d.id
              JOIN users u ON d.user_id = u.id
              $where_clause
              ORDER BY mh.visit_date DESC, mh.created_at DESC
              LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $medical_records = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching medical records: ' . $e->getMessage();
    $medical_records = [];
    $total_records = 0;
    $total_pages = 0;
}

// Pre-select patient if coming from patient page
$selected_patient_id = $_GET['patient_id'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - <?php echo SITE_NAME; ?></title>
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
                <!-- Medical History List -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-notes-medical"></i> Medical History</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if (hasRole('doctor') || hasRole('nurse')): ?>
                        <a href="medical-history.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Medical Record
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php displayAlert(); ?>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <input type="text" class="form-control" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" name="patient">
                                    <option value="">All Patients</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>" <?php echo $patient_filter == $patient['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" name="doctor">
                                    <option value="">All Doctors</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_filter == $doctor['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($doctor['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="date_from" placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="date_to" placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </form>
                        <?php if ($search || $patient_filter || $doctor_filter || $date_from || $date_to): ?>
                        <div class="mt-2">
                            <a href="medical-history.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Medical Records -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Medical Records (<?php echo number_format($total_records); ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($medical_records)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-notes-medical fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No medical records found</h5>
                                <?php if (hasRole('doctor') || hasRole('nurse')): ?>
                                    <p>Start by <a href="medical-history.php?action=add">adding your first medical record</a></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($medical_records as $record): ?>
                            <div class="card mb-3 border-left-primary">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6 class="card-title">
                                                <i class="fas fa-user text-primary"></i> 
                                                <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($record['patient_id']); ?></span>
                                            </h6>
                                            <p class="card-text">
                                                <strong>Chief Complaint:</strong> <?php echo htmlspecialchars($record['chief_complaint']); ?>
                                            </p>
                                            <?php if ($record['diagnosis']): ?>
                                            <p class="card-text">
                                                <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?>
                                            </p>
                                            <?php endif; ?>
                                            <?php if ($record['treatment']): ?>
                                            <p class="card-text">
                                                <strong>Treatment:</strong> <?php echo htmlspecialchars($record['treatment']); ?>
                                            </p>
                                            <?php endif; ?>
                                            <?php if ($record['prescribed_medications']): ?>
                                            <p class="card-text">
                                                <strong>Medications:</strong> <?php echo htmlspecialchars($record['prescribed_medications']); ?>
                                            </p>
                                            <?php endif; ?>
                                            <?php if ($record['notes']): ?>
                                            <p class="card-text">
                                                <strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-end">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> <?php echo formatDate($record['visit_date']); ?><br>
                                                    <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($record['doctor_name']); ?><br>
                                                    <i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($record['specialization']); ?>
                                                </small>
                                            </div>
                                            
                                            <!-- Vital Signs -->
                                            <?php if ($record['vital_signs']): ?>
                                                <?php $vitals = json_decode($record['vital_signs'], true); ?>
                                                <div class="mt-3">
                                                    <h6 class="text-muted"><i class="fas fa-heartbeat"></i> Vital Signs</h6>
                                                    <small>
                                                        <?php if (!empty($vitals['blood_pressure'])): ?>
                                                            <strong>BP:</strong> <?php echo htmlspecialchars($vitals['blood_pressure']); ?><br>
                                                        <?php endif; ?>
                                                        <?php if (!empty($vitals['temperature'])): ?>
                                                            <strong>Temp:</strong> <?php echo htmlspecialchars($vitals['temperature']); ?>°F<br>
                                                        <?php endif; ?>
                                                        <?php if (!empty($vitals['heart_rate'])): ?>
                                                            <strong>HR:</strong> <?php echo htmlspecialchars($vitals['heart_rate']); ?> bpm<br>
                                                        <?php endif; ?>
                                                        <?php if (!empty($vitals['weight'])): ?>
                                                            <strong>Weight:</strong> <?php echo htmlspecialchars($vitals['weight']); ?><br>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($record['follow_up_date']): ?>
                                            <div class="mt-3">
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-calendar-check"></i> Follow-up: <?php echo formatDate($record['follow_up_date']); ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="medical-history.php?action=view&id=<?php echo $record['id']; ?>" class="btn btn-info" title="View Full Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (hasRole('doctor') || hasRole('nurse')): ?>
                                                    <a href="prescriptions.php?action=add&medical_history_id=<?php echo $record['id']; ?>" class="btn btn-success" title="Add Prescription">
                                                        <i class="fas fa-pills"></i>
                                                    </a>
                                                    <a href="lab-tests.php?action=add&patient_id=<?php echo $record['patient_id']; ?>" class="btn btn-warning" title="Order Lab Test">
                                                        <i class="fas fa-flask"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Medical history pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo buildQueryString(['search' => $search, 'patient' => $patient_filter, 'doctor' => $doctor_filter, 'date_from' => $date_from, 'date_to' => $date_to]); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo buildQueryString(['search' => $search, 'patient' => $patient_filter, 'doctor' => $doctor_filter, 'date_from' => $date_from, 'date_to' => $date_to]); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo buildQueryString(['search' => $search, 'patient' => $patient_filter, 'doctor' => $doctor_filter, 'date_from' => $date_from, 'date_to' => $date_to]); ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($action == 'add'): ?>
                <!-- Add Medical Record Form -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-plus"></i> Add Medical Record
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="medical-history.php" class="btn btn-secondary">
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
                                        <label for="patient_id">Patient *</label>
                                        <select class="form-control" id="patient_id" name="patient_id" required>
                                            <option value="">Select Patient</option>
                                            <?php foreach ($patients as $patient): ?>
                                                <option value="<?php echo $patient['id']; ?>" <?php echo ($selected_patient_id == $patient['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="doctor_id">Doctor *</label>
                                        <select class="form-control" id="doctor_id" name="doctor_id" required>
                                            <option value="">Select Doctor</option>
                                            <?php foreach ($doctors as $doctor): ?>
                                                <option value="<?php echo $doctor['id']; ?>">
                                                    <?php echo htmlspecialchars($doctor['full_name'] . ' - ' . $doctor['specialization']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="visit_date">Visit Date *</label>
                                <input type="date" class="form-control" id="visit_date" name="visit_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="chief_complaint">Chief Complaint *</label>
                                <textarea class="form-control" id="chief_complaint" name="chief_complaint" rows="2" 
                                          placeholder="Main reason for the visit..." required></textarea>
                            </div>

                            <!-- Vital Signs -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6><i class="fas fa-heartbeat"></i> Vital Signs</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group mb-3">
                                                <label for="blood_pressure">Blood Pressure</label>
                                                <input type="text" class="form-control" id="blood_pressure" name="blood_pressure" 
                                                       placeholder="120/80">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group mb-3">
                                                <label for="temperature">Temperature (°F)</label>
                                                <input type="text" class="form-control" id="temperature" name="temperature" 
                                                       placeholder="98.6">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group mb-3">
                                                <label for="heart_rate">Heart Rate (bpm)</label>
                                                <input type="number" class="form-control" id="heart_rate" name="heart_rate" 
                                                       placeholder="72">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group mb-3">
                                                <label for="respiratory_rate">Respiratory Rate</label>
                                                <input type="number" class="form-control" id="respiratory_rate" name="respiratory_rate" 
                                                       placeholder="16">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="weight">Weight</label>
                                                <input type="text" class="form-control" id="weight" name="weight" 
                                                       placeholder="70 kg">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="height">Height</label>
                                                <input type="text" class="form-control" id="height" name="height" 
                                                       placeholder="170 cm">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="diagnosis">Diagnosis</label>
                                <textarea class="form-control" id="diagnosis" name="diagnosis" rows="2" 
                                          placeholder="Primary and secondary diagnoses..."></textarea>
                            </div>

                            <div class="form-group mb-3">
                                <label for="treatment">Treatment Plan</label>
                                <textarea class="form-control" id="treatment" name="treatment" rows="3" 
                                          placeholder="Treatment plan and procedures..."></textarea>
                            </div>

                            <div class="form-group mb-3">
                                <label for="prescribed_medications">Prescribed Medications</label>
                                <textarea class="form-control" id="prescribed_medications" name="prescribed_medications" rows="2" 
                                          placeholder="List of prescribed medications with dosage..."></textarea>
                            </div>

                            <div class="form-group mb-3">
                                <label for="follow_up_date">Follow-up Date</label>
                                <input type="date" class="form-control" id="follow_up_date" name="follow_up_date">
                            </div>

                            <div class="form-group mb-3">
                                <label for="notes">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Any additional observations or notes..."></textarea>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Medical Record
                                </button>
                                <a href="medical-history.php" class="btn btn-secondary">Cancel</a>
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

<?php
function buildQueryString($params) {
    $query_parts = [];
    foreach ($params as $key => $value) {
        if (!empty($value)) {
            $query_parts[] = $key . '=' . urlencode($value);
        }
    }
    return $query_parts ? '&' . implode('&', $query_parts) : '';
}
?>